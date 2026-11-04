<?php

namespace App\Services\InvestmentIndex;

use App\Models\InvestmentLegalRecord;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * A project's legal status from its VERIFIED legal records (official
 * sources only), oldest to newest:
 *
 *  - excluded      a cancellation, illegal listing, restriction, or any
 *                  record of severity "serious" that no later record from
 *                  the same authority has cleared. Never recommended.
 *  - approved / partially_approved / pending
 *                  the weakest of each authority's latest current approval;
 *                  open disputes and warnings deduct from the legal score.
 *  - not_verified  no current verified approval. Never shown as approved.
 */
class LegalStatus
{
    /** Record types and statuses that clear an earlier problem from the same authority. */
    protected const CLEARING_TYPES = ['regularisation'];

    protected const CLEARING_STATUSES = ['cleared', 'approved'];

    protected const APPROVAL_ORDER = ['pending' => 0, 'partially_approved' => 1, 'approved' => 2];

    /**
     * @param  Collection<int, InvestmentLegalRecord>  $records  verified records of one project
     * @return array{status: string, label: string, score: ?float, eligible: bool, excluded: bool, reasons: list<string>, risks: list<array{points: int, text: string}>, records: Collection}
     */
    public static function resolve(Collection $records, ?CarbonInterface $today = null): array
    {
        $today ??= now();
        $config = config('investment_index.legal');
        $records = $records->sortBy(fn ($r) => [$r->effectiveDate()->timestamp, $r->id])->values();
        $reasons = [];
        $risks = [];

        $open = $records->reject(fn ($r) => static::isClearedLater($r, $records));

        $blocking = $open->filter(fn ($r) => in_array($r->status, $config['exclude'], true) || $r->severity === 'serious');
        if ($blocking->isNotEmpty()) {
            foreach ($blocking as $r) {
                $reasons[] = "{$r->authority}: ".config('investment_index.legal.statuses.'.$r->status, $r->status)
                    .($r->severity === 'serious' ? ' (serious)' : '').($r->reference ? " — {$r->reference}" : '').' on '.$r->effectiveDate()->format('j M Y').'.';
            }

            return ['status' => 'excluded', 'label' => 'Excluded — legal', 'score' => 0.0, 'eligible' => false, 'excluded' => true,
                'reasons' => $reasons, 'risks' => [['points' => 10, 'text' => 'Confirmed serious legal or regulatory issue.']], 'records' => $records];
        }

        // Each authority's latest approval that is still current.
        $approvals = $records->filter(fn ($r) => in_array($r->record_type, ['approval', 'regularisation'], true) && isset(self::APPROVAL_ORDER[$r->status]))
            ->groupBy(fn ($r) => mb_strtolower($r->authority))
            ->map(fn ($g) => $g->last())
            ->filter(function ($r) use ($today, &$reasons) {
                if ($r->valid_until && $r->valid_until->lt($today)) {
                    $reasons[] = "{$r->authority} approval".($r->reference ? " {$r->reference}" : '').' expired on '.$r->valid_until->format('j M Y').'.';

                    return false;
                }

                return true;
            });

        if ($approvals->isEmpty()) {
            $reasons[] = 'No current approval from an official authority is on file.';

            return ['status' => 'not_verified', 'label' => 'Legal status not verified', 'score' => null, 'eligible' => false, 'excluded' => false,
                'reasons' => $reasons, 'risks' => [['points' => 3, 'text' => 'Legal status has not been verified.']], 'records' => $records];
        }

        $weakest = $approvals->sortBy(fn ($r) => self::APPROVAL_ORDER[$r->status])->first();
        $status = $weakest->status;
        foreach ($approvals as $r) {
            $reasons[] = "{$r->authority}: ".config('investment_index.legal.statuses.'.$r->status).($r->reference ? " ({$r->reference})" : '').', '.$r->effectiveDate()->format('j M Y').'.';
        }
        if ($status === 'pending') {
            $risks[] = ['points' => 3, 'text' => 'Approval is still pending.'];
        }

        $score = (float) $config['scores'][$status];
        $issues = $open->filter(fn ($r) => in_array($r->status, ['disputed', 'warning'], true) && in_array($r->severity, ['minor', 'material'], true));
        foreach ($issues as $r) {
            $score -= $config['deductions'][$r->severity];
            $text = "{$r->authority}: ".config('investment_index.legal.statuses.'.$r->status)." ({$r->severity})".($r->title ? " — {$r->title}" : '').'.';
            $reasons[] = $text;
            $risks[] = ['points' => $r->severity === 'material' ? 3 : 1, 'text' => $text];
        }

        return ['status' => $status, 'label' => config('investment_index.legal.statuses.'.$status), 'score' => max(0.0, $score),
            'eligible' => in_array($status, $config['eligible'], true), 'excluded' => false, 'reasons' => $reasons, 'risks' => $risks, 'records' => $records];
    }

    /** A problem is cleared by a later verified clearance or approval from the same authority. */
    protected static function isClearedLater(InvestmentLegalRecord $record, Collection $records): bool
    {
        if (in_array($record->record_type, ['approval', 'regularisation'], true)) {
            return false;
        }

        return $records->contains(fn ($later) => $later->id !== $record->id
            && mb_strtolower($later->authority) === mb_strtolower($record->authority)
            && $later->effectiveDate()->gt($record->effectiveDate())
            && (in_array($later->record_type, self::CLEARING_TYPES, true) || in_array($later->status, self::CLEARING_STATUSES, true)));
    }
}
