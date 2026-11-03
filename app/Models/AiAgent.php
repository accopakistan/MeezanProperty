<?php

namespace App\Models;

use App\Services\Agents\AiProvider;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A registered AI agent. Definitions are versioned: every change to
 * instructions, model, tools or status writes an ai_agent_versions row.
 * Only "active" agents accept new tasks.
 */
class AiAgent extends Model
{
    public const STATUSES = [
        'active' => 'Active',
        'disabled' => 'Disabled',
        'maintenance' => 'Maintenance',
        'error' => 'Error',
        'configuration_required' => 'Configuration Required',
    ];

    public const TIERS = [
        'routine' => 'Routine classification',
        'extraction' => 'Structured extraction',
        'support' => 'Customer support',
        'research' => 'Research',
        'reasoning' => 'Complex reasoning',
    ];

    protected $fillable = ['primary_provider', 'secondary_provider', 'secondary_model', 'fallback_enabled', 'max_tokens', 'daily_request_limit', 'monthly_budget_usd', 'name', 'role', 'description', 'instructions', 'model_tier', 'model', 'temperature', 'tools', 'memory_policy', 'status', 'daily_token_budget'];

    protected function casts(): array
    {
        return ['tools' => 'array', 'memory_policy' => 'array', 'temperature' => 'float', 'fallback_enabled' => 'boolean', 'monthly_budget_usd' => 'float'];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function versions(): HasMany
    {
        return $this->hasMany(AiAgentVersion::class, 'agent_id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(AiTask::class, 'agent_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function canUse(string $tool): bool
    {
        return in_array($tool, $this->tools ?? [], true);
    }

    /** The model this agent uses on its primary provider: its own override, else its tier's, else the provider default. */
    public function resolvedModel(): string
    {
        return (string) app(AiProvider::class)->route($this, (string) $this->model_tier)['primary']['model'];
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    /** Write the current definition as a new version. */
    public function snapshot(?User $by, ?string $note): AiAgentVersion
    {
        return $this->versions()->create([
            'version' => $this->version,
            'snapshot' => $this->only(['primary_provider', 'secondary_provider', 'secondary_model', 'fallback_enabled', 'max_tokens', 'daily_request_limit', 'monthly_budget_usd', 'name', 'role', 'description', 'instructions', 'model_tier', 'model', 'temperature', 'tools', 'memory_policy', 'status', 'daily_token_budget']),
            'change_note' => $note,
            'changed_by' => $by?->id,
            'created_at' => now(),
        ]);
    }
}
