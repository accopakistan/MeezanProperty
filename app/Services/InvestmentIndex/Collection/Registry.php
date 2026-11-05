<?php

namespace App\Services\InvestmentIndex\Collection;

use App\Models\InvestmentException;
use App\Models\InvestmentLegalRecord;
use App\Models\InvestmentRegistryEntry;
use App\Models\Project;
use Illuminate\Support\Collection;

/**
 * Links official list entries to real projects and turns matched entries
 * into verified legal records (official source, list URL, collection time,
 * the row itself as excerpt). Matching is automatic only for a single,
 * near-exact name in the same city; anything less certain goes to staff.
 * Staff decisions (manual / rejected) are never overwritten.
 */
class Registry
{
    /** Project categories an official housing-scheme list can describe. */
    public const SCHEME_CATEGORIES = ['residential_society'];

    /**
     * @param  bool  $record  write the legal record for a new match (the collector records, and counts, itself)
     * @return string the entry's match status after matching
     */
    public function match(InvestmentRegistryEntry $entry, ?Collection $candidates = null, bool $record = true): string
    {
        if (in_array($entry->match_status, ['manual', 'rejected'], true)) {
            return $entry->match_status;
        }
        $candidates ??= $this->candidates($entry->city, $entry->raw['_category'] ?? null);
        $auto = (float) config('investment_index.collection.match_auto');
        $review = (float) config('investment_index.collection.match_review');

        $scored = $candidates->map(fn (Project $p) => ['project' => $p, 'score' => SchemeNames::similarity($entry->scheme_name, $p->name)])
            ->filter(fn ($c) => $c['score'] >= $review)->sortByDesc('score')->values();
        $sure = $scored->filter(fn ($c) => $c['score'] >= $auto);

        if ($sure->count() === 1) {
            $entry->forceFill(['project_id' => $sure[0]['project']->id, 'match_status' => 'matched', 'match_score' => $sure[0]['score']])->save();
            // A question asked before this project existed has its answer now.
            InvestmentException::where('registry_entry_id', $entry->id)->where('type', 'ambiguous_match')->where('status', 'open')
                ->update(['status' => 'resolved', 'resolved_at' => now(), 'resolution' => 'Matched automatically to '.$sure[0]['project']->name.'.']);
            $record && $this->record($entry);

            return 'matched';
        }
        if ($scored->isNotEmpty()) {
            $entry->forceFill(['project_id' => null, 'match_status' => 'ambiguous', 'match_score' => $scored[0]['score']])->save();
            InvestmentException::raise('ambiguous_match', "Which project is “{$entry->scheme_name}”?",
                $entry->authority.' '.InvestmentRegistryEntry::LIST_TYPES[$entry->list_type].' entry could be: '
                .$scored->take(5)->map(fn ($c) => $c['project']->name.' ('.round($c['score'] * 100).'%)')->implode(', ').'.',
                ['source_id' => $entry->source_id, 'registry_entry_id' => $entry->id], ['key' => 'entry-'.$entry->id, 'candidates' => $scored->take(5)->map(fn ($c) => $c['project']->id)->all()]);

            return 'ambiguous';
        }
        $entry->forceFill(['project_id' => null, 'match_status' => 'unmatched', 'match_score' => null])->save();

        return 'unmatched';
    }

    /** Staff decision: this entry is this project (or, with null, not a project here). */
    public function decide(InvestmentRegistryEntry $entry, ?Project $project, int $userId): void
    {
        $entry->forceFill(['project_id' => $project?->id, 'match_status' => $project ? 'manual' : 'rejected', 'match_score' => null, 'matched_by' => $userId])->save();
        InvestmentException::where('registry_entry_id', $entry->id)->where('type', 'ambiguous_match')->where('status', 'open')
            ->update(['status' => 'resolved', 'resolved_by' => $userId, 'resolved_at' => now(), 'resolution' => $project ? 'Matched to '.$project->name.'.' : 'Not a project on Meezan Property.']);
        $project && $this->record($entry);
    }

