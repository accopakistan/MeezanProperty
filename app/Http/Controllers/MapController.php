<?php

namespace App\Http\Controllers;

use App\Enums\PropertyType;
use App\Models\Area;
use App\Models\AreaBoundary;
use App\Models\City;
use App\Models\Property;
use App\Services\PropertySearch;
use App\Support\AreaListingCounts;
use App\Support\Format;
use App\Support\Lookup;
use App\Support\MapMarkers;
use App\Support\Seo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Map search (/map) and its JSON endpoints.
 *
 * The page renders the first results on the server (it works without
 * JavaScript); the map script then asks /map/data for markers and the
 * result list whenever filters, the viewport ("Search this area") or the
 * page change. Only public coordinates ever leave the server (see
 * Property::publicCoordinates()); listings without their own pin are drawn
 * at their area's centre and labelled approximate.
 */
class MapController extends Controller
{
    /** Results per page in the list beside the map. */
    protected const PER_PAGE = 10;

    /** Most boundaries returned for one viewport. */
    protected const MAX_BOUNDARIES = 300;

    public function page(Request $request): View
    {
        $search = new PropertySearch($request->query());
        $properties = $search->paginate(self::PER_PAGE);

        // The detail panel shows the chosen listing, or the first result.
        $selected = $properties->getCollection()->firstWhere('slug', $request->query('selected')) ?? $properties->first();
        $selected?->setRelation('images', $selected->publicImages()->get());

        return view('properties.map', [
            'search' => $search,
            'properties' => $properties,
            'categories' => $this->categories($search),
            'selected' => $selected,
            'map' => $this->markers($search, $request),
            'cities' => $this->cities(),
        ]);
    }

    /** Markers, counts and the rendered result list for the current search. */
    public function data(Request $request): JsonResponse
    {
        $search = new PropertySearch($request->query());
        $properties = $search->paginate(self::PER_PAGE)->withPath(route('properties.map'));
        $map = $this->markers($search, $request);

        return response()->json([
            'total' => $properties->total(),
            'mode' => $map['mode'],
            'markers' => $map['markers'],
            'located' => $map['located'],
            'approximate' => $map['approximate'],
            'polygon' => $search->polygon()?->points,
            // Area / society choices for the filter form, for the searched city.
            'areaOptions' => Lookup::areas($search->get('city'))->concat($search->areas())->unique('id')
                ->map(fn (Area $a) => ['value' => $a->slug, 'label' => $a->name, 'selected' => $search->area()?->id === $a->id])->values(),
            'summary' => view('properties.partials.map-summary', $map)->render(),
            'list' => view('properties.partials.map-results', [
                'search' => $search,
                'properties' => $properties,
                'categories' => $this->categories($search),
            ])->render(),
        ])->header('X-Robots-Tag', 'noindex');
    }

    /** The detail panel for one listing (marker or card clicked). */
    public function preview(Property $property): View
    {
        abort_unless($property->isPublic(), 404);
        $property->load(['area:id,name,slug', 'dealer.dealerProfile']);
        $property->setRelation('images', $property->publicImages()->get());

        return view('properties.partials.map-detail', ['p' => $property]);
    }

    /** Published boundaries overlapping the viewport, as a GeoJSON FeatureCollection. */
    public function boundaries(Request $request): JsonResponse
    {
        $box = $request->validate([
            'north' => ['required', 'numeric', 'between:-90,90'],
            'south' => ['required', 'numeric', 'between:-90,90'],
            'east' => ['required', 'numeric', 'between:-180,180'],
            'west' => ['required', 'numeric', 'between:-180,180'],
        ]);
        [$south, $north] = [min($box['south'], $box['north']), max($box['south'], $box['north'])];
        [$west, $east] = [min($box['west'], $box['east']), max($box['west'], $box['east'])];

        // Society outlines are only meaningful at city scale; never send a whole country's worth.
        if ($north - $south > 3 || $east - $west > 3) {
            return response()->json(['type' => 'FeatureCollection', 'features' => [], 'zoomIn' => true]);
        }

        $rows = AreaBoundary::published()->intersecting($south, $west, $north, $east)
            ->with('area:id,name,slug,city,type')->orderBy('id')->limit(self::MAX_BOUNDARIES)->get();
        $counts = AreaListingCounts::for($rows->pluck('area_id')->all());

        return response()->json([
            'type' => 'FeatureCollection',
            'features' => $rows->map(fn (AreaBoundary $b) => [
                'type' => 'Feature',
                'id' => $b->id,
                'properties' => [
                    'id' => $b->id,
                    'name' => $b->area->name,
                    'city' => $b->area->city,
                    'listings' => $counts[$b->area_id] ?? 0,
                ],
                'geometry' => $b->geometry,
            ])->all(),
        ])->header('Cache-Control', 'public, max-age=300')->header('X-Robots-Tag', 'noindex');
    }

