<?php

namespace App\Services\Ai;

use App\Services\Matching\MatchCriteria;

/**
 * Converts a buyer's free-text message into structured match criteria.
 *
 * The default implementation is rule-based. An LLM-backed implementation can
 * be bound in AppServiceProvider (driven by MEEZAN_AI_DRIVER); it must only
 * extract criteria — listings always come from the PropertyMatcher.
 */
interface RequirementParser
{
    public function parse(string $message): MatchCriteria;
}
