{{-- Legend with real counts: located pins vs. listings shown at their area's centre. --}}
@if ($mode === 'grid')
    <span><strong class="text-ink">{{ number_format($located) }}</strong> located {{ \Illuminate\Support\Str::plural('listing', $located) }} grouped by location; zoom in and search this area for individual pins.</span>
@else
    <span class="flex items-center gap-1.5"><span class="inline-block h-3 w-4 rounded bg-brand-500"></span>{{ number_format($located) }} on the map</span>
@endif
@if ($approximate)
    <span class="flex items-center gap-1.5"><span class="inline-block h-3 w-4 rounded border-[1.5px] border-dashed border-white bg-brand-600 ring-1 ring-brand-600"></span>{{ number_format($approximate) }} at area centre (approximate)</span>
@endif
@if (! $located && ! $approximate)
    <span>No listings in this search have a map location yet.</span>
@endif
