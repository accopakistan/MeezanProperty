<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiAgent;
use App\Models\AiApproval;
use App\Models\AiDraft;
use App\Models\AiKnowledgeDocument;
use App\Models\AiMemory;
use App\Models\AiProviderSetting;
use App\Models\AiTask;
use App\Models\AiToolCall;
use App\Models\AiUsageRecord;
use App\Models\AppSetting;
use App\Models\StaffTask;
use App\Models\SupportTicket;
use App\Services\Agents\AgentCatalog;
use App\Services\Agents\AgentReadiness;
use App\Services\Agents\AiProvider;
use App\Services\Agents\ApprovalExecutor;
use App\Services\Agents\CentralAiOrchestrator;
use App\Services\Agents\ConnectorStatus;
use App\Services\Agents\OutputGuard;
use App\Services\Agents\ToolDefinition;
use App\Services\Agents\ToolRegistry;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * AI Command Center: the agent workforce, tasks, approvals, usage and cost,
 * memory and knowledge, and connector status. Admin only (route middleware).
 * Nothing here shows API keys; connector screens show status only.
 */
class AiCommandCenterController extends Controller
{
    public function overview(ConnectorStatus $connectors, AiProvider $provider, AgentReadiness $readiness): View
    {
        $today = now()->startOfDay();
        $agents = AiAgent::orderBy('id')->get();

        return view('admin.ai.overview', [
            'agents' => $agents,
            'readiness' => $readiness->all($agents),
            'agentJobsWaiting' => DB::table('jobs')->where('queue', config('meezan.agents.queue'))->count(),
            'openTickets' => SupportTicket::whereIn('status', ['open', 'in_progress'])->count(),
            'openStaffTasks' => StaffTask::where('status', 'open')->count(),
            'tasksByStatus' => AiTask::where('created_at', '>=', now()->subDays(7))->toBase()->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status'),
            'pendingApprovals' => AiApproval::where('status', 'pending')->count(),
            'recentTasks' => AiTask::with('agent')->whereNull('parent_id')->latest('id')->limit(10)->get(),
            'failed' => AiTask::with('agent')->where('status', 'failed')->where('created_at', '>=', now()->subDays(7))->latest('id')->limit(5)->get(),
            'tokensToday' => (int) AiUsageRecord::where('created_at', '>=', $today)->selectRaw('coalesce(sum(tokens_in + tokens_out), 0) as t')->value('t'),
            'costToday' => AiUsageRecord::where('created_at', '>=', $today)->whereNotNull('cost_usd')->sum('cost_usd'),
            'pricingSet' => AiProvider::pricing() !== [],
            'llm' => $provider->available() && config('meezan.agents.use_llm'),
            'connectors' => $connectors->all(),
        ]);
    }

    public function agents(AgentReadiness $readiness): View
    {
        $agents = AiAgent::orderBy('id')->get();

        return view('admin.ai.agents', ['agents' => $agents, 'readiness' => $readiness->all($agents)]);
    }

    public function agent(AiAgent $agent, ToolRegistry $registry, AgentReadiness $readiness, AiProvider $provider): View
    {
        return view('admin.ai.agent', [
            'agent' => $agent,
            'ready' => $readiness->all(collect([$agent]))[$agent->slug],
            'providerLabels' => $provider->labels(),
            'modelWarning' => $provider->modelWarning((string) $agent->primary_provider, $agent->model),
            'recentProblems' => AiToolCall::where('agent_id', $agent->id)->whereNotIn('status', ['ok', 'approval_requested'])->latest('id')->limit(5)->get(),
            'tools' => collect($registry->all())->reject(fn ($t) => $t->risk === 'prohibited'),
            'versions' => $agent->versions()->with('changer')->latest('version')->limit(20)->get(),
            'tasks' => $agent->tasks()->latest('id')->limit(10)->get(),
            'memories' => AiMemory::where('agent_id', $agent->id)->whereIn('scope', ['agent'])->latest('id')->limit(20)->get(),
            'implemented' => AgentCatalog::worker($agent->slug) !== null,
            'canDelegate' => AgentCatalog::canDelegate($agent->slug),
        ]);
    }

