<?php

namespace App\Services\InvestmentIndex\Collection;

use App\Models\InvestmentCollectionRun;
use App\Models\InvestmentException;
use App\Models\InvestmentRegistryEntry;
use App\Models\MarketSource;
use App\Services\AuditLogger;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Collects one authorized official source without human steps: read the
 * list, record every entry (first/last seen, content hash), match new or
 * changed entries to projects, write verified legal records for matches
 * (which recalculates the Index), and mark entries no longer listed.
 *
 * Exceptions go to staff instead of being guessed: a source that cannot
 * be read, a list that shrank suspiciously (nothing is marked removed), an
 * approval stage it cannot read, an uncertain match, a matched scheme that
 * left a list, or official lists that disagree.
 */
class CollectionService
{
    public function __construct(protected ListReader $reader, protected Registry $registry) {}

    /** @param  bool  $dryRun  read and report only; nothing is stored */
    public function collect(MarketSource $source, string $trigger = 'schedule', bool $dryRun = false, ?Fetcher $fetcher = null): InvestmentCollectionRun|array
    {
        $fetcher ??= new Fetcher;
        $preset = $source->collectorPreset();

        if ($dryRun) {
            $result = $this->reader->read($preset ?? throw new CollectionError('format', 'This source has no collector.'), $fetcher);

            return $this->preview($result, $preset, $fetcher);
        }

        $run = new InvestmentCollectionRun;
        $run->forceFill(['source_id' => $source->id, 'trigger' => $trigger, 'status' => 'running', 'started_at' => now()])->save();
        if (! $source->isCollectable()) {
            return $this->finish($run, $source, 'blocked', error: 'Not collected: the source must be active, linked to a collector and authorized (access basis recorded by an admin).');
        }

        try {
            $result = $this->reader->read($preset, $fetcher);
        } catch (CollectionError $e) {
            $type = $e->kind === 'format' ? 'format_change' : 'source_access';
            InvestmentException::raise($type, "{$source->name}: ".($type === 'format_change' ? 'the list could not be read' : 'the source could not be reached'),
                $e->getMessage(), ['source_id' => $source->id], ['key' => $e->kind]);

            return $this->finish($run, $source, $e->kind === 'robots' ? 'blocked' : 'failed', ['pages_fetched' => $fetcher->requests, 'exceptions_opened' => 1], $e->getMessage());
        } catch (Throwable $e) {
            return $this->failed($run, $source, $e, ['pages_fetched' => $fetcher->requests]);
        }

        $entries = $result['entries'];
        $previous = InvestmentCollectionRun::where('source_id', $source->id)->where('status', 'completed')->latest('id')->value('entries_found');
        $suspicious = count($entries) === 0 || ($previous && count($entries) < $previous * (float) config('investment_index.collection.min_entry_ratio'));
        $counts = ['pages_fetched' => $fetcher->requests, 'entries_found' => count($entries), 'entries_new' => 0, 'entries_changed' => 0,
            'entries_removed' => 0, 'matched' => 0, 'legal_records_created' => 0, 'exceptions_opened' => 0, 'content_hash' => $result['hash']];

        try {
            DB::transaction(function () use ($entries, $source, $preset, $result, $suspicious, $previous, &$counts) {
                $now = now();
                $seen = [];
                foreach ($entries as $e) {
                    $seen[] = $this->store($e, $source, $preset, $now, $counts);
                }
                if ($suspicious) {
                    InvestmentException::raise('format_change', "{$source->name}: far fewer entries than last time",
                        count($entries).' entries were read (last complete run: '.($previous ?? 0).'). Nothing was marked as removed. Check the source before the next run.', ['source_id' => $source->id], ['key' => 'shrunk']);
                    $counts['exceptions_opened']++;
                } elseif ($result['complete']) {
                    $this->markRemoved($source, $seen, $counts);
                }
            });
        } catch (Throwable $e) {
            return $this->failed($run, $source, $e, $counts);
        }

        $status = $suspicious || ! $result['complete'] ? 'partial' : 'completed';
        AuditLogger::record('investment_index.collected', $source, ['run' => $run->id, 'status' => $status] + $counts);

        return $this->finish($run, $source, $status, $counts);
    }

