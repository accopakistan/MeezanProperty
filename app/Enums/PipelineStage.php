<?php

namespace App\Enums;

/**
 * A dealer's own sales stage for one lead (stored on the lead assignment,
 * so each dealer on a shared lead has an independent pipeline). Separate
 * from LeadStatus, which is the marketplace's routing status.
 */
enum PipelineStage: string
{
    case New = 'new';
    case ContactAttempted = 'contact_attempted';
    case Contacted = 'contacted';
    case Qualified = 'qualified';
    case ViewingScheduled = 'viewing_scheduled';
    case Negotiating = 'negotiating';
    case Won = 'won';
    case Lost = 'lost';
    case NoResponse = 'no_response';
    case Invalid = 'invalid';
    case Duplicate = 'duplicate';

    public function label(): string
    {
        return match ($this) {
            self::New => 'New',
            self::ContactAttempted => 'Contact attempted',
            self::Contacted => 'Contacted',
            self::Qualified => 'Qualified',
            self::ViewingScheduled => 'Viewing scheduled',
            self::Negotiating => 'Negotiating',
            self::Won => 'Won',
            self::Lost => 'Lost',
            self::NoResponse => 'No response',
            self::Invalid => 'Invalid',
            self::Duplicate => 'Duplicate',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::New => 'info',
            self::ContactAttempted, self::NoResponse => 'warning',
            self::Contacted, self::Qualified, self::ViewingScheduled, self::Negotiating => 'brand',
            self::Won => 'success',
            self::Lost => 'danger',
            self::Invalid, self::Duplicate => 'muted',
        };
    }

    /** Kanban column the stage is shown in. */
    public function column(): string
    {
        return match ($this) {
            self::New, self::ContactAttempted => 'new',
            self::Contacted, self::NoResponse => 'contacted',
            self::Qualified => 'qualified',
            self::ViewingScheduled => 'viewing',
            self::Negotiating => 'negotiating',
            self::Won => 'won',
            self::Lost, self::Invalid, self::Duplicate => 'lost',
        };
    }

    /** @return array<string, array{label: string, stage: self}> column key => label and the stage a card dropped there moves to */
    public static function columns(): array
    {
        return [
            'new' => ['label' => 'New', 'stage' => self::New],
            'contacted' => ['label' => 'Contacted', 'stage' => self::Contacted],
            'qualified' => ['label' => 'Qualified', 'stage' => self::Qualified],
            'viewing' => ['label' => 'Viewing', 'stage' => self::ViewingScheduled],
            'negotiating' => ['label' => 'Negotiating', 'stage' => self::Negotiating],
            'won' => ['label' => 'Won', 'stage' => self::Won],
            'lost' => ['label' => 'Lost / closed', 'stage' => self::Lost],
        ];
    }

    public function isClosed(): bool
    {
        return in_array($this, [self::Won, self::Lost, self::Invalid, self::Duplicate], true);
    }

    /** These stages need a reason (lost: a reason code; invalid/duplicate: an explanation). */
    public function needsReason(): bool
    {
        return in_array($this, [self::Lost, self::Invalid, self::Duplicate], true);
    }

    /** @return list<self> stages a lead in this stage may move to */
    public function allowedNext(): array
    {
        return match ($this) {
            self::New => [self::ContactAttempted, self::Contacted, self::NoResponse, self::Lost, self::Invalid, self::Duplicate],
            self::ContactAttempted => [self::Contacted, self::NoResponse, self::Lost, self::Invalid, self::Duplicate],
            self::Contacted => [self::Qualified, self::ViewingScheduled, self::Negotiating, self::NoResponse, self::Lost, self::Invalid, self::Duplicate],
            self::Qualified => [self::Contacted, self::ViewingScheduled, self::Negotiating, self::Won, self::NoResponse, self::Lost],
            self::ViewingScheduled => [self::Qualified, self::Negotiating, self::Won, self::NoResponse, self::Lost],
            self::Negotiating => [self::Qualified, self::ViewingScheduled, self::Won, self::NoResponse, self::Lost],
            self::NoResponse => [self::ContactAttempted, self::Contacted, self::Lost],
            // Closed stages can be reopened (a mistake, or the buyer came back).
            self::Lost => [self::Contacted, self::Qualified],
            self::Won => [self::Negotiating],
            self::Invalid, self::Duplicate => [self::Contacted],
        };
    }

    public function canMoveTo(self $to): bool
    {
        return in_array($to, $this->allowedNext(), true);
    }

    /** Stages at or beyond "contacted" (for the first-contact milestone). */
    public function isContactMade(): bool
    {
        return in_array($this, [self::Contacted, self::Qualified, self::ViewingScheduled, self::Negotiating, self::Won], true);
    }

    public function isQualifiedOrLater(): bool
    {
        return in_array($this, [self::Qualified, self::ViewingScheduled, self::Negotiating, self::Won], true);
    }
}
