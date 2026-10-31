<?php

namespace App\Services;

use App\Models\Property;

/**
 * How complete a listing's information is, to help dealers improve it and
 * reviewers prioritise. It is not a rating of the property, its value or
 * the dealer, and is never shown to buyers.
 */
class ListingCompleteness
{
    /** Below this a draft is not "ready to submit". */
    public const READY_AT = 60;

    /** Recommended photo count and the size under which a photo counts as low resolution. */
    public const GOOD_PHOTO_COUNT = 5;

    public const LOW_RES_WIDTH = 1000;

    /**
     * @return array{score: int, missing: list<string>, ready: bool}
     */
    public static function for(Property $p): array
    {
        $images = $p->exists ? ($p->relationLoaded('images') ? $p->images->where('status', 'active') : $p->images()->where('status', 'active')->get(['id', 'width'])) : collect();
        $hasRooms = $p->type?->hasRooms() ?? false;
        $words = str_word_count(strip_tags((string) $p->description));

        // [weight, met, what to improve]
        $checks = [
            [10, mb_strlen((string) $p->title) >= 20, 'Write a descriptive title (at least 20 characters)'],
            [15, $words >= 60, 'Describe the property in at least 60 words'],
            [10, (bool) $p->price && (bool) $p->type && (bool) $p->purpose, 'Add the price, type and purpose'],
            [10, (bool) $p->city && (bool) $p->area_id, 'Choose the city and area'],
            [10, (bool) $p->area_size, 'Add the plot or unit size'],
            [10, ! $hasRooms || ($p->bedrooms !== null && $p->bathrooms !== null), 'Add bedrooms and bathrooms'],
            [15, $images->count() >= self::GOOD_PHOTO_COUNT, 'Add at least '.self::GOOD_PHOTO_COUNT.' photos'],
            [5, $images->isNotEmpty() && $images->every(fn ($i) => ! $i->width || $i->width >= self::LOW_RES_WIDTH), 'Use photos at least '.self::LOW_RES_WIDTH.' px wide'],
            [10, $p->hasCoordinates(), 'Place the exact map pin (you can still show only an approximate location)'],
            [5, (bool) ($p->address || ($p->amenities ?? [])), 'Add the street/block or the features'],
        ];

        $score = 0;
        $missing = [];
        foreach ($checks as [$weight, $met, $hint]) {
            $met ? $score += $weight : $missing[] = $hint;
        }

        return ['score' => $score, 'missing' => $missing, 'ready' => $score >= self::READY_AT];
    }
}
