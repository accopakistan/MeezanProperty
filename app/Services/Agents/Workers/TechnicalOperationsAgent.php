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
            'integrations' => ['integration', 'connector', 'api health'],
            'remediate' => ['fix it', 'restart', 'deploy', 'run command', 'delete', 'clear cache', 'migrate'],
        ];
    }

    public function handle(AgentContext $ctx, string $intent): Report
    {
        return match ($intent) {
            'integrations' => $this->integrations($ctx),
            'remediate' => $this->remediate($ctx),
            default => $this->health($ctx),
        };
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
