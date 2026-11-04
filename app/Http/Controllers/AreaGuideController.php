<?php

namespace App\Http\Controllers;

use App\Models\Area;
use App\Models\Project;
use App\Models\Property;
use App\Services\AreaInsights;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AreaGuideController extends Controller
{
    /** Guides for popular and listed areas per city; the full directory lives on each city page. */
    public function index(): View
    {
        $areas = Area::active()->whereIn('city', config('meezan.cities'))
            ->where(fn ($q) => $q->where('is_popular', true)->orWhereHas('properties', fn ($p) => $p->published()))
            ->withCount(['properties as listings_count' => fn ($q) => $q->published()])
            ->ordered()
            ->get()
            ->groupBy('city');

        return view('areas.index', ['cities' => collect(config('meezan.cities'))->mapWithKeys(fn ($c) => [$c => $areas[$c] ?? collect()])]);
    }

    public function show(Area $area, AreaInsights $insights): View|RedirectResponse
    {
        // A merged duplicate keeps its URL working by pointing at the surviving record.
        if ($area->merged_into_id && $target = Area::find($area->merged_into_id)) {
            return redirect()->route('areas.show', $target, 301);
        }
        abort_unless($area->is_active, 404);
        $area->load(['parent.parent', 'aliases']);

        return view('areas.show', [
            'area' => $area,
            'children' => $area->children()->active()->ordered()->get(['id', 'name', 'slug', 'type']),
            'insights' => $insights->for($area),
            'latest' => Property::published()->forCards()->where('area_id', $area->id)->latest('published_at')->take(4)->get(),
            'projects' => Project::published()->where('is_demo', false)->where('area_id', $area->id)->latest()->take(3)->get(),
            'dealerCount' => $area->dealers()->where('verification_status', 'verified')->count(),
        ]);
    }
}
