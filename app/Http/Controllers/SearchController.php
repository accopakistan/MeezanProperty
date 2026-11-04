<?php

namespace App\Http\Controllers;

use App\Enums\PropertyType;
use App\Services\Investment\RankingBrowser;
use App\Services\PropertySearch;
use App\Support\Cities;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Target of the shared search bar (Buy | Rent | Plots | Commercial).
 * Property mode: Buy and Rent search listings for sale or rent, Plots searches
 * plot listings, Commercial searches commercial listings (commercial plots
 * included) or, with an investment category, that category's rankings.
 * Investment mode (Investment Rankings pages): the same tabs open residential,
 * rental-income (ranked by yield), plot and commercial rankings.
 * The tab stays in the results URL, so the results page shows the same tab.
 */
class SearchController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $segments = array_keys(RankingBrowser::segments());
        $d = $request->validate([
            'tab' => ['nullable', Rule::in(['sale', 'rent', 'plots', 'commercial'])],
            'mode' => ['nullable', Rule::in(['property', 'investment'])],
            'city' => ['nullable', Rule::in(Cities::names())],
            'location' => ['nullable', 'string', 'max:100'],
            'area' => ['nullable', 'string', 'max:180'],
            'type' => ['nullable', Rule::enum(PropertyType::class)],
            'max_price' => ['nullable', 'integer', 'min:1'],
            'beds' => ['nullable', 'integer', 'between:1,10'],
            'size' => ['nullable', 'string', 'max:20', 'regex:/^[a-z0-9-]+$/'],
            'investment' => ['nullable', Rule::in($segments)],
            'segment' => ['nullable', Rule::in($segments)],
            'stage' => ['nullable', Rule::in(array_keys(RankingBrowser::PROJECT_STAGES))],
            'investment_city' => ['nullable', Rule::in(array_merge(['pakistan'], array_map([Cities::class, 'slug'], Cities::names())))],
            'q' => ['nullable', 'string', 'max:80'],
            'budget' => ['nullable', 'integer', 'min:100000'],
            'min_yield' => ['nullable', 'numeric', 'between:0,50'],
        ]);
        $tab = $d['tab'] ?? 'sale';

        return ($d['mode'] ?? 'property') === 'investment' ? $this->investment($tab, $d) : $this->property($tab, $d);
    }

    protected function property(string $tab, array $d): RedirectResponse
    {
        // Commercial + an investment category → that category's rankings (malls, plazas, towers …).
        if ($tab === 'commercial' && ! empty($d['investment'])) {
            $seg = RankingBrowser::segment($d['investment']);

            return redirect()->route('rankings.segment', [RankingBrowser::citySlug($d['city'] ?? null), $seg['group'], $d['investment']]
                + array_filter(['budget' => $d['max_price'] ?? null, 'stage' => $d['stage'] ?? null]));
        }

        $type = $d['type'] ?? null;
        // A type that does not belong to the tab is dropped (e.g. a house after switching to Plots).
        $allowed = match ($tab) {
            'plots' => PropertyType::inGroup('plots'),
            'commercial' => array_merge(PropertyType::inGroup('commercial'), [PropertyType::CommercialPlot]),
            'rent' => array_merge(PropertyType::inGroup('homes'), PropertyType::inGroup('commercial')),
            default => PropertyType::cases(),
        };
        if ($type && ! in_array(PropertyType::from($type), $allowed, true)) {
            $type = null;
        }

        return redirect()->route('properties.index', array_filter([
            'tab' => $tab,
            'city' => $d['city'] ?? null,
            'area' => $d['area'] ?? null,
            'location' => empty($d['area']) ? ($d['location'] ?? null) : null,
            'type' => $type,
            'max_price' => $d['max_price'] ?? null,
            'beds' => in_array($tab, ['sale', 'rent'], true) ? ($d['beds'] ?? null) : null,
            'size' => in_array($tab, ['plots', 'commercial'], true) && isset(PropertySearch::SIZES[$d['size'] ?? '']) ? $d['size'] : null,
        ], fn ($v) => $v !== null && $v !== ''));
    }

    protected function investment(string $tab, array $d): RedirectResponse
    {
        $city = $d['investment_city'] ?? RankingBrowser::citySlug($d['city'] ?? null);
        $segment = $d['segment'] ?? match ($tab) {
            'rent' => 'apartments',
            'plots' => 'plots',
            default => null,
        };
        $query = array_filter([
            'q' => $d['q'] ?? null,
            'budget' => $d['budget'] ?? null,
            'min_yield' => in_array($tab, ['rent', 'commercial'], true) ? ($d['min_yield'] ?? null) : null,
            'size' => $tab === 'plots' ? ($d['size'] ?? null) : null,
            'stage' => in_array($tab, ['sale', 'commercial'], true) ? ($d['stage'] ?? null) : null,
            // Rent = rental income: ranked by yield.
            'sort' => $tab === 'rent' ? 'yield' : null,
        ], fn ($v) => $v !== null && $v !== '');

        if ($segment) {
            return redirect()->route('rankings.segment', [$city, RankingBrowser::segment($segment)['group'], $segment] + $query);
        }

        // No category chosen: the residential or commercial overview (Pakistan has a group page too).
        return redirect()->route('rankings.group', [$city, $tab === 'commercial' ? 'commercial' : 'residential'] + array_intersect_key($query, ['q' => 1]));
    }
}
