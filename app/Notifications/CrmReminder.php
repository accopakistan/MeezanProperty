<?php

namespace App\Notifications;

use App\Models\LeadAppointment;
use App\Models\LeadAssignment;
use App\Models\LeadFollowUp;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * To a dealer about their own CRM: a follow-up is due or overdue, an
 * appointment is coming up, a lead has had no activity for a while, or
 * staff moved a lead away. In-app always; email per the dealer's
 * preference once mail is configured. No buyer contact details and no
 * note text are included.
 */
class CrmReminder extends QueuedMailNotification
{
    public function __construct(public string $event, public Model $subject)
    {
        $this->afterCommit();
    }

    public function inApp(object $notifiable): ?array
    {
        $assignment = $this->assignment();
        $lead = $assignment?->lead;
        $what = $lead ? collect([$lead->type?->label(), $lead->area?->name ? 'in '.$lead->area->name : null])->filter()->implode(' ') : '';
        $what = $what !== '' ? $what : 'your lead';

        [$title, $body, $icon] = match ($this->event) {
            'follow_up_due' => ['Follow-up due: '.$this->subject->typeLabel(), 'For '.$what.', due '.$this->subject->due_at->localTime()->format('j M, g:i a').'.', 'clock'],
            'follow_up_overdue' => ['Overdue follow-up: '.$this->subject->typeLabel(), 'For '.$what.', was due '.$this->subject->due_at->localTime()->format('j M, g:i a').'.', 'alert'],
            'appointment_upcoming' => [$this->subject->typeLabel().' at '.$this->subject->starts_at->localTime()->format('g:i a'), 'With the buyer for '.$what.' on '.$this->subject->starts_at->localTime()->format('j M').'.', 'calendar'],
            'lead_stale' => ['Lead needs attention', 'No activity on '.$what.' for '.config('meezan.crm.stale_after_days').' days.', 'alert'],
            'assignment_removed' => ['A lead was moved to another dealer', 'Meezan Property staff reassigned '.$what.'. It is no longer in your CRM.', 'inbox'],
            default => ['CRM update', '', 'bell'],
        };

        return [
            'title' => $title,
            'body' => $body,
            'url' => $assignment && $this->event !== 'assignment_removed' ? route('dealer.crm.leads.show', $assignment) : route('dealer.crm'),
            'icon' => $icon,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $entry = $this->inApp($notifiable);

        return (new MailMessage)
            ->subject('Meezan Property: '.$entry['title'])
            ->greeting($entry['title'])
            ->line($entry['body'])
            ->action('Open your CRM', $entry['url'])
            ->salutation('Meezan Property');
    }

    protected function assignment(): ?LeadAssignment
    {
        $assignment = match (true) {
            $this->subject instanceof LeadAssignment => $this->subject,
            $this->subject instanceof LeadFollowUp, $this->subject instanceof LeadAppointment => $this->subject->assignment,
            default => null,
        };

        return $assignment?->loadMissing('lead.area:id,name');
    }
}
