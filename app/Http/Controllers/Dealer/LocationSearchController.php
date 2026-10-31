<?php

namespace App\Http\Controllers\Dealer;

use App\Http\Controllers\Controller;
use App\Services\Geocoder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Place search for the listing location picker. Results only move the map;
 * the dealer still places the pin on the property themselves.
 */
class LocationSearchController extends Controller
{
    public function __invoke(Request $request, Geocoder $geocoder): JsonResponse
    {
        $data = $request->validate(['q' => ['required', 'string', 'min:3', 'max:120']]);

        $bounds = config('meezan.map.pakistan_bounds');
        $results = collect($geocoder->search($data['q']))
            ->filter(fn ($r) => $r['lat'] >= $bounds['south'] && $r['lat'] <= $bounds['north'] && $r['lng'] >= $bounds['west'] && $r['lng'] <= $bounds['east'])
            ->values();

        return response()->json(['results' => $results]);
    }
}
