<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * A versioned scoring methodology: weights per ranking category (each
 * summing to 100) and normalisation parameters. Active and retired
 * versions are never edited; changes are made in a new draft version.
 */
class InvestmentMethodology extends Model
{
    protected $fillable = ['name', 'weights', 'parameters', 'notes'];

    protected function casts(): array
    {
        return ['weights' => 'array', 'parameters' => 'array', 'activated_at' => 'datetime'];
    }

    public static function active(): ?self
    {
        return static::where('status', 'active')->latest('version')->first();
    }

    public function isEditable(): bool
    {
        return $this->status === 'draft';
    }

    /** @return array<string, int> */
    public function weightsFor(string $category): array
    {
        return array_map('intval', $this->weights[$category] ?? []);
    }

    public function parameter(string $key, mixed $default = null): mixed
    {
        return $this->parameters[$key] ?? config('meezan.investment.default_parameters.'.$key, $default);
    }

    /**
     * Every category needs every factor, weights 0-100 in whole numbers, summing to exactly 100.
     *
     * @throws ValidationException
     */
    public static function validateWeights(array $weights): array
    {
        $factors = array_keys(config('meezan.investment.factors'));
        $errors = [];
        $clean = [];
        foreach (array_keys(config('meezan.investment.categories')) as $category) {
            $row = $weights[$category] ?? [];
            foreach ($factors as $factor) {
                $value = $row[$factor] ?? null;
                $v = Validator::make(['w' => $value], ['w' => ['required', 'integer', 'between:0,100']]);
                if ($v->fails()) {
                    $errors["weights.$category.$factor"] = 'Each weight must be a whole number from 0 to 100.';

                    continue;
                }
                $clean[$category][$factor] = (int) $value;
            }
            if (isset($clean[$category]) && count($clean[$category]) === count($factors) && array_sum($clean[$category]) !== 100) {
                $label = config('meezan.investment.categories.'.$category.'.label');
                $errors["weights.$category"] = "{$label}: weights add up to ".array_sum($clean[$category]).'; they must add up to 100.';
            }
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return $clean;
    }
}
