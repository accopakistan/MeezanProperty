<?php

namespace App\Services\Agents\Workers;

use App\Services\Agents\AgentContext;
use App\Services\Agents\Report;

/** Technical Operations: read-only diagnostics (errors, queues, integrations, agent failures). No commands, edits or deployments. */
class TechnicalOperationsAgent extends Agent
{
    public static function intents(): array
    {
        return [
            'health' => ['health', 'error', 'log', 'queue', 'failed job', 'technical', 'broken', 'performance'],
            'collection' => ['collection job', 'data collection', 'collection fail', 'source collection'],
            'integrations' => ['integration', 'connector', 'api health'],
            'remediate' => ['fix it', 'restart', 'deploy', 'run command', 'delete', 'clear cache', 'migrate'],
            'customer_issue' => [], // chosen only by the customer routes, never by keywords
        ];
    }

    public function handle(AgentContext $ctx, string $intent): Report
    {
        return match ($intent) {
            'integrations' => $this->integrations($ctx),
            'collection' => $this->collection($ctx),
            'remediate' => $this->remediate($ctx),
            'customer_issue' => $this->customerIssue($ctx),
            default => $this->health($ctx),
        };
    }

    /** A customer reports a problem with the site. Shares no diagnostics; tells staff. */
    protected function customerIssue(AgentContext $ctx): Report
    {
        $r = new Report('Technical issue');
        $r->verbatim = true;
        $ticket = $this->ticketForCustomer($ctx, $r, 'technical', 'normal', 'Technical issue reported by a customer');
        $reference = $ticket ? " (reference {$ticket})" : '';
        $r->finding($this->urdu($ctx->task->request)
            ? "Shukriya. Maine yeh masla hamari team ko bhej diya hai{$reference}. Aap property ki talash jari rakh sakte hain, ya team se baat ke liye 'human' likhein."
            : "Thank you. I've passed this to our team{$reference}. You can keep searching here, or reply 'human' to reach our team.");

        return $r;
    }

    /** Whether official data collection is running, from its own run records. */
    protected function collection(AgentContext $ctx): Report
    {
        $r = new Report('Investment data collection');
        $c = $this->need($ctx, $r, 'investment_index.coverage');
        if ($c) {
            $sources = collect($c['sources']);
            $r->table('Sources', ['Source', 'Collected automatically', 'Last run', 'Last success'], $sources->map(fn ($s) => [$s['name'], $s['automatically_collected'] ? 'yes' : 'no', $s['last_run_status'] ?? 'never run', $s['last_success'] ?? 'never'])->all());
            $failing = $sources->whereIn('last_run_status', ['failed', 'blocked', 'partial']);
            $r->finding($sources->where('automatically_collected', true)->count().' sources are collected automatically; '.$failing->count().' had a problem on their last run.');
            $failing->isNotEmpty() && $r->recommend('Check Admin → Investment Index → Official sources: '.$failing->pluck('name')->implode(', ').'.');
        }

        return $r;
    }

    protected function health(AgentContext $ctx): Report
    {
        $r = new Report('Application health');
        $h = $this->need($ctx, $r, 'system.health');
        if ($h) {
            $r->finding("Environment {$h['environment']}, debug ".($h['debug_mode'] ? 'on' : 'off').", queue driver {$h['queue_driver']}; queued jobs {$h['queued_jobs']}, failed jobs {$h['failed_jobs']}, failed agent tasks (24 h) {$h['failed_agent_tasks_24h']}.");
            $r->finding('Pending migrations: '.($h['pending_migrations'] ?? 'unknown').'; oldest queued job: '.($h['oldest_queued_job_hours'] !== null ? $h['oldest_queued_job_hours'].' hours' : 'none').'.');
            $r->table('Queues', ['Queue', 'Waiting', 'Failed'], collect($h['queues'])->map(fn ($q, $n) => [$n, $q['waiting'], $q['failed']])->values()->all(), 'All queues are empty.');
            $r->table('Scheduled agent jobs: last run', ['Job', 'Last run', 'Status'], collect($h['scheduled_agent_jobs'])->map(fn ($j) => [$j['job'], $j['last_run'], $j['status']])->all(), 'No scheduled agent job has run yet.');
            $r->table('Recent log errors (redacted)', ['Level', 'Message', 'Last seen', 'Count'], collect($h['recent_log_errors'])->map(fn ($e) => [$e['level'], $e['message'], $e['last_seen'], $e['count']])->all(), 'No recent errors in the log tail.');
            $h['failed_jobs'] && $r->recommend('Inspect failed jobs (php artisan queue:failed) and retry them after fixing the cause.');
            ($h['queued_jobs'] > 50 || ($h['oldest_queued_job_hours'] ?? 0) >= 1) && $r->recommend('Jobs are waiting: make sure a worker is running (php artisan queue:work --queue=whatsapp,default,agents).');
            ($h['pending_migrations'] ?? 0) > 0 && $r->recommend('Run the pending migrations during a planned update (staff action).');
            $h['debug_mode'] && $h['environment'] === 'production' && $r->recommend('Turn APP_DEBUG off in production.');
            $r->limitation($h['note']);
        }

        return $r;
    }

    protected function integrations(AgentContext $ctx): Report
    {
        $r = new Report('Integration status');
        $c = $this->need($ctx, $r, 'connectors.status');
        $c && $r->table('Connectors', ['Integration', 'Status', 'Detail'], collect($c)->map(fn ($x) => [$x['label'], str_replace('_', ' ', $x['status']), $x['detail']])->values()->all());

        return $r;
    }

    protected function remediate(AgentContext $ctx): Report
    {
        $r = new Report('Remediation request');
        $t = $ctx->tool('system.shell');
        $r->source('system.shell');
        $r->finding('I work in read-only diagnostic mode and cannot run commands, change configuration, delete files or deploy: '.$t['error']);
        $task = $ctx->tool('staff.create_task', ['title' => 'Technical request from AI Command Center', 'description' => mb_substr($ctx->task->request, 0, 1500), 'category' => 'technical']);
        $r->source('staff.create_task');
        $task['ok'] && $r->recommend('Passed to staff as task #'.$task['data']['staff_task_id'].'.');

        return $r;
    }
}
