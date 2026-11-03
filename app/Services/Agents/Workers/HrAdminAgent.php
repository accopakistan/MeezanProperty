<?php

namespace App\Services\Agents\Workers;

use App\Services\Agents\AgentContext;
use App\Services\Agents\Report;

/** HR & Administration: staff directory (names/roles only), workload, internal tasks, procedures. No employment decisions. */
class HrAdminAgent extends Agent
{
    public static function intents(): array
    {
        return [
            'workload' => ['staff', 'workload', 'directory', 'team', 'who is'],
            'task' => ['create task', 'assign', 'reminder', 'remind'],
            'procedure' => ['procedure', 'process', 'policy', 'meeting'],
            'decision' => ['fire', 'hire', 'salary', 'promotion', 'disciplin', 'compensation'],
        ];
    }

    public function handle(AgentContext $ctx, string $intent): Report
    {
        return match ($intent) {
            'task' => $this->task($ctx),
            'procedure' => $this->procedure($ctx),
            'decision' => $this->decline(),
            default => $this->workload($ctx),
        };
    }

    protected function workload(AgentContext $ctx): Report
    {
        $r = new Report('Staff workload');
        $d = $this->need($ctx, $r, 'staff.directory');
        if ($d) {
            $r->table('Staff', ['Name', 'Role', 'Status', 'Open tasks'], collect($d['staff'])->map(fn ($s) => [$s['name'], $s['role'], $s['status'], $s['open_tasks']])->all(), 'No staff accounts.');
            $r->finding("Unassigned open tasks: {$d['unassigned_open_tasks']}.");
            $r->limitation($d['note']);
        }

        return $r;
    }

    protected function task(AgentContext $ctx): Report
    {
        $r = new Report('Internal task');
        $t = $ctx->tool('staff.create_task', ['title' => mb_substr($ctx->task->input['title'] ?? $ctx->task->request, 0, 200), 'description' => mb_substr($ctx->task->request, 0, 2000), 'category' => 'admin', 'due_in_days' => $ctx->task->input['due_in_days'] ?? 3]);
        $r->source('staff.create_task');
        $r->finding($t['ok'] ? 'Created staff task #'.$t['data']['staff_task_id'].' (unassigned; staff choose who takes it).' : 'Not created: '.$t['error']);

        return $r;
    }

    protected function procedure(AgentContext $ctx): Report
    {
        $r = new Report('Procedures');
        $k = $this->need($ctx, $r, 'knowledge.search', ['query' => $ctx->task->request, 'category' => 'procedures', 'limit' => 3]);
        if (! ($k['found'] ?? 0)) {
            $r->missing('No approved procedure document matches this request.');
        }
        foreach ($k['documents'] ?? [] as $doc) {
            $r->section($doc['title'], [mb_substr($doc['excerpt'], 0, 600), 'Source: '.$doc['source']]);
        }

        return $r;
    }

    protected function decline(): Report
    {
        return (new Report('Employment decisions'))->finding('I do not make or recommend hiring, dismissal, disciplinary or pay decisions, and I hold no personal employee records. Please handle this directly with management.');
    }
}
