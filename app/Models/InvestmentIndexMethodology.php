<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * A versioned Investment Index methodology: factor weights per category
 * (each category summing to 100) and parameters. Active and retired
 * versions are never edited; a change is a new version, so every past
 * run can be explained by the weights it used.
 */
class InvestmentIndexMethodology extends Model
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

    /** The active methodology; version 1 is created from config on first use. */
    public static function current(): self
    {
        if ($active = static::active()) {
            return $active;
        }
        $m = new static(['name' => 'Meezan Investment Index v1', 'weights' => static::configWeights(),
            'notes' => 'Initial category weights from the Investment Index scope.']);
        $m->forceFill(['version' => (int) static::max('version') + 1, 'status' => 'active', 'activated_at' => now()])->save();

        return $m;
    }

    /** @return array<string, array<string, int>> category => factor => weight */
    public static function configWeights(): array
    {
        return collect(config('investment_index.categories'))->map(fn ($c) => array_map(fn ($f) => (int) $f['weight'], $c['factors']))->all();
    }

    /** @return array<string, int> */
    public function weightsFor(string $category): array
    {
        return array_map('intval', $this->weights[$category] ?? []);
    }

    public function parameter(string $key, mixed $default = null): mixed
    {
        return $this->parameters[$key] ?? config('investment_index.parameters.'.$key, $default);
    }

    /**
     * Each category keeps exactly its own factors, as whole numbers 0-100 summing to 100.
     *
     * @throws ValidationException
     */
    public static function validateWeights(array $weights): array
    {
        $errors = [];
        $clean = [];
        foreach (config('investment_index.categories') as $key => $category) {
            $valid = true;
            foreach (array_keys($category['factors']) as $factor) {
                $value = $weights[$key][$factor] ?? null;
                if (! is_numeric($value) || (int) $value != $value || $value < 0 || $value > 100) {
                    $errors["weights.$key.$factor"] = 'Each weight must be a whole number from 0 to 100.';
                    $valid = false;

                    continue;
                }
                $clean[$key][$factor] = (int) $value;
            }
            if ($valid && array_sum($clean[$key]) !== 100) {
                $errors["weights.$key"] = "{$category['label']}: weights add up to ".array_sum($clean[$key] ?? []).'; they must add up to 100.';
            }
            // Legal approval can never be switched off: unverified legal status is never treated as approved.
            if (($clean[$key]['legal_approval'] ?? 0) < 1) {
                $errors["weights.$key.legal_approval"] = "{$category['label']}: legal approval must keep a weight.";
            }
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return $clean;
    }
}
