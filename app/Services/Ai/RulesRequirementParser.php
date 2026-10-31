<?php

namespace App\Services\Ai;

use App\Enums\AreaUnit;
use App\Enums\PropertyType;
use App\Enums\Purpose;
use App\Services\Matching\MatchCriteria;
use App\Support\Format;
use App\Support\UrduText;
use Illuminate\Support\Str;

/**
 * Keyword and pattern based parser for English and common Roman-Urdu terms,
 * e.g. "10 marla ghar DHA mein 5 crore tak" or "2 bed flat on rent in Gulberg".
 */
class RulesRequirementParser implements RequirementParser
{
    /** @var array<string, PropertyType> */
    protected const TYPE_KEYWORDS = [
        'commercial plot' => PropertyType::CommercialPlot,
        'residential plot' => PropertyType::ResidentialPlot,
        'plot file' => PropertyType::PlotFile,
        'upper portion' => PropertyType::UpperPortion,
        'lower portion' => PropertyType::LowerPortion,
        'agricultural' => PropertyType::AgriculturalLand,
        'farmhouse' => PropertyType::Farmhouse,
        'farm house' => PropertyType::Farmhouse,
        'penthouse' => PropertyType::Penthouse,
        'apartment' => PropertyType::Apartment,
        'flat' => PropertyType::Apartment,
        'warehouse' => PropertyType::Warehouse,
        'godown' => PropertyType::Warehouse,
        'factory' => PropertyType::Factory,
        'building' => PropertyType::Building,
        'plaza' => PropertyType::Building,
        'office' => PropertyType::Office,
        'shop' => PropertyType::Shop,
        'dukan' => PropertyType::Shop,
        'plot' => PropertyType::ResidentialPlot,
        'bungalow' => PropertyType::House,
        'villa' => PropertyType::House,
        'kothi' => PropertyType::House,
        'house' => PropertyType::House,
        'home' => PropertyType::House,
        'ghar' => PropertyType::House,
        'makan' => PropertyType::House,
    ];

    /** English and Roman-Urdu number words, converted when they quantify a size or room count. */
    protected const NUMBER_WORDS = [
        'one' => 1, 'two' => 2, 'three' => 3, 'four' => 4, 'five' => 5, 'six' => 6, 'seven' => 7, 'eight' => 8, 'nine' => 9, 'ten' => 10,
        'aik' => 1, 'ek' => 1, 'do' => 2, 'teen' => 3, 'char' => 4, 'chaar' => 4, 'panch' => 5, 'paanch' => 5, 'chay' => 6, 'chhe' => 6,
        'saat' => 7, 'aath' => 8, 'nau' => 9, 'das' => 10,
    ];

    /** @var array<string, string> phrase => amenity key */
    protected const AMENITY_KEYWORDS = [
        'park facing' => 'park_facing', 'facing park' => 'park_facing', 'corner' => 'corner', 'basement' => 'basement',
        'servant quarter' => 'servant_quarter', 'lawn' => 'lawn', 'garden' => 'lawn', 'parking' => 'parking', 'garage' => 'parking',
        'lift' => 'lift', 'elevator' => 'lift', 'main boulevard' => 'main_boulevard', 'security' => 'security', 'cctv' => 'cctv',
        'sui gas' => 'gas', 'gas connection' => 'gas', 'generator' => 'electricity_backup', 'solar' => 'electricity_backup',
        'drawing room' => 'drawing_room', 'store room' => 'store_room',
    ];

    public function __construct(protected ?LocationExtractor $locations = null)
    {
        $this->locations ??= new LocationExtractor;
    }

