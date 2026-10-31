<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\SavedSearch;
use App\Services\PropertySearch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SavedSearchController extends Controller
{
    /** Query parameters that may be stored; anything else is discarded. */
    public const KEYS = [
        'purpose', 'group', 'type', 'area', 'q', 'city', 'min_price', 'max_price', 'beds', 'baths', 'size',
        'min_area', 'max_area', 'unit', 'furnishing', 'completion', 'min_covered', 'max_covered', 'features',
        'verified', 'photos', 'listed', 'reduced', 'sort', 'north', 'south', 'east', 'west', 'poly',
    ];

    public const MAX_PER_USER = 20;

    public function index(Request $request): View
    {
        $searches = $request->user()->savedSearches()->latest()->get()
            ->each(fn (SavedSearch $s) => $s->setAttribute('new_count', $s->newMatchesQuery()->count()));

        return view('account.saved-searches', ['searches' => $searches]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:120'],
            'filters' => ['required', 'string', 'max:4000'],
            'alert_frequency' => ['nullable', Rule::in(array_keys(SavedSearch::FREQUENCIES))],
        ]);

        $filters = array_intersect_key((array) json_decode($data['filters'], true), array_flip(self::KEYS));
        $user = $request->user();

        if ($user->savedSearches()->count() >= self::MAX_PER_USER) {
            return back()->with('error', 'You can keep up to '.self::MAX_PER_USER.' saved searches. Delete one first.');
        }

        $search = new SavedSearch([
            'name' => ($data['name'] ?? null) ?: (new PropertySearch($filters))->headline(),
            'filters' => $filters,
            'alert_frequency' => $data['alert_frequency'] ?? 'weekly',
        ]);
        $search->user_id = $user->id;
        $search->last_checked_at = now();
        $search->save();

        return back()->with('status', 'Search saved. New matching listings will appear in your alerts.');
    }

    public function update(Request $request, SavedSearch $savedSearch): RedirectResponse
    {
        $this->authorizeOwner($request, $savedSearch);

        $savedSearch->update($request->validate([
            'name' => ['required', 'string', 'max:120'],
            'alert_frequency' => ['required', Rule::in(array_keys(SavedSearch::FREQUENCIES))],
        ]));

        return back()->with('status', 'Saved search updated.');
    }

    public function destroy(Request $request, SavedSearch $savedSearch): RedirectResponse
    {
        $this->authorizeOwner($request, $savedSearch);
        $savedSearch->delete();

        return back()->with('status', 'Saved search deleted.');
    }

    /** Open the search and mark its new matches as seen. */
    public function open(Request $request, SavedSearch $savedSearch): RedirectResponse
    {
        $this->authorizeOwner($request, $savedSearch);
        $savedSearch->forceFill(['last_checked_at' => now()])->save();

        return redirect()->to($savedSearch->url());
    }

    public function alerts(Request $request): View
    {
        $searches = $request->user()->savedSearches()->latest()->get();

        $groups = $searches->map(fn (SavedSearch $s) => [
            'search' => $s,
            'matches' => $s->newMatchesQuery()->forCards()->limit(6)->get(),
            'count' => $s->newMatchesQuery()->count(),
        ])->filter(fn ($g) => $g['count'] > 0)->values();

        return view('account.alerts', ['groups' => $groups, 'hasSearches' => $searches->isNotEmpty()]);
    }

    public function markAllSeen(Request $request): RedirectResponse
    {
        $request->user()->savedSearches()->update(['last_checked_at' => now()]);

        return back()->with('status', 'All alerts marked as seen.');
    }

    protected function authorizeOwner(Request $request, SavedSearch $search): void
    {
        abort_unless($search->user_id === $request->user()->id, 404);
    }
}
