<?php

namespace App\Http\Controllers;

use App\Models\Property;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SavedPropertyController extends Controller
{
    public function index(Request $request): View
    {
        $group = in_array($request->query('group'), ['homes', 'plots', 'commercial'], true) ? $request->query('group') : null;

        $properties = $request->user()->savedProperties()
            ->forCards()
            ->when($group, fn ($q) => $q->inGroup($group))
            ->orderByPivot('created_at', 'desc')
            ->paginate(12)
            ->withQueryString();

        $counts = $request->user()->savedProperties()->pluck('type')
            ->countBy(fn ($type) => $type->group());

        return view('account.saved', ['properties' => $properties, 'group' => $group, 'counts' => $counts]);
    }

    public function toggle(Request $request, Property $property): JsonResponse|RedirectResponse
    {
        $user = $request->user();

        if (! $user) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Sign in to save properties.'], 401)
                : redirect()->guest(route('login'));
        }

        if (! $user->hasRole('buyer')) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Saving is available on buyer accounts.'], 403)
                : back()->with('error', 'Saving properties is available on buyer accounts.');
        }

        // Only published listings can be newly saved; removing is always allowed.
        abort_if(! $property->isPublic() && ! $user->hasSaved($property), 404);

        // An explicit `saved` (e.g. the Remove button) sets the state; without it
        // the heart toggles. A plain toggle behind a "Remove" label could re-save
        // a listing that the heart had already removed.
        if ($request->has('saved')) {
            $saved = $request->boolean('saved');
            $saved
                ? $user->savedProperties()->syncWithoutDetaching([$property->id])
                : $user->savedProperties()->detach($property->id);
        } else {
            $result = $user->savedProperties()->toggle($property->id);
            $saved = count($result['attached']) > 0;
        }

        if ($request->expectsJson()) {
            return response()->json(['saved' => $saved]);
        }

        return back()->with('status', $saved ? 'Property saved.' : 'Property removed from saved.');
    }
}
