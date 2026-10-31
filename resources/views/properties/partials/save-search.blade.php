{{-- "Save search" for the current filters. Buyers save directly; guests are asked to sign in. --}}
@php $user = auth()->user(); @endphp
@if (! $user)
    <a href="{{ route('login') }}" class="btn btn-outline btn-sm" title="Sign in to save this search"><x-icon name="bell" class="size-4" /> Save search</a>
@elseif ($user->hasRole('buyer'))
    <form method="POST" action="{{ route('account.saved-searches.store') }}" class="flex items-center gap-2">
        @csrf
        <input type="hidden" name="filters" value="{{ json_encode(request()->except(['page', 'selected', 'view', 'follow'])) }}">
        <button class="btn btn-outline btn-sm"><x-icon name="bell" class="size-4" /> Save search</button>
    </form>
@endif
