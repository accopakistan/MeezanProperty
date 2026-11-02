<?php

namespace App\Http\Controllers;

use App\Models\Area;
use App\Support\Cities;
use App\Support\LocationResolver;
use App\Support\Seo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Category landing pages for a city (/lahore/houses-for-sale) or an area
 * (/areas/dha-phase-6/houses-for-sale), built only from real listings in
 * the existing location data. Categories come from config('meezan.seo');
 * anything else is a 404. Pages with fewer than the minimum real listings
 * stay usable but are "noindex, follow" and left out of the sitemap.
 */
class LocationListingController extends Controller
{
    public function city(Request $request, string $city, string $category): View
    {
        $name = Cities::fromSlug($city);
        abort_unless($name && Seo::category($category), 404);

        return $this->render($request, $category, $name, null);
    }

    public function area(Request $request, Area $area, string $category): View|RedirectResponse
    {
        abort_unless(Seo::category($category), 404);
        if ($area->merged_into_id && $target = Area::find($area->merged_into_id)) {
            return redirect()->route('areas.category', [$target->slug, $category], 301);
        }
        abort_unless($area->is_active && in_array($area->city, Cities::names(), true), 404);

        return $this->render($request, $category, $area->city, $area->load('parent.parent'));
    }

    protected function render(Request $request, string $category, string $city, ?Area $area): View
    {
        $definition = Seo::category($category);
        $base = Seo::categoryQuery($category, $city, $area);
        $listings = (clone $base)->forCards()->orderByDesc('is_featured')->latest('published_at')->paginate(24)->withQueryString();
        abort_if($listings->currentPage() > 1 && $listings->isEmpty(), 404);

        $counts = Seo::categoryCounts($city, $area);
        $place = $area ? $area->name.', '.$city : $city;
        $prices = (clone $base)->toBase()->selectRaw('min(price) as low, max(price) as high')->first();
        $canonicalBase = $area ? route('areas.category', [$area->slug, $category]) : route('cities.category', [Cities::slug($city), $category]);

        // Areas of the city with listings in this category (most first), for city pages.
        // Counted on properties alone (areas shares column names such as city and type), then named.
        $topAreas = collect();
        if (! $area) {
            $totals = (clone $base)->toBase()->whereNotNull('area_id')->groupBy('area_id')
                ->orderByDesc('total')->limit(12)->pluck(DB::raw('count(*) as total'), 'area_id');
            $topAreas = Area::whereIn('id', $totals->keys())->get(['id', 'name', 'slug'])
                ->each(fn ($a) => $a->total = (int) $totals[$a->id])
                ->sortByDesc('total')->values();
        }

        return view('locations.listings', [
            'category' => $category,
            'definition' => $definition,
            'city' => $city,
            'citySlug' => Cities::slug($city),
            'area' => $area,
            'place' => $place,
            'listings' => $listings,
            'indexable' => Seo::indexable($counts[$category]['real'] ?? 0),
            'canonical' => $canonicalBase.($listings->currentPage() > 1 ? '?page='.$listings->currentPage() : ''),
            'priceRange' => $prices && $prices->low ? [(int) $prices->low, (int) $prices->high] : null,
            'related' => collect($counts)->except($category)->filter(fn ($c) => $c['listings'] > 0),
            'topAreas' => $topAreas,
            'searchUrl' => route('properties.index', array_filter([
                'purpose' => $definition['purpose'],
                'group' => $definition['group'] ?? (in_array('house', $definition['types'] ?? [], true) || in_array('apartment', $definition['types'] ?? [], true) ? 'homes' : null),
                'city' => $area ? null : $city,
                'area' => $area?->slug,
            ])),
            'childAreas' => $area ? Area::active()->whereIn('id', array_diff(LocationResolver::withDescendants([$area->id]), [$area->id]))->where('parent_id', $area->id)->ordered()->get(['name', 'slug']) : collect(),
        ]);
    }
}
