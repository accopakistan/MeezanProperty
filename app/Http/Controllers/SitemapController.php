<?php

namespace App\Http\Controllers;

use App\Models\Area;
use App\Models\Property;
use App\Support\Cities;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $static = collect(['home', 'properties.buy', 'properties.rent', 'properties.plots', 'properties.commercial', 'properties.index', 'ai-finder', 'pages.about', 'pages.how-it-works', 'pages.contact'])
            ->map(fn ($name) => ['loc' => route($name), 'lastmod' => null]);

        $cities = collect(Cities::names())->map(fn ($city) => ['loc' => route('cities.show', Cities::slug($city)), 'lastmod' => null]);

        // Area guides for locations people browse: popular ones and those with live listings.
        // The other directory entries stay reachable from city pages without bloating the sitemap.
        $areas = Area::active()->whereIn('city', Cities::names())
            ->where(fn ($q) => $q->where('is_popular', true)->orWhereHas('properties', fn ($p) => $p->published()))
            ->ordered()->get(['slug'])
            ->map(fn ($a) => ['loc' => route('areas.show', $a->slug), 'lastmod' => null]);

        // Demo listings are excluded from search engines.
        $properties = Property::published()->where('is_demo', false)->latest('updated_at')->take(5000)->get(['slug', 'updated_at'])
            ->map(fn ($p) => ['loc' => route('properties.show', $p), 'lastmod' => $p->updated_at->toAtomString()]);

        return response()
            ->view('sitemap', ['urls' => $static->concat($cities)->concat($areas)->concat($properties)])
            ->header('Content-Type', 'application/xml');
    }
}
