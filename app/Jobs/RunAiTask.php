<?php

namespace App\Jobs;

use App\Models\AiTask;
use App\Services\Agents\CentralAiOrchestrator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Runs one agent task on the queue. The orchestrator claims the task
 * atomically, so a duplicate job does nothing; retries are bounded by the
 * task's attempt counter, not by the queue. The unique lock ends when
 * processing starts, because a retry is dispatched from inside the job.
 */
class RunAiTask implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(public int $taskId) {}

    public function uniqueId(): string
    {
        return 'ai-task-'.$this->taskId;
    }

    public function handle(CentralAiOrchestrator $orchestrator): void
    {
        $task = AiTask::find($this->taskId);
        $task && $task->status === 'pending' && $orchestrator->execute($task);
    }
}
