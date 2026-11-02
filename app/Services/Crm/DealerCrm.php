<?php

namespace App\Services\Crm;

use App\Enums\PipelineStage;
use App\Enums\PropertyStatus;
use App\Models\LeadActivity;
use App\Models\LeadAppointment;
use App\Models\LeadAssignment;
use App\Models\LeadFollowUp;
use App\Models\LeadNote;
use App\Models\LeadRecommendation;
use App\Models\Property;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\LocalTime;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Every change to a dealer's CRM goes through here. All state is on the
 * dealer's own lead assignment, so one dealer's stage, notes, follow-ups
 * and appointments never affect another dealer on the same lead, and the
 * lead's marketplace status is not touched.
 *
 * Each change is written to the assignment's activity timeline; stage
 * changes (including won/lost), appointment changes and staff actions are
 * also written to the audit log.
 */
class DealerCrm
{
    /**
     * @param  array{reason?: ?string, note?: ?string, transaction_type?: ?string, property_id?: ?int, value?: ?string}  $details
     */
    public function changeStage(LeadAssignment $assignment, PipelineStage $to, User $by, array $details = []): void
    {
        $this->guardActive($assignment);
        $from = $assignment->pipeline_stage;
        if ($from === $to) {
            return;
        }
        if (! $from->canMoveTo($to)) {
            throw new InvalidArgumentException("A lead cannot move from {$from->label()} to {$to->label()}.");
        }

        $reason = $details['reason'] ?? null;
        $note = filled($details['note'] ?? null) ? mb_substr(trim($details['note']), 0, 500) : null;
        if ($to === PipelineStage::Lost && ! array_key_exists((string) $reason, config('meezan.crm.lost_reasons'))) {
            throw new InvalidArgumentException('Choose why the lead was lost.');
        }
        if (in_array($to, [PipelineStage::Invalid, PipelineStage::Duplicate], true) && ! $note) {
            throw new InvalidArgumentException('Explain why the lead is '.strtolower($to->label()).'.');
        }

        $won = [];
        if ($to === PipelineStage::Won) {
            $type = $details['transaction_type'] ?? null;
            if ($type !== null && ! in_array($type, ['sale', 'rent'], true)) {
                throw new InvalidArgumentException('Unknown transaction type.');
            }
            $propertyId = $details['property_id'] ?? null;
            if ($propertyId && ! Property::withTrashed()->whereKey($propertyId)->exists()) {
                throw new InvalidArgumentException('That property does not exist.');
            }
            $won = ['won_transaction_type' => $type, 'won_property_id' => $propertyId ?: null, 'won_value' => filled($details['value'] ?? null) ? $details['value'] : null];
        }

        DB::transaction(function () use ($assignment, $from, $to, $by, $reason, $note, $won) {
            $now = now();
            $assignment->forceFill([
                'pipeline_stage' => $to,
                'stage_changed_at' => $now,
                'responded_at' => $assignment->responded_at ?? $now,
                'first_contact_at' => $assignment->first_contact_at ?? ($to->isContactMade() ? $now : null),
                'qualified_at' => $assignment->qualified_at ?? ($to->isQualifiedOrLater() ? $now : null),
                'closed_at' => $to->isClosed() ? $now : null,
                'outcome_reason' => $to->needsReason() ? ($reason ?? $to->value) : null,
                'outcome_note' => $to->needsReason() || $to === PipelineStage::Won ? $note : null,
                'won_transaction_type' => $won['won_transaction_type'] ?? null,
                'won_property_id' => $won['won_property_id'] ?? null,
                'won_value' => $won['won_value'] ?? null,
            ]);
            $this->touch($assignment);

            $props = array_filter(['from' => $from->value, 'to' => $to->value, 'reason' => $to->needsReason() ? ($reason ?? null) : null]);
            $this->record($assignment, 'stage_changed', $by, $props);
            AuditLogger::record('lead.pipeline_changed', $assignment->lead, ['assignment_id' => $assignment->id, 'dealer_id' => $assignment->dealer_id] + $props, $by->id);
            $this->syncInquiry($assignment, $to);
        });
    }

    public function setPriority(LeadAssignment $assignment, string $priority, User $by): void
    {
        $this->guardActive($assignment);
        if (! array_key_exists($priority, config('meezan.crm.priorities'))) {
            throw new InvalidArgumentException('Unknown priority.');
        }
        if ($assignment->priority === $priority) {
            return;
        }
        $from = $assignment->priority;
        $assignment->priority = $priority;
        $this->touch($assignment);
        $this->record($assignment, 'priority_changed', $by, ['from' => $from, 'to' => $priority]);
    }

