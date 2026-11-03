<?php

namespace App\Services\Agents;

use App\Models\AiAgent;
use App\Models\AiMemory;
use App\Models\AiTask;
use App\Models\ConversationMessage;
use Illuminate\Support\Collection;

/**
 * Permission-aware memory for one agent working on one task.
 *
 * Reads: this task's notes, the linked conversation (recent messages only),
 * the agent's own approved long-term notes, and approved shared knowledge,
 * each only where the agent's memory policy allows it. Never another
 * agent's private notes.
 *
 * Writes: task notes are free; long-term and shared notes are only
 * *proposed* (status "proposed") and need an admin to approve them, so an
 * agent can never silently change company policy or approved facts.
 */
class MemoryStore
{
    public function __construct(protected AiAgent $agent, protected AiTask $task) {}

    public function remember(string $key, string $content, string $source = 'task'): AiMemory
    {
        return AiMemory::create([
            'agent_id' => $this->agent->id, 'scope' => 'task', 'reference' => $this->task->uuid, 'key' => mb_substr($key, 0, 120),
            'content' => OutputGuard::text($content), 'source' => $source, 'status' => 'approved', 'expires_at' => now()->addDays(30),
        ]);
    }

    /** Propose a long-term (own) or shared note; it is not used until approved. */
    public function propose(string $key, string $content, bool $shared = false): AiMemory
    {
        $current = AiMemory::where('scope', $shared ? 'shared' : 'agent')->where('key', $key)
            ->when(! $shared, fn ($q) => $q->where('agent_id', $this->agent->id))->where('status', 'approved')->max('version');

        return AiMemory::create([
            'agent_id' => $shared ? null : $this->agent->id, 'scope' => $shared ? 'shared' : 'agent', 'key' => mb_substr($key, 0, 120),
            'content' => OutputGuard::text($content), 'source' => 'Proposed by '.$this->agent->name.' (task '.$this->task->uuid.')',
            'status' => 'proposed', 'version' => ($current ?? 0) + 1,
        ]);
    }

    /** @return array{task: Collection, agent: Collection, shared: Collection, conversation: Collection} */
    public function context(): array
    {
        $live = fn ($q) => $q->where('status', 'approved')->where(fn ($w) => $w->whereNull('expires_at')->orWhere('expires_at', '>', now()));

        return [
            'task' => AiMemory::where('scope', 'task')->where('reference', $this->task->uuid)->where($live)->get(['key', 'content', 'source', 'created_at']),
            'agent' => ($this->agent->memory_policy['agent'] ?? false) ? AiMemory::where('scope', 'agent')->where('agent_id', $this->agent->id)->where($live)->get(['key', 'content', 'source', 'version']) : collect(),
            'shared' => ($this->agent->memory_policy['shared'] ?? false) ? AiMemory::where('scope', 'shared')->whereNull('agent_id')->where($live)->get(['key', 'content', 'source', 'version']) : collect(),
            'conversation' => $this->conversation(),
        ];
    }

    /** Last few messages of the linked conversation (the channel already holds consent and retention). */
    protected function conversation(): Collection
    {
        $id = $this->task->input['conversation_id'] ?? null;
        if (! $id || ! ($this->agent->memory_policy['conversation'] ?? false)) {
            return collect();
        }

        return ConversationMessage::where('conversation_id', $id)->latest('id')->limit(6)->get(['role', 'body', 'created_at'])
            ->reverse()->map(fn ($m) => ['role' => $m->role, 'text' => OutputGuard::text(mb_substr((string) $m->body, 0, 300))])->values();
    }
}
