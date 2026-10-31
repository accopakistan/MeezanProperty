<?php

namespace App\Http\Controllers;

use App\Models\Area;
use App\Support\LocationLevels;
use App\Support\LocationResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Location look-ups for search boxes and forms. With 1,000+ locations the
 * browser never receives the whole list: it asks for matches as you type.
 */
class LocationController extends Controller
{
    /** Typed suggestions: GET /locations/suggest?q=gulb&city=Lahore */
    public function suggest(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:40'],
        ]);

        [$place, $typedCity] = LocationResolver::splitCity($data['q'] ?? '');
        $city = LocationResolver::city($data['city'] ?? null) ?? $typedCity;

        $areas = $place === '' ? $this->popular($city) : LocationResolver::suggest($place, $city);
        $cities = $place === '' && ! $city
            ? []
            : collect(config('meezan.cities'))->filter(fn ($c) => $place !== '' && str_starts_with(mb_strtolower($c), mb_strtolower($place)))->values()->all();

        return response()->json([
            'cities' => $cities,
            'areas' => $areas->map(fn (Area $a) => [
                'id' => $a->id,
                'name' => $a->name,
                'slug' => $a->slug,
                'city' => $a->city,
                'type' => $a->typeLabel(),
                'parent' => $a->parent && ! str_contains($a->name, $a->parent->name) ? $a->parent->name : null,
            ])->values(),
        ])->header('Cache-Control', 'public, max-age=300');
    }

    /** Top-level locations of a city for the listing form: GET /locations/options?city=Karachi */
    public function options(Request $request): JsonResponse
    {
        $city = LocationResolver::city($request->string('city')->toString());
        abort_unless($city, 422);

        return response()->json(LocationLevels::options($city, null)->map(fn (Area $a) => $this->option($a))->values());
    }

    /** Child locations (phases, sectors, blocks): GET /locations/{area}/children */
    public function children(Area $area): JsonResponse
    {
        $children = LocationLevels::options($area->city, $area->id);

        return response()->json(['label' => LocationLevels::label($children), 'options' => $children->map(fn (Area $a) => $this->option($a))->values()]);
    }

    protected function option(Area $a): array
    {
        return ['id' => $a->id, 'name' => $a->name, 'lat' => $a->latitude, 'lng' => $a->longitude, 'children' => $a->children_count];
    }

    protected function popular(?string $city)
    {
        return Area::active()->inCity($city)->whereIn('city', config('meezan.cities'))->popular()
            ->with('parent:id,name')->ordered()->limit(10)->get();
    }
}