    public function addNote(LeadAssignment $assignment, string $body, User $by): LeadNote
    {
        $this->guardActive($assignment);
        $note = new LeadNote(['body' => trim($body)]);
        $note->lead_assignment_id = $assignment->id;
        $note->author_id = $by->id;
        $note->save();
        $this->touch($assignment);
        $this->record($assignment, 'note_added', $by, ['note_id' => $note->id]);

        return $note;
    }

    public function updateNote(LeadNote $note, string $body, User $by): void
    {
        if ($note->author_id !== $by->id) {
            throw new InvalidArgumentException('Only the author can edit a note.');
        }
        $note->forceFill(['body' => trim($body), 'edited_at' => now()])->save();
        $this->record($note->assignment, 'note_edited', $by, ['note_id' => $note->id]);
    }

    /** @param  array{type: string, due_at: string|Carbon, priority?: ?string, notes?: ?string}  $data */
    public function createFollowUp(LeadAssignment $assignment, array $data, User $by): LeadFollowUp
    {
        $this->guardActive($assignment);
        $followUp = new LeadFollowUp([
            'type' => $data['type'],
            'priority' => $data['priority'] ?? 'normal',
            'due_at' => LocalTime::fromInput($data['due_at']),
            'notes' => $data['notes'] ?? null,
        ]);
        $followUp->lead_assignment_id = $assignment->id;
        $followUp->dealer_id = $assignment->dealer_id;
        $followUp->save();
        $this->touch($assignment);
        $this->record($assignment, 'follow_up_created', $by, ['follow_up_id' => $followUp->id, 'type' => $followUp->type, 'due_at' => $followUp->due_at->toIso8601String()]);

        return $followUp;
    }

    public function finishFollowUp(LeadFollowUp $followUp, string $outcome, User $by): void
    {
        if ($followUp->status !== 'pending' || ! in_array($outcome, ['completed', 'cancelled'], true)) {
            throw new InvalidArgumentException('This follow-up is already '.$followUp->status.'.');
        }
        $followUp->forceFill(['status' => $outcome, 'completed_at' => $outcome === 'completed' ? now() : null])->save();
        $assignment = $followUp->assignment;
        $this->touch($assignment);
        $this->record($assignment, 'follow_up_'.$outcome, $by, ['follow_up_id' => $followUp->id, 'was_overdue' => $followUp->due_at->isPast() ?: null]);
    }

