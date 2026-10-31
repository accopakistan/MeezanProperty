<?php

namespace App\Services\Assistant;

use App\Enums\Purpose;
use App\Models\BuyerRequirement;
use App\Models\ContactConsent;
use App\Models\Conversation;
use App\Models\Lead;
use App\Models\User;
use App\Notifications\RequirementMatchedDealers;
use App\Services\AuditLogger;
use App\Services\Leads\LeadRouter;
use Illuminate\Support\Facades\DB;

/**
 * Turns a conversation's requirement into a saved buyer requirement and,
 * only on an explicit request with consent, into a lead routed to up to
 * config('meezan.leads.max_dealers_per_requirement') matching dealers.
 *
 * One requirement per conversation (updated, never duplicated), and the
 * existing LeadRouter keeps one lead per requirement.
 */
class RequirementSaver
{
    public function __construct(protected LeadRouter $router) {}

    /**
     * @param  array{name?: ?string, phone?: ?string}  $contact  only for buyers without an account (WhatsApp)
     */
    public function save(Conversation $conversation, ?User $user = null, array $contact = []): ?BuyerRequirement
    {
        $state = new RequirementState($conversation->state);
        $state->inferPurpose();
        if (! $state->isSubstantial()) {
            return null;
        }

        return DB::transaction(function () use ($conversation, $user, $contact, $state) {
            $requirement = $conversation->requirement
                // The same buyer saving the same kind of search again updates it.
                ?? ($user ? BuyerRequirement::where('user_id', $user->id)->where('status', 'active')
                    ->where('city', $state->get('city'))->where('purpose', $state->get('purpose'))
                    ->where(fn ($q) => $state->get('type') ? $q->where('type', $state->get('type')) : $q->whereNull('type'))
                    ->latest('id')->first() : null)
                ?? new BuyerRequirement;

            $c = $state->toCriteria();
            $requirement->fill([
                'title' => $requirement->title ?? 'From the AI assistant',
                'purpose' => $c->purpose ?? Purpose::Sale,
                'type' => $c->type,
                'city' => $c->city,
                'area_ids' => $c->areaIds ?: null,
                'min_price' => $c->minPrice,
                'max_price' => $c->maxPrice,
                'min_area_sqft' => $c->minAreaSqft,
                'bedrooms' => $c->bedrooms,
                'preferences' => array_filter([
                    'bathrooms' => $c->bathrooms, 'amenities' => $c->amenities, 'furnishing' => $c->furnishing,
                    'tags' => $c->tags, 'budget_approx' => $c->budgetApprox ?: null, 'purpose_assumed' => $state->get('purpose_inferred'),
                ]) ?: null,
                'contact_name' => $contact['name'] ?? $requirement->contact_name,
                'contact_phone' => $contact['phone'] ?? $requirement->contact_phone,
            ]);
            $requirement->user_id ??= $user?->id;
            $requirement->conversation_id = $conversation->id;
            $requirement->source = $conversation->channel === 'whatsapp' ? 'whatsapp' : 'ai';
            $created = ! $requirement->exists;
            $requirement->save();

            $conversation->setRelation('requirement', $requirement);
            $conversation->forceFill(['buyer_requirement_id' => $requirement->id, 'status' => $conversation->status === 'lead' ? 'lead' : 'qualified'])->save();
            AuditLogger::record($created ? 'requirement.created_by_ai' : 'requirement.updated_by_ai', $requirement, ['conversation_id' => $conversation->id], $user?->id);

            return $requirement;
        });
    }

    /** Record what the matcher found for a saved requirement (no extra query). */
    public function recordMatches(BuyerRequirement $requirement, int $count): void
    {
        $requirement->forceFill([
            'match_status' => $count > 0 ? 'matched' : 'no_match',
            'last_match_count' => $count,
            'matched_at' => $count > 0 ? now() : $requirement->matched_at,
        ])->save();
    }

    /**
     * Share the requirement with matching dealers. Requires the buyer's
     * explicit consent; returns the lead (existing one if already shared).
     *
     * @param  array{name?: ?string, phone?: ?string}  $contact
     * @return array{lead: ?Lead, requirement: ?BuyerRequirement, dealers: int}
     */
    public function connectDealers(Conversation $conversation, ?User $user, string $source, array $contact = []): array
    {
        $requirement = $this->save($conversation, $user, $contact);
        if (! $requirement) {
            return ['lead' => null, 'requirement' => null, 'dealers' => 0];
        }

        ContactConsent::record('share_with_dealers', $conversation->channel, $source, $user?->id, $contact['phone'] ?? $user?->phone ?? $user?->email, $conversation->id);

        $lead = $this->router->routeRequirement($requirement, true);
        $dealers = $lead?->activeAssignments()->count() ?? 0;

        $conversation->forceFill(['lead_id' => $lead?->id, 'status' => 'lead'])->save();
        if ($user && $dealers > 0) {
            $user->notify(new RequirementMatchedDealers($requirement, $dealers));
        }

        return ['lead' => $lead, 'requirement' => $requirement, 'dealers' => $dealers];
    }
}
