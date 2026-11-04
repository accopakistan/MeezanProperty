@if ($runs->isEmpty())
    <p class="mt-2 text-[14px] text-muted">No collections yet.</p>
@else
    <div class="mt-2 overflow-x-auto">
        <table class="w-full text-left text-[13px]">
            <thead class="text-muted"><tr><th class="py-1.5 pr-3 font-medium">Run</th><th class="py-1.5 pr-3 font-medium">Source</th><th class="py-1.5 pr-3 font-medium">Status</th><th class="py-1.5 pr-3 font-medium">Entries</th><th class="py-1.5 pr-3 font-medium">Matched</th><th class="py-1.5 pr-3 font-medium">Legal records</th><th class="py-1.5 font-medium">Exceptions</th></tr></thead>
            <tbody class="divide-y divide-line">
                @foreach ($runs as $r)
                    <tr>
                        <td class="py-1.5 pr-3 tabular-nums">#{{ $r->id }} <span class="block text-muted">{{ $r->started_at?->localTime()->format('j M, H:i') }}</span></td>
                        <td class="py-1.5 pr-3">{{ $r->source?->name }}</td>
                        <td class="py-1.5 pr-3"><span @class(['font-semibold', 'text-emerald-700' => $r->status === 'completed', 'text-amber-700' => in_array($r->status, ['partial', 'blocked'], true), 'text-red-600' => $r->status === 'failed'])>{{ \App\Models\InvestmentCollectionRun::STATUSES[$r->status] ?? $r->status }}</span>@if ($r->error)<span class="block text-muted">{{ \Illuminate\Support\Str::limit($r->error, 140) }}</span>@endif</td>
                        <td class="py-1.5 pr-3 tabular-nums">{{ $r->entries_found }} <span class="text-muted">(+{{ $r->entries_new }}, −{{ $r->entries_removed }})</span></td>
                        <td class="py-1.5 pr-3 tabular-nums">{{ $r->matched }}</td>
                        <td class="py-1.5 pr-3 tabular-nums">{{ $r->legal_records_created }}</td>
                        <td class="py-1.5 tabular-nums">{{ $r->exceptions_opened }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
