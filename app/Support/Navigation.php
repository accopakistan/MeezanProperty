<?php

namespace App\Support;

use App\Models\Package;

/**
 * The public site navigation: one definition for the desktop mega menu and the
 * mobile menu. Every link points at a page that exists; nothing is promoted
 * that isn't ready (dealer packages appear only once a final package is offered).
 *
 * Menu panel photos (public/images/menu) are from Pexels, free for commercial use under the
 * Pexels licence: buy 27692049, rent 7641906, investment 7580643, dealers 16749990.
 */
class Navigation
{
    /**
     * @return list<array{key: string, label: string, routes: list<string>, columns: list<array{title: string, links: list<array{label: string, url: string, icon?: string, hint?: string}>}>, feature?: array}>
     */
    public static function sections(): array
    {
        $cities = Cities::names();
        $cityLinks = fn (string $category) => array_map(fn ($city) => ['label' => $city, 'url' => route('cities.category', [Cities::slug($city), $category])], $cities);
        $areas = Lookup::popularAreas(6, config('meezan.default_city'));

        return [
            [
                'key' => 'buy', 'label' => 'Buy', 'routes' => ['properties.buy', 'properties.plots', 'cities.*', 'areas.*'],
                'columns' => [
                    ['title' => 'Property type', 'links' => [
                        ['label' => 'Homes for sale', 'url' => route('properties.buy'), 'icon' => 'home', 'hint' => 'Houses, apartments and portions'],
                        ['label' => 'Houses', 'url' => route('properties.index', ['purpose' => 'sale', 'type' => 'house']), 'icon' => 'home'],
                        ['label' => 'Apartments & flats', 'url' => route('properties.index', ['purpose' => 'sale', 'type' => 'apartment']), 'icon' => 'building'],
                        ['label' => 'Plots', 'url' => route('properties.plots'), 'icon' => 'plot', 'hint' => 'Residential and commercial plots'],
                        ['label' => 'Commercial for sale', 'url' => route('properties.index', ['purpose' => 'sale', 'group' => 'commercial']), 'icon' => 'store'],
                    ]],
                    ['title' => 'Buy in a city', 'links' => $cityLinks('property-for-sale')],
                    ['title' => 'Popular areas in '.config('meezan.default_city'), 'links' => $areas->map(fn ($a) => ['label' => $a->name, 'url' => route('areas.show', $a->slug)])->push(['label' => 'All area guides', 'url' => route('areas.index')])->all()],
                ],
                'feature' => ['image' => 'images/menu/buy.jpg', 'image_position' => 'center 20%', 'icon' => 'sparkles', 'title' => 'Describe it, we find it', 'body' => 'Tell the AI Property Finder what you need in plain words, in English or Urdu.', 'cta' => 'Try AI Property Finder', 'url' => route('ai-finder')],
            ],
            [
                'key' => 'rent', 'label' => 'Rent', 'routes' => ['properties.rent'],
                'columns' => [
                    ['title' => 'Property type', 'links' => [
                        ['label' => 'All rentals', 'url' => route('properties.rent'), 'icon' => 'key', 'hint' => 'Monthly rents across Pakistan'],
                        ['label' => 'Houses for rent', 'url' => route('properties.index', ['purpose' => 'rent', 'type' => 'house']), 'icon' => 'home'],
                        ['label' => 'Flats for rent', 'url' => route('properties.index', ['purpose' => 'rent', 'type' => 'apartment']), 'icon' => 'building'],
                        ['label' => 'Upper portions', 'url' => route('properties.index', ['purpose' => 'rent', 'type' => 'upper_portion']), 'icon' => 'layers'],
                        ['label' => 'Shops & offices', 'url' => route('properties.index', ['purpose' => 'rent', 'group' => 'commercial']), 'icon' => 'briefcase'],
                    ]],
                    ['title' => 'Rent in a city', 'links' => $cityLinks('property-for-rent')],
                    ['title' => 'Before you rent', 'links' => [
                        ['label' => 'Rental yield calculator', 'url' => route('investment.yield'), 'icon' => 'chart', 'hint' => 'For landlords and investors'],
                        ['label' => 'Map search', 'url' => route('properties.map', ['purpose' => 'rent']), 'icon' => 'map'],
                        ['label' => 'How it works', 'url' => route('pages.how-it-works'), 'icon' => 'info'],
                    ]],
                ],
                'feature' => ['image' => 'images/menu/rent.jpg', 'image_position' => 'center', 'icon' => 'whatsapp', 'title' => 'Ask our team', 'body' => 'Tell us what you are looking for and we will help you find it.', 'cta' => 'WhatsApp us', 'url' => Contact::whatsappUrl() ?? route('pages.contact'), 'external' => (bool) Contact::whatsapp()],
            ],
            [
                'key' => 'invest', 'label' => 'Investment', 'routes' => ['rankings.*', 'projects.*'],
                'columns' => [
                    ['title' => 'Meezan Investment Index', 'links' => [
                        ['label' => 'Index overview', 'url' => route('rankings.index'), 'icon' => 'chart', 'hint' => 'Researched projects across Pakistan'],
                        ['label' => 'How the Index works', 'url' => route('rankings.methodology'), 'icon' => 'file-text'],
                        ['label' => 'New projects', 'url' => route('projects.index'), 'icon' => 'building'],
                    ]],
                    ['title' => 'Research by category', 'links' => collect(config('investment_index.categories'))->map(fn ($c, $key) => ['label' => $c['label'], 'url' => route('rankings.category', $key)])->values()->all()],
                    ['title' => 'Rankings by city', 'links' => array_map(fn ($city) => ['label' => $city, 'url' => route('rankings.city', Cities::slug($city))], $cities)],
                ],
                'feature' => ['image' => 'images/menu/investment.jpg', 'image_position' => '70% center', 'icon' => 'shield', 'title' => 'Research, not recommendations', 'body' => 'Every project shows its legal status, sources and missing evidence. A project is ranked only when verified evidence supports it.', 'cta' => 'Explore the Index', 'url' => route('rankings.index')],
            ],
            [
                'key' => 'explore', 'label' => 'Explore', 'routes' => ['properties.map', 'compare', 'areas.index', 'pages.*', 'investment.*'],
                'columns' => [
                    ['title' => 'Search tools', 'links' => [
                        ['label' => 'All properties', 'url' => route('properties.index'), 'icon' => 'search'],
                        ['label' => 'Map search', 'url' => route('properties.map'), 'icon' => 'map', 'hint' => 'Browse listings on the map'],
                        ['label' => 'Compare properties', 'url' => route('compare'), 'icon' => 'layers'],
                        ['label' => 'Area guides', 'url' => route('areas.index'), 'icon' => 'map-pin'],
                    ]],
                    ['title' => 'Cities', 'links' => array_map(fn ($city) => ['label' => $city, 'url' => route('cities.show', Cities::slug($city))], $cities)],
                    ['title' => 'Investment tools', 'links' => [
                        ['label' => 'Investment insights', 'url' => route('investment.index'), 'icon' => 'target'],
                        ['label' => 'Compare investments', 'url' => route('investment.compare'), 'icon' => 'layers'],
                        ['label' => 'Rental yield calculator', 'url' => route('investment.yield'), 'icon' => 'chart'],
                    ]],
                    ['title' => 'Company', 'links' => [
                        ['label' => 'About us', 'url' => route('pages.about'), 'icon' => 'info'],
                        ['label' => 'How it works', 'url' => route('pages.how-it-works'), 'icon' => 'check'],
                        ['label' => 'Contact us', 'url' => route('pages.contact'), 'icon' => 'mail'],
                    ]],
                ],
            ],
            [
                'key' => 'dealers', 'label' => 'Dealers', 'routes' => ['dealers.*', 'sell', 'sell.*', 'pricing', 'dealer.register'],
                'columns' => [
                    ['title' => 'Find a professional', 'links' => [
                        ['label' => 'Find a dealer', 'url' => route('dealers.index'), 'icon' => 'users', 'hint' => 'Agencies by city and area'],
                    ]],
                    ['title' => 'Sell or list', 'links' => array_values(array_filter([
                        ['label' => 'List your property', 'url' => route('sell'), 'icon' => 'plus', 'hint' => 'Owners and dealers'],
                        ['label' => 'Register as a dealer', 'url' => route('dealer.register'), 'icon' => 'badge-check'],
                        Package::offered()->exists() ? ['label' => 'Dealer packages', 'url' => route('pricing'), 'icon' => 'credit-card'] : null,
                    ]))],
                ],
                'feature' => ['image' => 'images/menu/dealers.jpg', 'image_position' => '72% center', 'icon' => 'briefcase', 'title' => 'Grow your agency', 'body' => 'List properties, receive buyer leads for your areas and manage them in one dashboard.', 'cta' => 'Register as a dealer', 'url' => route('dealer.register')],
            ],
        ];
    }

    /** Whether a section holds the current page, for its "active" marker. */
    public static function isActive(array $section): bool
    {
        return request()->routeIs(...$section['routes']);
    }
}
