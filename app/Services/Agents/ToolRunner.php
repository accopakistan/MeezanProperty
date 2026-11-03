<?php

namespace App\Services\Agents;

use App\Models\AiApproval;
use App\Models\AiToolCall;
use Illuminate\Support\Facades\Validator;
use Throwable;

/**
 * The only way an agent touches application data. For every call:
 * registered tool → not prohibited → assigned to this agent → valid input →
 * within the tool-call and time limits → run (or request approval) → audit.
 * Failures are returned to the agent, never thrown into the application.
 */
class ToolRunner
{
    public function __construct(protected ToolRegistry $registry) {}

    /** @return array{ok: bool, status: string, data: mixed, error: ?string} */
    public function call(AgentContext $ctx, string $name, array $input = []): array
    {
        $started = microtime(true);
        $tool = $this->registry->get($name);
        $limits = config('meezan.agents.limits');

        $result = match (true) {
            $tool === null => $this->fail('invalid', "Unknown tool \"{$name}\"."),
            $tool->risk === 'prohibited' => $this->fail('prohibited', 'This action is prohibited for AI agents and needs separate human authorisation outside the AI system.'),
            ! $ctx->agent->canUse($name) => $this->fail('denied', "The {$ctx->agent->name} agent is not permitted to use \"{$name}\"."),
            $ctx->toolCalls >= $limits['max_tool_calls'] => $this->fail('limit', 'Tool-call limit for this task reached ('.$limits['max_tool_calls'].').'),
            $ctx->elapsed() > $limits['max_seconds'] => $this->fail('limit', 'Time limit for this task reached ('.$limits['max_seconds'].' s).'),
            default => null,
        };

        if ($result === null) {
            $ctx->toolCalls++;
            $validator = Validator::make($input, $tool->rules);
            if ($validator->fails()) {
                $result = $this->fail('invalid', 'Invalid input: '.implode(' ', $validator->errors()->all()));
            } elseif ($tool->risk === 'approval') {
                $result = $this->requestApproval($ctx, $tool, $validator->validated());
            } else {
                try {
                    $result = ['ok' => true, 'status' => 'ok', 'data' => OutputGuard::clean(($tool->handler)($validator->validated(), $ctx)), 'error' => null];
                } catch (Throwable $e) {
                    report($e);
                    $result = $this->fail('failed', 'The tool failed: '.class_basename($e).'.');
                }
            }
        }

        AiToolCall::create([
            'task_id' => $ctx->task->id,
            'agent_id' => $ctx->agent->id,
            'tool' => mb_substr($name, 0, 60),
            'risk' => $tool?->risk ?? 'unknown',
            'input' => OutputGuard::clean($input),
            'status' => $result['status'],
            'summary' => $this->summarise($result['data']),
            'error' => $result['error'],
            'duration_ms' => (int) round((microtime(true) - $started) * 1000),
            'created_at' => now(),
        ]);
        $ctx->trace[] = ['tool' => $name, 'status' => $result['status']];

        return $result;
    }

    protected function requestApproval(AgentContext $ctx, ToolDefinition $tool, array $input): array
    {
        $approval = AiApproval::create([
            'task_id' => $ctx->task->id,
            'agent_id' => $ctx->agent->id,
            'action' => $tool->name,
            'risk' => 'approval',
            'description' => $tool->describe ? ($tool->describe)($input) : $tool->description,
            'payload' => $input,
            'evidence' => ['request' => mb_substr($ctx->task->request, 0, 500), 'agent' => $ctx->agent->slug],
            'status' => 'pending',
            'expires_at' => now()->addDays((int) config('meezan.agents.limits.approval_expiry_days', 14)),
        ]);
        $ctx->approvalRequested = true;
        AgentAlerts::approvalWaiting($approval, $ctx->agent->name);

        return ['ok' => true, 'status' => 'approval_requested', 'data' => ['approval_id' => $approval->id, 'message' => 'Waiting for staff approval; nothing has been done yet.'], 'error' => null];
    }

    protected function fail(string $status, string $error): array
    {
        return ['ok' => false, 'status' => $status, 'data' => null, 'error' => $error];
    }

    /** A small shape summary for the audit log (counts, not full data). */
    protected function summarise(mixed $data): ?array
    {
        if (! is_array($data)) {
            return $data === null ? null : ['value' => mb_substr((string) $data, 0, 120)];
        }

        return collect($data)->map(fn ($v) => is_array($v) ? ['items' => count($v)] : (is_scalar($v) || $v === null ? $v : gettype($v)))->take(20)->all();
    }
}
