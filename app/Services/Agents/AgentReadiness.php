<?php

namespace App\Services\Agents;

use App\Models\AiAgent;
use App\Models\AiKnowledgeDocument;
use App\Models\AiTask;
use App\Models\AiToolCall;
use App\Models\AiUsageRecord;
use Illuminate\Support\Collection;

/**
 * What an agent can really do right now, worked out from evidence rather
 * than from its database status:
 *
 *   verified             active, and a task completed with no failed tool
 *                        since its configuration last changed (local data)
 *   untested             active, but not run since its configuration changed
 *   waiting_credentials  has nothing to work from until something is configured
 *   disabled             switched off or in maintenance
 *   error                marked as error, or its latest task failed
 *   in_development       no workflow exists
 *
 * "limits" names capabilities that stay off until a connector is configured.
 * None of these states means a language model was used: see "model".
 */
class AgentReadiness
{
    public const LABELS = [
        'verified' => 'Active and verified',
        'untested' => 'Configured, not tested',
        'waiting_credentials' => 'Waiting for credentials',
        'disabled' => 'Disabled',
        'error' => 'Error',
        'in_development' => 'In development',
    ];

    public function __construct(protected ConnectorStatus $connectors, protected AiProvider $provider) {}

    /** @return Collection<string, array> keyed by agent slug */
    public function all(?Collection $agents = null): Collection
    {
        $agents ??= AiAgent::orderBy('id')->get();
        $connectors = $this->connectors->all();
        $live = config('meezan.agents.use_llm') && $this->provider->available();
        $labels = $this->provider->labels();
        $since = now()->subDays(30);

        $latest = AiTask::whereIn('id', AiTask::selectRaw('max(id)')->whereIn('status', ['completed', 'awaiting_approval', 'failed'])->groupBy('agent_id'))->get(['id', 'agent_id', 'status', 'error', 'completed_at'])->keyBy('agent_id');
        $counts = AiTask::toBase()->selectRaw("agent_id, sum(case when status = 'completed' then 1 else 0 end) as completed, sum(case when status = 'failed' then 1 else 0 end) as failed, max(completed_at) as last_run")->groupBy('agent_id')->get()->keyBy('agent_id');
        $tokens = AiUsageRecord::where('created_at', '>=', $since)->toBase()->selectRaw('agent_id, sum(tokens_in + tokens_out) as tokens, sum(cost_usd) as cost')->groupBy('agent_id')->get()->keyBy('agent_id');

        return $agents->mapWithKeys(fn (AiAgent $a) => [$a->slug => $this->state($a, $connectors, $live, $labels, $latest[$a->id] ?? null, $counts[$a->id] ?? null, $tokens[$a->id] ?? null)]);
    }

    protected function state(AiAgent $agent, array $connectors, bool $live, array $labels, ?AiTask $latest, ?object $counts, ?object $tokens): array
    {
        $needs = AgentCatalog::needs()[$agent->slug] ?? [];
        $limits = collect($needs['limits'] ?? [])->filter(fn ($what, $key) => in_array($connectors[$key]['status'] ?? 'not_configured', ['not_configured', 'requires_setup'], true))
            ->map(fn ($what, $key) => $what.' ('.($connectors[$key]['label'] ?? $key).')')->values()->all();

        $state = match (true) {
            ! AgentCatalog::worker($agent->slug) => 'in_development',
            in_array($agent->status, ['disabled', 'maintenance'], true) => 'disabled',
            $agent->status === 'error' || $latest?->status === 'failed' => 'error',
            $agent->status === 'configuration_required' || ! $this->requirementsMet($needs['requires'] ?? [], $connectors) => 'waiting_credentials',
            $this->verifiedSinceLastChange($agent) => 'verified',
            default => 'untested',
        };

        return [
            'state' => $state,
            'label' => self::LABELS[$state],
            'limits' => $limits,
            'model' => $this->modelState($agent, $live, $labels),
            'completed' => (int) ($counts->completed ?? 0),
            'failed' => (int) ($counts->failed ?? 0),
            'last_run' => $counts->last_run ?? null,
            'last_error' => $latest?->status === 'failed' ? $latest->error : null,
            'tokens_30d' => (int) ($tokens->tokens ?? 0),
            'cost_30d' => isset($tokens->cost) ? (float) $tokens->cost : null,
        ];
    }

    /** Which provider and model this agent would really use now, or why it runs in rules mode. */
    protected function modelState(AiAgent $agent, bool $live, array $labels): string
    {
        if (! $live) {
            return 'Rules mode: no language model is configured';
        }
        $route = $this->provider->route($agent, (string) $agent->model_tier);
        $primary = $route['primary'];
        if (! $this->provider->config($primary['provider'])->ready()) {
            return $route['fallback'] && $this->provider->config($route['secondary']['provider'])->ready()
                ? "{$labels[$primary['provider']]} is not available: fallback to {$labels[$route['secondary']['provider']]}"
                : "Rules mode: {$labels[$primary['provider']]} has no API key or is disabled";
        }

        if (filled($primary['model']) && $this->provider->row($primary['provider'])?->connection_status === 'error') {
            return "Rules mode for now: {$labels[$primary['provider']]} failed its last connection test";
        }

        return blank($primary['model']) ? "Rules mode: no model is selected for {$labels[$primary['provider']]}" : "{$labels[$primary['provider']]}: {$primary['model']}";
    }

    protected function requirementsMet(array $requires, array $connectors): bool
    {
        if ($requires === []) {
            return true;
        }
        foreach ($requires as $need) {
            if (str_starts_with($need, 'knowledge:')) {
                if (AiKnowledgeDocument::approved()->where('category', substr($need, 10))->exists()) {
                    return true;
                }
            } elseif (in_array($connectors[$need]['status'] ?? 'not_configured', ['configured', 'verified'], true)) {
                return true;
            }
        }

        return false;
    }

    protected function verifiedSinceLastChange(AiAgent $agent): bool
    {
        $task = AiTask::where('agent_id', $agent->id)->where('status', 'completed')->where('tool_calls', '>', 0)
            ->where('completed_at', '>=', $agent->updated_at)->latest('id')->first(['id']);

        return $task && ! AiToolCall::where('task_id', $task->id)->where('status', 'failed')->exists();
    }
}