    public function updateAgent(Request $request, AiAgent $agent, ToolRegistry $registry): RedirectResponse
    {
        $allowed = collect($registry->all())->reject(fn ($t) => $t->risk === 'prohibited')->keys()->all();
        $providers = array_keys(AiProvider::ADAPTERS);
        $modelName = ['nullable', 'string', 'max:80', 'regex:/^[A-Za-z0-9._:\-]+$/'];
        $data = $request->validate([
            'primary_provider' => ['sometimes', Rule::in($providers)],
            'secondary_provider' => ['sometimes', 'nullable', Rule::in($providers), 'different:primary_provider'],
            'secondary_model' => ['sometimes', ...$modelName],
            'fallback_enabled' => ['sometimes', 'boolean'],
            'max_tokens' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:128000'],
            'daily_request_limit' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:1000000'],
            'monthly_budget_usd' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:1000000'],
            'instructions' => ['required', 'string', 'max:6000'],
            'model_tier' => ['required', Rule::in(array_keys(AiAgent::TIERS))],
            'model' => ['nullable', 'string', 'max:80', 'regex:/^[A-Za-z0-9._:\-]+$/'],
            'temperature' => ['required', 'numeric', 'between:0,1'],
            'status' => ['required', Rule::in(array_keys(AiAgent::STATUSES))],
            'daily_token_budget' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'tools' => ['array'],
            'tools.*' => ['string', Rule::in($allowed)],
            'change_note' => ['required', 'string', 'max:255'],
        ]);
        $agent->fill(collect($data)->except('change_note')->all() + ['tools' => array_values($data['tools'] ?? [])]);
        if (! $agent->isDirty()) {
            return back()->with('status', 'No changes to save.');
        }
        $agent->forceFill(['version' => $agent->version + 1])->save();
        $agent->snapshot($request->user(), $data['change_note']);
        AuditLogger::record('ai.agent_updated', $agent, ['version' => $agent->version, 'changed' => array_keys($agent->getChanges())]);

        return back()->with('status', "{$agent->name} saved as version {$agent->version}.");
    }

    public function tasks(Request $request): View
    {
        $q = AiTask::with('agent')->latest('id');
        $request->filled('status') && $q->where('status', $request->string('status'));
        $request->filled('agent') && $q->whereHas('agent', fn ($a) => $a->where('slug', $request->string('agent')));
        $request->boolean('subtasks') || $q->whereNull('parent_id');

        return view('admin.ai.tasks', ['tasks' => $q->paginate(25)->withQueryString(), 'agents' => AiAgent::orderBy('id')->get()]);
    }

    public function runTask(Request $request, CentralAiOrchestrator $brain): RedirectResponse
    {
        $data = $request->validate([
            'request' => ['required', 'string', 'min:3', 'max:2000'],
            'agent' => ['nullable', 'string', Rule::exists('ai_agents', 'slug')],
            'queue' => ['nullable', 'boolean'],
        ]);
        $task = $brain->submit($data['request'], $data['agent'] ?? null, $request->user(), 'admin', queue: (bool) ($data['queue'] ?? false));
        AuditLogger::record('ai.task_submitted', $task, ['agent' => $task->agent_id]);

        return redirect()->route('admin.ai.tasks.show', $task)->with('status', 'Task '.$task->statusLabel().'.');
    }

    public function task(AiTask $task): View
    {
        $task->load(['agent', 'requester', 'parent', 'children.agent', 'approvals.decider']);

        return view('admin.ai.task', [
            'task' => $task,
            'toolCalls' => AiToolCall::where('task_id', $task->id)->orderBy('id')->get(),
            'usage' => AiUsageRecord::where('task_id', $task->id)->orderBy('id')->get(),
        ]);
    }

