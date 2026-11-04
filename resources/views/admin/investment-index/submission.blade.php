<x-layouts.dashboard title="Owner document check" section="admin" heading="{{ $s->claim() }}" subheading="{{ $s->project->name }} · submitted {{ $s->created_at->localTime()->format('j M Y, H:i') }} by {{ $s->organisation }}">
    @include('admin.investment-index.partials.nav')

    <div class="grid gap-6 lg:grid-cols-[1.3fr_1fr]">
        <section class="card card-body">
            <h2 class="text-base font-bold">What is claimed</h2>
            <dl class="mt-3 grid gap-x-6 gap-y-2 text-[14px] sm:grid-cols-[auto_1fr]">
                <dt class="text-muted">Project</dt><dd><a class="link" href="{{ route('projects.show', $s->project) }}">{{ $s->project->name }}</a> ({{ $s->project->categoryLabel() }}, {{ $s->project->city }})</dd>
                <dt class="text-muted">Claim</dt><dd class="font-semibold">{{ $s->claim() }}</dd>
                @if ($s->figures)
                    <dt class="text-muted">Figures</dt><dd>Annual rent PKR {{ number_format($s->figures['annual_rent']) }} ÷ value PKR {{ number_format($s->figures['property_value']) }} ({{ \App\Services\InvestmentIndex\EvidenceSubmissions::VALUE_BASES[$s->figures['value_basis']] ?? $s->figures['value_basis'] }}) = {{ $s->value_numeric }}% gross</dd>
                @endif
                @if ($s->kind === 'legal')
                    <dt class="text-muted">Document</dt><dd>{{ config('investment_index.legal.record_types.'.$s->record_type) }} · {{ $s->reference }} @if ($s->issued_on)· issued {{ $s->issued_on->format('j M Y') }}@endif</dd>
                @endif
                <dt class="text-muted">As of</dt><dd>{{ $s->observed_on->format('j M Y') }}</dd>
                <dt class="text-muted">Issued by</dt><dd>{{ \App\Models\InvestmentEvidenceSubmission::DOCUMENT_TYPES[$s->document_type] }}@if ($s->issuer) — {{ $s->issuer }}@endif</dd>
                <dt class="text-muted">Submitter</dt><dd>{{ $s->user->name }} ({{ $s->user->email }}), {{ $s->submitter_role }}, {{ $s->organisation }}</dd>
                @if ($s->notes)<dt class="text-muted">Notes</dt><dd>{{ $s->notes }}</dd>@endif
            </dl>
            <h3 class="mt-5 text-sm font-bold">Documents</h3>
            <ul class="mt-2 space-y-1.5 text-[14px]">
                @foreach ($s->files as $i => $file)
                    <li><a class="link" href="{{ route('admin.investment-index.submissions.file', [$s, $i]) }}" target="_blank" rel="noopener">{{ $file['name'] }}</a> <span class="text-muted">· {{ $file['mime'] }} · {{ number_format($file['size'] / 1024) }} KB · SHA-256 {{ substr($file['sha256'], 0, 12) }}…</span></li>
                @endforeach
            </ul>
        </section>

        <section class="card card-body">
            @if ($s->status === 'pending')
                <h2 class="text-base font-bold">Your check</h2>
                <ul class="mt-2 list-disc space-y-1 pl-5 text-[13px] text-muted">
                    <li>The documents name this development and support the exact figure or status claimed, as of the date given.</li>
                    <li>Independent documents carry the issuer's name, letterhead and signature or seal; approvals can be confirmed with the authority.</li>
                    <li>If anything looks altered or inconsistent, reject it and say why.</li>
                </ul>
                <form method="POST" action="{{ route('admin.investment-index.submissions.review', $s) }}" class="mt-4 space-y-3">@csrf
                    <x-field name="note" type="textarea" rows="3" label="Note" hint="Required when rejecting; the submitter sees it." />
                    <div class="flex gap-2">
                        <button name="decision" value="verify" class="btn btn-primary">Verify</button>
                        <button name="decision" value="reject" class="btn btn-secondary">Reject</button>
                    </div>
                </form>
            @else
                <h2 class="text-base font-bold">{{ $s->statusLabel() }}</h2>
                <p class="mt-1 text-[14px] text-muted">By {{ $s->reviewer?->name }} on {{ $s->reviewed_at?->localTime()->format('j M Y, H:i') }}.@if ($s->review_note) {{ $s->review_note }}@endif</p>
                @if ($s->evidence || $s->legalRecord)<p class="mt-2 text-[14px]">Recorded as verified {{ $s->kind === 'legal' ? 'legal record' : 'evidence' }} #{{ ($s->evidence ?? $s->legalRecord)->id }}.</p>@endif
            @endif
        </section>
    </div>
</x-layouts.dashboard>
