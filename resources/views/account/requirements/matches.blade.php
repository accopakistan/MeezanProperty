<x-layouts.dashboard title="Matches" section="account" heading="Best matches" :subheading="$requirement->title ?: $requirement->summary()">
    <x-slot:actions>
        <a href="{{ route('account.requirements.edit', $requirement) }}" class="btn btn-secondary">Edit requirement</a>
    </x-slot:actions>
    @include('ai-finder.partials.understood', ['criteria' => $result->criteria])
    @include('ai-finder.partials.results', ['result' => $result])
</x-layouts.dashboard>
