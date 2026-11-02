<?php

namespace App\Notifications;

use App\Models\LeadAssignment;
use App\Models\User;
use App\Notifications\Channels\WhatsAppChannel;
use App\Support\Format;
use App\Support\WhatsAppNumber;
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

    /** Also on WhatsApp for dealers who switched lead alerts on (consent is checked again by MessagePolicy). */
    public function via(object $notifiable): array
    {
        $channels = parent::via($notifiable);

        return $notifiable instanceof User && $notifiable->isActive() && $notifiable->notify_whatsapp && self::whatsAppNumber($notifiable)
            ? [...$channels, WhatsAppChannel::class]
            : $channels;
    }

    public static function whatsAppNumber(User $dealer): ?string
    {
        return WhatsAppNumber::normalize($dealer->dealerProfile?->whatsapp_number);
    }

    /**
     * The requirement only: no buyer name or number. The dealer opens the
     * dashboard to see the lead. Outside the 24-hour window this is sent as
     * the approved "dealer_lead" template (requirement, location, budget).
     *
     * @return array<string, mixed>|null
     */
    public function toWhatsApp(object $notifiable): ?array
    {
        $to = $notifiable instanceof User ? self::whatsAppNumber($notifiable) : null;
        if (! $to) {
            return null;
        }
        $lead = $this->assignment->loadMissing(['lead.requirement', 'lead.property:id,title,reference', 'lead.area:id,name'])->lead;
        $requirement = $lead?->requirement;

        $wanted = $lead?->property
            ? 'Enquiry about '.$lead->property->reference
            : (collect([
                $requirement?->min_area_sqft ? round($requirement->min_area_sqft / config('meezan.marla_sqft')).' Marla' : null,
                $lead?->type?->label(),
                $lead?->purpose?->label(),
            ])->filter()->implode(' ') ?: 'Buyer requirement');
        $location = collect([$lead?->area?->name, $requirement?->city])->filter()->unique()->implode(', ') ?: 'Not specified';
        $budget = $requirement?->max_price ? 'Up to PKR '.Format::compactPrice($requirement->max_price) : 'Not specified';
        $template = config('meezan.whatsapp.templates.dealer_lead');

        return [
            'to' => $to,
            'purpose' => 'dealer_lead',
            'dedupe_key' => 'lead-alert:'.$this->assignment->id,
            'lead_id' => $lead?->id,
            'body' => "New qualified lead available.\n\nRequirement:\n{$wanted}\n\nLocation:\n{$location}\n\nBudget:\n{$budget}\n\nOpen your Meezan Property dashboard to review the lead:\n".route('dealer.leads'),
            'template' => $template['name'] ? ['name' => $template['name'], 'language' => $template['language'], 'parameters' => [$wanted, $location, $budget]] : null,
        ];
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
