{{-- Location facts from the research dataset. Approval wording repeats the cited source only. --}}
<section class="card card-body mt-6" aria-labelledby="location-title">
    <h2 id="location-title" class="font-serif text-xl font-bold">About this location</h2>
    <dl class="mt-4 grid gap-x-8 gap-y-3 text-sm sm:grid-cols-2 lg:grid-cols-3">
        <div><dt class="text-muted">Type</dt><dd class="font-medium">{{ $area->typeLabel() }}</dd></div>
        @if ($area->parent)
            <div><dt class="text-muted">Part of</dt><dd class="font-medium"><a href="{{ route('areas.show', $area->parent) }}" class="link">{{ $area->parent->name }}</a></dd></div>
        @endif
        @if ($area->authority)
            <div><dt class="text-muted">Authority</dt><dd class="font-medium">{{ $area->authority }}</dd></div>
        @endif
        @if (! in_array($area->approval_status, ['not_applicable'], true))
            <div class="sm:col-span-2 lg:col-span-3">
                <dt class="text-muted">Approval status</dt>
                <dd class="font-medium">
                    {{ $area->approvalLabel() }}
                    @if ($area->approval_reference)<span class="block text-[13px] font-normal text-muted">{{ $area->approval_reference }}</span>@endif
                    @if ($area->verification_source_url)
                        <a href="{{ $area->verification_source_url }}" class="link text-[13px] font-normal" rel="nofollow noopener" target="_blank">Source</a>
                    @endif
                    @if ($area->verified_on)<span class="text-[13px] font-normal text-muted">· checked {{ $area->verified_on->format('j M Y') }}</span>@endif
                </dd>
            </div>
        @endif
        @if ($area->aliases->isNotEmpty())
            <div class="sm:col-span-2 lg:col-span-3"><dt class="text-muted">Also known as</dt><dd>{{ $area->aliases->pluck('alias')->implode(', ') }}</dd></div>
        @endif
    </dl>
    @if (! in_array($area->approval_status, ['not_applicable'], true))
        <p class="mt-4 rounded-lg bg-page p-3 text-[13px] text-muted">
            Status shown as published by the authority on the date checked; approvals change. Meezan Property does not certify any scheme.
            Confirm the current status with the development authority before you buy.
        </p>
    @endif
    @if ($children->isNotEmpty())
        <h3 class="mt-5 text-sm font-semibold">Phases, sectors and blocks</h3>
        <ul class="mt-2 flex flex-wrap gap-2">
            @foreach ($children as $child)
                <li><a href="{{ route('areas.show', $child) }}" class="inline-flex rounded-full border border-line px-3 py-1 text-[13px] hover:border-brand-300 hover:text-brand-700">{{ $child->name }}</a></li>
            @endforeach
        </ul>
    @endif
</section>
