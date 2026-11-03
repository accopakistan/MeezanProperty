<?php

namespace App\Services\Agents;

use App\Models\AiAgent;
use App\Models\AiProviderSetting;
use App\Models\AiTask;
use App\Models\AiUsageRecord;

/**
 * Limits checked before model calls, using recorded provider usage: tokens per
 * task (a task and all its subtasks count together), per agent per day, overall
 * per day; requests per day; monthly spend per agent, per provider and in total;
 * and the emergency stop. A monthly budget also counts the most the next request
 * could cost, so it cannot be overrun, and a model with no price cannot spend
 * against a budget. A stop is reported to administrators.
 */
class BudgetGuard
{
    /** @return array{0: bool, 1: ?string} allowed, reason */
    public function check(AiAgent $agent, AiTask $task): array
    {
        $l = config('meezan.agents.limits');

        $spent = AiTask::where('root_id', $task->root_id ?? $task->id)->selectRaw('coalesce(sum(tokens_in + tokens_out), 0) as t')->value('t');
        if ((int) $spent >= $l['max_tokens_per_task']) {
            return $this->stop('Task token limit reached ('.$l['max_tokens_per_task'].').');
        }
        $agentLimit = $agent->daily_token_budget ?: $l['daily_tokens_per_agent'];
        if ($this->tokensToday(AiUsageRecord::where('agent_id', $agent->id)) >= $agentLimit) {
            return $this->stop("{$agent->name} daily token budget reached ({$agentLimit}).");
        }

        return $this->overall();
    }

    /** The overall limits alone (routing calls have no agent or task yet). */
    public function overall(): array
    {
        $l = config('meezan.agents.limits');
        if (AiProvider::controls()['emergency_stop']) {
            return [false, 'AI requests are stopped by an administrator (emergency stop).'];
        }
        if ($this->tokensToday(AiUsageRecord::query()) >= $l['daily_tokens_total']) {
            return $this->stop('Overall daily token budget reached ('.$l['daily_tokens_total'].').');
        }
        if ($l['daily_cost_total_usd'] !== null && (float) AiUsageRecord::where('created_at', '>=', now()->startOfDay())->sum('cost_usd') >= $l['daily_cost_total_usd']) {
            return $this->stop('Overall daily cost budget reached (USD '.$l['daily_cost_total_usd'].').');
        }

        return [true, null];
    }

    /**
     * The last check before any paid request, whoever makes it.
     *
     * @return array{0: bool, 1: ?string}
     */
    public function beforeRequest(string $provider, string $model, ?AiAgent $agent, array $messages, int $maxTokens): array
    {
        $controls = AiProvider::controls();
        if ($controls['emergency_stop']) {
            return [false, 'AI requests are stopped by an administrator (emergency stop).'];
        }

        $today = fn ($q) => $q->where('created_at', '>=', now()->startOfDay())->count();
        if ($controls['daily_requests_total'] && $today(AiUsageRecord::query()) >= $controls['daily_requests_total']) {
            return $this->stop('Daily AI request limit reached ('.$controls['daily_requests_total'].').');
        }
        if ($agent?->daily_request_limit && $today(AiUsageRecord::where('agent_id', $agent->id)) >= $agent->daily_request_limit) {
            return $this->stop("{$agent->name} daily request limit reached ({$agent->daily_request_limit}).");
        }

        $budgets = array_filter([
            'The monthly AI budget' => [$controls['monthly_total_usd'], AiUsageRecord::query()],
            'The '.$provider.' monthly budget' => [AiProviderSetting::where('provider', $provider)->value('monthly_budget_usd'), AiUsageRecord::where('provider', $provider)],
            'The '.($agent?->name ?? 'agent').' monthly budget' => [$agent?->monthly_budget_usd, AiUsageRecord::where('agent_id', $agent?->id)],
        ], fn ($b) => $b[0] !== null);
        if ($budgets !== []) {
            $characters = collect($messages)->sum(fn ($m) => mb_strlen((string) ($m['content'] ?? '')));
            $mostItCouldCost = AiProvider::costOf($model, (int) ceil($characters / 2), $maxTokens);
            if ($mostItCouldCost === null) {
                return $this->stop("A monthly budget is set, but {$model} has no price, so spending cannot be controlled. Enter its price in Usage & Cost.");
            }
            foreach ($budgets as $name => [$limit, $query]) {
                if ((float) $query->where('created_at', '>=', now()->startOfMonth())->sum('cost_usd') + $mostItCouldCost > (float) $limit) {
                    return $this->stop("{$name} is used up (USD {$limit}).");
                }
            }
        }

        return [true, null];
    }

    protected function tokensToday($query): int
    {
        return (int) $query->where('created_at', '>=', now()->startOfDay())->selectRaw('coalesce(sum(tokens_in + tokens_out), 0) as t')->value('t');
    }

    protected function stop(string $why): array
    {
        AgentAlerts::budgetReached($why);

        return [false, $why];
    }
}
