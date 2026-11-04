{{-- Badges. $badge: 'status' | 'score' | 'demo' (detail panel only: "Illustrative data"); $item: a RankingBrowser item. --}}
@if ($badge === 'demo')
    @if ($item['is_demo'] ?? false)<span class="badge bg-gold-50 px-2 py-0.5 text-[11px] font-semibold text-gold-700 ring-1 ring-gold-200" title="{{ config('meezan.investment.demo_notice') }}">Illustrative data</span>@endif
@elseif ($badge === 'status')
    <span @class(['badge ring-1', 'bg-emerald-50 text-emerald-700 ring-emerald-200' => $item['status'] === 'ranked', 'bg-amber-50 text-amber-800 ring-amber-200' => $item['status'] === 'provisional',
        'bg-sky-50 text-sky-800 ring-sky-200' => $item['status'] === 'review', 'bg-page text-muted ring-line' => $item['status'] === 'insufficient'])>{{ $item['status_label'] }}</span>
@elseif ($item['score'] !== null)
    <span @class(['inline-grid min-w-11 place-items-center rounded-md px-1.5 py-1 text-sm font-bold text-white tabular-nums', 'bg-emerald-600' => $item['score'] >= 80,
        'bg-lime-600' => $item['score'] >= 65 && $item['score'] < 80, 'bg-amber-500' => $item['score'] >= 50 && $item['score'] < 65, 'bg-red-500' => $item['score'] < 50,
        'outline-2 outline-offset-1 outline-amber-400 outline-dashed' => $item['status'] === 'provisional'])
          title="{{ $item['status'] === 'provisional' ? 'Provisional: scored on limited data' : 'Meezan Property Investment Score (MPIS)' }}">{{ rtrim(rtrim(number_format($item['score'], 1), '0'), '.') }}</span>
@else
    <span class="text-[12px] text-muted" title="{{ $item['reason'] ?? 'Not enough verified data to score' }}">Not scored</span>
@endif
