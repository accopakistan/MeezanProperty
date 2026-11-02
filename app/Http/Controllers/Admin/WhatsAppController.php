<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessInboundWhatsAppMessage;
use App\Models\AppSetting;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\Lead;
use App\Models\OutboundMessage;
use App\Models\WebhookEvent;
use App\Services\AuditLogger;
use App\Services\WhatsApp\OutboundMessenger;
use App\Services\WhatsApp\Providers\MetaCloudProvider;
use App\Services\WhatsApp\Providers\MockProvider;
use App\Services\WhatsApp\WebhookProcessor;
use App\Services\WhatsApp\WhatsAppClient;
use App\Services\WhatsApp\WhatsAppSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * WhatsApp control center: integration status (never secret values), live
 * counts, automation switches, the outbound log with retries and, on local
 * or testing environments only, the TEST / MOCK console.
 */
class WhatsAppController extends Controller
{
    public function index(WhatsAppClient $client, WhatsAppSettings $settings): View
    {
        $provider = $client->provider();
        $whatsapp = fn () => Conversation::where('channel', 'whatsapp');
        $inbound = fn () => ConversationMessage::where('direction', 'inbound')->whereHas('conversation', fn ($q) => $q->where('channel', 'whatsapp'));
        $appUrl = (string) config('app.url');
        $host = (string) parse_url($appUrl, PHP_URL_HOST);
        $isLocalHost = in_array($host, ['localhost', '127.0.0.1', '::1'], true) || str_ends_with($host, '.test') || str_ends_with($host, '.localhost');

        return view('admin.whatsapp.index', [
            'provider' => $provider,
            'driver' => config('meezan.whatsapp.driver'),
            'switches' => collect(WhatsAppSettings::SWITCHES)->map(fn ($s, $key) => ['label' => $s[2], 'on' => $settings->get($key)]),
            'checks' => $client->provider() instanceof MockProvider
                ? [...$provider->configurationChecks(), ...app(MetaCloudProvider::class)->configurationChecks()]
                : $provider->configurationChecks(),
            'receiving' => $client->isConfigured(),
            'isLive' => $client->isLive(),
            'webhookUrl' => route('webhooks.whatsapp'),
            'publicUrl' => ! $isLocalHost && str_starts_with($appUrl, 'https://'),
            'verifiedAt' => AppSetting::get('whatsapp.webhook_verified_at'),
            'lastWebhook' => WebhookEvent::where('provider', 'whatsapp')->latest('id')->first(),
            'lastTest' => AppSetting::get('whatsapp.last_connection_test'),
            'counts' => [
                'conversations' => $whatsapp()->count(),
                'active' => $whatsapp()->where('last_message_at', '>=', now()->subDay())->count(),
                'incoming' => $inbound()->count(),
                'outgoing' => OutboundMessage::count(),
                'failed' => OutboundMessage::where('status', 'failed')->count(),
                'retrying' => OutboundMessage::where('status', 'queued')->whereNotNull('last_error')->count(),
                'blocked' => OutboundMessage::where('status', 'blocked')->count(),
                'held' => $inbound()->whereIn('processing_status', ['held', 'rate_limited'])->count(),
                'processingFailed' => $inbound()->where('processing_status', 'failed')->count(),
                'leads' => Lead::whereHas('requirement', fn ($q) => $q->where('source', 'whatsapp'))->count(),
                'test' => OutboundMessage::where('is_test', true)->count(),
                'webhookFailures' => WebhookEvent::where('provider', 'whatsapp')->where('status', 'failed')->where('created_at', '>=', now()->subDays(7))->count(),
            ],
            'queue' => $this->queueState(),
            'attention' => OutboundMessage::with('conversation:id,channel,external_contact')->whereIn('status', ['failed', 'blocked'])->latest('id')->take(10)->get(),
            'heldMessages' => $inbound()->with('conversation:id,external_contact,contact_name')->whereIn('processing_status', ['held', 'rate_limited', 'failed'])->latest('id')->take(10)->get(),
            'simulator' => $this->simulatorAvailable($client, $settings),
            'lastSimulated' => session('whatsapp_simulated'),
        ]);
    }

