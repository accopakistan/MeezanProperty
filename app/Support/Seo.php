<?php

namespace App\Support;

use App\Enums\PropertyType;
use App\Models\Area;
use App\Models\Property;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Search-engine helpers built only from real data: listing titles and
 * descriptions, category landing pages, and JSON-LD. Nothing here adds
 * claims (ratings, awards, figures) that are not in the database.
 */
class Seo
{
    /** "10 Marla House for Sale in DHA Phase 6, Lahore". */
    public static function propertyTitle(Property $property): string
    {
        return trim($property->areaLabel().' '.$property->type->label().' '.preg_replace('/^For /', 'for ', $property->purpose->label()).' in '.$property->locationLabel());
    }

    /** Factual summary from the listing's own fields, then its description, within ~155 characters. */
    public static function propertyDescription(Property $property): string
    {
        $facts = collect([
            $property->bedrooms ? $property->bedrooms.' bed' : null,
            $property->bathrooms ? $property->bathrooms.' bath' : null,
            $property->areaLabel(),
            $property->priceLabel(),
        ])->filter()->implode(' · ');

        return Str::limit(self::propertyTitle($property).': '.$facts.'. '.Str::squish(strip_tags((string) $property->description)), 154, '…');
    }

    /** The most specific landing category for a listing (e.g. houses-for-sale), else property-for-sale/rent. */
    public static function categoryFor(Property $property): string
    {
        $purpose = $property->purpose->value;
        $categories = collect(config('meezan.seo.categories'))->filter(fn ($c) => $c['purpose'] === $purpose);

        return $categories->search(fn ($c) => in_array($property->type->value, $c['types'] ?? [], true))
            ?: $categories->search(fn ($c) => $c['group'] && $c['group'] === $property->type->group())
            ?: 'property-for-'.$purpose;
    }

    /** @return array{label: string, purpose: string, group: ?string, types: ?list<string>}|null */
    public static function category(string $slug): ?array
    {
        return config('meezan.seo.categories.'.$slug);
    }

    /** Published listings in a category, optionally limited to a city or an area (with its phases and blocks). */
    public static function categoryQuery(string $slug, ?string $city = null, ?Area $area = null): Builder
    {
        $category = self::category($slug);

        return Property::query()->published()
            ->where('purpose', $category['purpose'])
            ->when($category['group'], fn ($q, $g) => $q->inGroup($g))
            ->when($category['types'], fn ($q, $t) => $q->whereIn('type', $t))
            ->when($city, fn ($q, $c) => $q->where('city', $c))
            ->when($area, fn ($q, $a) => $q->whereIn('area_id', LocationResolver::withDescendants([$a->id])));
    }

    /**
     * Listing counts per category for a city or an area, cached for ten
     * minutes: "listings" (all published, decides whether to link to the
     * page) and "real" (non-demo, decides whether it is indexed and listed
     * in the sitemap).
     *
     * @return array<string, array{listings: int, real: int}>
     */
    public static function categoryCounts(?string $city = null, ?Area $area = null): array
    {
        $key = 'seo:category-counts:'.($area ? 'area:'.$area->id : 'city:'.$city);

        return Cache::remember($key, 600, function () use ($city, $area) {
            // One grouped query (purpose × type), then summed per category.
            $rows = Property::query()->published()
                ->when($city, fn ($q, $c) => $q->where('city', $c))
                ->when($area, fn ($q, $a) => $q->whereIn('area_id', LocationResolver::withDescendants([$a->id])))
                ->toBase()->groupBy('purpose', 'type')
                ->get(['purpose', 'type', DB::raw('count(*) as listings'), DB::raw('sum(case when is_demo = 0 then 1 else 0 end) as real_listings')]);

            return collect(config('meezan.seo.categories'))->map(function ($category) use ($rows) {
                $types = $category['types'] ?? ($category['group'] ? array_map(fn ($t) => $t->value, PropertyType::inGroup($category['group'])) : null);
                $matching = $rows->where('purpose', $category['purpose'])->filter(fn ($r) => $types === null || in_array($r->type, $types, true));

                return ['listings' => (int) $matching->sum('listings'), 'real' => (int) $matching->sum('real_listings')];
            })->all();
        });
    }

    /** A landing page is indexed only with enough real listings (never thin pages). */
    public static function indexable(int $realListings): bool
    {
        return $realListings >= (int) config('meezan.seo.min_listings');
    }

    public static function defaultImage(): string
    {
        return asset(config('meezan.seo.default_image'));
    }

    /** Organization: only facts we hold (name, site, logo, configured public contact). */
    public static function organization(): array
    {
        $contact = config('meezan.contact');

        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => 'Meezan Property',
            'url' => route('home'),
            'logo' => asset('images/ref/logo.png'),
            'email' => $contact['email'] ?: null,
            'telephone' => $contact['phone'] ?: null,
            'areaServed' => ['@type' => 'Country', 'name' => 'Pakistan'],
        ]);
    }

    /** JSON-LD, safe to print inside a <script> element. */
    public static function json(array $data): string
    {
        return json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP);
    }
}
