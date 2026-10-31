<?php

namespace App\Services;

use App\Enums\PropertyStatus;
use App\Enums\Purpose;
use App\Models\Inquiry;
use App\Models\LeadAssignment;
use App\Models\Property;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Listing and lead performance for one dealer, built only from that dealer's
 * own records. Daily views and response times are recorded from the day
 * tracking was introduced; earlier periods show what exists, not estimates.
 */
class DealerPerformance
{
    public const PERIODS = [7 => 'Last 7 days', 30 => 'Last 30 days', 90 => 'Last 90 days'];

    public function __construct(protected AreaInsights $insights) {}

    /**
     * @return array<string, mixed>
     */
    public function for(User $dealer, int $days = 30): array
    {
        $days = array_key_exists($days, self::PERIODS) ? $days : 30;
        $since = now()->subDays($days - 1)->startOfDay();

        $listings = $dealer->properties()->with(['area:id,name', 'coverImage'])->withCount('images')->latest('updated_at')->get();
        $ids = $listings->pluck('id');

        $viewsByListing = $this->sumBy('property_view_stats', 'views', $ids, 'date', $since->toDateString());
        $savesByListing = DB::table('saved_properties')->whereIn('property_id', $ids)->where('created_at', '>=', $since)
            ->groupBy('property_id')->selectRaw('property_id, count(*) as total')->pluck('total', 'property_id');
        $savesAllTime = DB::table('saved_properties')->whereIn('property_id', $ids)
            ->groupBy('property_id')->selectRaw('property_id, count(*) as total')->pluck('total', 'property_id');

        $inquiries = Inquiry::where('recipient_id', $dealer->id)->where('created_at', '>=', $since)
            ->get(['id', 'property_id', 'status', 'created_at', 'responded_at']);
        $inquiriesByListing = $inquiries->countBy('property_id');

        $assignments = LeadAssignment::where('dealer_id', $dealer->id)->where('assigned_at', '>=', $since)
            ->with('lead:id,status')->get();

        $rows = $listings->map(fn (Property $p) => $this->row($p, (int) ($viewsByListing[$p->id] ?? 0), (int) ($savesByListing[$p->id] ?? 0), (int) ($savesAllTime[$p->id] ?? 0), (int) ($inquiriesByListing[$p->id] ?? 0)));

        $viewsTotal = (int) $viewsByListing->sum();
        $responded = $inquiries->whereNotNull('responded_at');

        return [
            'days' => $days,
            'since' => $since,
            'trackingSince' => $this->trackingSince(),
            'kpis' => [
                'views' => $viewsTotal,
                'saves' => (int) $savesByListing->sum(),
                'inquiries' => $inquiries->count(),
                'conversion' => $viewsTotal > 0 ? round($inquiries->count() / $viewsTotal * 100, 1) : null,
                'responseRate' => $inquiries->count() ? (int) round($responded->count() / $inquiries->count() * 100) : null,
                'medianResponseHours' => $this->medianHours($responded, 'created_at'),
                'live' => $listings->filter->isPublic()->count(),
            ],
            'leads' => [
                'received' => $assignments->count(),
                'responded' => $assignments->whereNotNull('responded_at')->count(),
                'medianResponseHours' => $this->medianHours($assignments->whereNotNull('responded_at'), 'assigned_at'),
                'byStatus' => $assignments->countBy(fn ($a) => $a->lead?->status?->label() ?? 'Unknown')->sortDesc(),
            ],
            'chart' => $this->chart($ids, $dealer, $since, $days),
            'rows' => $rows,
            'actions' => $rows->filter(fn ($r) => $r['issues'])->values(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function row(Property $p, int $views, int $saves, int $savesAllTime, int $inquiries): array
    {
        $issues = [];
        $live = $p->isPublic();

        if ($p->status === PropertyStatus::Rejected) {
            $issues[] = 'Rejected'.($p->rejection_reason ? ': '.$p->rejection_reason : '').' — edit and resubmit';
        }
        if ($p->status === PropertyStatus::Draft) {
            $issues[] = 'Still a draft — submit it for review';
        }
        if ($p->images_count === 0) {
            $issues[] = 'No photos';
        } elseif ($p->images_count < 5) {
            $issues[] = 'Only '.$p->images_count.' '.str('photo')->plural($p->images_count).' — listings with 5+ photos are easier to judge';
        }
        if (! $p->hasCoordinates()) {
            $issues[] = 'No map location, so it cannot appear on map or drawn-area searches';
        }
        if ($p->type->group() !== 'plots' && ! $p->covered_area_sqft) {
            $issues[] = 'Covered area missing';
        }
        if (mb_strlen((string) $p->description) < 150) {
            $issues[] = 'Short description';
        }
        if ($live && $p->canBeRenewed()) {
            $issues[] = 'Expires '.$p->expires_at->format('j M').' — renew to stay visible';
        } elseif ($p->hasLapsed()) {
            $issues[] = 'Expired '.$p->expires_at->format('j M').' — '.($p->canBeRenewed() ? 'renew to put it back online' : 'submit it for review to relist');
        }

        $vsArea = null;
        if ($p->purpose === Purpose::Sale && ($perMarla = $p->pricePerMarla()) && $p->area_id) {
            if ($median = $this->insights->medianPerMarla($p->area_id, $p->type)) {
                $vsArea = (int) round(($perMarla - $median) / $median * 100);
            }
        }

        return [
            'property' => $p,
            'views' => $views,
            'viewsAllTime' => (int) $p->views,
            'saves' => $saves,
            'savesAllTime' => $savesAllTime,
            'inquiries' => $inquiries,
            'conversion' => $views > 0 ? round($inquiries / $views * 100, 1) : null,
            'daysLive' => $live && $p->published_at ? (int) $p->published_at->diffInDays(now()) : null,
            'vsArea' => $vsArea,
            'issues' => $issues,
        ];
    }

    /**
     * @return array{labels: list<string>, series: array<string, array{color: string, values: list<int>}>}
     */
    protected function chart(Collection $ids, User $dealer, Carbon $since, int $days): array
    {
        $dates = collect(range(0, $days - 1))->map(fn ($d) => $since->copy()->addDays($d));

        $views = DB::table('property_view_stats')->whereIn('property_id', $ids)->where('date', '>=', $since->toDateString())
            ->groupBy('date')->selectRaw('date, sum(views) as total')->pluck('total', 'date')
            ->mapWithKeys(fn ($v, $d) => [Carbon::parse($d)->toDateString() => (int) $v]);
        $inquiries = Inquiry::where('recipient_id', $dealer->id)->where('created_at', '>=', $since)->get(['created_at'])
            ->countBy(fn ($i) => $i->created_at->toDateString());

        return [
            'labels' => $dates->map->format('j M')->all(),
            'series' => [
                'Views' => ['color' => '#6a3fb0', 'values' => $dates->map(fn ($d) => $views[$d->toDateString()] ?? 0)->all()],
                'Inquiries' => ['color' => '#d4af37', 'values' => $dates->map(fn ($d) => $inquiries[$d->toDateString()] ?? 0)->all()],
            ],
        ];
    }

    protected function sumBy(string $table, string $column, Collection $ids, string $dateColumn, string $since): Collection
    {
        return DB::table($table)->whereIn('property_id', $ids)->where($dateColumn, '>=', $since)
            ->groupBy('property_id')->selectRaw("property_id, sum({$column}) as total")->pluck('total', 'property_id');
    }

    protected function medianHours(Collection $rows, string $from): ?float
    {
        $hours = $rows->map(fn ($r) => abs($r->{$from}->diffInMinutes($r->responded_at)) / 60)->sort()->values();
        $n = $hours->count();

        if ($n === 0) {
            return null;
        }

        return round($n % 2 ? $hours[intdiv($n, 2)] : ($hours[$n / 2 - 1] + $hours[$n / 2]) / 2, 1);
    }

    protected function trackingSince(): ?Carbon
    {
        $first = DB::table('property_view_stats')->min('date');

        return $first ? Carbon::parse($first) : null;
    }
}
