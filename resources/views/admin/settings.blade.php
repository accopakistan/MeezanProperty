<x-layouts.dashboard title="Settings" section="admin" heading="Settings" subheading="Integration status. Values are set in the server environment and never shown here.">
    <div class="grid gap-6 xl:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)]">
        <section class="card overflow-hidden">
            <h2 class="border-b border-line px-5 py-4 font-bold">Integrations</h2>
            <ul class="divide-y divide-line">
                @foreach ($checks as $check)
                    @php [$label, $ok, $env] = $check; $state = $check[3] ?? ($ok ? 'Configured' : 'Not configured'); @endphp
                    <li class="flex items-start gap-4 px-5 py-4">
                        <span @class(['mt-0.5 grid size-7 shrink-0 place-items-center rounded-full', 'bg-emerald-50 text-emerald-600' => $ok, 'bg-amber-50 text-amber-600' => ! $ok])>
                            <x-icon :name="$ok ? 'check' : 'alert'" class="size-4" />
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-semibold">{{ $label }}</p>
                            <p class="mt-0.5 font-mono text-xs break-words text-muted">{{ $env }}</p>
                        </div>
                        <span class="text-xs font-semibold {{ $ok ? 'text-emerald-700' : 'text-amber-700' }}">{{ $state }}</span>
                    </li>
                @endforeach
            </ul>
        </section>

        <section class="card card-body">
            <h2 class="font-bold">Platform</h2>
            <dl class="mt-4 space-y-3 text-sm">
                <div class="flex justify-between gap-4"><dt class="text-muted">Cities open</dt><dd class="font-semibold">{{ implode(', ', $cities) }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-muted">Marla size</dt><dd class="font-semibold">{{ $marla }} sq ft</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-muted">Listing lifetime</dt><dd class="font-semibold">{{ $lifetime }} days</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-muted">AI matching driver</dt><dd class="font-semibold">{{ $aiDriver === 'rules' ? 'Built-in rules (no external AI)' : $aiDriver }}</dd></div>
                <div><dt class="text-muted">WhatsApp webhook URL</dt><dd class="mt-1 rounded-lg bg-page px-3 py-2 font-mono text-xs break-all">{{ $webhookUrl }}</dd></div>
            </dl>
        </section>
    </div>
</x-layouts.dashboard>
