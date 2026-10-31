<x-layouts.dashboard title="Leads & inquiries" section="dealer" heading="Leads & inquiries" subheading="Inquiries on your listings and buyer requirements routed to your areas.">
    <div class="mb-5 inline-flex rounded-xl border border-line bg-white p-1">
        @foreach (['inquiries' => 'Listing inquiries', 'requirements' => 'Requirement leads'] as $key => $label)
            <a href="{{ route('dealer.leads', ['tab' => $key]) }}" @class(['rounded-lg px-4 py-2 text-sm font-semibold', 'bg-brand-600 text-white' => $tab === $key, 'text-muted hover:text-ink' => $tab !== $key])>{{ $label }}</a>
        @endforeach
    </div>

    @if ($tab === 'inquiries')
        @if ($inquiries->isEmpty())
            <x-empty-state icon="inbox" title="No inquiries yet">Inquiries buyers send from your listing pages will appear here.</x-empty-state>
        @else
            <div class="space-y-3">
                @foreach ($inquiries as $inquiry)
                    <article class="card card-body">
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h2 class="font-bold">{{ $inquiry->name }}</h2>
                                    <x-status-badge :tone="$inquiry->statusTone()" :label="ucfirst($inquiry->status)" />
                                </div>
                                <p class="mt-1 text-[13px] text-muted">
                                    About
                                    @if ($inquiry->property && ! $inquiry->property->trashed())
                                        <a href="{{ route('properties.show', $inquiry->property) }}" class="link">{{ $inquiry->property->reference }} – {{ $inquiry->property->title }}</a>
                                    @else
                                        a removed listing
                                    @endif
                                    · {{ $inquiry->created_at->format('j M Y, g:i a') }}
                                </p>
                            </div>
                            <form method="POST" action="{{ route('dealer.inquiries.update', $inquiry) }}" class="flex items-center gap-2">
                                @csrf @method('PATCH')
                                <label for="status-{{ $inquiry->id }}" class="sr-only">Status</label>
                                <select id="status-{{ $inquiry->id }}" name="status" class="input w-auto py-2 text-sm" data-autosubmit>
                                    @foreach (['new' => 'New', 'contacted' => 'Contacted', 'closed' => 'Closed'] as $value => $label)
                                        <option value="{{ $value }}" @selected($inquiry->status === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                <noscript><button class="btn btn-secondary btn-sm">Update</button></noscript>
                            </form>
                        </div>
                        <p class="mt-4 rounded-xl bg-page p-4 text-sm leading-6 whitespace-pre-line">{{ $inquiry->message }}</p>
                        <div class="mt-4 flex flex-wrap items-center gap-2 text-sm">
                            <span class="text-[13px] text-muted">Prefers {{ $inquiry->preferred_contact }}:</span>
                            <a href="tel:{{ preg_replace('/[^\d+]/', '', $inquiry->phone) }}" class="btn btn-secondary btn-sm"><x-icon name="phone" class="size-4" /> {{ $inquiry->phone }}</a>
                            <a href="https://wa.me/{{ preg_replace('/^0/', '92', preg_replace('/\D+/', '', $inquiry->phone)) }}" target="_blank" rel="noopener" class="btn btn-secondary btn-sm"><x-icon name="whatsapp" class="size-4" /> WhatsApp</a>
                            <a href="mailto:{{ $inquiry->email }}" class="btn btn-secondary btn-sm"><x-icon name="mail" class="size-4" /> {{ $inquiry->email }}</a>
                        </div>
                    </article>
                @endforeach
            </div>
            <div class="mt-6">{{ $inquiries->links() }}</div>
        @endif
    @else
        @unless (auth()->user()->isVerifiedDealer())
            <div class="mb-5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">Requirement leads are routed only to verified dealers. They will start once your agency is verified.</div>
        @endunless
        @if ($assignments->isEmpty())
            <x-empty-state icon="target" title="No requirement leads yet">When a buyer looking in your areas agrees to share their requirement, it appears here.</x-empty-state>
        @else
            <div class="card overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="table">
                        <thead><tr><th>Buyer</th><th>Looking for</th><th>Contact</th><th>Status</th><th>Assigned</th><th class="sr-only">Update</th></tr></thead>
                        <tbody>
                            @foreach ($assignments as $assignment)
                                @php $req = $assignment->lead->requirement; @endphp
                                <tr>
                                    <td class="font-semibold">{{ $req?->user?->name ?? $req?->contact_name ?? '—' }}</td>
                                    <td class="min-w-64 text-[13px]">{{ $req?->summary() }}</td>
                                    <td class="text-[13px]">
                                        @if ($req?->user)
                                            <a href="mailto:{{ $req->user->email }}" class="link">{{ $req->user->email }}</a>
                                            @if ($req->user->phone)<br><a href="tel:{{ $req->user->phone }}" class="text-muted">{{ $req->user->phone }}</a>@endif
                                        @elseif ($req?->contact_phone)
                                            {{-- Shared by the buyer on WhatsApp, with consent --}}
                                            <a href="https://wa.me/{{ preg_replace('/\D+/', '', $req->contact_phone) }}" class="link" target="_blank" rel="noopener">WhatsApp +{{ preg_replace('/\D+/', '', $req->contact_phone) }}</a>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($assignment->status === 'active')
                                            <x-status-badge :tone="$assignment->lead->status->tone()" :label="$assignment->lead->status->label()" />
                                        @else
                                            <x-status-badge tone="muted" label="Reassigned" />
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap text-muted">{{ $assignment->assigned_at->format('j M Y') }}</td>
                                    <td>
                                        @if ($assignment->status === 'active')
                                            <form method="POST" action="{{ route('dealer.assignments.update', $assignment) }}">
                                                @csrf @method('PATCH')
                                                <select name="status" class="input w-auto py-1.5 text-[13px]" data-autosubmit aria-label="Update lead status">
                                                    <option value="">Update…</option>
                                                    @foreach (['contacted' => 'Contacted', 'qualified' => 'Qualified', 'closed' => 'Closed (won)', 'lost' => 'Lost'] as $value => $label)
                                                        <option value="{{ $value }}">{{ $label }}</option>
                                                    @endforeach
                                                </select>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="mt-6">{{ $assignments->links() }}</div>
        @endif
    @endif
</x-layouts.dashboard>