    public function messages(Request $request): View
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(OutboundMessage::STATUSES)],
            'purpose' => ['nullable', 'string', 'max:40'],
        ]);

        return view('admin.whatsapp.messages', [
            'messages' => OutboundMessage::query()
                ->when($filters['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
                ->when($filters['purpose'] ?? null, fn ($q, $p) => $q->where('purpose', $p))
                ->latest('id')->paginate(30)->withQueryString(),
            'counts' => OutboundMessage::groupBy('status')->selectRaw('status, count(*) as total')->pluck('total', 'status'),
            'filters' => $filters,
        ]);
    }

    public function updateSettings(Request $request, WhatsAppSettings $settings): RedirectResponse
    {
        $request->validate(['switch' => ['required', Rule::in(array_keys(WhatsAppSettings::SWITCHES))], 'on' => ['required', 'boolean']]);
        $settings->set($request->input('switch'), $request->boolean('on'), $request->user());

        return back()->with('status', WhatsAppSettings::SWITCHES[$request->input('switch')][2].' turned '.($request->boolean('on') ? 'on' : 'off').'.');
    }

    public function testConnection(Request $request, WhatsAppClient $client): RedirectResponse
    {
        $result = $client->provider()->testConnection();
        AppSetting::put('whatsapp.last_connection_test', $result + ['at' => now()->toIso8601String(), 'provider' => $client->provider()->key()], $request->user()->id);
        AuditLogger::record('whatsapp.connection_tested', $request->user(), ['provider' => $client->provider()->key(), 'ok' => $result['ok']], $request->user()->id);

        return back()->with($result['ok'] ? 'status' : 'error', $result['detail']);
    }

    public function retry(Request $request, OutboundMessage $message, OutboundMessenger $messenger): RedirectResponse
    {
        try {
            $message = $messenger->retry($message, $request->user());
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with($message->status === 'blocked' ? 'error' : 'status', $message->status === 'blocked'
            ? 'Not retried: '.(OutboundMessage::BLOCK_REASONS[$message->blocked_reason] ?? $message->blocked_reason).'.'
            : 'Message #'.$message->id.' queued again.');
    }

    /**
     * TEST / MOCK console: feeds a WhatsApp-shaped payload through the real
     * webhook processor (same normalisation, idempotency and assistant).
     */
    public function simulate(Request $request, WhatsAppClient $client, WhatsAppSettings $settings, WebhookProcessor $processor, OutboundMessenger $messenger): RedirectResponse
    {
        abort_unless($this->simulatorAvailable($client, $settings), 404);
        $data = $request->validate([
            'action' => ['required', Rule::in(['message', 'duplicate', 'fail_next', 'fail_next_temporary', 'status'])],
            'from' => ['nullable', 'regex:/^92\d{10}$/'],
            'name' => ['nullable', 'string', 'max:60'],
            'text' => ['nullable', 'required_if:action,message', 'string', 'max:1000'],
            'process_now' => ['nullable', 'boolean'],
            'outbound_id' => ['nullable', 'required_if:action,status', 'integer'],
            'delivery' => ['nullable', 'required_if:action,status', Rule::in(['delivered', 'read', 'failed'])],
        ], ['from.regex' => 'Use a test number such as 920000000001 (92 followed by 10 digits).']);
        $from = $data['from'] ?? '920000000001';

        if (in_array($data['action'], ['fail_next', 'fail_next_temporary'], true)) {
            MockProvider::failNext($data['action'] === 'fail_next' ? 'permanent' : 'temporary');
            AuditLogger::record('whatsapp.simulator_used', $request->user(), ['action' => $data['action']], $request->user()->id);

            return back()->with('status', 'The next mock send will fail ('.($data['action'] === 'fail_next' ? 'permanent' : 'temporary').').');
        }

        if ($data['action'] === 'status') {
            $outbound = OutboundMessage::where('is_test', true)->findOrFail($data['outbound_id']);
            $processor->process(['object' => 'whatsapp_business_account', 'entry' => [['changes' => [['value' => ['statuses' => [array_filter([
                'id' => $outbound->provider_message_id, 'status' => $data['delivery'],
                'errors' => $data['delivery'] === 'failed' ? [['code' => 131000, 'title' => 'Simulated delivery failure (test)']] : null,
            ])]]]]]]]);

            return back()->with('status', 'Simulated "'.$data['delivery'].'" status for message #'.$outbound->id.'.');
        }

        $last = session('whatsapp_simulated');
        $id = $data['action'] === 'duplicate' && $last ? $last['id'] : 'wamid.TEST.'.Str::random(20);
        $text = $data['action'] === 'duplicate' && $last ? $last['text'] : $data['text'];
        $from = $data['action'] === 'duplicate' && $last ? $last['from'] : $from;
        $payload = ['object' => 'whatsapp_business_account', 'entry' => [['changes' => [['value' => [
            'contacts' => [['wa_id' => $from, 'profile' => ['name' => $data['name'] ?? 'Test buyer']]],
            'messages' => [['id' => $id, 'from' => $from, 'timestamp' => (string) now()->timestamp, 'type' => 'text', 'text' => ['body' => $text]]],
        ]]]]]];

        // Same idempotency record as the real webhook, under a test provider name.
        $event = WebhookEvent::firstOrCreate(['provider' => 'whatsapp-test', 'payload_hash' => hash('sha256', $id)], ['status' => 'processing']);
        $stored = $event->wasRecentlyCreated ? $processor->process($payload) : 0;
        $event->update(['status' => $stored ? 'processed' : 'duplicate', 'messages' => $stored]);
        AuditLogger::record('whatsapp.simulator_used', $request->user(), ['action' => $data['action'], 'stored' => $stored], $request->user()->id);

        if ($stored && $request->boolean('process_now')) {
            $this->runNow($id, $messenger);
        }

        return back()->with('whatsapp_simulated', ['id' => $id, 'text' => $text, 'from' => $from])
            ->with('status', $stored ? 'Test message received'.($request->boolean('process_now') ? ' and processed.' : '. It is waiting for the queue worker.') : 'Duplicate delivery: ignored, nothing stored or answered.');
    }

    /** Local convenience: run this message's jobs now instead of waiting for the queue worker. */
    protected function runNow(string $externalId, OutboundMessenger $messenger): void
    {
        $inbound = ConversationMessage::where('external_id', $externalId)->first();
        if ($inbound?->processing_status === 'queued') {
            app()->call([new ProcessInboundWhatsAppMessage($inbound->id), 'handle']);
        }
        OutboundMessage::where('conversation_id', $inbound?->conversation_id)->where('status', 'queued')->pluck('id')
            ->each(fn ($id) => $messenger->deliver($id));
    }

    protected function simulatorAvailable(WhatsAppClient $client, WhatsAppSettings $settings): bool
    {
        return app()->environment(['local', 'testing']) && ($client->provider() instanceof MockProvider);
    }

    /** @return array{connection: string, pending: ?int, failed: int} */
    protected function queueState(): array
    {
        $connection = (string) config('queue.default');

        return [
            'connection' => $connection,
            'pending' => $connection === 'database' ? DB::table(config('queue.connections.database.table', 'jobs'))->count() : null,
            'failed' => DB::table('failed_jobs')->count(),
        ];
    }
}
