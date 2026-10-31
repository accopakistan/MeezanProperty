<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\WebhookEvent;
use App\Notifications\AssistantNeedsAttention;
use App\Services\WhatsApp\WebhookProcessor;
use App\Services\WhatsApp\WhatsAppClient;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

class WhatsAppWebhookController extends Controller
{
    /**
     * Meta's subscription handshake: echo hub.challenge when the token matches.
     */
    public function verify(Request $request): Response
    {
        $token = config('services.whatsapp.verify_token');

        if ($token && $request->query('hub_mode') === 'subscribe' && hash_equals($token, (string) $request->query('hub_verify_token'))) {
            return response((string) $request->query('hub_challenge'), 200)->header('Content-Type', 'text/plain');
        }

        return response('Forbidden', 403);
    }

    /**
     * Order of checks: configured → signature → shape → duplicate delivery →
     * process. Every delivery is recorded in webhook_events; a processing
     * failure is logged, reported to admins and answered with 500 so Meta
     * retries (message ids keep the retry from storing anything twice).
     */
    public function receive(Request $request, WhatsAppClient $client, WebhookProcessor $processor): JsonResponse
    {
        if (! $client->isConfigured()) {
            return response()->json(['message' => 'WhatsApp integration is not configured.'], 503);
        }

        $raw = $request->getContent();
        if (! $client->verifySignature($raw, $request->header('X-Hub-Signature-256'))) {
            return response()->json(['message' => 'Invalid signature.'], 401);
        }

        $hash = hash('sha256', $raw);
        $payload = json_decode($raw, true);
        if (! is_array($payload) || ! WebhookProcessor::isValid($payload)) {
            WebhookEvent::firstOrCreate(['provider' => 'whatsapp', 'payload_hash' => $hash], ['status' => 'rejected', 'error' => 'Unexpected payload shape']);

            return response()->json(['message' => 'Invalid payload.'], 422);
        }

        try {
            $event = WebhookEvent::create(['provider' => 'whatsapp', 'payload_hash' => $hash, 'status' => 'processing']);
        } catch (UniqueConstraintViolationException) {
            $previous = WebhookEvent::where('provider', 'whatsapp')->where('payload_hash', $hash)->first();
            if ($previous && $previous->status !== 'failed') {
                return response()->json(['received' => 0, 'duplicate' => true]);
            }
            $event = $previous;
        }

        try {
            $stored = $processor->process($payload);
            $event->update(['status' => 'processed', 'messages' => $stored, 'error' => null]);
        } catch (Throwable $e) {
            $event->update(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 250)]);
            Log::error('WhatsApp webhook processing failed', ['event' => $event->id, 'error' => $e->getMessage()]);
            Notification::send(User::activeAdmins(), new AssistantNeedsAttention('webhook_failed', null, 'Webhook event #'.$event->id));

            return response()->json(['message' => 'Processing failed.'], 500);
        }

        return response()->json(['received' => $stored]);
    }
}
