<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Place search against OpenStreetMap Nominatim, limited to Pakistan.
 *
 * Runs server-side so the identifying User-Agent and caching stay under our
 * control. Nominatim's public service allows light use only: one request a
 * second and no search-as-you-type, so callers search on submit and results
 * are cached. Switch to a commercial or self-hosted geocoder before heavy use.
 *
 * Results are places to look at, never a property's exact location.
 */
class Geocoder
{
    protected const ENDPOINT = 'https://nominatim.openstreetmap.org/search';

    /**
     * @return list<array{label: string, lat: float, lng: float, type: string}>
     */
    public function search(string $query, int $limit = 5): array
    {
        $query = trim(preg_replace('/\s+/', ' ', $query));
        if (mb_strlen($query) < 3) {
            return [];
        }

        $key = 'geocode:'.md5(mb_strtolower($query).'|'.$limit);
        if (is_array($cached = Cache::get($key))) {
            return $cached;
        }

        try {
            $response = Http::timeout(10)
                ->withHeaders(['User-Agent' => config('app.name').' location search ('.config('app.url').')'])
                ->get(self::ENDPOINT, [
                    'q' => $query,
                    'countrycodes' => 'pk',
                    'format' => 'json',
                    'limit' => $limit,
                    // English names where OSM has them; otherwise the local (Urdu) name.
                    'accept-language' => 'en',
                ]);
        } catch (\Throwable $e) {
            Log::warning('Geocoder request failed', ['error' => $e->getMessage()]);

            return [];
        }

        if (! $response->successful()) {
            Log::warning('Geocoder returned an error', ['status' => $response->status()]);

            return [];
        }

        $results = collect($response->json() ?? [])
            ->filter(fn ($hit) => is_array($hit) && isset($hit['lat'], $hit['lon']))
            ->map(fn ($hit) => [
                'label' => $this->shortLabel((string) ($hit['display_name'] ?? $query)),
                'lat' => round((float) $hit['lat'], 7),
                'lng' => round((float) $hit['lon'], 7),
                'type' => (string) ($hit['addresstype'] ?? $hit['type'] ?? 'place'),
            ])
            // OSM often holds the same place as a node and an area.
            ->unique('label')
            ->values()
            ->all();

        // Only successful look-ups are cached, so an outage is not remembered.
        Cache::put($key, $results, now()->addDays(7));

        return $results;
    }

    /**
     * "Liberty Market, Gulberg, Lahore Cant, Lahore Cantonment Tehsil, Lahore
     * District, …, Punjab, 54660, Pakistan" → "Liberty Market, Gulberg, Lahore Cant".
     */
    protected function shortLabel(string $label): string
    {
        $parts = collect(explode(',', $label))
            ->map(fn ($part) => trim($part))
            ->reject(fn ($part) => $part === '' || preg_match('/\b(Tehsil|District|Division)\b|^(Punjab|Sindh|Khyber Pakhtunkhwa|Balochistan|Islamabad Capital Territory|Pakistan)$|^\d{5}$/i', $part))
            ->unique()
            ->values();

        return $parts->isEmpty() ? $label : $parts->implode(', ');
    }
}
