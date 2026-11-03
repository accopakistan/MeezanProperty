<?php

namespace App\Http\Controllers;

use App\Models\Property;
use App\Services\NearbyPlaces;
use App\Services\PropertySearch;
use App\Support\MapMarkers;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PropertyController extends Controller
{
    /** Most results plotted on a map at once. */
    protected const MAP_LIMIT = 500;

    public function index(Request $request): View
    {
        $search = new PropertySearch($request->query());
        $properties = $search->paginate();

        return view('properties.index', [
            'search' => $search,
            'properties' => $properties,
            'markers' => $this->mapMarkers($search, $request, 'properties.index'),
        ]);
    }

    /**
     * Nearby places for the listing page, fetched by the page after it loads
     * so a slow map service never delays the listing itself.
     */
    public function nearby(Request $request, Property $property, NearbyPlaces $places): JsonResponse
    {
        if (! $property->isPublic()) {
            $user = $request->user();
            abort_unless($user && ($user->isAdmin() || $property->user_id === $user->id), 404);
        }
        abort_unless($property->publicCoordinates() !== null, 404);

        $result = $places->around($property);

        return $result === null
            ? response()->json(['available' => false], 503)
            : response()->json(['available' => true] + $result)->header('Cache-Control', 'public, max-age=3600');
    }

    public function show(Request $request, Property $property): View
    {
        // Unpublished listings are visible only to their dealer and to admins.
        if (! $property->isPublic()) {
            $user = $request->user();
            abort_unless($user && ($user->isAdmin() || $property->user_id === $user->id), 404);
        }

        // The public gallery shows active photos only; moderated-out photos stay on file.
        $property->load(['area', 'dealer.dealerProfile', 'priceChanges']);
        $property->setRelation('images', $property->publicImages()->get());

        if ($property->isPublic() && ! $request->user()?->is($property->dealer)) {
            // Count one view per session per listing.
            $key = 'viewed.'.$property->id;
            if (! $request->session()->has($key)) {
                $request->session()->put($key, true);
                $property->increment('views');
                // Daily counts power the dealer performance reports.
                DB::table('property_view_stats')->upsert(
                    [['property_id' => $property->id, 'date' => now()->toDateString(), 'views' => 1]],
                    ['property_id', 'date'],
                    ['views' => DB::raw('property_view_stats.views + 1')],
                );
            }
        }

        // Related listings from real data: same city, purpose and type; same area first, then closest price, then newest.
        $similar = Property::published()->forCards()
            ->whereKeyNot($property->id)
            ->where('city', $property->city)
            ->where('purpose', $property->purpose)
            ->where('type', $property->type)
            ->orderByRaw('case when area_id = ? then 0 else 1 end', [$property->area_id ?? 0])
            ->orderByRaw('abs(cast(price as signed) - ?)', [(int) $property->price])
            ->latest('published_at')
            ->take(4)
            ->get();

        return view('properties.show', [
            'property' => $property,
            'similar' => $similar,
            'markers' => MapMarkers::for([$property]),
            'isPreview' => ! $property->isPublic(),
        ]);
    }

    /**
     * Markers for every result of the search (not just the current page), so
     * the map shows the whole picture the way the list counts it.
     *
     * @return list<array<string, mixed>>
     */
    protected function mapMarkers(PropertySearch $search, Request $request, string $route = 'properties.map'): array
    {
        $results = $search->query()
            ->with(['area:id,name,slug,latitude,longitude', 'coverImage'])
            ->limit(self::MAP_LIMIT)
            ->get(['id', 'slug', 'title', 'type', 'purpose', 'price', 'area_id', 'area_size', 'area_unit', 'bedrooms', 'latitude', 'longitude', 'location_privacy', 'public_latitude', 'public_longitude', 'published_at', 'is_featured']);

        return MapMarkers::for($results, $request->query(), $route);
    }
}