    public function retryTask(AiTask $task, CentralAiOrchestrator $brain): RedirectResponse
    {
        $result = $brain->retry($task);

        return back()->with('status', $result->status === 'failed' && $result->attempts >= config('meezan.agents.limits.max_attempts')
            ? 'This task has used all its attempts.' : 'Task '.$result->statusLabel().'.');
    }

    public function cancelTask(AiTask $task, CentralAiOrchestrator $brain): RedirectResponse
    {
        $brain->cancel($task);
        AuditLogger::record('ai.task_cancelled', $task);

        return back()->with('status', 'Task '.$task->fresh()->statusLabel().'.');
    }

    /** Download the report as Markdown. */
    public function exportTask(AiTask $task): StreamedResponse
    {
        $r = $task->result ?? [];
        $md = ['# '.($r['title'] ?? $task->title), '', '- Agent: '.($task->agent?->name ?? '—'), '- Task: '.$task->uuid, '- Status: '.$task->statusLabel(), '- Generated: '.($r['generated_at'] ?? '—'), '- Mode: '.($task->mode ?? '—'), ''];
        $list = fn (string $h, array $items) => $items ? array_merge(["## {$h}", ''], array_map(fn ($i) => '- '.$i, $items), ['']) : [];
        $md = array_merge($md, $r['narrative'] ?? null ? ['## Narrative', '', $r['narrative'], ''] : [], $list('Findings', $r['summary'] ?? []));
        foreach ($r['sections'] ?? [] as $s) {
            $md[] = '## '.$s['heading'];
            $md[] = '';
            foreach ($s['items'] ?? [] as $i) {
                $md[] = '- '.$i;
            }
            if ($t = $s['table'] ?? null) {
                $md[] = '| '.implode(' | ', $t['columns']).' |';
                $md[] = '|'.str_repeat(' --- |', count($t['columns']));
                foreach ($t['rows'] as $row) {
                    $md[] = '| '.implode(' | ', array_map(fn ($c) => str_replace('|', '/', (string) $c), $row)).' |';
                }
            }
            $md[] = '';
        }
        $md = array_merge($md, $list('Recommendations', $r['recommendations'] ?? []), $list('Missing data', $r['missing'] ?? []), $list('Limitations', $r['limitations'] ?? []), $list('Data sources (tools)', $r['data_sources'] ?? []));

        return response()->streamDownload(fn () => print (OutputGuard::text(implode("\n", $md))), 'ai-report-'.$task->uuid.'.md', ['Content-Type' => 'text/markdown; charset=UTF-8']);
    }

    public function approvals(Request $request): View
    {
        $status = $request->input('status', 'pending');

        return view('admin.ai.approvals', [
            'approvals' => AiApproval::with(['agent', 'task', 'decider'])->when($status !== 'all', fn ($q) => $q->where('status', $status))->latest('id')->paginate(25)->withQueryString(),
            'status' => $status,
        ]);
    }

