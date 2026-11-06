<?php

namespace App\Http\Controllers;

use App\Enums\PropertyType;
use App\Models\Area;
use App\Models\Property;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Landing pages for the four main ways people browse: buy, rent, plots and
 * commercial. Each has its own copy, defaults and featured type tiles.
 */
class CategoryController extends Controller
{
    public function buy(): View
    {
        return $this->landing('buy', [
            'purpose' => 'sale',
            'group' => null,
            'eyebrow' => 'Property for sale',
            'title' => 'Homes for Sale in Pakistan',
            'intro' => 'Houses, apartments and portions for sale. For plots and commercial property, use the Plots and Commercial tabs.',
            'seo' => 'Houses, apartments and property for sale in Lahore, Karachi, Islamabad, Multan, Faisalabad, Quetta and Peshawar. Reviewed listings from verified dealers.',
            'types' => [PropertyType::House, PropertyType::Apartment, PropertyType::UpperPortion, PropertyType::Farmhouse, PropertyType::Penthouse, PropertyType::LowerPortion],
            'tips' => [
                ['icon' => 'file-text', 'title' => 'Check the title documents', 'body' => 'Ask for the registry or allotment letter and confirm it with the society or the Punjab land record before paying a token.'],
                ['icon' => 'shield', 'title' => 'Use verified dealers', 'body' => 'Look for the verified badge — it means Meezan Property has checked the dealer’s identity and agency details.'],
                ['icon' => 'map-pin', 'title' => 'Visit at different times', 'body' => 'See the street in the morning and evening to judge traffic, water pressure and electricity supply.'],
            ],
        ]);
    }

    public function rent(): View
    {
        return $this->landing('rent', [
            'purpose' => 'rent',
            'group' => null,
            'eyebrow' => 'Property for rent',
            'title' => 'Properties for Rent in Pakistan',
            'intro' => 'Find houses, apartments and commercial properties for rent',
            'seo' => 'Houses, upper and lower portions, apartments and offices for rent in Lahore, Karachi, Islamabad, Multan, Faisalabad, Quetta and Peshawar, with monthly prices. Reviewed listings on Meezan Property.',
            'types' => [PropertyType::House, PropertyType::UpperPortion, PropertyType::LowerPortion, PropertyType::Apartment, PropertyType::Office, PropertyType::Shop],
            'tips' => [
                ['icon' => 'file-text', 'title' => 'Get a written agreement', 'body' => 'Agree rent, advance, security deposit and notice period in a signed tenancy agreement.'],
                ['icon' => 'shield', 'title' => 'Police registration', 'body' => 'Tenant registration with the local police station is required in Punjab — ask your landlord or dealer.'],
                ['icon' => 'key', 'title' => 'Inspect before moving in', 'body' => 'Note meter readings and the condition of fittings, and photograph them on handover.'],
            ],
        ]);
    }

    public function plots(): View
    {
        return $this->landing('plots', [
            'purpose' => 'sale',
            'group' => 'plots',
            'eyebrow' => 'Plots & land',
            'title' => 'Residential & Commercial Plots',
            'intro' => 'Find plots in top locations across Pakistan',
            'seo' => 'Residential plots, commercial plots and plot files for sale in Lahore, Karachi, Islamabad, Multan, Faisalabad, Quetta and Peshawar. Compare 5 Marla, 10 Marla and 1 Kanal plots.',
            'types' => PropertyType::inGroup('plots'),
            'tips' => [
                ['icon' => 'shield', 'title' => 'Confirm society approval', 'body' => 'Check the housing scheme is approved by LDA or the relevant authority before buying a plot or file.'],
                ['icon' => 'map', 'title' => 'Possession and development', 'body' => 'Ask whether the plot has possession, and whether roads, gas and electricity are in place.'],
                ['icon' => 'tag', 'title' => 'Know the size convention', 'body' => 'Meezan Property uses 1 Marla = 225 sq ft and 1 Kanal = 20 Marla for size filters.'],
            ],
        ]);
    }

    public function commercial(): View
    {
        return $this->landing('commercial', [
            'purpose' => null,
            'group' => 'commercial',
            'eyebrow' => 'Commercial property',
            'title' => 'Commercial Properties in Pakistan',
            'intro' => 'Shops, offices, plazas and commercial spaces',
            'seo' => 'Commercial property for sale and rent in Lahore, Karachi, Islamabad, Multan, Faisalabad, Quetta and Peshawar — shops, offices, warehouses and buildings.',
            'types' => PropertyType::inGroup('commercial'),
            'tips' => [
                ['icon' => 'map-pin', 'title' => 'Footfall and frontage', 'body' => 'Main-road frontage and nearby anchors drive value for shops — visit during business hours.'],
                ['icon' => 'file-text', 'title' => 'Commercialisation fees', 'body' => 'Confirm the plot’s commercial status and that commercialisation fees have been paid.'],
                ['icon' => 'building', 'title' => 'Parking and services', 'body' => 'Check parking, lifts, backup power and maintenance charges for offices and plazas.'],
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $page
     */
    protected function landing(string $key, array $page): View
    {
        $base = Property::published()
            ->when($page['purpose'], fn ($q, $v) => $q->where('purpose', $v))
            ->when($page['group'], fn ($q, $g) => $q->inGroup($g))
            ->when(! $page['group'] && $key === 'buy', fn ($q) => $q->inGroup('homes'));

        $listings = (clone $base)->forCards()->orderByDesc('is_featured')->latest('published_at')->take(8)->get();
        $total = (clone $base)->count();

        $typeCounts = (clone $base)->select('type', DB::raw('count(*) as total'))->groupBy('type')->pluck('total', 'type');

        $locations = Area::popular()->ordered()
            ->withCount(['properties as listings_count' => fn ($q) => $q->published()
                ->when($page['purpose'], fn ($p, $v) => $p->where('purpose', $v))
                ->when($page['group'], fn ($p, $g) => $p->inGroup($g))])
            ->take(8)
            ->get();

        $searchParams = array_filter(['purpose' => $page['purpose'], 'group' => $page['group'] ?? ($key === 'buy' ? 'homes' : null)]);

        return view('categories.show', compact('key', 'page', 'listings', 'total', 'typeCounts', 'locations', 'searchParams'));
    }
}
