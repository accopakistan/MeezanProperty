<?php

namespace App\Services\InvestmentIndex;

use App\Models\InvestmentIndexChange;
use App\Models\InvestmentIndexRun;
use Illuminate\Support\Carbon;

/**
 * Records how each project's ranking changed from the previous published
 * run, with the reasons: factor scores that moved (and why), legal status,
 * confidence, eligibility, methodology version, or other projects moving.
 * Rows are only ever added.
 */
class ChangeHistory
{
    /** @return int changes recorded */
    public static function record(InvestmentIndexRun $run, ?InvestmentIndexRun $previous, array $rows): int
    {
        $before = $previous ? $previous->scores()->get()->keyBy('project_id') : collect();
        $after = collect($rows)->keyBy('project_id');
        $now = now();
        $changes = [];

        foreach ($after->keys()->merge($before->keys())->unique() as $projectId) {
            $old = $before->get($projectId);
            $new = $after->get($projectId);
            $change = static::kind($old, $new);
            if (! $change) {
                continue;
            }
            $changes[] = [
                'run_id' => $run->id, 'previous_run_id' => $previous?->id, 'project_id' => $projectId,
                'category' => $new['category'] ?? $old->category, 'change' => $change,
                'from_status' => $old?->status, 'to_status' => $new['status'] ?? null,
                'from_rank' => $old?->category_rank, 'to_rank' => $new['category_rank'] ?? null,
                'from_score' => $old?->score, 'to_score' => $new['score'] ?? null,
                'from_overall_rank' => $old?->overall_rank, 'to_overall_rank' => $new['overall_rank'] ?? null,
                'reasons' => json_encode(static::reasons($old, $new, $previous?->methodology_version, $run->methodology_version)),
                'created_at' => $now, 'updated_at' => $now,
            ];
        }
        foreach (array_chunk($changes, 200) as $chunk) {
            InvestmentIndexChange::insert($chunk);
        }

        return count($changes);
    }

    protected static function kind($old, ?array $new): ?string
    {
        $wasRanked = $old?->status === 'ranked';
        $isRanked = ($new['status'] ?? null) === 'ranked';

        return match (true) {
            ! $wasRanked && $isRanked => 'entered',
            $wasRanked && ! $isRanked => 'left',
            $wasRanked && $isRanked && $new['category_rank'] < $old->category_rank => 'moved_up',
            $wasRanked && $isRanked && $new['category_rank'] > $old->category_rank => 'moved_down',
            $wasRanked && $isRanked && (abs((float) $new['score'] - (float) $old->score) >= 0.1 || $new['overall_rank'] !== $old->overall_rank) => 'rescored',
            ! $old && $new !== null => 'status', // first assessment, not ranked
            $old && $new && $old->status !== $new['status'] => 'status',
            default => null,
        };
    }

    /** @return list<string> */
    protected static function reasons($old, ?array $new, ?int $fromVersion, int $toVersion): array
    {
        $reasons = [];
        $statuses = config('investment_index.statuses');
        if ($fromVersion && $fromVersion !== $toVersion) {
            $reasons[] = "Methodology changed from version {$fromVersion} to {$toVersion}.";
        }
        if (! $new) {
            return array_merge($reasons, ['The project is no longer assessed (unpublished, out of scope or removed).']);
        }
        if (! $old) {
            $reasons[] = 'First assessment: '.$statuses[$new['status']].'.';
        } elseif ($old->status !== $new['status']) {
            $reasons[] = 'Status changed from '.$statuses[$old->status].' to '.$statuses[$new['status']].'.';
        }
        if ($old && $old->legal_status !== $new['legal_status']) {
            $reasons[] = 'Legal status changed from '.static::legalLabel($old->legal_status).' to '.static::legalLabel($new['legal_status']).'.';
        }

        $oldFactors = $old?->factors ?? [];
        foreach ($new['factors'] as $key => $f) {
            if (($f['display_only'] ?? false) || ! $old) {
                continue;
            }
            $was = $oldFactors[$key]['score'] ?? null;
            if ($was === $f['score'] || ($was !== null && $f['score'] !== null && abs($was - $f['score']) < 0.1)) {
                continue;
            }
            $reasons[] = $f['label'].': '.($was === null ? 'no verified data' : $was.' ('.($oldFactors[$key]['value'] ?? '').')')
                .' → '.($f['score'] === null ? 'no verified data' : $f['score'].' ('.$f['value'].')')
                .($f['observed_on'] ? ', verified record of '.Carbon::parse($f['observed_on'])->format('j M Y') : '').'.';
        }
        if ($old && abs($old->confidence_score - $new['confidence_score']) >= 5) {
            $reasons[] = 'Data Confidence Score changed from '.$old->confidence_score.' to '.$new['confidence_score'].'.';
        }
        if (! array_filter($reasons, fn ($r) => ! str_starts_with($r, 'Methodology')) && $old?->category_rank !== ($new['category_rank'] ?? null)) {
            $reasons[] = 'The project\'s own evidence did not change; other projects in the category changed.';
        }
        if ($new['status'] !== 'ranked') {
            $reasons = array_merge($reasons, array_values(array_filter($new['reasons'], fn ($r) => ! str_starts_with($r, 'Confidence:'))));
        }

        return array_values(array_unique($reasons));
    }

    protected static function legalLabel(string $status): string
    {
        return match ($status) {
            'not_verified' => 'not verified',
            'excluded' => 'excluded',
            default => mb_strtolower(config('investment_index.legal.statuses.'.$status, $status)),
        };
    }
}
