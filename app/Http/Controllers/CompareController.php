<?php

namespace App\Http\Controllers;

use App\Models\Property;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Side-by-side comparison of up to four published listings, kept in the
 * session so it works for guests as well as signed-in buyers.
 */
class CompareController extends Controller
{
    public const MAX = 4;

    public function index(Request $request): View
    {
        $ids = $request->session()->get('compare', []);

        $properties = Property::published()->forCards()->whereIn('id', $ids)->get()
            ->sortBy(fn ($p) => array_search($p->id, $ids, true))->values();

        // Drop anything that is no longer public.
        $request->session()->put('compare', $properties->pluck('id')->all());

        return view('compare', ['properties' => $properties]);
    }

    public function toggle(Request $request, Property $property): RedirectResponse
    {
        $ids = $request->session()->get('compare', []);

        if (in_array($property->id, $ids, true)) {
            $ids = array_values(array_diff($ids, [$property->id]));
            $message = 'Removed from comparison.';
        } else {
            abort_unless($property->isPublic(), 404);
            if (count($ids) >= self::MAX) {
                return back()->with('error', 'You can compare up to '.self::MAX.' properties. Remove one first.');
            }
            $ids[] = $property->id;
            $message = 'Added to comparison.';
        }

        $request->session()->put('compare', $ids);

        return back()->with('status', $message);
    }

    public function clear(Request $request): RedirectResponse
    {
        $request->session()->forget('compare');

        return redirect()->route('compare');
    }
}
