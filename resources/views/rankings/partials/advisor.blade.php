{{--
    AI Investment Advisor. Works as a plain GET form (?ask=…, answer rendered by the server) and is
    upgraded by the page script to ask the JSON endpoint in place. $advice (InvestmentAdvisor::answer or null),
    $city, $segmentKey, $examples (list of questions).
--}}
@php
    $examples ??= ['Which shopping mall is best for investment in '.($city ?? 'Lahore').'?', 'Which residential society has the strongest historical performance?',
        'Compare apartment buildings in '.($city ?? 'Lahore').'.', 'Which commercial projects have verified rental income?', 'What are the risks of investing in this project?'];
    $focusTabs = ['best' => 'Ask AI', 'compare' => 'Compare', 'rental' => 'Rental Analysis', 'forecast' => 'Forecast'];
    $focus = request('focus', 'best');
@endphp
<section class="card overflow-hidden" aria-labelledby="advisor-title" data-advisor data-endpoint="{{ route('rankings.advisor') }}" data-city="{{ $city }}" data-segment="{{ $segmentKey }}">
    <div class="flex items-center justify-between gap-2 border-b border-line px-4 py-3">
        <h2 id="advisor-title" class="flex items-center gap-2 text-base font-bold"><x-icon name="bot" class="size-5 text-brand-600" /> AI Investment Advisor</h2>
        @if (($calc ?? null)?->includes_demo)<span class="rounded bg-gold-500 px-2 py-0.5 text-[11px] font-extrabold text-brand-900">Illustrative data</span>@else<span class="flex items-center gap-1.5 text-[12px] font-semibold text-emerald-700"><span class="size-2 rounded-full bg-emerald-500"></span>Uses verified data only</span>@endif
    </div>
    <form method="GET" class="p-4" data-advisor-form>
        @foreach (request()->except(['ask', 'example', 'focus', 'page', 'part']) as $k => $v)@if (is_string($v))<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endif @endforeach
        <div class="grid grid-cols-4 gap-1 rounded-lg bg-page p-1 text-[12px] font-semibold" role="radiogroup" aria-label="Advisor focus">
            @foreach ($focusTabs as $k => $label)
                <label class="cursor-pointer rounded-md px-1.5 py-1.5 text-center has-checked:bg-brand-600 has-checked:text-white">
                    <input type="radio" name="focus" value="{{ $k }}" class="sr-only" @checked($focus === $k)> {{ $label }}
                </label>
            @endforeach
        </div>

        <div class="mt-3 space-y-2" data-advisor-answer aria-live="polite">
            @if ($advice)
                <p class="rounded-lg bg-brand-50 px-3 py-2 text-[13px] font-semibold text-brand-800">{{ $advice['question'] }}</p>
                <div class="flex gap-2.5">
                    <x-logo compact class="size-7 shrink-0" />
                    <div class="min-w-0 space-y-1 text-[13px]">
                        @foreach ($advice['answer'] as $line)<p>{{ $line }}</p>@endforeach
                        @if ($advice['missing'])<p class="text-muted">Missing: {{ implode(' ', $advice['missing']) }}</p>@endif
                        <p class="text-[11px] text-muted">Source: Meezan Property published rankings{{ $advice['methodology'] ? ' (methodology v'.$advice['methodology'].($advice['published_at'] ? ', published '.$advice['published_at'] : '').')' : '' }}{{ $advice['sources'] ? '; data from '.implode(', ', $advice['sources']) : '' }}. {{ match ($advice['mode']) { 'llm-agent' => 'Answered by the Investment Analyst agent (Central AI Brain); worded by AI and checked against the data.', 'rules-agent' => 'Answered by the Investment Analyst agent (Central AI Brain) from the data.', 'llm' => 'Worded by AI, checked against the data.', default => 'Answered directly from the data.' } }}</p>
                    </div>
                </div>
            @else
                <p class="text-[13px] text-muted">Ask about a city, society, project or property type. Answers come from published Meezan Property rankings{{ ($calc ?? null)?->includes_demo ? ', currently the illustrative preview dataset' : '' }} and say when data is missing.</p>
                <ul class="flex flex-wrap gap-1.5">
                    @foreach ($examples as $q)
                        <li><button type="submit" name="example" value="{{ $q }}" class="rounded-full bg-brand-50 px-3 py-1 text-left text-[12px] font-medium text-brand-800 hover:bg-brand-100" data-advisor-example>{{ $q }}</button></li>
                    @endforeach
                </ul>
            @endif
        </div>

        <div class="mt-3 flex gap-2">
            <label class="sr-only" for="advisor-ask">Your question</label>
            <input id="advisor-ask" name="ask" maxlength="300" class="input h-10" placeholder="Ask about any society, project or investment…" value="{{ $advice['question'] ?? '' }}">
            <button class="btn btn-primary h-10 px-3" aria-label="Ask"><x-icon name="send" class="size-4" /></button>
        </div>
        <p class="mt-2 text-[11px] text-muted">{{ config('meezan.investment.disclaimer') }}</p>
    </form>
</section>
