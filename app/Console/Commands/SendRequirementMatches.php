<?php

namespace App\Console\Commands;

use App\Models\BuyerRequirement;
use App\Notifications\NewRequirementMatches;
use App\Services\Matching\MatchCriteria;
use App\Services\Matching\PropertyMatch;
use App\Services\Matching\PropertyMatcher;
use Illuminate\Console\Command;

/**
 * Tells buyers about listings published since their AI-saved requirement was
 * last checked. In-app always; email only through the usual account-email
 * preference. WhatsApp buyers get nothing here: proactive WhatsApp messages
 * need an approved template and opt-in, which are not set up yet.
 */
class SendRequirementMatches extends Command
{
    protected $signature = 'meezan:requirement-matches';

    protected $description = 'Notify buyers of new listings matching requirements saved through the AI assistant';

    public function handle(PropertyMatcher $matcher): int
    {
        $notified = 0;

        BuyerRequirement::with('user')
            ->where('status', 'active')
            ->whereIn('source', ['ai', 'whatsapp'])
            ->whereNotNull('user_id')
            ->chunkById(100, function ($requirements) use ($matcher, &$notified) {
                foreach ($requirements as $requirement) {
                    $since = $requirement->matched_at ?? $requirement->created_at;
                    $result = $matcher->match(MatchCriteria::fromRequirement($requirement), 10);

                    // Only listings that are new and not above budget.
                    $fresh = $result->matches
                        ->filter(fn (PropertyMatch $m) => $m->property->published_at?->gt($since) && $m->budgetStatus !== PropertyMatch::BUDGET_ABOVE)
                        ->values();

                    $requirement->forceFill(['matched_at' => now(), 'last_match_count' => $result->matches->count(), 'match_status' => $result->isEmpty() ? 'no_match' : 'matched'])->save();

                    if ($fresh->isNotEmpty() && $requirement->user?->isActive()) {
                        $requirement->user->notify(new NewRequirementMatches($requirement, $fresh->count()));
                        $notified++;
                    }
                }
            });

        $this->info("Notified {$notified} buyer(s).");

        return self::SUCCESS;
    }
}
