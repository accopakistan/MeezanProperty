<x-layouts.dashboard title="My inquiries" section="account" heading="My inquiries" subheading="Messages you’ve sent to dealers about their listings.">
    @if ($inquiries->isEmpty())
        <x-empty-state icon="message" title="No inquiries yet">
            Use “Send an inquiry” on a listing to contact its dealer. Your inquiries will be tracked here.
            <x-slot:actions><a href="{{ route('properties.index') }}" class="btn btn-primary">Find property</a></x-slot:actions>
        </x-empty-state>
    @else
        <div class="space-y-3">
            @foreach ($inquiries as $inquiry)
                @php $property = $inquiry->property; @endphp
                <article class="card flex flex-col gap-4 p-4 sm:flex-row sm:items-center">
                    <img src="{{ $property?->coverThumbUrl() ?? $property?->placeholderUrl() ?? asset('images/placeholders/homes.svg') }}" alt="" class="h-20 w-full rounded-xl object-cover sm:w-28">
                    <div class="min-w-0 flex-1">
                        @if ($property && $property->isPublic())
                            <a href="{{ route('properties.show', $property) }}" class="font-semibold hover:text-brand-700">{{ $property->title }}</a>
                        @else
                            <p class="font-semibold text-muted">{{ $property?->title ?? 'Listing removed' }} <span class="text-xs">(no longer listed)</span></p>
                        @endif
                        <p class="mt-0.5 text-[13px] text-muted">{{ $property?->reference }} · Sent {{ $inquiry->created_at->format('j M Y, g:i a') }}</p>
                        <p class="mt-2 line-clamp-2 text-sm text-ink/80">{{ $inquiry->message }}</p>
                    </div>
                    <x-status-badge :tone="$inquiry->statusTone()" :label="['new' => 'Sent', 'contacted' => 'Dealer responded', 'closed' => 'Closed'][$inquiry->status] ?? ucfirst($inquiry->status)" />
                </article>
            @endforeach
        </div>
        <div class="mt-6">{{ $inquiries->links() }}</div>
    @endif
</x-layouts.dashboard>
