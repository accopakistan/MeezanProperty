<?php

namespace App\Services\WhatsApp;

use App\Models\Conversation;
use App\Models\User;
use App\Notifications\AssistantNeedsAttention;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\Notification;
use InvalidArgumentException;

/**
 * Moves a WhatsApp chat between the AI and staff: pending → assigned →
 * resolved. While a handoff is open the AI does not reply. It answers again
 * only after a member of staff chooses to resume it.
 */
class HumanHandoff
{
    /** A customer or an agent asked for staff: open it and alert the admins. */
    public function request(Conversation $conversation, string $reason): void
    {
        if ($conversation->hasOpenHandoff()) {
            return;
        }
        $this->open($conversation, $reason);
        $conversation->forceFill(['escalated_at' => now(), 'escalation_reason' => $reason, 'status' => $conversation->status === 'lead' ? 'lead' : 'escalated'])->save();
        AuditLogger::record('conversation.escalated', $conversation, ['reason' => $reason]);
        Notification::send(User::activeAdmins(), new AssistantNeedsAttention($reason, $conversation));
    }

    /** Stop the AI replying and wait for staff. Alerts are the caller's job. */
    public function open(Conversation $conversation, string $reason): void
    {
        $conversation->forceFill([
            'mode' => 'human',
            'handoff_status' => 'pending',
            'handoff_reason' => mb_substr($reason, 0, 40),
            'handoff_requested_at' => now(),
            'handoff_assigned_at' => null,
            'handoff_resolved_at' => null,
            'assigned_to' => null,
        ])->save();
    }

    public function assign(Conversation $conversation, User $staff, User $by): void
    {
        if (! $staff->isAdmin() || $staff->status !== 'active') {
            throw new InvalidArgumentException('Only an active administrator can take a conversation.');
        }
        $conversation->forceFill([
            'mode' => 'human',
            'handoff_status' => 'assigned',
            'handoff_reason' => $conversation->handoff_reason ?? 'staff_takeover',
            'handoff_requested_at' => $conversation->handoff_requested_at ?? now(),
            'handoff_assigned_at' => now(),
            'handoff_resolved_at' => null,
            'assigned_to' => $staff->id,
        ])->save();
        AuditLogger::record('conversation.handoff_assigned', $conversation, ['staff_id' => $staff->id], $by->id);
    }

    /** Close the handoff. The AI answers again only if $resumeAi is true. */
    public function resolve(Conversation $conversation, User $by, bool $resumeAi): void
    {
        $conversation->forceFill([
            'handoff_status' => $conversation->handoff_status ? 'resolved' : null,
            'handoff_resolved_at' => $conversation->handoff_status ? now() : null,
            'escalated_at' => null,
            'escalation_reason' => null,
            'status' => $conversation->lead_id ? 'lead' : ($conversation->buyer_requirement_id ? 'qualified' : 'active'),
            'mode' => $resumeAi ? 'bot' : $conversation->mode,
        ])->save();
        AuditLogger::record('conversation.handoff_resolved', $conversation, ['resume_ai' => $resumeAi], $by->id);
    }
}