    /** What the boundary pop-up shows: real counts per category and links. */
    public function boundary(AreaBoundary $boundary): JsonResponse
    {
        abort_unless($boundary->status === 'published', 404);
        $area = $boundary->load('area')->area;

        $categories = collect(Seo::categoryCounts($area->city, $area))
            ->filter(fn ($c, $slug) => $c['listings'] > 0 && ! str_starts_with($slug, 'property-for-'))
            ->map(fn ($c, $slug) => ['label' => config('meezan.seo.categories')[$slug]['label'], 'count' => $c['listings'], 'url' => route('areas.category', [$area->slug, $slug])])
            ->values();

        return response()->json([
            'name' => $area->name,
            'city' => $area->city,
            'listings' => AreaListingCounts::for([$area->id])[$area->id],
            'categories' => $categories,
            'mapUrl' => route('properties.map', ['area' => $area->slug]),
            'guideUrl' => route('areas.show', $area->slug),
        ])->header('X-Robots-Tag', 'noindex');
    }

    /**
     * Pins (and area groups) while the located listings fit max_points;
     * above that, counted grid cells plus area totals, so the response size
     * stays bounded however many listings match.
     *
     * @return array{mode: string, markers: list<array<string, mixed>>, located: int, approximate: int}
     */
    protected function markers(PropertySearch $search, Request $request): array
    {
        $base = fn () => $search->query()->reorder();
        $located = $base()->whereNotNull('public_latitude')->count();
        $approximate = $base()->whereNull('public_latitude')->whereHas('area', fn ($a) => $a->whereNotNull('latitude'))->count();
        $max = (int) config('meezan.map.max_points');

        if ($located + $approximate <= $max) {
            $rows = $search->query()
                ->with(['area:id,name,slug,latitude,longitude', 'coverImage'])
                ->get(['id', 'slug', 'title', 'type', 'purpose', 'price', 'area_id', 'area_size', 'area_unit', 'bedrooms', 'latitude', 'longitude', 'location_privacy', 'public_latitude', 'public_longitude', 'published_at', 'is_featured']);

            return ['mode' => 'points', 'markers' => MapMarkers::for($rows, $request->query()), 'located' => $located, 'approximate' => $approximate];
        }

        return ['mode' => 'grid', 'markers' => [...$this->grid($base()), ...$this->areaTotals($base(), $request)], 'located' => $located, 'approximate' => $approximate];
    }

    /** @return list<array<string, mixed>> */
    protected function grid(Builder $query): array
    {
        $extent = (clone $query)->toBase()->whereNotNull('public_latitude')
            ->selectRaw('min(public_latitude) as s, max(public_latitude) as n, min(public_longitude) as w, max(public_longitude) as e')->first();
        if (! $extent || $extent->s === null) {
            return [];
        }

        // About 40 cells across the data's extent (Pakistan coordinates are positive, so truncation = floor).
        $cell = max(((float) $extent->n - (float) $extent->s), ((float) $extent->e - (float) $extent->w), 0.01) / 40;
        $floor = in_array(DB::getDriverName(), ['mysql', 'mariadb'], true) ? 'floor(%s / ?)' : 'cast(%s / ? as integer)';

        return $query->toBase()->whereNotNull('public_latitude')
            ->selectRaw(sprintf($floor, 'public_latitude').' as gy, '.sprintf($floor, 'public_longitude').' as gx, count(*) as n, avg(public_latitude) as lat, avg(public_longitude) as lng, min(price) as low', [$cell, $cell])
            ->groupBy('gy', 'gx')->get()
            ->map(fn ($c) => [
                'kind' => 'grid',
                'id' => 'grid-'.$c->gy.'-'.$c->gx,
                'lat' => round((float) $c->lat, 5),
                'lng' => round((float) $c->lng, 5),
                'count' => (int) $c->n,
                'label' => (int) $c->n === 1 ? Format::compactPrice((int) $c->low) : number_format($c->n).' listings',
                'from' => (int) $c->n > 1 ? Format::compactPrice((int) $c->low).'+' : null,
            ])->values()->all();
    }

    /** @return list<array<string, mixed>> */
    protected function areaTotals(Builder $query, Request $request): array
    {
        $totals = $query->toBase()->whereNull('public_latitude')->whereNotNull('area_id')
            ->groupBy('area_id')->selectRaw('area_id, count(*) as n, min(price) as low')->get()->keyBy('area_id');

        return Area::whereIn('id', $totals->keys())->whereNotNull('latitude')->get(['id', 'name', 'slug', 'latitude', 'longitude'])
            ->map(fn (Area $a) => MapMarkers::areaSummary($a, (int) $totals[$a->id]->n, (int) $totals[$a->id]->low, $request->query()))
            ->values()->all();
    }

    /** @return list<array{0: string, 1: ?string, 2: int}> */
    protected function categories(PropertySearch $search): array
    {
        $facets = $search->facets();
        $groupCount = fn (array $types) => (int) collect($types)->sum(fn ($t) => $facets['types'][$t->value] ?? 0);

        return [
            ['All', null, array_sum($facets['types'])],
            ['Houses', 'house', $facets['types']['house'] ?? 0],
            ['Apartments', 'apartment', $facets['types']['apartment'] ?? 0],
            ['Plots', 'plots', $groupCount(PropertyType::inGroup('plots'))],
            ['Commercial', 'commercial', $groupCount(PropertyType::inGroup('commercial'))],
        ];
    }

    /** The seven launch cities with their stored centres, for the city selector. */
    protected function cities(): array
    {
        return collect(config('meezan.cities'))->map(fn ($name) => [
            'name' => $name,
            'centre' => City::centre($name),
        ])->all();
    }
}
