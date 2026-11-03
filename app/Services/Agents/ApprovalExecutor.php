<?php

namespace App\Services\Agents;

use App\Models\AiApproval;
use App\Models\AiDraft;
use App\Models\AiTask;
use App\Models\InvestmentMethodology;
use App\Models\StaffTask;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Carries out an approved agent action, once. Only an active admin decides.
 * What "carry out" means is deliberately narrow:
 *  - dealer verification, lead reassignment and package changes become staff
 *    tasks (staff make the change in the existing admin screens, with their
 *    own checks and audit);
 *  - a methodology change creates a DRAFT version (never activates it);
 *  - publishing and bulk sending need a verified connector; with none, the
 *    approval is recorded and the execution is "blocked".
 * Prohibited actions never reach here: they cannot create approvals.
 */
class ApprovalExecutor
{
    public function __construct(protected ConnectorStatus $connectors) {}

    public function approve(AiApproval $approval, User $admin, ?string $note = null): AiApproval
    {
        $this->authorise($approval, $admin);

        return DB::transaction(function () use ($approval, $admin, $note) {
            // Lock the row so two admins cannot execute it twice.
            $approval = AiApproval::whereKey($approval->id)->lockForUpdate()->firstOrFail();
            if (! $approval->isPending()) {
                throw new RuntimeException('This approval is no longer pending.');
            }
            $execution = $this->execute($approval, $admin);
            $approval->forceFill(['status' => 'approved', 'decided_by' => $admin->id, 'decided_at' => now(), 'decision_note' => $note, 'execution' => $execution])->save();
            AuditLogger::record('ai.approval_approved', $approval, ['action' => $approval->action, 'result' => $execution['status']]);
            $this->settleTask($approval);

            return $approval->fresh();
        });
    }

    public function reject(AiApproval $approval, User $admin, ?string $note = null): AiApproval
    {
        $this->authorise($approval, $admin);
        $updated = AiApproval::whereKey($approval->id)->where('status', 'pending')
            ->update(['status' => 'rejected', 'decided_by' => $admin->id, 'decided_at' => now(), 'decision_note' => $note]);
        if (! $updated) {
            throw new RuntimeException('This approval is no longer pending.');
        }
        $approval->refresh();
        if ($approval->action === 'content.publish' || $approval->action === 'communications.send_bulk') {
            AiDraft::whereKey($approval->payload['draft_id'] ?? 0)->where('status', 'draft')->update(['status' => 'rejected']);
        }
        AuditLogger::record('ai.approval_rejected', $approval, ['action' => $approval->action]);
        $this->settleTask($approval);

        return $approval;
    }

    /** Mark pending approvals past their expiry date as expired. */
    public function expire(): int
    {
        $ids = AiApproval::where('status', 'pending')->whereNotNull('expires_at')->where('expires_at', '<=', now())->pluck('id');
        AiApproval::whereIn('id', $ids)->update(['status' => 'expired', 'decided_at' => now(), 'decision_note' => 'Expired without a decision']);
        AiApproval::whereIn('id', $ids)->get()->each(fn ($a) => $this->settleTask($a));

        return $ids->count();
    }

    protected function authorise(AiApproval $approval, User $admin): void
    {
        if (! $admin->isAdmin() || ! $admin->isActive()) {
            throw new RuntimeException('Only an active administrator can decide AI approvals.');
        }
        if ($approval->risk !== 'approval') {
            throw new RuntimeException('This action cannot be approved.');
        }
    }

