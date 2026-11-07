<?php

namespace App\Services\InvestmentIndex;

use App\Support\Cities;

class IndexHeroImage
{
    public const ASSET_DIR = 'images/investment-index/';

    /**
     * Map of normalized category keys.
     */
    public const CATEGORY_KEYS = [
        'housing-societies' => 'housing-societies',
        'societies' => 'housing-societies',
        'commercial-plazas' => 'commercial-plazas',
        'commercial' => 'commercial-plazas',
        'apartment-buildings' => 'apartment-buildings',
        'apartments' => 'apartment-buildings',
        'shopping-malls' => 'shopping-malls',
        'malls' => 'shopping-malls',
        'business-towers' => 'business-towers',
        'towers' => 'business-towers',
    ];

    /**
     * Resolve the hero banner image data for an investment view.
     *
     * Precedence:
     * 1. Category-specific banner if category is provided (even on city-category pages).
     * 2. City-specific banner if city is provided without a category.
     * 3. National Pakistan banner for the main investment index.
     * 4. Reliable local fallback image if the resolved file is missing.
     *
     * @return array{url: string, path: string, alt: string, type: string, city: ?string, category: ?string}
     */
    public static function resolve(?string $city = null, ?string $category = null): array
    {
        $normalizedCity = self::normalizeCity($city);
        $normalizedCategory = self::normalizeCategory($category);

        // 1. Category takes precedence on category pages & city-category combinations
        if ($normalizedCategory) {
            $catRelative = self::ASSET_DIR . 'category-' . $normalizedCategory . '.jpg';
            if (file_exists(public_path($catRelative))) {
                return [
                    'url' => asset($catRelative),
                    'path' => $catRelative,
                    'alt' => self::categoryAlt($normalizedCategory, $normalizedCity),
                    'type' => 'category',
                    'city' => $normalizedCity,
                    'category' => $normalizedCategory,
                ];
            }
        }

        // 2. City banner for general city index pages
        if ($normalizedCity && $normalizedCity !== 'pakistan') {
            $citySlug = Cities::slug($normalizedCity) ?? mb_strtolower($normalizedCity);
            $cityRelative = self::ASSET_DIR . 'city-' . $citySlug . '.jpg';
            if (file_exists(public_path($cityRelative))) {
                return [
                    'url' => asset($cityRelative),
                    'path' => $cityRelative,
                    'alt' => self::cityAlt($normalizedCity),
                    'type' => 'city',
                    'city' => $normalizedCity,
                    'category' => null,
                ];
            }
        }

        // 3. National Pakistan banner
        $nationalRelative = self::ASSET_DIR . 'national.jpg';
        if (file_exists(public_path($nationalRelative))) {
            return [
                'url' => asset($nationalRelative),
                'path' => $nationalRelative,
                'alt' => 'Meezan Property Investment Index — Modern Pakistan Real Estate & Commercial Architecture',
                'type' => 'national',
                'city' => null,
                'category' => null,
            ];
        }

        // 4. Default fallback image
        $fallbackRelative = self::ASSET_DIR . 'fallback.jpg';
        return [
            'url' => asset($fallbackRelative),
            'path' => $fallbackRelative,
            'alt' => 'Meezan Property Investment Intelligence Platform',
            'type' => 'fallback',
            'city' => $normalizedCity,
            'category' => $normalizedCategory,
        ];
    }

    /**
     * Category label helper for titles and badges.
     */
    public static function categoryLabel(?string $category): ?string
    {
        if (! $category) {
            return null;
        }

        $normalized = self::normalizeCategory($category);
        $config = config("investment_index.categories.{$normalized}");

        return $config['label'] ?? match ($normalized) {
            'housing-societies' => 'Housing Societies',
            'commercial-plazas' => 'Commercial Plazas',
            'apartment-buildings' => 'Apartment Buildings',
            'shopping-malls' => 'Shopping Malls',
            'business-towers' => 'Business Towers',
            default => str_replace('-', ' ', ucwords($category, '-')),
        };
    }

    protected static function normalizeCity(?string $city): ?string
    {
        if (! $city) {
            return null;
        }

        $c = trim($city);
        if (in_array(mb_strtolower($c), ['pakistan', 'all'], true)) {
            return null;
        }

        return Cities::canonical($c) ?? $c;
    }

    protected static function normalizeCategory(?string $category): ?string
    {
        if (! $category) {
            return null;
        }

        $c = mb_strtolower(trim($category));

        return self::CATEGORY_KEYS[$c] ?? (config("investment_index.categories.{$c}") ? $c : null);
    }

    protected static function categoryAlt(string $category, ?string $city): string
    {
        $catLabel = self::categoryLabel($category);
        $loc = $city ? " in {$city}" : ' across Pakistan';

        return match ($category) {
            'housing-societies' => "Modern planned housing societies and residential communities{$loc}",
            'commercial-plazas' => "Contemporary commercial plazas and retail complexes{$loc}",
            'apartment-buildings' => "Luxury high-rise residential apartment towers{$loc}",
            'shopping-malls' => "Modern shopping mall architecture and commercial frontages{$loc}",
            'business-towers' => "Corporate business towers and modern office architecture{$loc}",
            default => "{$catLabel}{$loc} — Meezan Property Investment Index",
        };
    }

    protected static function cityAlt(string $city): string
    {
        return match (mb_strtolower($city)) {
            'lahore' => 'Modern Lahore skyline, commercial real estate, and architectural developments',
            'karachi' => 'Karachi coastal financial district, high-rise towers, and commercial skyline',
            'islamabad' => 'Islamabad planned urban architecture and modern towers against the Margalla Hills',
            'multan' => 'Multan urban developments, commercial avenues, and modern architecture',
            'faisalabad' => 'Faisalabad commercial district, industrial urban centers, and real estate growth',
            'peshawar' => 'Peshawar modern urban architecture, road infrastructure, and commercial hubs',
            'quetta' => 'Quetta urban real estate developments set against the surrounding mountain valleys',
            default => "{$city} real estate and commercial investment developments",
        };
    }
}