    /** Match unmatched and ambiguous entries in a project's city against it (a new or renamed project). */
    public function matchProject(Project $project): int
    {
        if ($project->is_demo || ! in_array($project->category, self::SCHEME_CATEGORIES, true)) {
            return 0;
        }
        $candidates = $this->candidates($project->city);
        $matched = 0;
        InvestmentRegistryEntry::whereIn('match_status', ['unmatched', 'ambiguous'])->whereNull('removed_at')
            ->where('city', $project->city)->get()
            ->filter(fn ($e) => SchemeNames::similarity($e->scheme_name, $project->name) >= (float) config('investment_index.collection.match_review'))
            ->each(function ($e) use ($candidates, &$matched) {
                $matched += $this->match($e, $candidates) === 'matched' ? 1 : 0;
            });

        return $matched;
    }

    /**
     * The verified legal record for a matched entry (created once; a change
     * of status or date on the list creates a new record, so history stays).
     *
     * @return ?InvestmentLegalRecord the record created, null if none was needed
     */
    public function record(InvestmentRegistryEntry $entry): ?InvestmentLegalRecord
    {
        if (! $entry->isMatched() || ! $entry->status || $entry->removed_at) {
            return null;
        }
        $entry->loadMissing(['source', 'project']);
        $type = match (true) {
            $entry->list_type === 'illegal' => 'illegal_listing',
            $entry->status === 'cancelled' => 'cancellation',
            default => 'approval',
        };
        $record = new InvestmentLegalRecord([
            'project_id' => $entry->project_id, 'authority' => $entry->authority, 'record_type' => $type, 'status' => $entry->status,
            'severity' => $type === 'approval' ? 'none' : 'serious',
            'reference' => mb_substr($entry->reference ?: $entry->authority.' list entry '.$entry->external_key, 0, 160),
            'title' => mb_substr($entry->scheme_name.($entry->list_type === 'authority_scheme' ? ' (developed by '.$entry->authority.')' : ''), 0, 250),
            'issued_on' => $entry->issued_on, 'source_id' => $entry->source_id, 'source_url' => $entry->source_url, 'collected_at' => $entry->last_seen_at,
            'content_hash' => $entry->content_hash, 'excerpt' => mb_substr(json_encode($entry->raw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 0, 2000),
            'collector' => $entry->source?->collector, 'verification_method' => 'official_registry',
            'meta' => ['registry_entry_id' => $entry->id, 'raw_status' => $entry->raw_status, 'list_type' => $entry->list_type],
        ]);
        // Lists read by eye from a scan wait for a person; the rest are copied exactly from the authority's own data.
        $review = (bool) ($entry->raw['_review'] ?? false);
        $record->forceFill(['verification_status' => $review ? 'unverified' : 'verified']);
        if ($review) {
            $record->verification_method = 'awaiting_staff_review';
        }
        $fingerprint = (fn () => $this->fingerprintFor())->call($record);
        if (InvestmentLegalRecord::where('fingerprint', $fingerprint)->exists()) {
            return null;
        }
        $record->save();

        // Approved by one official list and listed illegal by another: excluded until staff look.
        $types = InvestmentLegalRecord::verified()->where('project_id', $entry->project_id)->pluck('record_type')->unique();
        if ($types->contains('illegal_listing') && $types->contains('approval')) {
            InvestmentException::raise('legal_conflict', "Official records disagree on {$entry->project?->name}",
                'The project appears both as approved and on an illegal-schemes list. It is excluded from recommendations until an official clearance is on file.',
                ['project_id' => $entry->project_id, 'source_id' => $entry->source_id], ['key' => 'conflict-'.$entry->project_id]);
        }

        return $record;
    }

    /** @return Collection<int, Project> real projects an official scheme list could describe */
    protected function candidates(?string $city, ?string $category = null): Collection
    {
        return Project::where('is_demo', false)->whereIn('category', $category ? [$category] : self::SCHEME_CATEGORIES)
            ->when($city, fn ($q) => $q->where('city', $city))->get(['id', 'name', 'city', 'category']);
    }
}