    public function parse(string $message): MatchCriteria
    {
        // Urdu script is rewritten to the Roman forms below; unknown words are dropped, not guessed.
        $message = UrduText::toRoman($message);
        $place = $this->locations->extract($message);

        $text = ' '.Str::of($message)->lower()->replaceMatches('/(\d),(\d)/', '$1$2')->replaceMatches('/[^\p{L}\p{N}\.\s]/u', ' ')->squish().' ';

        // "half kanal", "aadha kanal" → 0.5; "derh" (1½) and "dhai" (2½).
        $text = preg_replace(
            [
                '/\b(half|aadha|adha|aadhi)\s+(?=(kanal|marla)\b)/',
                '/\b(one and a half|one and half|derh|dairh|darh)\s+(?=(kanal|marla|crore|lakh)\b)/',
                '/\b(two and a half|two and half|dhai|dhaai|arhai)\s+(?=(kanal|marla|crore|lakh)\b)/',
            ],
            ['0.5 ', '1.5 ', '2.5 '],
            $text,
        );

        // "five bedrooms" / "panch kamre" -> "5 bedrooms"
        $words = implode('|', array_keys(self::NUMBER_WORDS));
        $text = preg_replace_callback(
            '/\b('.$words.')\s+(?=(bed|beds|bedroom|bedrooms|bhk|kamre|kamray|rooms?|marla|kanal|bath|baths|bathroom|bathrooms|washrooms?)\b)/',
            fn ($m) => self::NUMBER_WORDS[$m[1]].' ',
            $text,
        );

        $budget = $this->budget($text);

        return new MatchCriteria(
            purpose: $this->purpose($text),
            type: $this->type($text),
            group: $this->group($text),
            city: $place['city'] ?? '',
            areaIds: $place['areaIds'],
            minPrice: $budget['min'],
            maxPrice: $budget['max'],
            minAreaSqft: $this->size($text),
            bedrooms: $this->bedrooms($text),
            bathrooms: $this->bathrooms($text),
            // Words that belong to a place name ("Garden Town") are not amenities.
            amenities: $this->amenities(str_replace(array_map(fn ($k) => ' '.$k.' ', $place['matched'] ?? []), '  ', $text)),
            clarify: $place['clarify'],
            unsupportedCity: $place['unsupported'],
            furnishing: $this->furnishing($text),
            tags: $this->tags($text),
            budgetApprox: $budget['approx'],
        );
    }

    protected function bathrooms(string $text): ?int
    {
        if (preg_match('/(\d{1,2})\s*(bath|baths|bathroom|bathrooms|washrooms?)\b/', $text, $m)) {
            return min(10, (int) $m[1]);
        }

        return null;
    }

    /**
     * @return list<string>
     */
    protected function amenities(string $text): array
    {
        $found = [];
        foreach (self::AMENITY_KEYWORDS as $phrase => $key) {
            if (str_contains($text, ' '.$phrase.' ') || str_contains($text, ' '.$phrase.'s ')) {
                $found[] = $key;
            }
        }

        return array_values(array_intersect(array_unique($found), array_keys(config('meezan.amenities'))));
    }

    protected function purpose(string $text): ?Purpose
    {
        if (preg_match('/\b(rent|rental|kiraya|kiraye|lease|to let)\b/', $text)) {
            return Purpose::Rent;
        }

        if (preg_match('/\b(buy|purchase|sale|khareed|kharidna|khareedna|khareedni|invest)\b/', $text)) {
            return Purpose::Sale;
        }

        return null;
    }

    protected function type(string $text): ?PropertyType
    {
        foreach (self::TYPE_KEYWORDS as $keyword => $type) {
            if (preg_match('/\b'.preg_quote($keyword, '/').'s?\b/', $text)) {
                return $type;
            }
        }

        return null;
    }

    protected function group(string $text): ?string
    {
        return preg_match('/\bcommercial\b/', $text) ? 'commercial' : null;
    }

