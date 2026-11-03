<?php

namespace App\Services\Agents;

use Closure;

/**
 * One tool an agent may call. Risk decides what happens when it is called:
 *
 *   read        runs within the agent's permissions (reports, search)
 *   low         runs within permissions (drafts, internal tasks, tickets)
 *   approval    never runs directly: creates an approval request for staff
 *   prohibited  never runs, whoever asks (refunds, transfers, deletions, shell, deploy)
 */
final class ToolDefinition
{
    public const RISKS = ['read' => 'Read only', 'low' => 'Low-risk internal', 'approval' => 'Approval required', 'prohibited' => 'Prohibited'];

    /**
     * @param  array<string, mixed>  $rules  Laravel validation rules for the input
     * @param  Closure(array, AgentContext): array  $handler  returns structured output (no secrets, no private contact data)
     * @param  ?Closure(array): string  $describe  human description of an approval request
     */
    public function __construct(
        public readonly string $name,
        public readonly string $description,
        public readonly string $risk,
        public readonly array $rules,
        public readonly ?Closure $handler = null,
        public readonly string $category = 'general',
        public readonly ?Closure $describe = null,
    ) {}
}
