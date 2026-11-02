@php use App\Models\OutboundMessage; @endphp
<x-layouts.dashboard title="WhatsApp messages" section="admin" heading="Outgoing WhatsApp messages" subheading="Every outgoing message and its delivery status. Numbers are masked; statuses change only on real provider responses.">
    <x-breadcrumbs :items="[['WhatsApp', route('admin.whatsapp')], ['Outgoing messages', null]]" class="mb-5" />
    <div class="mb-5 flex gap-1 overflow-x-auto rounded-xl border border-line bg-white p-1 scrollbar-none">
        <a href="{{ route('admin.whatsapp.messages') }}" @class(['shrink-0 rounded-lg px-3.5 py-2 text-sm font-semibold', 'bg-brand-600 text-white' => empty($filters['status']), 'text-muted hover:text-ink' => ! empty($filters['status'])])>All <span class="text-xs opacity-75">{{ $counts->sum() }}</span></a>
        @foreach (OutboundMessage::STATUSES as $status)
            <a href="{{ route('admin.whatsapp.messages', ['status' => $status]) }}" @class(['shrink-0 rounded-lg px-3.5 py-2 text-sm font-semibold', 'bg-brand-600 text-white' => ($filters['status'] ?? null) === $status, 'text-muted hover:text-ink' => ($filters['status'] ?? null) !== $status])>{{ ucfirst($status) }} <span class="text-xs opacity-75">{{ $counts[$status] ?? 0 }}</span></a>
        @endforeach
    </div>

    @if ($messages->isEmpty())
        <x-empty-state icon="message" title="No outgoing messages">Messages appear here when the assistant replies, staff reply or dealers are alerted.</x-empty-state>
    @else
        <div class="card overflow-hidden"><div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>#</th><th>To</th><th>Purpose</th><th>Message</th><th>Status</th><th>Provider</th><th>Time</th><th class="sr-only">Actions</th></tr></thead>
                <tbody>
                    @foreach ($messages as $message)
                        <tr>
                            <td class="tabular-nums">{{ $message->id }}</td>
                            <td class="whitespace-nowrap">{{ $message->maskedRecipient() }}@if ($message->conversation_id)<a href="{{ route('admin.conversations.show', $message->conversation_id) }}" class="block text-xs text-brand-700 hover:underline">conversation</a>@endif</td>
                            <td class="text-[13px] whitespace-nowrap">{{ str_replace('_', ' ', $message->purpose) }}<span class="block text-xs text-muted">{{ $message->category }} · {{ $message->type }}</span></td>
                            <td class="max-w-80 text-[13px]"><span class="line-clamp-2 whitespace-pre-line">{{ $message->body }}</span></td>
                            <td><x-status-badge :tone="$message->statusTone()" :label="ucfirst($message->status)" />
                                @if ($message->status === 'blocked')<span class="mt-1 block max-w-48 text-xs text-muted">{{ OutboundMessage::BLOCK_REASONS[$message->blocked_reason] ?? $message->blocked_reason }}</span>
                                @elseif ($message->last_error)<span class="mt-1 block max-w-48 text-xs text-muted">{{ $message->last_error }}</span>@endif</td>
                            <td class="text-[13px] whitespace-nowrap">{{ $message->provider }}@if ($message->is_test) <span class="badge bg-amber-50 text-amber-700">Test</span>@endif<span class="block text-xs text-muted">{{ $message->attempts }} {{ \Illuminate\Support\Str::plural('attempt', $message->attempts) }}</span></td>
                            <td class="text-[13px] whitespace-nowrap text-muted">{{ $message->created_at->format('j M, g:i a') }}@if ($message->delivered_at)<span class="block">delivered {{ $message->delivered_at->format('g:i a') }}</span>@endif @if ($message->read_at)<span class="block">read {{ $message->read_at->format('g:i a') }}</span>@endif</td>
                            <td class="text-right">@if ($message->isRetryable())<form method="POST" action="{{ route('admin.whatsapp.retry', $message) }}">@csrf <button class="btn btn-secondary btn-sm">Retry</button></form>@endif</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div></div>
        <div class="mt-6">{{ $messages->links() }}</div>
    @endif
</x-layouts.dashboard>
