<?php

namespace App\Console\Commands;

use App\Models\Conversation;
use App\Services\AuditLogger;
use Illuminate\Console\Command;

/**
 * Applies the conversation retention policy (config meezan.ai.retention_days,
 * env MEEZAN_AI_RETENTION_DAYS). With no policy set (0) nothing is deleted.
 * Conversations that produced a saved requirement or a lead are always kept.
 */
class PruneConversations extends Command
{
    protected $signature = 'meezan:prune-conversations {--dry-run : Count without deleting}';

    protected $description = 'Delete old assistant conversations according to the retention policy';

    public function handle(): int
    {
        $days = (int) config('meezan.ai.retention_days', 0);
        if ($days <= 0) {
            $this->info('No retention policy set (MEEZAN_AI_RETENTION_DAYS=0): nothing deleted.');

            return self::SUCCESS;
        }

        $query = Conversation::query()
            ->where('last_message_at', '<', now()->subDays($days))
            ->whereNull('buyer_requirement_id')
            ->whereNull('lead_id');
        $count = (clone $query)->count();

        if ($this->option('dry-run')) {
            $this->info("{$count} conversation(s) older than {$days} days would be deleted.");

            return self::SUCCESS;
        }

        // Messages go with their conversation (foreign key cascade).
        $query->chunkById(200, fn ($chunk) => $chunk->each->delete());
        AuditLogger::record('conversations.pruned', null, ['count' => $count, 'retention_days' => $days]);
        $this->info("Deleted {$count} conversation(s) older than {$days} days.");

        return self::SUCCESS;
    }
}
