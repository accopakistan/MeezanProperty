<?php

namespace App\Console\Commands;

use App\Models\AiMemory;
use App\Models\AiTask;
use App\Services\Agents\ApprovalExecutor;
use App\Services\Agents\CentralAiOrchestrator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Scheduled agent work. Each run has a dated idempotency key, so running a
 * job twice in the same period does not create a second task.
 *   health        Technical Operations: daily system health check
 *   subscriptions Subscription Manager: daily expiry and past-due review
 *   ceo-weekly    AI CEO: weekly business report
 *   maintenance   expire approvals and memories, fail tasks stuck in "running",
 *                 and ask Technical Operations to look when work has failed
 */
class AgentsScheduled extends Command
{
    protected $signature = 'meezan:agents {job : health|subscriptions|ceo-weekly|maintenance}';

    protected $description = 'Run a scheduled AI agent job through the Central AI Brain';

    public function handle(CentralAiOrchestrator $brain, ApprovalExecutor $approvals): int
    {
        $job = $this->argument('job');
        $runs = [
            'health' => ['technical-operations', 'health', 'Daily system health check', 'agents-health-'.now()->toDateString()],
            'subscriptions' => ['subscription-manager', 'expiring', 'Daily subscription expiry and past-due review', 'agents-subscriptions-'.now()->toDateString()],
            'ceo-weekly' => ['ceo', 'business_report', 'Weekly business performance report', 'agents-ceo-'.now()->format('o-\WW')],
        ];

        if ($job === 'maintenance') {
            $expired = $approvals->expire();
            $memories = AiMemory::whereNotNull('expires_at')->where('expires_at', '<=', now())->where('status', 'approved')->update(['status' => 'archived']);
            $stuck = AiTask::where('status', 'running')->where('started_at', '<', now()->subMinutes(30))
                ->update(['status' => 'failed', 'error' => 'Stopped: the task did not finish within 30 minutes.', 'completed_at' => now()]);
            $this->info("Approvals expired: {$expired}; memories expired: {$memories}; stuck tasks failed: {$stuck}.");

            $since = now()->subHour();
            if (DB::table('failed_jobs')->where('failed_at', '>=', $since)->exists() || AiTask::where('status', 'failed')->where('updated_at', '>=', $since)->whereKeyNot(0)->where('source', '!=', 'event')->exists()) {
                $brain->submit('System errors detected in the last hour: review application health', 'technical-operations', null, 'event', [], 'event-system-errors-'.now()->format('Y-m-d-H'), queue: true, intent: 'health');
                $this->info('Failures found: Technical Operations task queued.');
            }

            return self::SUCCESS;
        }
        if (! isset($runs[$job])) {
            $this->error('Unknown job. Use: health, subscriptions, ceo-weekly or maintenance.');

            return self::INVALID;
        }

        [$agent, $intent, $request, $key] = $runs[$job];
        $task = $brain->submit($request, $agent, null, 'schedule', [], $key, intent: $intent);
        $this->info("{$request}: task {$task->uuid} {$task->status}.");

        return $task->status === 'failed' ? self::FAILURE : self::SUCCESS;
    }
}
