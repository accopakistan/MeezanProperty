<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BuyerRequirement;
use App\Models\Conversation;
use App\Models\Lead;
use App\Models\OutboundMessage;
use App\Models\User;
use App\Models\WebhookEvent;
use App\Services\Agents\AgentCatalog;
use App\Services\AuditLogger;
use App\Services\WhatsApp\HumanHandoff;
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
            'handoff' => ['nullable', Rule::in(array_keys(Conversation::HANDOFF_STATUSES))],
            'agent' => ['nullable', Rule::in(array_keys(AgentCatalog::all()))],
            'q' => ['nullable', 'string', 'max:60'],
        ]);
        $search = addcslashes(trim($filters['q'] ?? ''), '%_\\');

        return view('admin.conversations.index', [
            'conversations' => Conversation::query()
                ->with(['user:id,name', 'requirement:id,purpose,type,city,area_ids,max_price', 'lead:id,status', 'assignee:id,name', 'latestMessage'])
                ->withCount('messages')
                ->when($filters['mode'] ?? null, fn ($q, $m) => $q->where('mode', $m))
                ->when($filters['channel'] ?? null, fn ($q, $c) => $q->where('channel', $c))
                ->when($filters['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
                ->when($filters['handoff'] ?? null, fn ($q, $h) => $q->where('handoff_status', $h))
                ->when($filters['agent'] ?? null, fn ($q, $a) => $q->where('assigned_agent', $a))
                ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->where('contact_name', 'like', "%{$search}%")->orWhere('external_contact', 'like', "%{$search}")))
                ->latest('last_message_at')
                ->paginate(25)
                ->withQueryString(),
            'filters' => $filters,
            'mode' => $filters['mode'] ?? null,
            'counts' => [
                'escalated' => Conversation::where('status', 'escalated')->count(),
                'handoffs' => Conversation::where('handoff_status', 'pending')->count(),
                'failed' => Conversation::where('status', 'failed')->count(),
                'requirements' => BuyerRequirement::whereIn('source', ['ai', 'whatsapp'])->count(),
                'leads' => Lead::whereHas('requirement', fn ($q) => $q->whereIn('source', ['ai', 'whatsapp']))->count(),
                'webhookFailures' => WebhookEvent::where('status', 'failed')->where('created_at', '>=', now()->subDays(7))->count(),
            ],
            'configured' => $client->isConfigured(),
            'canSend' => $client->canSend(),
            'delivery' => $client->deliveryMode(),
        ]);
    }

    public function show(Conversation $conversation, WhatsAppClient $client): View
    {
        $conversation->load(['assignee:id,name', 'user:id,name,email', 'requirement', 'lead.assignments.dealer.dealerProfile:id,user_id,agency_name']);

        return view('admin.conversations.show', [
            'conversation' => $conversation,
            'messages' => $conversation->recentMessages(200),
            'canSend' => $client->canSend(),
            'revealed' => session('revealed_contact_'.$conversation->id),
            'staff' => User::activeAdmins(),
            'delivery' => $client->deliveryMode(),
            'outbound' => OutboundMessage::where('conversation_id', $conversation->id)->whereNotNull('conversation_message_id')
                ->get(['id', 'conversation_message_id', 'api_mode', 'is_test'])->keyBy('conversation_message_id'),
        ]);
    }

    public function updateMode(Request $request, Conversation $conversation, HumanHandoff $handoff): RedirectResponse
    {
        $data = $request->validate(['mode' => ['required', Rule::in(['bot', 'human', 'closed'])]]);
        // Taking over assigns the chat; going back to the AI or closing ends any open handoff.
        if ($data['mode'] === 'human' && $conversation->channel === 'whatsapp') {
            $handoff->assign($conversation, $request->user(), $request->user());
        } elseif ($conversation->hasOpenHandoff()) {
            $handoff->resolve($conversation, $request->user(), resumeAi: false);
        }
        $conversation->update(['mode' => $data['mode']] + ($data['mode'] === 'closed' ? ['status' => 'closed'] : []));

        AuditLogger::record('conversation.mode_changed', $conversation, ['mode' => $data['mode']]);

        return back()->with('status', 'Conversation handed to '.($data['mode'] === 'human' ? 'the team' : $data['mode']).'.');
    }

    /** Mark an escalation as handled. The chat stays where it is; the AI resumes only by "Return to bot". */
    public function resolve(Request $request, Conversation $conversation, HumanHandoff $handoff): RedirectResponse
    {
        if ($conversation->hasOpenHandoff()) {
            $handoff->resolve($conversation, $request->user(), resumeAi: false);
        }
        $conversation->update(['status' => $conversation->buyer_requirement_id ? 'qualified' : 'active', 'escalated_at' => null, 'escalation_reason' => null]);
        AuditLogger::record('conversation.escalation_resolved', $conversation);

        return back()->with('status', 'Escalation marked as handled.');
    }

    /** Assign a handoff to a member of staff, or resolve it, with or without resuming the AI. */
    public function handoff(Request $request, Conversation $conversation, HumanHandoff $handoff): RedirectResponse
    {
        abort_unless($conversation->channel === 'whatsapp', 404);
        $data = $request->validate([
            'action' => ['required', Rule::in(['assign', 'resolve', 'resume'])],
            'staff_id' => ['nullable', 'required_if:action,assign', 'integer'],
        ]);

        if ($data['action'] === 'assign') {
            $staff = User::activeAdmins()->firstWhere('id', (int) $data['staff_id']);
            if (! $staff) {
                return back()->with('error', 'Choose an active administrator.');
            }
            $handoff->assign($conversation, $staff, $request->user());

            return back()->with('status', 'Conversation assigned to '.$staff->name.'.');
        }
        $handoff->resolve($conversation, $request->user(), resumeAi: $data['action'] === 'resume');

        return back()->with('status', $data['action'] === 'resume' ? 'Handoff resolved. The AI will answer new messages.' : 'Handoff resolved. The chat stays with the team.');
    }

    /** Staff-only note; never sent to the customer. */
    public function note(Request $request, Conversation $conversation): RedirectResponse
    {
        $data = $request->validate(['internal_note' => ['nullable', 'string', 'max:2000']]);
        $conversation->forceFill(['internal_note' => $data['internal_note'] ?? null])->save();
        AuditLogger::record('conversation.note_updated', $conversation);

        return back()->with('status', 'Note saved.');
    }

    /**
     * Staff reply on WhatsApp. Goes through the same messenger and policy as
     * every message (opt-out and the 24-hour window apply; the automation
     * switch does not, so staff can still answer while automation is off).
     */
    public function reply(Request $request, Conversation $conversation, OutboundMessenger $messenger, HumanHandoff $handoff): RedirectResponse
    {
        abort_unless($conversation->channel === 'whatsapp', 404);
        $data = $request->validate(['body' => ['required', 'string', 'max:1000']]);

        $message = $conversation->messages()->create(['direction' => 'outbound', 'role' => 'assistant', 'type' => 'text', 'intent' => 'staff_reply', 'body' => $data['body'], 'staff_id' => $request->user()->id]);
        // Whoever answers a waiting chat has picked it up.
        if ($conversation->handoff_status === 'pending') {
            $handoff->assign($conversation, $request->user(), $request->user());
        }
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
