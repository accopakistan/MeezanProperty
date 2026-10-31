<x-layouts.dashboard title="Notifications" :section="$section" heading="Notifications" subheading="Activity on your account. Emails, if switched on, carry the same messages.">
    @if ($unread)
        <x-slot:actions>
            <form method="POST" action="{{ route('notifications.read-all') }}">@csrf
                <button class="btn btn-secondary"><x-icon name="check" class="size-4" /> Mark all as read</button>
            </form>
        </x-slot:actions>
    @endif

    <div class="mb-5 inline-flex rounded-md border border-line bg-white p-1">
        @foreach (['all' => 'All', 'unread' => 'Unread'.($unread ? ' ('.$unread.')' : '')] as $value => $label)
            <a href="{{ route('notifications.index', $value === 'all' ? [] : ['filter' => $value]) }}" @class(['rounded px-3 py-1.5 text-sm font-medium', 'bg-brand-600 text-white' => $filter === $value, 'text-muted hover:text-ink' => $filter !== $value])>{{ $label }}</a>
        @endforeach
    </div>

    @if ($notifications->isEmpty())
        <x-empty-state icon="bell" :title="$filter === 'unread' ? 'You are all caught up' : 'No notifications yet'">
            New inquiries, leads, review decisions and expiry reminders will appear here.
        </x-empty-state>
    @else
        <ul class="card divide-y divide-line overflow-hidden">
            @foreach ($notifications as $notification)
                @php $data = $notification->data; $unreadItem = $notification->read_at === null; @endphp
                <li>
                    <a href="{{ route('notifications.open', $notification->id) }}" @class(['flex items-start gap-3 px-5 py-4 hover:bg-page', 'bg-brand-50/60' => $unreadItem])>
                        <span @class(['grid size-10 shrink-0 place-items-center rounded-xl', 'bg-brand-600 text-white' => $unreadItem, 'bg-page text-brand-600' => ! $unreadItem])>
                            <x-icon :name="$data['icon'] ?? 'bell'" class="size-5" />
                        </span>
                        <span class="min-w-0 flex-1">
                            <span @class(['block text-sm', 'font-bold' => $unreadItem, 'font-semibold' => ! $unreadItem])>{{ $data['title'] ?? 'Notification' }}</span>
                            @if (! empty($data['body']))<span class="mt-0.5 block text-[13px] text-muted">{{ $data['body'] }}</span>@endif
                            <span class="mt-1 block text-xs text-muted" title="{{ $notification->created_at->format('j M Y, g:i a') }}">{{ $notification->created_at->diffForHumans() }}</span>
                        </span>
                        @if ($unreadItem)<span class="mt-2 size-2.5 shrink-0 rounded-full bg-gold-500" aria-label="Unread"></span>@endif
                    </a>
                </li>
            @endforeach
        </ul>
        <div class="mt-6">{{ $notifications->links() }}</div>
    @endif
</x-layouts.dashboard>
