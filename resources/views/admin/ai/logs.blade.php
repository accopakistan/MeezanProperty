<x-layouts.dashboard title="AI Activity & Errors" section="admin" heading="Activity & Errors" subheading="Failed work, refused actions, provider errors and who asked whom. Application errors are read from the log with secrets and contact details removed.">
    @include('admin.ai.partials.nav')

    <div class="grid gap-6 xl:grid-cols-2">
        <section class="card card-body min-w-0">
            <h2 class="text-base font-bold">Failed tasks</h2>
            <ul class="mt-2 divide-y divide-line text-sm">
                @forelse ($failedTasks as $t)
                    <li class="py-2"><a href="{{ route('admin.ai.tasks.show', $t) }}" class="link">{{ \Illuminate\Support\Str::limit($t->title, 70) }}</a><br><span class="text-[13px] text-muted">{{ $t->agent?->name }} · {{ $t->created_at?->localTime()->format('j M, H:i') }} · {{ \Illuminate\Support\Str::limit($t->error, 140) }}</span></li>
                @empty
                    <li class="py-2 text-muted">No failed tasks.</li>
                @endforelse
            </ul>
        </section>

        <section class="card card-body min-w-0">
            <h2 class="text-base font-bold">Refused and failed tool calls</h2>
            <ul class="mt-2 divide-y divide-line text-sm">
                @forelse ($toolProblems as $c)
                    <li class="py-2"><span class="font-semibold">{{ $c->tool }}</span> @include('admin.ai.partials.status', ['status' => $c->status])<br><span class="text-[13px] text-muted">{{ $c->agent?->name }} · {{ $c->created_at?->localTime()->format('j M, H:i') }} · {{ \Illuminate\Support\Str::limit($c->error, 140) }}</span></li>
                @empty
                    <li class="py-2 text-muted">None.</li>
                @endforelse
            </ul>
        </section>

        <section class="card card-body min-w-0">
            <h2 class="text-base font-bold">Delegations</h2>
            <p class="mt-1 text-[13px] text-muted">Agents reach each other only through the Central AI Brain.</p>
            <ul class="mt-2 divide-y divide-line text-sm">
                @forelse ($delegations as $d)
                    <li class="py-2"><span class="font-semibold">{{ $d->parent?->agent?->name ?? '—' }}</span> → <a href="{{ route('admin.ai.tasks.show', $d) }}" class="link">{{ $d->agent?->name }}</a> @include('admin.ai.partials.status', ['status' => $d->status, 'label' => $d->statusLabel()])<br><span class="text-[13px] text-muted">{{ \Illuminate\Support\Str::limit($d->title, 80) }} · {{ $d->created_at?->localTime()->format('j M, H:i') }}</span></li>
                @empty
                    <li class="py-2 text-muted">No delegations yet.</li>
                @endforelse
            </ul>
        </section>

        <div class="min-w-0 space-y-6">
            <section class="card card-body">
                <h2 class="text-base font-bold">Language model errors</h2>
                <ul class="mt-2 divide-y divide-line text-sm">
                    @forelse ($providerErrors as $u)
                        <li class="py-2">{{ $u->purpose }} · {{ $u->provider }} · {{ $u->model }}<br><span class="text-[13px] text-muted">{{ $u->created_at?->localTime()->format('j M, H:i') }} · {{ $u->error }}</span></li>
                    @empty
                        <li class="py-2 text-muted">None recorded.</li>
                    @endforelse
                </ul>
            </section>
            <section class="card card-body">
                <h2 class="text-base font-bold">Provider fallbacks</h2>
                <ul class="mt-2 divide-y divide-line text-sm">
                    @forelse ($fallbacks as $u)
                        <li class="py-2">{{ $u->fallback_from }} → {{ $u->provider }} · {{ $u->model }}<br><span class="text-[13px] text-muted">{{ $u->agent?->name ?? 'General' }} · {{ $u->purpose }} · {{ $u->created_at?->localTime()->format('j M, H:i') }} · {{ $u->ok ? 'answered' : $u->error }}</span></li>
                    @empty
                        <li class="py-2 text-muted">None.</li>
                    @endforelse
                </ul>
            </section>
            <section class="card card-body">
                <h2 class="text-base font-bold">Application log errors</h2>
                <ul class="mt-2 divide-y divide-line text-sm">
                    @forelse ($logErrors as $e)
                        <li class="break-words py-2"><span class="font-semibold">{{ $e['level'] }}</span> × {{ $e['count'] }} · last {{ $e['last_seen'] }}<br><span class="text-[13px] text-muted">{{ $e['message'] }}</span></li>
                    @empty
                        <li class="py-2 text-muted">No recent errors in the log.</li>
                    @endforelse
                </ul>
            </section>
        </div>
    </div>
</x-layouts.dashboard>
