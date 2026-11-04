{{-- A project's Index status as a badge. $status: ranked | not_eligible | insufficient | excluded --}}
@php
    $styles = ['ranked' => 'bg-emerald-50 text-emerald-800 ring-emerald-200', 'not_eligible' => 'bg-amber-50 text-amber-800 ring-amber-200',
        'insufficient' => 'bg-slate-100 text-slate-700 ring-slate-200', 'excluded' => 'bg-red-50 text-red-800 ring-red-200'];
@endphp
<span @class(['badge ring-1 ring-inset', $styles[$status] ?? $styles['insufficient']])>{{ config('investment_index.statuses.'.$status, $status) }}</span>