    /** @param  array{type: string, starts_at: string|Carbon, duration_minutes?: ?int, property_id?: ?int, location?: ?string, notes?: ?string}  $data */
    public function scheduleAppointment(LeadAssignment $assignment, array $data, User $by): LeadAppointment
    {
        $this->guardActive($assignment);
        $propertyId = $this->checkedProperty($data['property_id'] ?? null);

        return DB::transaction(function () use ($assignment, $data, $by, $propertyId) {
            $appointment = new LeadAppointment([
                'type' => $data['type'],
                'starts_at' => LocalTime::fromInput($data['starts_at']),
                'duration_minutes' => $data['duration_minutes'] ?? 30,
                'location' => $data['location'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);
            $appointment->lead_assignment_id = $assignment->id;
            $appointment->dealer_id = $assignment->dealer_id;
            $appointment->property_id = $propertyId;
            $appointment->save();

            $this->touch($assignment);
            $this->record($assignment, 'appointment_scheduled', $by, ['appointment_id' => $appointment->id, 'type' => $appointment->type, 'starts_at' => $appointment->starts_at->toIso8601String(), 'property_id' => $propertyId]);
            AuditLogger::record('lead.appointment_scheduled', $assignment->lead, ['assignment_id' => $assignment->id, 'appointment_id' => $appointment->id, 'type' => $appointment->type], $by->id);

            // A scheduled viewing is evidence of the stage; earlier stages move forward, later ones are left alone.
            if ($appointment->type === 'viewing' && in_array($assignment->pipeline_stage, [PipelineStage::Contacted, PipelineStage::Qualified], true)) {
                $this->changeStage($assignment, PipelineStage::ViewingScheduled, $by);
            }

            return $appointment;
        });
    }

    /** Reschedule or edit details of a scheduled appointment. */
    public function updateAppointment(LeadAppointment $appointment, array $data, User $by): void
    {
        if ($appointment->status !== 'scheduled') {
            throw new InvalidArgumentException('Only a scheduled appointment can be changed.');
        }
        $before = $appointment->starts_at->toIso8601String();
        $appointment->fill(array_intersect_key($data, array_flip(['type', 'duration_minutes', 'location', 'notes'])));
        if (isset($data['starts_at'])) {
            $appointment->starts_at = LocalTime::fromInput($data['starts_at']);
        }
        if (array_key_exists('property_id', $data)) {
            $appointment->property_id = $this->checkedProperty($data['property_id']);
        }
        if ($appointment->isDirty('starts_at')) {
            $appointment->reminder_sent_at = null;
        }
        $appointment->save();
        $this->touch($appointment->assignment);
        $this->record($appointment->assignment, 'appointment_updated', $by, ['appointment_id' => $appointment->id, 'from' => $before, 'to' => $appointment->starts_at->toIso8601String()]);
        AuditLogger::record('lead.appointment_updated', $appointment->assignment->lead, ['appointment_id' => $appointment->id, 'from' => $before, 'to' => $appointment->starts_at->toIso8601String()], $by->id);
    }

    public function setAppointmentStatus(LeadAppointment $appointment, string $status, ?string $reason, User $by): void
    {
        if ($appointment->status !== 'scheduled' || ! in_array($status, ['completed', 'cancelled', 'no_show'], true)) {
            throw new InvalidArgumentException('This appointment is already '.str_replace('_', ' ', $appointment->status).'.');
        }
        $appointment->forceFill(['status' => $status, 'status_reason' => $reason])->save();
        $this->touch($appointment->assignment);
        $this->record($appointment->assignment, 'appointment_'.$status, $by, ['appointment_id' => $appointment->id]);
        AuditLogger::record('lead.appointment_'.$status, $appointment->assignment->lead, ['appointment_id' => $appointment->id, 'reason' => $reason], $by->id);
    }

    /** Recommend a real, live listing. Each listing once per lead. */
    public function recommend(LeadAssignment $assignment, int $propertyId, ?string $note, User $by): LeadRecommendation
    {
        $this->guardActive($assignment);
        $property = Property::published()->find($propertyId);
        if (! $property) {
            throw new InvalidArgumentException('Only live listings on Meezan Property can be recommended.');
        }
        if ($assignment->recommendations()->where('property_id', $property->id)->exists()) {
            throw new InvalidArgumentException($property->reference.' is already recommended for this lead.');
        }

        $recommendation = new LeadRecommendation(['note' => $note]);
        $recommendation->lead_assignment_id = $assignment->id;
        $recommendation->property_id = $property->id;
        $recommendation->dealer_id = $assignment->dealer_id;
        $recommendation->save();
        $this->touch($assignment);
        $this->record($assignment, 'property_recommended', $by, ['property_id' => $property->id, 'reference' => $property->reference]);

        return $recommendation;
    }

    /** The dealer records what the buyer said; nothing is assumed. */
    public function updateRecommendation(LeadRecommendation $recommendation, string $status, User $by): void
    {
        if (! array_key_exists($status, LeadRecommendation::STATUSES)) {
            throw new InvalidArgumentException('Unknown recommendation status.');
        }
        $from = $recommendation->status;
        $recommendation->forceFill(['status' => $status])->save();
        $this->touch($recommendation->assignment);
        $this->record($recommendation->assignment, 'recommendation_updated', $by, ['property_id' => $recommendation->property_id, 'from' => $from, 'to' => $status]);
    }

    public function record(LeadAssignment $assignment, string $type, ?User $actor, array $properties = []): LeadActivity
    {
        $activity = new LeadActivity(['type' => $type, 'properties' => $properties ?: null]);
        $activity->lead_id = $assignment->lead_id;
        $activity->lead_assignment_id = $assignment->id;
        $activity->actor_id = $actor?->id;
        $activity->save();

        return $activity;
    }

    protected function touch(LeadAssignment $assignment): void
    {
        $assignment->forceFill(['last_activity_at' => now(), 'stale_flagged_at' => null])->save();
    }

    protected function guardActive(LeadAssignment $assignment): void
    {
        if (! $assignment->isActive()) {
            throw new InvalidArgumentException('This lead is no longer assigned to you.');
        }
    }

    protected function checkedProperty(?int $propertyId): ?int
    {
        if (! $propertyId) {
            return null;
        }
        if (! Property::where('status', PropertyStatus::Published)->whereKey($propertyId)->exists()) {
            throw new InvalidArgumentException('Choose a live listing for the appointment.');
        }

        return $propertyId;
    }

    /** A listing enquiry has a single dealer: keep its simple inquiry status in step with the CRM stage. */
    protected function syncInquiry(LeadAssignment $assignment, PipelineStage $to): void
    {
        $inquiry = $assignment->lead->inquiry;
        if (! $inquiry || $inquiry->recipient_id !== $assignment->dealer_id) {
            return;
        }
        $status = $to->isClosed() ? 'closed' : ($to === PipelineStage::New ? 'new' : 'contacted');
        $inquiry->forceFill(['status' => $status, 'responded_at' => $inquiry->responded_at ?? now()])->save();
    }
}
