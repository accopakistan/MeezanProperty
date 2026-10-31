<?php

namespace App\Notifications;

use App\Models\LeadAssignment;
use App\Support\Format;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * To a dealer when a buyer requirement lead is routed to them, or an admin
 * reassigns a lead. Only the requirement is summarised; buyer contact
 * details stay in the dashboard.
 */
class LeadAssigned extends QueuedMailNotification
{
    public function __construct(public LeadAssignment $assignment)
    {
        $this->afterCommit();
    }

    public function inApp(object $notifiable): ?array
    {
        $lead = $this->assignment->loadMissing(['lead.property:id,title,reference', 'lead.area:id,name'])->lead;

        return [
            'title' => 'New buyer lead'.($lead?->area?->name ? ' in '.$lead->area->name : ''),
            'body' => $lead?->property
                ? 'Inquiry about '.$lead->property->reference
                : (collect([$lead?->type?->label(), $lead?->purpose?->label()])->filter()->implode(' · ') ?: 'Buyer requirement'),
            'url' => route('dealer.leads'),
            'icon' => 'users',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $lead = $this->assignment->loadMissing(['lead.requirement', 'lead.property:id,title,reference', 'lead.area:id,name'])->lead;
        $requirement = $lead?->requirement;

        $summary = collect([
            $lead?->type?->label(),
            $lead?->purpose?->label(),
            $lead?->area?->name ? 'in '.$lead->area->name : null,
            $requirement?->bedrooms ? $requirement->bedrooms.'+ bedrooms' : null,
            $requirement?->max_price ? 'budget up to PKR '.Format::compactPrice($requirement->max_price) : null,
        ])->filter()->implode(' · ');

        $message = (new MailMessage)
            ->subject('New buyer lead'.($lead?->area?->name ? ' in '.$lead->area->name : ''))
            ->greeting('Assalam o Alaikum '.$notifiable->name.',')
            ->line('A buyer lead has been assigned to you'.($this->assignment->reason ? ' ('.$this->assignment->reason.')' : '').'.');

        if ($lead?->property) {
            $message->line('Listing: **'.$lead->property->title.'** ('.$lead->property->reference.')');
        }
        if ($summary !== '') {
            $message->line('Looking for: '.$summary);
        }

        return $message
            ->action('Open your leads', route('dealer.leads'))
            ->line('The buyer agreed to share their details with matched dealers. Please contact them promptly.')
            ->salutation('Meezan Property')
            ->line($this->preferencesLine());
    }
}
