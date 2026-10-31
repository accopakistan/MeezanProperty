<?php

namespace App\Http\Controllers;

use App\Enums\PropertyType;
use App\Models\Area;
use App\Models\Property;
use App\Services\CityDirectory;
use App\Support\Cities;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * City landing page (/lahore, /karachi …): real listing counts, popular and
 * listed locations, the location directory by kind, and verified dealers.
 */
class CityController extends Controller
{
    /** Location kinds shown in the directory, in this order. */
    protected const DIRECTORY = [
        'Housing societies & schemes' => ['housing_scheme', 'cooperative_society', 'government_scheme', 'defence_scheme'],
        'Neighbourhoods' => ['neighbourhood', 'cantonment', 'sector'],
        'Commercial areas' => ['commercial_area'],
        'Main roads' => ['corridor'],
    ];

    public function __invoke(string $city, CityDirectory $directory): View
    {
        $name = Cities::fromSlug($city);
        abort_unless($name, 404);

        $typeCounts = Property::published()->where('city', $name)->select('type', DB::raw('count(*) as total'))->groupBy('type')->pluck('total', 'type');

        $listed = Property::published()->where('city', $name)->whereNotNull('area_id')
            ->select('area_id', DB::raw('count(*) as total'))->groupBy('area_id')->orderByDesc('total')->limit(12)->pluck('total', 'area_id');

        $popular = Area::active()->where('city', $name)
            ->where(fn ($q) => $q->where('is_popular', true)->orWhereIn('id', $listed->keys()))
            ->ordered()->take(12)->get(['id', 'name', 'slug', 'type', 'city']);

        // Top-level locations only (phases and blocks are reached from their society).
        $directoryAreas = Area::active()->where('city', $name)->whereNull('parent_id')
            ->whereIn('type', array_merge(...array_values(self::DIRECTORY)))
            ->orderBy('name')->get(['id', 'name', 'slug', 'type', 'approval_status']);

        return view('cities.show', [
            'city' => $name,
            'slug' => $city,
            'model' => $directory->model($name),
            'summary' => $directory->summary($name),
            'typeCounts' => $typeCounts,
            'groups' => collect(PropertyType::cases())->groupBy(fn ($t) => $t->group())->map(fn ($types) => $types->sum(fn ($t) => $typeCounts[$t->value] ?? 0)),
            'latest' => Property::published()->forCards()->where('city', $name)->latest('published_at')->take(8)->get(),
            'popular' => $popular,
            'listedCounts' => $listed,
            'directory' => collect(self::DIRECTORY)->map(fn ($types) => $directoryAreas->whereIn('type', $types)->values())->filter->isNotEmpty(),
            'dealers' => $directory->dealers($name),
            'otherCities' => array_values(array_diff(Cities::names(), [$name])),
        ]);
    }
}
