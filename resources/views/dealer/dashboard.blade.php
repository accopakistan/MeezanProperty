@php
    use App\Enums\DealerStatus;
    $status = $profile->verification_status;
@endphp
<x-layouts.dashboard title="Dealer dashboard" section="dealer" :heading="'Welcome, '.\Illuminate\Support\Str::before($dealer->name, ' ')" :subheading="$profile->agency_name">
    <x-slot:actions>
        <a href="{{ route('dealer.properties.create') }}" class="btn btn-primary"><x-icon name="plus" class="size-4" /> Add a listing</a>
    </x-slot:actions>

    {{-- Verification status --}}
    @php
        $waiting = in_array($status, [DealerStatus::Pending, DealerStatus::Submitted, DealerStatus::UnderReview, DealerStatus::Expired], true);
        $headline = $profile->verificationHeadline();
        $submitted = $profile->verification_submitted_at;
    @endphp
    @if ($status !== DealerStatus::Verified)
        <div @class(['mb-6 flex flex-col gap-3 rounded-2xl border p-5 sm:flex-row sm:items-center',
                     'border-amber-200 bg-amber-50' => $waiting,
                     'border-red-200 bg-red-50' => ! $waiting])>
            <span @class(['grid size-11 shrink-0 place-items-center rounded-xl', 'bg-amber-100 text-amber-700' => $waiting, 'bg-red-100 text-red-700' => ! $waiting])>
                <x-icon :name="$waiting ? 'clock' : 'alert'" />
            </span>
            <div class="flex-1 text-sm">
                <p class="font-bold">{{ $status->label() }} · {{ $headline['title'] }}</p>
                @if ($waiting)
                    <p class="text-ink/75">{{ $headline['body'] }} You can prepare and submit listings now; the verified badge and requirement leads start once you’re approved.</p>
                    {{-- Stepper from reference 5C. Verification Confirmation --}}
                    <ol class="mt-3 flex max-w-lg items-center text-xs">
                        @foreach ([
                            ['Submitted', $submitted?->format('j M Y') ?? 'Not yet', $submitted && $status !== DealerStatus::Pending ? 'done' : 'current'],
                            ['Under Review', '1–2 working days', $status === DealerStatus::UnderReview ? 'current' : ($status === DealerStatus::Submitted ? 'current' : 'todo')],
                            ['Verified', 'You will be notified', 'todo'],
                        ] as $i => [$step, $note, $state])
                            <li class="flex flex-1 items-center gap-2 {{ $i < 2 ? 'after:mx-2 after:h-0.5 after:flex-1 after:bg-amber-200' : '' }}">
                                <span class="grid size-6 shrink-0 place-items-center rounded-full {{ ['done' => 'bg-emerald-500 text-white', 'current' => 'bg-brand-600 text-white', 'todo' => 'bg-zinc-200 text-zinc-500'][$state] }}">
                                    <x-icon :name="$state === 'done' ? 'check' : ($state === 'current' ? 'clock' : 'badge-check')" class="size-3.5" />
                                </span>
                                <span class="leading-tight"><span class="block font-semibold">{{ $step }}</span><span class="text-muted">{{ $note }}</span></span>
                            </li>
                        @endforeach
                    </ol>
                @else
                    <p class="text-ink/75">{{ $profile->rejection_reason ?: 'Please contact Meezan Property support for details.' }}</p>
                @endif
            </div>
            <div class="flex flex-wrap gap-2">
                @if ($status !== DealerStatus::Suspended)
                    <a href="{{ route('dealer.verification') }}" class="btn btn-primary btn-sm"><x-icon name="upload" class="size-4" /> {{ $profile->documents()->exists() ? 'Verification documents' : 'Upload documents' }}</a>
                @endif
                <a href="{{ route('dealer.profile') }}" class="btn btn-secondary btn-sm">Review profile</a>
            </div>
        </div>
    @endif

    {{-- Reference: 5D. Dealer Dashboard (Overview) --}}
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat-card label="Active Listings" :value="$stats['published']" icon="home" tone="brand" :hint="$stats['pending'].' pending review'" :href="route('dealer.properties.index', ['status' => 'published'])" />
        <x-stat-card label="Total Inquiries" :value="$totalInquiries" icon="users" tone="brand" :hint="$newInquiries.' new'" :href="route('dealer.leads')" />
        <x-stat-card label="Listing Views" :value="number_format($totalViews)" icon="eye" tone="success" hint="Across all your listings" />
        <x-stat-card label="Deals Closed" :value="$closed" icon="badge-check" tone="info" hint="Inquiries marked closed" />
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1.5fr)_minmax(0,1fr)]">
        <section class="card card-body" aria-labelledby="inq-chart">
            <h2 id="inq-chart" class="font-bold">Inquiries (Last 30 Days)</h2>
            <x-line-chart class="mt-3" :labels="$chart['labels']" :series="$chart['series']" label="Inquiries per day over the last 30 days by preferred contact method" />
        </section>
        <section class="card card-body" aria-labelledby="type-chart">
            <h2 id="type-chart" class="font-bold">Property Types</h2>
            <x-donut-chart class="mt-5" :rows="$typeShare" label="Your published listings by category" />
        </section>
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1.5fr)_minmax(0,1fr)]">
        <section class="card" aria-labelledby="recent-listings">
            <div class="flex items-center justify-between border-b border-line px-5 py-4">
                <h2 id="recent-listings" class="font-bold">Recent listings</h2>
                <a href="{{ route('dealer.properties.index') }}" class="text-sm font-semibold text-brand-600">View all</a>
            </div>
            @if ($recentListings->isEmpty())
                <x-empty-state compact class="m-5 border-0" icon="layers" title="No listings yet">
                    Add your first property — it will be reviewed before it goes live.
                    <x-slot:actions><a href="{{ route('dealer.properties.create') }}" class="btn btn-primary btn-sm">Add a listing</a></x-slot:actions>
                </x-empty-state>
            @else
                <ul class="divide-y divide-line">
                    @foreach ($recentListings as $property)
                        <li class="flex items-center gap-4 px-5 py-3.5">
                            <img src="{{ $property->coverThumbUrl() ?? $property->placeholderUrl() }}" alt="" class="h-12 w-16 shrink-0 rounded-lg object-cover">
                            <div class="min-w-0 flex-1">
                                <a href="{{ route('dealer.properties.edit', $property) }}" class="block truncate text-sm font-semibold hover:text-brand-700">{{ $property->title }}</a>
                                <p class="text-xs text-muted">{{ $property->reference }} · {{ $property->area?->name }} · {{ $property->priceLabel() }}</p>
                            </div>
                            <x-status-badge :tone="$property->status->tone()" :label="$property->status->label()" />
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        <div class="space-y-6">
            <section class="card card-body" aria-labelledby="pipeline">
                <h2 id="pipeline" class="font-bold">Listing pipeline</h2>
                <dl class="mt-4 grid grid-cols-3 gap-3 text-center">
                    @foreach (['draft' => 'Drafts', 'rejected' => 'Needs changes', 'expired' => 'Expired'] as $key => $label)
                        <a href="{{ route('dealer.properties.index', ['status' => $key]) }}" class="rounded-xl bg-page p-3 hover:bg-brand-50">
                            <dd class="text-xl font-extrabold">{{ $stats[$key] }}</dd>
                            <dt class="text-xs text-muted">{{ $label }}</dt>
                        </a>
                    @endforeach
                </dl>
            </section>

            <section class="card" aria-labelledby="recent-inquiries">
                <div class="flex items-center justify-between border-b border-line px-5 py-4">
                    <h2 id="recent-inquiries" class="font-bold">Latest inquiries</h2>
                    <span class="text-xs text-muted">{{ $activeLeads }} active {{ \Illuminate\Support\Str::plural('lead', $activeLeads) }}</span>
                </div>
                @forelse ($recentInquiries as $inquiry)
                    <a href="{{ route('dealer.leads') }}" class="flex items-start gap-3 border-b border-line px-5 py-3.5 last:border-0 hover:bg-page">
                        <span class="grid size-9 shrink-0 place-items-center rounded-full bg-brand-50 text-xs font-bold text-brand-700">{{ mb_strtoupper(mb_substr($inquiry->name, 0, 1)) }}</span>
                        <span class="min-w-0 flex-1 text-sm">
                            <span class="flex items-center justify-between gap-2"><span class="truncate font-semibold">{{ $inquiry->name }}</span><span class="shrink-0 text-xs text-muted">{{ $inquiry->created_at->diffForHumans(short: true) }}</span></span>
                            <span class="block truncate text-xs text-muted">{{ $inquiry->property?->title ?? 'Listing removed' }}</span>
                        </span>
                    </a>
                @empty
                    <p class="px-5 py-8 text-center text-sm text-muted">No inquiries yet. They’ll appear here as buyers contact you.</p>
                @endforelse
            </section>

            <section class="card card-body" aria-labelledby="areas">
                <h2 id="areas" class="font-bold">Your operating areas</h2>
                <div class="mt-3 flex flex-wrap gap-1.5">
                    @foreach ($profile->areas as $area)
                        <span class="badge bg-brand-50 text-brand-700">{{ $area->name }}</span>
                    @endforeach
                </div>
                <p class="mt-3 text-xs text-muted">Requirement leads are routed to verified dealers by these areas.</p>
            </section>
        </div>
    </div>
</x-layouts.dashboard>
