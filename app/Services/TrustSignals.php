<?php

namespace App\Services;

use App\Enums\PropertyStatus;
use App\Models\Property;

/**
 * Internal review signals for a listing. Neutral by design: a signal says
 * what to check ("Duplicate suspected", "Requires review"), never that the
 * dealer did anything wrong, and none of it is shown to buyers.
 */
class TrustSignals
{
    /** Submissions by one dealer in 24 hours above which review is suggested. */
    public const RAPID_SUBMISSIONS = 15;

    /** Price change since the last published price that is worth a look. */
    public const PRICE_CHANGE = 0.30;

    public function __construct(protected DuplicateDetector $duplicates) {}

    /**
     * Compute and store the signals reviewers need when a listing is
     * submitted (duplicates, burst of submissions, big price change,
     * location mismatch). Sets flagged_at when anything needs a look.
     */
    public function atSubmission(Property $property): array
    {
        $this->duplicates->record($property);
        $flags = collect($property->review_flags ?? [])->except(['rapid_submissions', 'price_change'])->all();

        $recent = Property::where('user_id', $property->user_id)->whereKeyNot($property->id)
            ->where('submitted_at', '>=', now()->subDay())->count() + 1;
        if ($recent > self::RAPID_SUBMISSIONS) {
            $flags['rapid_submissions'] = $recent;
        }

        $lastPublished = $property->priceChanges()->latest('changed_at')->value('old_price');
        if ($lastPublished && $property->price && abs($property->price - $lastPublished) / $lastPublished > self::PRICE_CHANGE) {
            $flags['price_change'] = ['from' => (int) $lastPublished, 'to' => (int) $property->price];
        }

        $property->review_flags = $flags ?: null;
        $property->flagged_at = ($flags || $property->location_status === 'flagged') ? ($property->flagged_at ?? now()) : null;

        return $flags;
    }

    /**
     * Everything a reviewer should see, in a fixed order.
     *
     * @return list<array{key: string, label: string, tone: string, detail: ?string}>
     */
    public function for(Property $property): array
    {
        $profile = $property->dealer?->dealerProfile;
        $flags = $property->review_flags ?? [];
        $completeness = ListingCompleteness::for($property);
        $signals = [];

        $signals[] = $profile?->isVerified()
            ? ['key' => 'dealer', 'label' => 'Verified dealer', 'tone' => 'success', 'detail' => null]
            : ['key' => 'dealer', 'label' => 'Dealer verification required', 'tone' => 'warning', 'detail' => $profile?->verification_status->label()];

        $signals[] = match ($property->location_status) {
            'verified' => ['key' => 'location', 'label' => 'Location verified', 'tone' => 'success', 'detail' => null],
            'exact' => ['key' => 'location', 'label' => 'Exact location supplied', 'tone' => 'info', 'detail' => 'Not yet checked by a reviewer'],
            'flagged' => ['key' => 'location', 'label' => 'Location requires review', 'tone' => 'warning', 'detail' => $property->location_flag_reason],
            'approximate' => ['key' => 'location', 'label' => 'Approximate area location', 'tone' => 'neutral', 'detail' => 'No exact pin'],
            default => ['key' => 'location', 'label' => 'No map location', 'tone' => 'neutral', 'detail' => null],
        };

        $signals[] = ['key' => 'completeness', 'label' => 'Listing '.$completeness['score'].'% complete', 'tone' => $completeness['ready'] ? 'success' : 'warning',
            'detail' => $completeness['missing'] ? implode('; ', array_slice($completeness['missing'], 0, 3)) : null];

        foreach ($flags['duplicates'] ?? [] as $dup) {
            $signals[] = ['key' => 'duplicate', 'label' => 'Duplicate suspected: '.$dup['reference'], 'tone' => 'warning', 'detail' => implode(', ', $dup['reasons'])];
        }
        if (isset($flags['rapid_submissions'])) {
            $signals[] = ['key' => 'rapid', 'label' => 'Many submissions in 24 hours', 'tone' => 'warning', 'detail' => $flags['rapid_submissions'].' listings submitted by this dealer'];
        }
        if (isset($flags['price_change'])) {
            $signals[] = ['key' => 'price', 'label' => 'Large price change', 'tone' => 'warning', 'detail' => number_format($flags['price_change']['from']).' → '.number_format($flags['price_change']['to']).' PKR'];
        }
        if ($reports = $property->reports()->where('status', 'open')->count()) {
            $signals[] = ['key' => 'reports', 'label' => $reports.' open '.str('report')->plural($reports), 'tone' => 'warning', 'detail' => null];
        }
        if ($flagged = $property->images()->whereIn('status', ['flagged', 'rejected'])->count()) {
            $signals[] = ['key' => 'images', 'label' => $flagged.' '.str('photo')->plural($flagged).' need attention', 'tone' => 'warning', 'detail' => null];
        }
        if ($property->status === PropertyStatus::ChangesRequested) {
            $signals[] = ['key' => 'changes', 'label' => 'Changes requested', 'tone' => 'warning', 'detail' => $property->rejection_reason];
        }

        return $signals;
    }
}
