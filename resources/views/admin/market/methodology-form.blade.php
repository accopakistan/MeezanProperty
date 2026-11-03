<x-layouts.dashboard :title="'Methodology v'.$methodology->version" section="admin" :heading="'Methodology v'.$methodology->version.' ('.$methodology->status.')'" subheading="Whole-number weights from 0 to 100; each ranking category must add up to exactly 100. A zero weight leaves a factor out of that ranking.">
    @include('admin.market.partials.nav')
    <form method="POST" action="{{ route('admin.market.methodologies.update', $methodology) }}" class="card card-body space-y-4">
        @csrf @method('PATCH')
        <div class="grid gap-4 sm:grid-cols-2">
            <x-field name="name" label="Name" required :value="$methodology->name" />
            <x-field name="notes" label="Notes (why the change)" :value="$methodology->notes" />
        </div>
        @foreach ($errors->get('weights.*') as $messages)@foreach ($messages as $msg)<p class="field-error">{{ $msg }}</p>@endforeach @endforeach
        <div class="relative overflow-x-auto">
            <table class="w-full min-w-[760px] text-left text-[13px]">
                <thead><tr><th class="py-2 font-medium text-muted">Factor</th>@foreach ($categories as $c)<th class="py-2 font-medium">{{ str_replace('Best ', '', $c['label']) }}</th>@endforeach</tr></thead>
                <tbody class="divide-y divide-line">
                    @foreach ($factors as $key => $label)
                        <tr>
                            <th scope="row" class="py-2 pr-2 font-medium">{{ $label }}</th>
                            @foreach ($categories as $ck => $c)
                                <td class="py-1.5 pr-2"><label class="sr-only" for="w-{{ $ck }}-{{ $key }}">{{ $c['label'] }}: {{ $label }}</label>
                                    <input id="w-{{ $ck }}-{{ $key }}" name="weights[{{ $ck }}][{{ $key }}]" type="number" min="0" max="100" step="1" class="input h-9 w-20 px-2" value="{{ old("weights.$ck.$key", $methodology->weightsFor($ck)[$key] ?? 0) }}" @disabled(! $methodology->isEditable())></td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if ($methodology->isEditable())<button class="btn btn-primary">Save draft</button>@endif
    </form>
    @if ($methodology->isEditable())
        <form method="POST" action="{{ route('admin.market.methodologies.activate', $methodology) }}" class="mt-4" data-confirm="Activate version {{ $methodology->version }}? Future calculations will use it; published rankings keep their version.">
            @csrf<button class="btn btn-outline">Activate this version</button>
        </form>
    @endif
</x-layouts.dashboard>
