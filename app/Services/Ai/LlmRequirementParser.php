<?php

namespace App\Services\Ai;

use App\Enums\AreaUnit;
use App\Enums\PropertyType;
use App\Enums\Purpose;
use App\Services\Matching\MatchCriteria;
use App\Support\LocationName;
use App\Support\LocationResolver;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Optional LLM-backed requirement parser (OpenAI-compatible chat completions).
 *
 * The model only extracts structured criteria; every value is validated
 * against our enums, areas and amenity keys, and listings always come from
 * the database matcher. Any failure falls back to the rule-based parser, and
 * rule-based results fill anything the model left empty.
 */
class LlmRequirementParser implements RequirementParser
{
    public function __construct(protected RulesRequirementParser $fallback) {}

    public function parse(string $message): MatchCriteria
    {
        $rules = $this->fallback->parse($message);

        if (trim($message) === '' || ! filled(config('services.openai.key'))) {
            return $rules;
        }

        try {
            $response = Http::withToken(config('services.openai.key'))
                ->timeout(12)
                ->post(rtrim(config('services.openai.base_url'), '/').'/chat/completions', [
                    'model' => config('services.openai.model'),
                    'temperature' => 0,
                    'response_format' => ['type' => 'json_object'],
                    'messages' => [
                        ['role' => 'system', 'content' => $this->instructions()],
                        ['role' => 'user', 'content' => mb_substr($message, 0, 500)],
                    ],
                ])
                ->throw();

            $data = json_decode((string) $response->json('choices.0.message.content'), true);

            return is_array($data) ? $rules->merge($this->toCriteria($data, $rules)) : $rules;
        } catch (Throwable $e) {
            Log::warning('LLM requirement parsing failed; using rules', ['error' => $e->getMessage()]);

            return $rules;
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function toCriteria(array $data, MatchCriteria $rules): MatchCriteria
    {
        // Model-suggested place names are only accepted when they resolve to a
        // known location in the city the rules found (or, with no city, to a
        // single city), so the model can never move a search to another city.
        $areaIds = [];
        if (! $rules->clarify && ! $rules->unsupportedCity) {
            $found = collect((array) ($data['areas'] ?? []))->filter(fn ($n) => is_string($n) && $n !== '')
                ->flatMap(fn (string $name) => LocationResolver::find($name, $rules->city ?: null)->filter(fn ($a) => LocationName::normalize($a->name) === LocationName::normalize($name)
                    || $a->aliases()->where('normalized', LocationName::normalize($name))->exists()));
            if ($found->pluck('city')->unique()->count() === 1) {
                $areaIds = $found->pluck('id')->unique()->values()->all();
            }
        }

        $size = null;
        if (is_array($data['min_size'] ?? null) && is_numeric($data['min_size']['value'] ?? null)) {
            $unit = AreaUnit::tryFrom((string) ($data['min_size']['unit'] ?? 'marla')) ?? AreaUnit::Marla;
            $size = $unit->toSquareFeet((float) $data['min_size']['value']);
        }

        $int = fn ($v, int $max) => is_numeric($v) && $v > 0 ? min($max, (int) $v) : null;

        return new MatchCriteria(
            purpose: Purpose::tryFrom((string) ($data['purpose'] ?? '')),
            type: PropertyType::tryFrom((string) ($data['type'] ?? '')),
            city: '',
            areaIds: $areaIds,
            maxPrice: $int($data['max_price'] ?? null, 50_000_000_000),
            minAreaSqft: $size,
            bedrooms: $int($data['bedrooms'] ?? null, 10),
            bathrooms: $int($data['bathrooms'] ?? null, 10),
            amenities: array_values(array_intersect((array) ($data['amenities'] ?? []), array_keys(config('meezan.amenities')))),
        );
    }

    protected function instructions(): string
    {
        $types = implode(', ', array_map(fn ($t) => $t->value, PropertyType::cases()));
        $amenities = implode(', ', array_keys(config('meezan.amenities')));

        return <<<PROMPT
        Extract a Pakistani property search requirement from the user's message (English, Urdu or Roman Urdu).
        Reply with JSON only, using exactly these keys (null when not stated):
        {"purpose": "sale"|"rent"|null, "type": one of [{$types}] or null, "areas": [area or society names exactly as the user wrote them], "max_price": integer PKR or null,
         "min_size": {"value": number, "unit": "marla"|"kanal"|"sqft"|"sqyd"} or null, "bedrooms": integer or null, "bathrooms": integer or null,
         "amenities": [keys from: {$amenities}]}
        1 crore = 10,000,000 PKR; 1 lakh = 100,000 PKR. Only list places the user named; never guess a city.
        Never invent listings or values that the user did not state.
        PROMPT;
    }
}