    public function decide(Request $request, AiApproval $approval, ApprovalExecutor $executor): RedirectResponse
    {
        $data = $request->validate(['decision' => ['required', Rule::in(['approve', 'reject'])], 'note' => ['nullable', 'string', 'max:500']]);
        try {
            $approval = $data['decision'] === 'approve'
                ? $executor->approve($approval, $request->user(), $data['note'] ?? null)
                : $executor->reject($approval, $request->user(), $data['note'] ?? null);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', $data['decision'] === 'approve' ? 'Approved: '.($approval->execution['message'] ?? 'done.') : 'Rejected. Nothing was done.');
    }

    public function usage(AiProvider $provider): View
    {
        $since = now()->subDays(30);
        $stats = fn ($from) => AiUsageRecord::where('created_at', '>=', $from)->toBase()
            ->selectRaw('provider, count(*) as requests, sum(case when ok then 1 else 0 end) as succeeded, sum(tokens_in) as tin, sum(tokens_out) as tout, sum(cost_usd) as cost, sum(case when cost_usd is null and tokens_in + tokens_out > 0 then 1 else 0 end) as unpriced, sum(case when fallback_from is not null then 1 else 0 end) as fallbacks, sum(case when fallback_from is not null then cost_usd else 0 end) as fallback_cost')
            ->groupBy('provider')->get()->keyBy('provider');
        $monthSpend = AiUsageRecord::where('created_at', '>=', now()->startOfMonth())->whereNotNull('cost_usd')->exists()
            ? AiUsageRecord::where('created_at', '>=', now()->startOfMonth())->sum('cost_usd') : null;

        return view('admin.ai.usage', [
            'byAgent' => AiUsageRecord::where('ai_usage_records.created_at', '>=', $since)->leftJoin('ai_agents', 'ai_agents.id', '=', 'ai_usage_records.agent_id')
                ->selectRaw("coalesce(ai_agents.name, 'General') as agent, count(*) as calls, sum(tokens_in) as tin, sum(tokens_out) as tout, sum(cost_usd) as cost, sum(case when cost_usd is null and tokens_in + tokens_out > 0 then 1 else 0 end) as unpriced, sum(case when ok then 0 else 1 end) as errors")
                ->groupBy('agent')->orderBy('agent')->get(),
            'byModel' => AiUsageRecord::where('created_at', '>=', $since)->selectRaw('model, count(*) as calls, sum(tokens_in + tokens_out) as tokens, sum(cost_usd) as cost')->groupBy('model')->get(),
            'records' => AiUsageRecord::with('agent')->latest('id')->limit(30)->get(),
            'toolCalls' => AiToolCall::where('created_at', '>=', $since)->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status'),
            'pricing' => AiProvider::pricing(),
            'limits' => config('meezan.agents.limits'),
            'labels' => $provider->labels(),
            'today' => $stats(now()->startOfDay()),
            'month' => $stats(now()->startOfMonth()),
            'monthSpend' => $monthSpend === null ? null : (float) $monthSpend,
            'daily' => AiUsageRecord::where('created_at', '>=', now()->subDays(14)->startOfDay())->toBase()
                ->selectRaw('date(created_at) as day, provider, count(*) as requests, sum(tokens_in + tokens_out) as tokens, sum(cost_usd) as cost')
                ->groupByRaw('date(created_at), provider')->orderByDesc('day')->orderBy('provider')->get(),
            'topTasks' => AiUsageRecord::with('task:id,uuid,title')->where('created_at', '>=', $since)->whereNotNull('task_id')
                ->selectRaw('task_id, sum(tokens_in + tokens_out) as tokens, sum(cost_usd) as cost')->groupBy('task_id')
                ->orderByRaw('coalesce(sum(cost_usd), 0) desc')->orderByDesc('tokens')->limit(10)->get(),
            'controls' => AiProvider::controls(),
            'anyProvider' => $provider->anyReady(),
        ]);
    }

    public function updatePricing(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'pricing' => ['array', 'max:20'],
            'pricing.*.model' => ['nullable', 'string', 'max:80', 'regex:/^[A-Za-z0-9._:\-]+$/'],
            'pricing.*.input' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'pricing.*.output' => ['nullable', 'numeric', 'min:0', 'max:1000'],
        ]);
        $pricing = collect($data['pricing'] ?? [])->filter(fn ($p) => filled($p['model'] ?? null) && isset($p['input'], $p['output']))
            ->mapWithKeys(fn ($p) => [$p['model'] => ['input' => (float) $p['input'], 'output' => (float) $p['output']]])->all();
        AppSetting::put('ai.pricing', $pricing, $request->user()->id);
        AuditLogger::record('ai.pricing_updated', null, ['models' => array_keys($pricing)]);

