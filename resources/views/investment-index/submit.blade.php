<x-layouts.app title="Submit evidence — {{ $project->name }}" description="Submit documented evidence for the Meezan Investment Index." :noindex="true">
    <div class="container-page py-10">
        <x-breadcrumbs :items="[['Projects', route('projects.index')], [$project->name, route('projects.show', $project)], ['Submit evidence', null]]" />
        <div class="mt-6 grid gap-8 lg:grid-cols-[1.4fr_1fr]">
            <div>
                <h1 class="text-3xl font-extrabold">Submit evidence for {{ $project->name }}</h1>
                <p class="mt-3 text-[15px] leading-7 text-muted">For owners, developers and building managers. Attach the documents that support one fact, such as a rent roll, an occupancy register, audited accounts, a structural safety certificate or an approval letter. Meezan staff check every document before it counts in the Meezan Investment Index. Submitting never changes a ranking by itself, and rankings cannot be bought.</p>
                <x-flash />

                <form method="POST" action="{{ route('investment-index.evidence.store', $project) }}" enctype="multipart/form-data" class="card card-body mt-6 space-y-5" novalidate>
                    @csrf
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-field name="organisation" label="Organisation" hint="The developer, owner or management company." required />
                        <x-field name="submitter_role" label="Your role" placeholder="e.g. Finance manager" required />
                    </div>

                    <fieldset>
                        <legend class="label">What are you submitting?</legend>
                        <div class="mt-1 flex flex-wrap gap-4 text-[14px]">
                            <label class="flex items-center gap-2"><input type="radio" name="kind" value="evidence" @checked(old('kind', 'evidence') === 'evidence')> A fact about the development</label>
                            <label class="flex items-center gap-2"><input type="radio" name="kind" value="legal" @checked(old('kind') === 'legal')> An approval or NOC from an authority</label>
                        </div>
                        @error('kind')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </fieldset>

                    <fieldset class="space-y-4 rounded-xl border border-line p-4">
                        <legend class="px-1 text-sm font-bold">A fact about the development</legend>
                        <x-field name="factor" type="select" label="Fact" :options="$factors" placeholder="Choose…" />
                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-field name="percent" type="number" label="Percentage" hint="For development progress and occupancy (0–100)." step="0.1" min="0" max="100" />
                            <x-field name="level" type="select" label="Assessment" hint="For maintenance, footfall, tenant quality, safety, utilities, delivery and operations.">
                                <option value="">Choose…</option>
                                @foreach ($factors as $key => $label)
                                    @continue(in_array($key, ['development_progress', 'occupancy', 'rental_income'], true))
                                    <optgroup label="{{ $label }}">
                                        @foreach (\App\Services\InvestmentIndex\EvidenceSubmissions::levels($key) as $level => $levelLabel)
                                            <option value="{{ $level }}" @selected(old('level') === $level && old('factor') === $key)>{{ $levelLabel }}</option>
                                        @endforeach
                                    </optgroup>
                                @endforeach
                            </x-field>
                        </div>
                        @isset($factors['utilities'])
                            <x-field name="subject" type="select" label="Utility" :options="array_combine(\App\Services\InvestmentIndex\EvidenceSubmissions::UTILITIES, array_map('ucfirst', \App\Services\InvestmentIndex\EvidenceSubmissions::UTILITIES))" placeholder="For utilities only…" />
                        @endisset
                        @isset($factors['rental_income'])
                            <div class="grid gap-4 sm:grid-cols-3">
                                <x-field name="annual_rent" type="number" label="Annual rent received (PKR)" min="1" />
                                <x-field name="property_value" type="number" label="Value of the let space (PKR)" min="1" />
                                <x-field name="value_basis" type="select" label="Value based on" :options="\App\Services\InvestmentIndex\EvidenceSubmissions::VALUE_BASES" placeholder="Choose…" />
                            </div>
                            <p class="text-[13px] text-muted">The gross yield is calculated from these two figures; attach the rent roll or leases and the valuation or purchase documents.</p>
                        @endisset
                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-field name="document_type" type="select" label="Documents issued by" :options="\App\Models\InvestmentEvidenceSubmission::DOCUMENT_TYPES" placeholder="Choose…" hint="Structural safety needs a certificate from an independent engineer or authority." />
                            <x-field name="issuer" label="Issuer (if independent)" placeholder="e.g. auditor, valuer or engineering firm" />
                        </div>
                    </fieldset>

                    <fieldset class="space-y-4 rounded-xl border border-line p-4">
                        <legend class="px-1 text-sm font-bold">An approval or NOC from an authority</legend>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-field name="authority" label="Authority" placeholder="e.g. LDA, CDA, RDA, SBCA" />
                            <x-field name="reference" label="Letter / NOC number" />
                            <x-field name="record_type" type="select" label="Document" :options="['approval' => 'Approval / NOC', 'regularisation' => 'Regularisation / clearance']" placeholder="Choose…" />
                            <x-field name="legal_status" type="select" label="Status it states" :options="['approved' => 'Approved (final)', 'partially_approved' => 'Partially approved (e.g. technical approval)', 'pending' => 'Approval pending']" placeholder="Choose…" />
                            <x-field name="issued_on" type="date" label="Issued on" />
                        </div>
                    </fieldset>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-field name="observed_on" type="date" label="The figures are as of" :value="now()->toDateString()" required />
                        <div>
                            <label for="files" class="label">Documents <span class="text-red-500" aria-hidden="true">*</span></label>
                            <input id="files" type="file" name="files[]" multiple accept=".pdf,.jpg,.jpeg,.png,.webp" class="input" required>
                            <p class="mt-1 text-[13px] text-muted">Up to {{ \App\Models\InvestmentEvidenceSubmission::MAX_FILES }} PDF or image files, {{ config('meezan.verification.max_document_kb') / 1024 }} MB each. Only Meezan staff can open them.</p>
                            @error('files')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                            @error('files.*')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                    </div>
                    <x-field name="notes" type="textarea" label="Notes for the reviewer" rows="3" />
                    <label class="flex items-start gap-2 text-[14px]"><input type="checkbox" name="declaration" value="1" class="mt-1" @checked(old('declaration'))>
                        <span>I am authorised by the organisation to submit these documents, and they are genuine and complete. I understand that false documents are removed and reported.</span></label>
                    @error('declaration')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                    <button class="btn btn-primary">Submit for checking</button>
                </form>
            </div>

            <aside class="space-y-4">
                <section class="card card-body">
                    <h2 class="text-base font-bold">How it is used</h2>
                    <ul class="mt-2 list-disc space-y-1.5 pl-5 text-[14px] text-muted">
                        <li>Staff compare each document with the fact you state. Once verified, it counts in the scores, with your organisation named as the source.</li>
                        <li>Owner statements count for less than records from independent auditors, engineers or authorities.</li>
                        <li>Legal status also comes from the authorities' own published lists. A serious legal finding there cannot be outweighed by owner documents.</li>
                    </ul>
                </section>
                @if ($mine->isNotEmpty())
                    <section class="card card-body">
                        <h2 class="text-base font-bold">Your submissions</h2>
                        <ul class="mt-2 divide-y divide-line text-[14px]">
                            @foreach ($mine as $s)
                                <li class="py-2"><span class="font-semibold">{{ $s->claim() }}</span>
                                    <span class="block text-[13px] text-muted">{{ $s->created_at->localTime()->format('j M Y') }} · {{ $s->statusLabel() }}@if ($s->status === 'rejected' && $s->review_note) — {{ $s->review_note }}@endif</span></li>
                            @endforeach
                        </ul>
                    </section>
                @endif
            </aside>
        </div>
    </div>
</x-layouts.app>
