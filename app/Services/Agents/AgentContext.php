<?php

namespace App\Services\Agents;

use App\Models\AiAgent;
use App\Models\AiTask;
use App\Models\User;

/**
 * Everything an agent needs while working on one task: the task, the agent,
 * who asked, the counters the limits are checked against, and access to
 * tools, memory and delegation through the central orchestrator only.
 */
class AgentContext
{
    public int $toolCalls = 0;

    public float $startedAt;

    /** @var list<array{tool: string, status: string}> */
    public array $trace = [];

    public bool $approvalRequested = false;

    public function __construct(
        public readonly AiTask $task,
        public readonly AiAgent $agent,
        public readonly ?User $user,
        protected ToolRunner $tools,
        protected CentralAiOrchestrator $orchestrator,
        protected MemoryStore $memory,
    ) {
        $this->startedAt = microtime(true);
    }

    /** This task answers a customer on a public channel, not staff. */
    public function forCustomer(): bool
    {
        return ($this->task->input['audience'] ?? null) === 'customer';
    }

    /** @return array{ok: bool, status: string, data: mixed, error: ?string} */
    public function tool(string $name, array $input = []): array
    {
        return $this->tools->call($this, $name, $input);
    }

    /** Successful tool data, or null (the failure is recorded in the trace and the task). */
    public function data(string $name, array $input = []): mixed
    {
        $r = $this->tool($name, $input);

        return $r['ok'] ? $r['data'] : null;
    }

    /**
     * Ask another agent through the central orchestrator (depth- and budget-limited).
     *
     * @return array{ok: bool, report: ?array, error: ?string, task: ?string}
     */
    public function delegate(string $agentSlug, string $request, array $input = []): array
    {
        return $this->orchestrator->delegate($this, $agentSlug, $request, $input);
    }

    public function memory(): MemoryStore
    {
        return $this->memory;
    }

    public function elapsed(): float
    {
        return microtime(true) - $this->startedAt;
    }
}
