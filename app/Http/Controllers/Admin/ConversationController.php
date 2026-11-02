<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BuyerRequirement;
use App\Models\Conversation;
use App\Models\Lead;
use App\Models\OutboundMessage;
use App\Models\WebhookEvent;
use App\Services\AuditLogger;
use App\Services\WhatsApp\OutboundMessenger;
use App\Services\WhatsApp\WhatsAppClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * AI assistant management: website and WhatsApp conversations, what the
 * assistant understood, saved requirements, AI-created leads, escalations
 * and failed webhooks. Phone numbers are masked; revealing one is audited.
 */
class ConversationController extends Controller
{
    public function index(Request $request, WhatsAppClient $client): View
    {
        $filters = $request->validate([
            'mode' => ['nullable', Rule::in(['bot', 'human', 'closed'])],
            'channel' => ['nullable', Rule::in(['website', 'whatsapp'])],
            'status' => ['nullable', Rule::in(array_keys(Conversation::STATUSES))],
        ]);

        return view('admin.conversations.index', [
            'conversations' => Conversation::query()
                ->with(['user:id,name', 'requirement:id,purpose,type,city,area_ids,max_price', 'lead:id,status'])
                ->withCount('messages')
                ->when($filters['mode'] ?? null, fn ($q, $m) => $q->where('mode', $m))
                ->when($filters['channel'] ?? null, fn ($q, $c) => $q->where('channel', $c))
                ->when($filters['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
                ->latest('last_message_at')
                ->paginate(25)
                ->withQueryString(),
            'filters' => $filters,
            'mode' => $filters['mode'] ?? null,
            'counts' => [
                'escalated' => Conversation::where('status', 'escalated')->count(),
                'failed' => Conversation::where('status', 'failed')->count(),
                'requirements' => BuyerRequirement::whereIn('source', ['ai', 'whatsapp'])->count(),
                'leads' => Lead::whereHas('requirement', fn ($q) => $q->whereIn('source', ['ai', 'whatsapp']))->count(),
                'webhookFailures' => WebhookEvent::where('status', 'failed')->where('created_at', '>=', now()->subDays(7))->count(),
            ],
            'configured' => $client->isConfigured(),
            'canSend' => $client->canSend(),
        ]);
    }

    public function show(Conversation $conversation, WhatsAppClient $client): View
    {
        $conversation->load(['user:id,name,email', 'requirement', 'lead.assignments.dealer.dealerProfile:id,user_id,agency_name']);

        return view('admin.conversations.show', [
            'conversation' => $conversation,
            'messages' => $conversation->recentMessages(200),
            'canSend' => $client->canSend(),
            'revealed' => session('revealed_contact_'.$conversation->id),
        ]);
    }

    public function updateMode(Request $request, Conversation $conversation): RedirectResponse
    {
        $data = $request->validate(['mode' => ['required', Rule::in(['bot', 'human', 'closed'])]]);
        $conversation->update(['mode' => $data['mode']] + ($data['mode'] === 'closed' ? ['status' => 'closed'] : []));

        AuditLogger::record('conversation.mode_changed', $conversation, ['mode' => $data['mode']]);

        return back()->with('status', 'Conversation handed to '.($data['mode'] === 'human' ? 'the team' : $data['mode']).'.');
    }

    /** Mark an escalation as handled. */
    public function resolve(Conversation $conversation): RedirectResponse
    {
        $conversation->update(['status' => $conversation->buyer_requirement_id ? 'qualified' : 'active', 'escalated_at' => null, 'escalation_reason' => null]);
        AuditLogger::record('conversation.escalation_resolved', $conversation);

        return back()->with('status', 'Escalation marked as handled.');
    }

    /**
     * Staff reply on WhatsApp. Goes through the same messenger and policy as
     * every message (opt-out and the 24-hour window apply; the automation
     * switch does not, so staff can still answer while automation is off).
     */
    public function reply(Request $request, Conversation $conversation, OutboundMessenger $messenger): RedirectResponse
    {
        abort_unless($conversation->channel === 'whatsapp', 404);
        $data = $request->validate(['body' => ['required', 'string', 'max:1000']]);

        $message = $conversation->messages()->create(['direction' => 'outbound', 'role' => 'assistant', 'type' => 'text', 'intent' => 'staff_reply', 'body' => $data['body']]);
        $outbound = $messenger->queue([
            'to' => $conversation->external_contact, 'body' => $data['body'], 'category' => 'transactional', 'purpose' => 'staff_reply',
            'dedupe_key' => 'staff:'.$message->id, 'conversation_id' => $conversation->id, 'conversation_message_id' => $message->id,
        ]);
        AuditLogger::record('conversation.staff_replied', $conversation, ['outbound_id' => $outbound->id, 'status' => $outbound->status]);

        return back()->with($outbound->status === 'blocked' ? 'error' : 'status', $outbound->status === 'blocked'
            ? 'Not sent: '.(OutboundMessage::BLOCK_REASONS[$outbound->blocked_reason] ?? $outbound->blocked_reason).'. The reply is saved in the conversation.'
            : 'Reply queued for sending.');
    }

    /** Show the full WhatsApp number once, for follow-up; every reveal is audited. */
    public function revealContact(Conversation $conversation): RedirectResponse
    {
        abort_unless($conversation->channel === 'whatsapp', 404);
        AuditLogger::record('conversation.contact_revealed', $conversation);

        return back()->with('revealed_contact_'.$conversation->id, '+'.$conversation->external_contact);
    }
}