    /** @return int the entry's id */
    protected function store(array $e, MarketSource $source, array $preset, CarbonInterface $now, array &$counts): int
    {
        $status = OfficialStatus::for($preset['list_type'], $e['status_raw'], $preset['stages'] ?? null);
        $issued = OfficialStatus::date($e['date_raw'], $preset['list_type'] === 'approved' ? $e['status_raw'] : null);
        $hash = hash('sha256', json_encode($e['raw']));

        $entry = InvestmentRegistryEntry::firstOrNew(['source_id' => $source->id, 'external_key' => $e['key']]);
        $isNew = ! $entry->exists;
        $changed = ! $isNew && ($entry->content_hash !== $hash || $entry->removed_at !== null);
        $entry->forceFill([
            'authority' => $preset['authority'], 'list_type' => $preset['list_type'], 'scheme_name' => $e['name'],
            'normalized_name' => mb_substr(SchemeNames::normalize($e['name']), 0, 250), 'city' => $preset['city'] ?? null, 'location' => $e['location'],
            'raw_status' => $e['status_raw'] !== null ? mb_substr($e['status_raw'], 0, 250) : null, 'status' => $status, 'issued_on' => $issued,
            'reference' => $e['reference'] ? mb_substr($e['reference'], 0, 160) : null, 'source_url' => $e['url'], 'content_hash' => $hash, 'raw' => $e['raw'],
            'last_seen_at' => $now, 'removed_at' => null,
        ]);
        $entry->first_seen_at ??= $now;
        $entry->save();
        $counts['entries_new'] += $isNew ? 1 : 0;
        $counts['entries_changed'] += $changed ? 1 : 0;

        if ($status === null) {
            // Raised when the entry is new or its wording changed, not again on every daily run.
            if ($isNew || $changed) {
                InvestmentException::raise('unrecognised_status', "{$preset['authority']}: stage of “{$e['name']}” not recognised",
                    'The official wording "'.($e['status_raw'] ?? '').'" could not be read as an approval stage, so no legal status was recorded.',
                    ['source_id' => $source->id, 'registry_entry_id' => $entry->id], ['key' => 'entry-'.$entry->id]);
                $counts['exceptions_opened']++;
            }

            return $entry->id;
        }

        if ($isNew || $changed || in_array($entry->match_status, ['unmatched', 'ambiguous'], true)) {
            $before = $entry->match_status;
            $after = $this->registry->match($entry, record: false);
            $counts['matched'] += $after === 'matched' && $before !== 'matched' ? 1 : 0;
        }
        if ($entry->isMatched() && $this->registry->record($entry->fresh(['source', 'project']))) {
            $counts['legal_records_created']++;
        }

        return $entry->id;
    }

    /**
     * Entries this complete run did not see have left the list. A matched one goes
     * to staff; leaving a list is never read as a clearance or a cancellation.
     *
     * @param  list<int>  $seen  ids of the entries read in this run
     */
    protected function markRemoved(MarketSource $source, array $seen, array &$counts): void
    {
        InvestmentRegistryEntry::where('source_id', $source->id)->whereNull('removed_at')->whereNotIn('id', $seen)->get()
            ->each(function (InvestmentRegistryEntry $entry) use (&$counts) {
                $entry->forceFill(['removed_at' => now()])->save();
                $counts['entries_removed']++;
                if ($entry->isMatched()) {
                    InvestmentException::raise('removed_from_list', "“{$entry->scheme_name}” is no longer on the {$entry->authority} list",
                        'It was on the '.InvestmentRegistryEntry::LIST_TYPES[$entry->list_type].' list until '.$entry->last_seen_at->format('j M Y')
                        .'. Its legal records stay as they are until an official record says otherwise.',
                        ['source_id' => $entry->source_id, 'project_id' => $entry->project_id, 'registry_entry_id' => $entry->id], ['key' => 'removed-'.$entry->id]);
                    $counts['exceptions_opened']++;
                }
            });
    }

    /** An unexpected failure: logged, and staff are told (nothing from the run is kept). */
    protected function failed(InvestmentCollectionRun $run, MarketSource $source, Throwable $e, array $counts): InvestmentCollectionRun
    {
        report($e);
        InvestmentException::raise('source_access', "{$source->name}: collection failed", mb_substr($e->getMessage(), 0, 1000), ['source_id' => $source->id], ['key' => 'failed']);

        return $this->finish($run, $source, 'failed', ['exceptions_opened' => 1] + $counts, mb_substr($e->getMessage(), 0, 500));
    }

    protected function finish(InvestmentCollectionRun $run, MarketSource $source, string $status, array $counts = [], ?string $error = null): InvestmentCollectionRun
    {
        $run->forceFill($counts + ['status' => $status, 'error' => $error, 'finished_at' => now()])->save();
        $source->forceFill(['last_collected_at' => now(), 'last_collection_status' => $status])->save();

        return $run;
    }

    /** What a run would read, without storing anything. */
    protected function preview(array $result, array $preset, Fetcher $fetcher): array
    {
        $statuses = [];
        $unrecognised = [];
        foreach ($result['entries'] as $e) {
            $s = OfficialStatus::for($preset['list_type'], $e['status_raw'], $preset['stages'] ?? null);
            $statuses[$s ?? 'not recognised'] = ($statuses[$s ?? 'not recognised'] ?? 0) + 1;
            $s === null && count($unrecognised) < 10 && $unrecognised[] = $e['name'].': "'.$e['status_raw'].'"';
        }

        return ['entries' => count($result['entries']), 'complete' => $result['complete'], 'requests' => $fetcher->requests, 'statuses' => $statuses,
            'dated' => collect($result['entries'])->filter(fn ($e) => OfficialStatus::date($e['date_raw'], $e['status_raw']))->count(),
            'unrecognised_examples' => $unrecognised, 'first' => array_slice(array_map(fn ($e) => $e['name'], $result['entries']), 0, 5)];
    }
}
