<nav class="mb-6 flex flex-wrap gap-2 text-sm" aria-label="Investment Index">
    @foreach ([
        ['Overview', 'admin.investment-index.overview', null],
        ['Coverage', 'admin.investment-index.coverage', null],
        ['Research queue', 'admin.investment-index.research', null],
        ['Verification Centre', 'admin.investment-index.verification', \App\Models\InvestmentLegalRecord::where('verification_status', 'unverified')->count() + \App\Models\InvestmentEvidence::where('verification_status', 'unverified')->count()],
        ['Official sources', 'admin.investment-index.sources', null],
        ['Official registry', 'admin.investment-index.registry', null],
        ['Data validation', 'admin.investment-index.validation', null],
        ['Exceptions', 'admin.investment-index.exceptions', \App\Models\InvestmentException::where('status', 'open')->count()],
        ['Owner documents', 'admin.investment-index.submissions', \App\Models\InvestmentEvidenceSubmission::where('status', 'pending')->count()],
        ['Methodology', 'admin.investment-index.methodology', null],
    ] as [$label, $route, $count])
        @php $active = request()->routeIs($route.'*'); @endphp
        <a href="{{ route($route) }}" @class(['rounded-full px-3.5 py-1.5 font-semibold ring-1', 'bg-brand-600 text-white ring-brand-600' => $active, 'bg-white ring-line hover:ring-brand-300' => ! $active]) @if ($active) aria-current="page" @endif>{{ $label }}@if ($count) <span @class(['ml-1 rounded-full px-1.5 text-[11px]', 'bg-white/25' => $active, 'bg-gold-500 text-brand-900' => ! $active])>{{ $count }}</span>@endif</a>
    @endforeach
</nav>
