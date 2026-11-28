@props(['items' => [], 'title' => 'Questions and answers', 'id' => 'faq'])
{{-- Visible questions and answers plus matching FAQPage structured data (App\Support\Faq). --}}
@if (count($items))
    <section {{ $attributes->merge(['class' => 'card card-body']) }} aria-labelledby="{{ $id }}-title">
        <h2 id="{{ $id }}-title" class="font-serif text-xl font-bold">{{ $title }}</h2>
        <dl class="mt-4 divide-y divide-line">
            @foreach ($items as $item)
                <div class="py-3 first:pt-0 last:pb-0">
                    <dt class="font-semibold text-ink">{{ $item['question'] }}</dt>
                    <dd class="mt-1 text-sm leading-relaxed text-muted">
                        {{ $item['answer'] }}
                        @if (! empty($item['source']))
                            <a href="{{ $item['source'] }}" class="link lg:whitespace-nowrap" rel="nofollow noopener" target="_blank">{{ $item['sourceLabel'] ?? 'Source' }}</a>
                        @endif
                    </dd>
                </div>
            @endforeach
        </dl>
    </section>
    <script type="application/ld+json">{!! \App\Support\Seo::json(\App\Support\Faq::schema($items)) !!}</script>
@endif