    /**
     * "4 crore", "80 lakh", "25 million", "2 to 3 crore", "around 4 crore",
     * "PKR 40000000". A range gives a minimum too; "around"/"lagbhag" marks
     * the figure as approximate.
     *
     * @return array{min: ?int, max: ?int, approx: bool}
     */
    protected function budget(string $text): array
    {
        $units = 'crore|cr|caror|karor|lakh|lakhs|lac|lacs|million|thousand|k';
        $money = function (string $amount, string $unit): int {
            $multiplier = match ($unit) {
                'crore', 'cr', 'caror', 'karor' => Format::CRORE,
                'lakh', 'lakhs', 'lac', 'lacs' => Format::LAKH,
                'million' => 1_000_000,
                default => 1000,
            };

            return (int) round((float) $amount * $multiplier);
        };
        $approx = (bool) preg_match('/\b(around|approx|approximately|about|roughly|lagbhag|taqreeban|takreeban|kareeb)\b/', $text);

        // "2 to 3 crore", "2-3 crore", "2 se 3 crore"
        if (preg_match('/(\d+(?:\.\d+)?)\s*(?:to|se|and|-)\s*(\d+(?:\.\d+)?)\s*('.$units.')\b/', $text, $m)) {
            $low = $money($m[1], $m[3]);
            $high = $money($m[2], $m[3]);

            return ['min' => min($low, $high), 'max' => max($low, $high), 'approx' => $approx];
        }

        if (preg_match_all('/(\d+(?:\.\d+)?)\s*('.$units.')\b/', $text, $matches, PREG_SET_ORDER)) {
            return ['min' => null, 'max' => max(array_map(fn ($m) => $money($m[1], $m[2]), $matches)), 'approx' => $approx];
        }

        // A plain rupee amount: "PKR 40000000", "rs 85000".
        if (preg_match('/\b(?:pkr|rs|rupees?)\s*(\d{5,})\b|\b(\d{5,})\s*(?:pkr|rs|rupees?)\b/', $text, $m)) {
            return ['min' => null, 'max' => (int) ($m[1] ?: $m[2]), 'approx' => $approx];
        }

        return ['min' => null, 'max' => null, 'approx' => false];
    }

    protected function furnishing(string $text): ?string
    {
        return match (true) {
            (bool) preg_match('/\b(semi furnished|semifurnished)\b/', $text) => 'semi_furnished',
            (bool) preg_match('/\b(unfurnished|non furnished|without furniture)\b/', $text) => 'unfurnished',
            (bool) preg_match('/\b(furnished|with furniture)\b/', $text) => 'furnished',
            default => null,
        };
    }

    /**
     * Stated reasons ("for investment", "for my family"), stored with the
     * requirement only. They never change ranking or produce any claim.
     *
     * @return list<string>
     */
    protected function tags(string $text): array
    {
        return array_keys(array_filter([
            'investment' => preg_match('/\b(invest|investment|nivesh)\b/', $text),
            'family' => preg_match('/\b(family|ghar wale|bachon)\b/', $text),
            'commercial_use' => preg_match('/\b(business|commercial use|office use)\b/', $text),
        ]));
    }

    protected function size(string $text): ?int
    {
        if (! preg_match('/(\d+(?:\.\d+)?)\s*(marla|kanal|sq\s?ft|sqft|square feet|sq\s?yds?|sq yards?|square yards?|gaz)\b/', $text, $m)) {
            return null;
        }

        $unit = match (true) {
            str_starts_with($m[2], 'kanal') => AreaUnit::Kanal,
            str_contains($m[2], 'ft') || str_contains($m[2], 'feet') => AreaUnit::SquareFeet,
            str_contains($m[2], 'yd') || str_contains($m[2], 'yard') || $m[2] === 'gaz' => AreaUnit::SquareYards,
            default => AreaUnit::Marla,
        };

        return $unit->toSquareFeet((float) $m[1]);
    }

    protected function bedrooms(string $text): ?int
    {
        if (preg_match('/(\d{1,2})\s*(bed|beds|bedroom|bedrooms|bhk|kamre|kamray|rooms?)\b/', $text, $m)) {
            return min(10, (int) $m[1]);
        }

        return null;
    }
}