    /** @return array{status: string, message: string, reference?: string} */
    protected function execute(AiApproval $approval, User $admin): array
    {
        $p = $approval->payload ?? [];

        return match ($approval->action) {
            'dealers.verify' => $this->staffTask($approval, $admin, 'dealer_verification', 'Review dealer verification', 'user', $p['dealer_id'] ?? null,
                "Approved AI recommendation: {$p['recommendation']}\nReview the dealer's documents in Admin → Dealers and record the decision there."),
            'leads.reassign' => $this->staffTask($approval, $admin, 'lead_reassignment', 'Reassign lead #'.($p['lead_id'] ?? '?'), 'lead', $p['lead_id'] ?? null,
                "Move this lead to dealer #{$p['dealer_id']}. Reason: {$p['reason']}"),
            'subscriptions.change' => $this->staffTask($approval, $admin, 'subscription_change', 'Subscription change #'.($p['subscription_id'] ?? '?'), 'subscription', $p['subscription_id'] ?? null,
                "Requested change: {$p['change']}\nMake the change in Admin → Subscriptions; billing records are not changed automatically."),
            'investment.methodology_change' => $this->methodologyDraft($approval, $admin, $p),
            'content.publish', 'communications.send_bulk' => $this->channelAction($approval, $p),
            default => ['status' => 'blocked', 'message' => 'No executor is defined for this action; nothing was done.'],
        };
    }

    protected function staffTask(AiApproval $approval, User $admin, string $category, string $title, string $type, ?int $id, string $description): array
    {
        $task = StaffTask::create([
            'title' => $title, 'description' => $description, 'category' => $category,
            'related_type' => $type, 'related_id' => $id, 'status' => 'open', 'due_on' => now()->addDays(2)->toDateString(),
            'created_by' => $admin->id, 'agent_id' => $approval->agent_id, 'approval_id' => $approval->id,
        ]);

        return ['status' => 'staff_task_created', 'message' => "Staff task #{$task->id} created; the change itself is made by staff.", 'reference' => 'staff_task:'.$task->id];
    }

    protected function methodologyDraft(AiApproval $approval, User $admin, array $p): array
    {
        try {
            $weights = InvestmentMethodology::validateWeights($p['weights'] ?? []);
        } catch (ValidationException $e) {
            return ['status' => 'blocked', 'message' => 'The proposed weights are invalid: '.collect($e->errors())->flatten()->first()];
        }
        $active = InvestmentMethodology::active();
        $draft = new InvestmentMethodology([
            'name' => 'AI-proposed draft based on v'.($active?->version ?? 0),
            'weights' => $weights,
            'parameters' => $active?->parameters ?? config('meezan.investment.default_parameters'),
            'notes' => 'Proposed by the Investment Analyst agent (approval #'.$approval->id.'): '.$p['reason'],
        ]);
        $draft->forceFill(['version' => (int) InvestmentMethodology::max('version') + 1, 'status' => 'draft', 'created_by' => $admin->id])->save();
        AuditLogger::record('investment.methodology_created', $draft, ['from' => $active?->version, 'approval' => $approval->id]);

        return ['status' => 'draft_created', 'message' => "Draft methodology v{$draft->version} created. It is not active until an admin activates it.", 'reference' => 'methodology:'.$draft->id];
    }

    protected function channelAction(AiApproval $approval, array $p): array
    {
        $draft = AiDraft::find($p['draft_id'] ?? 0);
        $channel = $approval->action === 'communications.send_bulk' ? 'whatsapp' : ($p['channel'] ?? '');
        $status = $this->connectors->all()[$this->connectorFor($channel)]['status'] ?? 'not_configured';
        $draft?->update(['status' => 'approved']);

        if ($status !== 'verified') {
            return ['status' => 'blocked', 'message' => "Approved, but not sent: the {$channel} connector is ".str_replace('_', ' ', $status).'. The draft is marked approved for manual use.'];
        }

        // Even with a verified connector, nothing is posted automatically in this phase.
        return ['status' => 'blocked', 'message' => "Approved. Automatic {$channel} publishing is not enabled in this version; the approved draft is ready for staff to post."];
    }

    protected function connectorFor(string $channel): string
    {
        return match ($channel) {
            'facebook', 'instagram' => 'meta_graph',
            'whatsapp' => 'whatsapp',
            'email' => 'email',
            default => $channel,
        };
    }

    protected function settleTask(AiApproval $approval): void
    {
        $task = AiTask::find($approval->task_id);
        if ($task && $task->status === 'awaiting_approval' && ! $task->approvals()->where('status', 'pending')->exists()) {
            $task->forceFill(['status' => 'completed', 'completed_at' => now()])->save();
        }
    }
}
