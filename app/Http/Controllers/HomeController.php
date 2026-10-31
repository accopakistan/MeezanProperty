<?php

namespace App\Http\Controllers;

use App\Enums\PropertyType;
use App\Models\Area;
use App\Models\Property;
use App\Services\CityDirectory;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(CityDirectory $cities): View
    {
        $featured = Property::published()->forCards()->where('is_featured', true)->latest('published_at')->take(8)->get();
        $latest = Property::published()->forCards()->latest('published_at')->latest('id')->take(8)->get();

        // Published listing counts per type, used for category tiles.
        $typeCounts = Property::published()->select('type', DB::raw('count(*) as total'))->groupBy('type')->pluck('total', 'type');
        $groupCount = fn (string $group) => collect(PropertyType::inGroup($group))->sum(fn ($t) => $typeCounts[$t->value] ?? 0);

        $categories = [
            ['label' => 'Houses', 'icon' => 'home', 'count' => $typeCounts['house'] ?? 0, 'url' => route('properties.index', ['type' => 'house', 'purpose' => 'sale'])],
            ['label' => 'Apartments', 'icon' => 'building', 'count' => $typeCounts['apartment'] ?? 0, 'url' => route('properties.index', ['type' => 'apartment'])],
            ['label' => 'Residential plots', 'icon' => 'plot', 'count' => $typeCounts['residential_plot'] ?? 0, 'url' => route('properties.index', ['type' => 'residential_plot'])],
            ['label' => 'Commercial plots', 'icon' => 'map', 'count' => $typeCounts['commercial_plot'] ?? 0, 'url' => route('properties.index', ['type' => 'commercial_plot'])],
            ['label' => 'Shops & offices', 'icon' => 'store', 'count' => ($typeCounts['shop'] ?? 0) + ($typeCounts['office'] ?? 0), 'url' => route('properties.commercial')],
            ['label' => 'Portions & rentals', 'icon' => 'layers', 'count' => ($typeCounts['upper_portion'] ?? 0) + ($typeCounts['lower_portion'] ?? 0), 'url' => route('properties.rent')],
        ];

        $locations = Area::popular()->active()->where('city', config('meezan.default_city'))->ordered()
            ->withCount(['properties as listings_count' => fn ($q) => $q->published()])
            ->take(8)
            ->get();

        return view('home', [
            'featured' => $featured,
            'latest' => $latest,
            'categories' => $categories,
            'locations' => $locations,
            'cities' => $cities->summaries(),
            'totalPublished' => $typeCounts->sum(),
            'homesCount' => $groupCount('homes'),
        ]);
    }
}
