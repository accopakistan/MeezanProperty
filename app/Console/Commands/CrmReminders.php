<?php

namespace App\Console\Commands;

use App\Models\LeadAppointment;
use App\Models\LeadAssignment;
use App\Models\LeadFollowUp;
use App\Notifications\CrmReminder;
use App\Services\Crm\DealerCrm;
use Illuminate\Console\Command;

/**
 * Dealer CRM automation (every five minutes). Each reminder is sent once:
 * - follow-up due (reminded_at), and overdue an hour later (overdue_notified_at);
 * - appointment starting within the reminder window (reminder_sent_at);
 * - open lead with no dealer activity for stale_after_days (stale_flagged_at,
 *   cleared by the next activity).
 * Nothing is closed or sent to buyers automatically.
 */
class CrmReminders extends Command
{
    protected $signature = 'meezan:crm-reminders';

    protected $description = 'Send dealer CRM reminders and flag leads without activity';

    public function handle(DealerCrm $crm): int
    {
        $done = ['due' => 0, 'overdue' => 0, 'appointments' => 0, 'stale' => 0];
        $active = fn ($q) => $q->where('status', 'active');

        LeadFollowUp::pending()->whereNull('reminded_at')->where('due_at', '<=', now())->whereHas('assignment', $active)
            ->with('assignment.dealer')->chunkById(200, function ($followUps) use (&$done) {
                foreach ($followUps as $followUp) {
                    $followUp->forceFill(['reminded_at' => now()])->save();
                    $followUp->assignment->dealer?->notify(new CrmReminder('follow_up_due', $followUp));
                    $done['due']++;
                }
            });

        LeadFollowUp::pending()->whereNull('overdue_notified_at')->where('due_at', '<=', now()->subMinutes((int) config('meezan.crm.overdue_after_minutes')))
            ->whereHas('assignment', $active)->with('assignment.dealer')->chunkById(200, function ($followUps) use (&$done) {
                foreach ($followUps as $followUp) {
                    $followUp->forceFill(['overdue_notified_at' => now(), 'reminded_at' => $followUp->reminded_at ?? now()])->save();
                    $followUp->assignment->dealer?->notify(new CrmReminder('follow_up_overdue', $followUp));
                    $done['overdue']++;
                }
            });

        LeadAppointment::where('status', 'scheduled')->whereNull('reminder_sent_at')
            ->whereBetween('starts_at', [now(), now()->addMinutes((int) config('meezan.crm.appointment_reminder_minutes'))])
            ->whereHas('assignment', $active)->with('assignment.dealer')->chunkById(200, function ($appointments) use (&$done) {
                foreach ($appointments as $appointment) {
                    $appointment->forceFill(['reminder_sent_at' => now()])->save();
                    $appointment->assignment->dealer?->notify(new CrmReminder('appointment_upcoming', $appointment));
                    $done['appointments']++;
                }
            });

        LeadAssignment::active()->open()->whereNull('stale_flagged_at')
            ->where(fn ($q) => $q->where('last_activity_at', '<', now()->subDays((int) config('meezan.crm.stale_after_days')))
                ->orWhere(fn ($n) => $n->whereNull('last_activity_at')->where('assigned_at', '<', now()->subDays((int) config('meezan.crm.stale_after_days')))))
            ->with('dealer')->chunkById(200, function ($assignments) use (&$done, $crm) {
                foreach ($assignments as $assignment) {
                    $assignment->forceFill(['stale_flagged_at' => now()])->save();
                    $crm->record($assignment, 'flagged_stale', null);
                    $assignment->dealer?->notify(new CrmReminder('lead_stale', $assignment));
                    $done['stale']++;
                }
            });

        foreach ($done as $what => $count) {
            $this->line(str_pad($what, 14).$count);
        }

        return self::SUCCESS;
    }
}
