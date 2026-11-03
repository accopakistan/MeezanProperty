@props(['kind', 'level'])
{{-- Confidence or risk level badge. Colour is never the only cue: the label is always written out. --}}
@php
    $label = $kind === 'confidence' ? (\App\Models\InvestmentRanking::CONFIDENCE[$level] ?? $level) : ((\App\Models\InvestmentRanking::RISK[$level] ?? $level).($level === 'insufficient' ? '' : ' risk'));
    $tone = $kind === 'confidence'
        ? ['high' => 'bg-emerald-50 text-emerald-800 ring-emerald-200', 'medium' => 'bg-brand-50 text-brand-700 ring-brand-200', 'limited' => 'bg-amber-50 text-amber-800 ring-amber-200', 'insufficient' => 'bg-page text-muted ring-line'][$level] ?? 'bg-page text-muted ring-line'
        : ['low' => 'bg-emerald-50 text-emerald-800 ring-emerald-200', 'medium' => 'bg-amber-50 text-amber-800 ring-amber-200', 'high' => 'bg-red-50 text-red-700 ring-red-200', 'insufficient' => 'bg-page text-muted ring-line'][$level] ?? 'bg-page text-muted ring-line';
@endphp
<span {{ $attributes->merge(['class' => "inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 {$tone}"]) }}>{{ $label }}</span>