        return back()->with('status', 'Pricing saved. Costs are calculated from it for new model calls only; earlier calls stay unpriced.');
    }

    public function knowledge(): View
    {
        return view('admin.ai.knowledge', [
            'documents' => AiKnowledgeDocument::latest('id')->get(),
            'proposed' => AiMemory::with('agent')->where('status', 'proposed')->latest('id')->get(),
            'memories' => AiMemory::with('agent')->whereIn('scope', ['agent', 'shared'])->where('status', 'approved')->latest('id')->limit(50)->get(),
            'agents' => AiAgent::orderBy('id')->pluck('name', 'slug'),
        ]);
    }

    public function storeDocument(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'category' => ['required', Rule::in(array_keys(AiKnowledgeDocument::CATEGORIES))],
            'title' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:20000'],
            'source' => ['required', 'string', 'max:255'],
            'agents' => ['nullable', 'array'],
            'agents.*' => ['string', Rule::exists('ai_agents', 'slug')],
            'approve' => ['nullable', 'boolean'],
        ]);
        $doc = new AiKnowledgeDocument(collect($data)->only(['category', 'title', 'body', 'source'])->all());
        $doc->forceFill(['agents' => $data['agents'] ?? null, 'updated_by' => $request->user()->id, 'version' => 1] + ($request->boolean('approve')
            ? ['status' => 'approved', 'approved_by' => $request->user()->id, 'approved_at' => now()] : ['status' => 'draft']))->save();
        AuditLogger::record('ai.knowledge_created', $doc, ['status' => $doc->status]);

        return back()->with('status', "\"{$doc->title}\" saved as ".$doc->status.'.');
    }

    public function documentStatus(Request $request, AiKnowledgeDocument $document): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(['approved', 'archived'])]]);
        $document->forceFill(['status' => $data['status']] + ($data['status'] === 'approved' ? ['approved_by' => $request->user()->id, 'approved_at' => now()] : []))->save();
        AuditLogger::record('ai.knowledge_'.$data['status'], $document);

        return back()->with('status', "\"{$document->title}\" is now {$data['status']}.");
    }

    public function memoryDecision(Request $request, AiMemory $memory): RedirectResponse
    {
        $data = $request->validate(['decision' => ['required', Rule::in(['approve', 'reject', 'archive'])]]);
        if ($data['decision'] === 'approve' && $memory->status === 'proposed') {
            // The previous approved version of the same key is archived, never edited.
            AiMemory::where('scope', $memory->scope)->where('agent_id', $memory->agent_id)->where('key', $memory->key)->where('status', 'approved')->update(['status' => 'archived']);
            $memory->forceFill(['status' => 'approved', 'approved_by' => $request->user()->id, 'approved_at' => now()])->save();
        } else {
            $memory->forceFill(['status' => 'archived'])->save();
        }
        AuditLogger::record('ai.memory_'.$data['decision'], $memory, ['key' => $memory->key]);

        return back()->with('status', 'Memory "'.$memory->key.'" '.($memory->status === 'approved' ? 'approved' : 'archived').'.');
    }

    /** A note an administrator writes directly: approved from the start, replacing any earlier version of the same key. */
    public function storeMemory(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'scope' => ['required', Rule::in(['agent', 'shared'])],
            'agent' => ['nullable', 'required_if:scope,agent', 'string', Rule::exists('ai_agents', 'slug')],
            'key' => ['required', 'string', 'max:120'],
            'content' => ['required', 'string', 'max:2000'],
            'source' => ['required', 'string', 'max:255'],
            'expires_at' => ['nullable', 'date', 'after:today'],
        ]);
        $agentId = $data['scope'] === 'agent' ? AiAgent::where('slug', $data['agent'])->value('id') : null;
        $current = AiMemory::where('scope', $data['scope'])->where('agent_id', $agentId)->where('key', $data['key'])->where('status', 'approved');
        $version = (int) (clone $current)->max('version') + 1;
        $current->update(['status' => 'archived']);
        $memory = AiMemory::create(['agent_id' => $agentId, 'scope' => $data['scope'], 'key' => $data['key'], 'content' => OutputGuard::text($data['content']), 'source' => $data['source'],
            'status' => 'approved', 'version' => $version, 'approved_by' => $request->user()->id, 'approved_at' => now(), 'expires_at' => $data['expires_at'] ?? null]);
        AuditLogger::record('ai.memory_created', $memory, ['key' => $memory->key, 'version' => $version]);

        return back()->with('status', 'Note "'.$memory->key.'" saved as version '.$version.'.');
    }

    /** What agents have passed to people: tickets, internal tasks and drafts. */
    public function work(): View
    {
        return view('admin.ai.work', [
            'tickets' => SupportTicket::with('user:id,name')->whereIn('status', ['open', 'in_progress'])->orderByRaw("case priority when 'high' then 0 when 'normal' then 1 else 2 end")->latest('id')->limit(100)->get(),
            'staffTasks' => StaffTask::with(['agent:id,name', 'assignee:id,name'])->where('status', 'open')->orderByRaw('case when due_on is null then 1 else 0 end')->orderBy('due_on')->latest('id')->limit(100)->get(),
            'drafts' => AiDraft::with('agent:id,name')->latest('id')->limit(30)->get(),
        ]);
    }

    public function updateTicket(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(['in_progress', 'resolved'])]]);
        $ticket->forceFill(['status' => $data['status'], 'assigned_to' => $ticket->assigned_to ?? $request->user()->id])->save();
        AuditLogger::record('ai.ticket_'.$data['status'], $ticket, ['reference' => $ticket->reference]);

        return back()->with('status', "Ticket {$ticket->reference}: ".str_replace('_', ' ', $data['status']).'.');
    }

    public function updateStaffTask(Request $request, StaffTask $staffTask): RedirectResponse
    {
        $data = $request->validate(['action' => ['required', Rule::in(['assign', 'done', 'cancelled'])]]);
        $staffTask->forceFill($data['action'] === 'assign' ? ['assigned_to' => $request->user()->id] : ['status' => $data['action']])->save();
        AuditLogger::record('ai.staff_task_'.$data['action'], $staffTask, ['title' => $staffTask->title]);

        return back()->with('status', 'Task updated.');
    }

    public function updateDraft(Request $request, AiDraft $draft): RedirectResponse
    {
        $request->validate(['status' => ['required', Rule::in(['rejected'])]]);
        abort_unless($draft->status === 'draft', 409);
        $draft->forceFill(['status' => 'rejected'])->save();
        AuditLogger::record('ai.draft_rejected', $draft, ['title' => $draft->title]);

        return back()->with('status', 'Draft discarded.');
    }

    public function tools(ToolRegistry $registry): View
    {
        $assigned = [];
        foreach (AiAgent::orderBy('id')->get(['name', 'tools']) as $agent) {
            foreach ($agent->tools ?? [] as $tool) {
                $assigned[$tool] = ($assigned[$tool] ?? collect())->push($agent->name);
            }
        }

        return view('admin.ai.tools', [
            'groups' => collect($registry->all())->groupBy(fn ($t) => $t->risk, preserveKeys: true)->sortBy(fn ($g, $risk) => array_search($risk, array_keys(ToolDefinition::RISKS), true)),
            'assigned' => $assigned,
            'calls' => AiToolCall::where('created_at', '>=', now()->subDays(30))->toBase()->selectRaw('tool, count(*) as n')->groupBy('tool')->pluck('n', 'tool'),
        ]);
    }

    public function logs(ToolRegistry $registry): View
    {
        $health = ($registry->get('system.health')->handler)([], null);

        return view('admin.ai.logs', [
            'failedTasks' => AiTask::with('agent')->where('status', 'failed')->latest('id')->limit(15)->get(),
            'toolProblems' => AiToolCall::with('agent')->whereNotIn('status', ['ok', 'approval_requested'])->latest('id')->limit(15)->get(),
            'delegations' => AiTask::with(['agent', 'parent.agent'])->whereNotNull('parent_id')->latest('id')->limit(15)->get(),
            'providerErrors' => AiUsageRecord::where('ok', false)->latest('id')->limit(10)->get(),
            'fallbacks' => AiUsageRecord::with('agent:id,name')->whereNotNull('fallback_from')->latest('id')->limit(10)->get(),
            'logErrors' => $health['recent_log_errors'],
        ]);
    }

    public function providers(AiProvider $provider, AgentReadiness $readiness): View
    {
        $agents = AiAgent::orderBy('id')->get();

        return view('admin.ai.providers', [
            'providers' => collect(AiProvider::ADAPTERS)->map(function ($class, $key) use ($provider) {
                $config = $provider->config($key);
                $row = $provider->row($key);
                [$status, $label] = match (true) {
                    ! $config->enabled => ['disabled', 'Disabled'],
                    $config->keyUnreadable => ['error', 'Key unreadable'],
                    ! $config->hasKey() => ['not_configured', 'Not configured'],
                    $row?->connection_status === 'verified' && blank($config->defaultModel) => ['configured', 'Key works, no model chosen'],
                    $row?->connection_status === 'verified' => ['verified', 'Connection verified'],
                    $row?->connection_status === 'error' => ['error', 'Error'],
                    default => ['configured', 'Key added, not tested'],
                };

                return ['config' => $config, 'row' => $row, 'status' => $status, 'statusLabel' => $label, 'warning' => $provider->modelWarning($key, $config->defaultModel)];
            })->all(),
            'labels' => $provider->labels(),
            'default' => $provider->defaultProvider(),
            'controls' => AiProvider::controls(),
            'agents' => $agents,
            'routes' => $agents->mapWithKeys(fn ($a) => [$a->slug => $provider->route($a, (string) $a->model_tier, 'none')]),
            'readiness' => $readiness->all($agents),
        ]);
    }

    /**
     * Provider settings. The key is validated by hand so that a failed form
     * never flashes it into the session, and an empty field never removes one.
     */
    public function updateProvider(Request $request, string $provider, AiProvider $manager, ConnectorStatus $connectors): RedirectResponse
    {
        $validator = Validator::make($request->except('_token', '_method'), [
            'enabled' => ['required', 'boolean'],
            'api_key' => ['nullable', 'string', 'min:20', 'max:500', 'regex:/^\S+$/'],
            'remove_key' => ['nullable', 'boolean'],
            'default_model' => ['nullable', 'string', 'max:80', 'regex:/^[A-Za-z0-9._:\-]+$/'],
            'base_url' => ['nullable', 'url:https,http', 'max:255', function ($attribute, $value, $fail) {
                $local = in_array(parse_url($value, PHP_URL_HOST), ['localhost', '127.0.0.1'], true);
                if (parse_url($value, PHP_URL_SCHEME) !== 'https' && ! $local) {
                    $fail('The base URL must use HTTPS, because the API key is sent to it.');
                }
            }],
            'monthly_budget_usd' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
        ], ['api_key.regex' => 'An API key has no spaces.', 'api_key.min' => 'That looks too short to be an API key.']);
        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput($request->except('api_key'));
        }
        $data = $validator->validated();

        $row = AiProviderSetting::firstOrNew(['provider' => $provider]);
        $before = $row->exists ? $row->only(['enabled', 'default_model', 'base_url', 'monthly_budget_usd']) : [];
        $row->fill(['enabled' => (bool) $data['enabled'], 'default_model' => $data['default_model'] ?? null, 'base_url' => $data['base_url'] ?? null, 'monthly_budget_usd' => $data['monthly_budget_usd'] ?? null]);

        $keyChanged = false;
        if (filled($data['api_key'] ?? null)) {
            $row->api_key = $data['api_key'];
            $keyChanged = true;
        } elseif ($request->boolean('remove_key')) {
            $row->api_key = null;
            $keyChanged = true;
        }
        $connectionChanged = $keyChanged || $row->isDirty('base_url');
        if ($connectionChanged) {
            $row->fill(['connection_status' => 'untested', 'available_models' => null, 'last_error' => null, 'last_error_at' => null, 'last_success_at' => null]);
            $connectors->remember($provider, null);
        } elseif ($row->isDirty('default_model')) {
            // The model list stays useful, but the new model has not been proved.
            $row->connection_status = 'untested';
            $connectors->remember($provider, null);
        }
        $row->save();

        // Which fields changed, and whether the key did: never the key itself.
        AuditLogger::record('ai.provider_updated', $row, ['provider' => $provider, 'changed' => array_keys(array_diff_assoc(array_map('strval', $row->only(array_keys($before))), array_map('strval', $before))), 'api_key_changed' => $keyChanged]);

        return back()->with('status', $manager->config($provider)->label.' saved.'.($connectionChanged ? ' Test the connection next.' : ''));
    }

    public function testProvider(string $provider, AiProvider $manager, ConnectorStatus $connectors): RedirectResponse
    {
        $result = $manager->testConnection($provider);
        if (in_array($result['status'], ['verified', 'error'], true)) {
            $connectors->remember($provider, $result);
        }
        AuditLogger::record('ai.provider_tested', null, ['provider' => $provider, 'status' => $result['status']]);
        $message = $manager->config($provider)->label.': '.OutputGuard::text($result['detail']);

        return back()->with($result['status'] === 'error' || $result['status'] === 'not_configured' ? 'error' : 'status', $message);
    }

    /** Budgets, the emergency stop, fallback and channel routing. Each form sends only its own fields. */
    public function updateControls(Request $request, AiProvider $manager): RedirectResponse
    {
        $providers = array_keys(AiProvider::ADAPTERS);
        $data = $request->validate([
            'emergency_stop' => ['sometimes', 'boolean'],
            'fallback_allowed' => ['sometimes', 'boolean'],
            'default_provider' => ['sometimes', Rule::in($providers)],
            'monthly_total_usd' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:1000000'],
            'daily_requests_total' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:10000000'],
            'max_tokens_per_request' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:128000'],
            'channels' => ['sometimes', 'array:website,whatsapp'],
            'channels.*.provider' => ['nullable', Rule::in($providers)],
            'channels.*.model' => ['nullable', 'string', 'max:80', 'regex:/^[A-Za-z0-9._:\-]+$/'],
            'channels.*.fallback' => ['nullable', 'boolean'],
        ]);
        foreach (['emergency_stop', 'fallback_allowed'] as $flag) {
            if (array_key_exists($flag, $data)) {
                $data[$flag] = (bool) $data[$flag];
            }
        }
        foreach ($data['channels'] ?? [] as $channel => $values) {
            $data['channels'][$channel]['fallback'] = (bool) ($values['fallback'] ?? false);
        }
        $before = AiProvider::controls();
        AppSetting::put('ai.controls', $data + $before, $request->user()->id);
        AuditLogger::record('ai.controls_updated', null, ['changed' => array_keys(array_filter($data, fn ($v, $k) => ($before[$k] ?? null) != $v, ARRAY_FILTER_USE_BOTH))]);

        return back()->with('status', ($data['emergency_stop'] ?? false) ? 'Saved. Emergency stop is on: no paid AI requests will be made.' : 'Saved.');
    }

    public function connectors(ConnectorStatus $connectors): View
    {
        return view('admin.ai.connectors', ['connectors' => $connectors->all()]);
    }

    public function checkConnector(Request $request, string $key, ConnectorStatus $connectors): RedirectResponse
    {
        abort_unless(array_key_exists($key, $connectors->all()), 404);
        $result = $connectors->check($key);
        AuditLogger::record('ai.connector_checked', null, ['connector' => $key, 'status' => $result['status']]);

        return back()->with('status', $connectors->all()[$key]['label'].': '.str_replace('_', ' ', $result['status']).'. '.OutputGuard::text($result['detail'] ?? ''));
    }
}
