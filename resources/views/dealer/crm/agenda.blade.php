<x-layouts.dashboard title="Agenda" section="dealer" heading="Agenda" subheading="Your follow-ups and appointments. Overdue items stay here until you complete or cancel them.">
    @include('dealer.crm._nav')

    @if ($overdue->isNotEmpty())
        <section class="mb-6 rounded-xl border border-red-200 bg-red-50/60" aria-labelledby="overdue-title">
            <h2 id="overdue-title" class="px-5 pt-4 font-bold text-red-800">Overdue ({{ $overdue->count() }})</h2>
            <ul class="mt-2 divide-y divide-red-100">
                @foreach ($overdue as $followUp)
                    @include('dealer.crm._agenda-follow-up')
                @endforeach
            </ul>
        </section>
    @endif

    <div class="grid gap-6 xl:grid-cols-2">
        <section class="card overflow-hidden" aria-labelledby="upcoming-title">
            <h2 id="upcoming-title" class="card-body pb-2 font-bold">Upcoming follow-ups</h2>
            @if ($upcoming->isEmpty())
                <p class="card-body pt-0 text-sm text-muted">Nothing scheduled. Add follow-ups from a lead’s page.</p>
            @else
                <ul class="divide-y divide-line">
                    @foreach ($upcoming as $followUp)
                        @include('dealer.crm._agenda-follow-up')
                    @endforeach
                </ul>
                <div class="p-4">{{ $upcoming->links() }}</div>
            @endif
        </section>

        <section class="card overflow-hidden" aria-labelledby="appt-title">
            <h2 id="appt-title" class="card-body pb-2 font-bold">Appointments</h2>
            @if ($appointments->isEmpty())
                <p class="card-body pt-0 text-sm text-muted">No upcoming appointments.</p>
            @else
                <ul class="divide-y divide-line">
                    @foreach ($appointments as $appointment)
                        <li class="flex items-start gap-4 px-5 py-3 text-sm">
                            <span class="w-16 shrink-0 text-center"><span class="block text-xs font-semibold text-muted uppercase">{{ $appointment->starts_at->localTime()->format('D') }}</span><span class="block text-lg font-bold">{{ $appointment->starts_at->localTime()->format('j') }}</span><span class="block text-xs text-muted">{{ $appointment->starts_at->localTime()->format('M') }}</span></span>
                            <span class="min-w-0 flex-1">
                                <a href="{{ route('dealer.crm.leads.show', $appointment->lead_assignment_id) }}" class="font-semibold hover:text-brand-700">{{ $appointment->typeLabel() }} · {{ $appointment->starts_at->localTime()->format('g:i a') }} ({{ $appointment->duration_minutes }} min)</a>
                                <span class="block text-xs text-muted">{{ $appointment->property ? $appointment->property->reference.' · '.$appointment->property->title : ($appointment->location ?? 'No location set') }}</span>
                            </span>
                        </li>
                    @endforeach
                </ul>
                <div class="p-4">{{ $appointments->links() }}</div>
            @endif
        </section>
    </div>
</x-layouts.dashboard>
