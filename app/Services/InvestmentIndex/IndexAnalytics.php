<?php

namespace App\Services\InvestmentIndex;

use App\Models\InvestmentIndexMethodology;
use App\Models\InvestmentIndexRun;
use App\Models\InvestmentIndexScore;
use App\Models\InvestmentLegalRecord;
use App\Models\Project;
use App\Support\Cities;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class IndexAnalytics
{
    public function __construct(protected IndexBrowser $browser) {}

    /**
     * Aggregated national investment intelligence data and chart configurations.
     */
    public function nationalAnalytics(): array
    {
        $run = $this->browser->run();
        if (! $run) {
            return $this->emptyNationalAnalytics();
        }

        $cacheKey = "investment_analytics_national_run_{$run->id}";

        return Cache::remember($cacheKey, 3600, function () use ($run) {
            $scores = $run->scores()->with('project:id,name,city,category')->get();
            $rankedScores = $scores->where('status', 'ranked');
            $totals = $this->browser->researchTotals();

            // City-wise aggregation
            $cityData = [];
            foreach (Cities::names() as $city) {
                $cityScores = $scores->filter(fn ($s) => $s->project && $s->project->city === $city);
                $cityRanked = $cityScores->where('status', 'ranked');
                $cityData[$city] = [
                    'name' => $city,
                    'slug' => Cities::slug($city),
                    'total' => $cityScores->count(),
                    'ranked' => $cityRanked->count(),
                    'legal_verified' => $cityScores->whereIn('legal_status', ['approved', 'partially_approved', 'pending', 'excluded'])->count(),
                    'avg_score' => $cityRanked->count() > 0 ? round($cityRanked->avg('score'), 1) : 0,
                ];
            }

            // Category-wise aggregation
            $categoriesConfig = IndexBrowser::categories();
            $categoryData = [];
            foreach ($categoriesConfig as $key => $c) {
                $catScores = $scores->where('category', $key);
                $catRanked = $catScores->where('status', 'ranked');
                $topScored = $catRanked->sortBy('category_rank')->first();

                $categoryData[$key] = [
                    'key' => $key,
                    'label' => $c['label'],
                    'singular' => $c['singular'],
                    'total' => $catScores->count(),
                    'ranked' => $catRanked->count(),
                    'avg_score' => $catRanked->count() > 0 ? round($catRanked->avg('score'), 1) : 0,
                    'top_project' => $topScored?->project?->name ?? 'None',
                    'top_score' => $topScored ? round($topScored->score, 1) : null,
                ];
            }

            // Legal status distribution
            $legalCounts = [
                'Approved' => $scores->where('legal_status', 'approved')->count(),
                'Partially Approved' => $scores->where('legal_status', 'partially_approved')->count(),
                'Pending Review' => $scores->where('legal_status', 'pending')->count(),
                'Not Verified' => $scores->where('legal_status', 'not_verified')->count(),
                'Excluded' => $scores->where('legal_status', 'excluded')->count(),
            ];

            // Risk distribution
            $riskCounts = [
                'Low Risk' => $scores->where('risk_class', 'low')->count(),
                'Moderate Risk' => $scores->where('risk_class', 'moderate')->count(),
                'Elevated Risk' => $scores->where('risk_class', 'elevated')->count(),
                'Excluded' => $scores->where('risk_class', 'excluded')->count(),
            ];

            // Evidence coverage distribution
            $coverageBands = [
                '75–100%' => $scores->where('coverage', '>=', 0.75)->count(),
                '50–74%' => $scores->filter(fn ($s) => $s->coverage >= 0.50 && $s->coverage < 0.75)->count(),
                '25–49%' => $scores->filter(fn ($s) => $s->coverage >= 0.25 && $s->coverage < 0.50)->count(),
                '0–24%' => $scores->where('coverage', '<', 0.25)->count(),
            ];

            // Score distribution of ranked projects
            $scoreBands = [
                '90–100 (Tier 1 Prime)' => $rankedScores->where('score', '>=', 90)->count(),
                '80–89 (Tier 2 Strong)' => $rankedScores->filter(fn ($s) => $s->score >= 80 && $s->score < 90)->count(),
                '70–79 (Tier 3 Moderate)' => $rankedScores->filter(fn ($s) => $s->score >= 70 && $s->score < 80)->count(),
                '60–69 (Tier 4 Conditional)' => $rankedScores->filter(fn ($s) => $s->score >= 60 && $s->score < 70)->count(),
            ];

            return [
                'kpis' => [
                    'total_projects' => $totals['projects'],
                    'ranked_projects' => $totals['ranked'],
                    'verified_projects' => $totals['legal_verified'],
                    'under_research' => $totals['research'],
                    'awaiting_legal' => $totals['awaiting_check'],
                    'excluded_projects' => $totals['excluded'],
                    'provisional' => $totals['provisional'],
                    'published_at' => $totals['published_at'],
                    'methodology_version' => $run->methodology_version,
                    'national_avg_score' => $rankedScores->count() > 0 ? round($rankedScores->avg('score'), 1) : 0,
                    'national_avg_confidence' => $rankedScores->count() > 0 ? round($rankedScores->avg('confidence_score'), 1) : 0,
                ],
                'city_data' => $cityData,
                'category_data' => $categoryData,
                'charts' => [
                    'city_distribution' => [
                        'labels' => array_keys($cityData),
                        'total' => array_column($cityData, 'total'),
                        'ranked' => array_column($cityData, 'ranked'),
                    ],
                    'category_distribution' => [
                        'labels' => array_column($categoryData, 'label'),
                        'ranked' => array_column($categoryData, 'ranked'),
                        'total' => array_column($categoryData, 'total'),
                        'avg_score' => array_column($categoryData, 'avg_score'),
                    ],
                    'legal_distribution' => [
                        'labels' => array_keys($legalCounts),
                        'data' => array_values($legalCounts),
                        'colors' => ['#10B981', '#F59E0B', '#0EA5E9', '#94A3B8', '#F43F5E'],
                    ],
                    'risk_distribution' => [
                        'labels' => array_keys($riskCounts),
                        'data' => array_values($riskCounts),
                        'colors' => ['#10B981', '#F59E0B', '#F97316', '#EF4444'],
                    ],
                    'evidence_coverage' => [
                        'labels' => array_keys($coverageBands),
                        'data' => array_values($coverageBands),
                        'colors' => ['#4B1592', '#6A3FB0', '#8C66C8', '#D4C4EC'],
                    ],
                    'score_distribution' => [
                        'labels' => array_keys($scoreBands),
                        'data' => array_values($scoreBands),
                        'colors' => ['#10B981', '#4B1592', '#D6A13D', '#64748B'],
                    ],
                ],
            ];
        });
    }

    /**
     * City-level investment analytics and leaderboard.
     */
    public function cityAnalytics(string $cityName): array
    {
        $run = $this->browser->run();
        if (! $run) {
            return [];
        }

        $cacheKey = "investment_analytics_city_{$run->id}_".Cities::slug($cityName);

        return Cache::remember($cacheKey, 3600, function () use ($run, $cityName) {
            $scores = $run->scores()->whereHas('project', fn ($p) => $p->where('city', $cityName))->with('project:id,name,slug,city,developer,category,area_id', 'project.area:id,name')->get();
            $rankedScores = $scores->where('status', 'ranked');
            $totals = $this->browser->researchTotals($cityName);

            // Category breakdown for this city
            $categoryData = [];
            foreach (IndexBrowser::categories() as $key => $c) {
                $catScores = $scores->where('category', $key);
                $catRanked = $catScores->where('status', 'ranked');
                $categoryData[$key] = [
                    'key' => $key,
                    'label' => $c['label'],
                    'singular' => $c['singular'],
                    'total' => $catScores->count(),
                    'ranked' => $catRanked->count(),
                    'avg_score' => $catRanked->count() > 0 ? round($catRanked->avg('score'), 1) : 0,
                ];
            }

            // Legal distribution for city
            $legalCounts = [
                'Approved' => $scores->where('legal_status', 'approved')->count(),
                'Partially Approved' => $scores->where('legal_status', 'partially_approved')->count(),
                'Pending Review' => $scores->where('legal_status', 'pending')->count(),
                'Not Verified' => $scores->where('legal_status', 'not_verified')->count(),
                'Excluded' => $scores->where('legal_status', 'excluded')->count(),
            ];

            // Risk distribution for city
            $riskCounts = [
                'Low Risk' => $scores->where('risk_class', 'low')->count(),
                'Moderate Risk' => $scores->where('risk_class', 'moderate')->count(),
                'Elevated Risk' => $scores->where('risk_class', 'elevated')->count(),
                'Excluded' => $scores->where('risk_class', 'excluded')->count(),
            ];

            // Score distribution for city ranked projects
            $scoreBands = [
                '90–100 Prime' => $rankedScores->where('score', '>=', 90)->count(),
                '80–89 Strong' => $rankedScores->filter(fn ($s) => $s->score >= 80 && $s->score < 90)->count(),
                '70–79 Moderate' => $rankedScores->filter(fn ($s) => $s->score >= 70 && $s->score < 80)->count(),
                '60–69 Conditional' => $rankedScores->filter(fn ($s) => $s->score >= 60 && $s->score < 70)->count(),
            ];

            // Top qualifying projects across city (sorted by overall_index or score)
            $topProjects = $rankedScores->sortByDesc(fn ($s) => $s->overall_index ?? $s->score)->take(10)->values();

            return [
                'kpis' => [
                    'city' => $cityName,
                    'city_slug' => Cities::slug($cityName),
                    'total_projects' => $totals['projects'],
                    'ranked_projects' => $totals['ranked'],
                    'verified_projects' => $totals['legal_verified'],
                    'under_research' => $totals['research'],
                    'awaiting_legal' => $totals['awaiting_check'],
                    'excluded_projects' => $totals['excluded'],
                    'city_avg_score' => $rankedScores->count() > 0 ? round($rankedScores->avg('score'), 1) : 0,
                    'city_avg_confidence' => $rankedScores->count() > 0 ? round($rankedScores->avg('confidence_score'), 1) : 0,
                ],
                'category_data' => $categoryData,
                'top_projects' => $topProjects,
                'charts' => [
                    'category_distribution' => [
                        'labels' => array_column($categoryData, 'label'),
                        'total' => array_column($categoryData, 'total'),
                        'ranked' => array_column($categoryData, 'ranked'),
                    ],
                    'legal_distribution' => [
                        'labels' => array_keys($legalCounts),
                        'data' => array_values($legalCounts),
                        'colors' => ['#10B981', '#F59E0B', '#0EA5E9', '#94A3B8', '#F43F5E'],
                    ],
                    'risk_distribution' => [
                        'labels' => array_keys($riskCounts),
                        'data' => array_values($riskCounts),
                        'colors' => ['#10B981', '#F59E0B', '#F97316', '#EF4444'],
                    ],
                    'score_distribution' => [
                        'labels' => array_keys($scoreBands),
                        'data' => array_values($scoreBands),
                        'colors' => ['#10B981', '#4B1592', '#D6A13D', '#64748B'],
                    ],
                ],
            ];
        });
    }

    /**
     * Category-level analytics for national or specific city pages.
     */
    public function categoryAnalytics(string $categoryKey, ?string $cityName = null): array
    {
        $run = $this->browser->run();
        if (! $run) {
            return [];
        }

        $citySlug = $cityName ? Cities::slug($cityName) : 'pakistan';
        $cacheKey = "investment_analytics_cat_{$run->id}_{$categoryKey}_{$citySlug}";

        return Cache::remember($cacheKey, 3600, function () use ($run, $categoryKey, $cityName) {
            $scores = $run->scores()->where('category', $categoryKey)
                ->when($cityName, fn ($q) => $q->whereHas('project', fn ($p) => $p->where('city', $cityName)))
                ->with('project:id,name,slug,city,developer,category,area_id', 'project.area:id,name')
                ->get();

            $rankedScores = $scores->where('status', 'ranked');
            $leaderboard = $this->browser->leaderboard($categoryKey, $cityName, IndexBrowser::publicTop());

            // Score & confidence comparison data for top 10
            $chartProjects = [];
            foreach ($leaderboard as $idx => $s) {
                $chartProjects[] = [
                    'name' => $s->project->name,
                    'short_name' => mb_substr($s->project->name, 0, 18).(mb_strlen($s->project->name) > 18 ? '…' : ''),
                    'rank' => $idx + 1,
                    'score' => (float) $s->score,
                    'confidence' => (float) $s->confidence_score,
                    'coverage' => round($s->coverage * 100, 0),
                    'risk' => $s->riskLabel(),
                ];
            }

            // Risk distribution in this category
            $riskCounts = [
                'Low Risk' => $scores->where('risk_class', 'low')->count(),
                'Moderate Risk' => $scores->where('risk_class', 'moderate')->count(),
                'Elevated Risk' => $scores->where('risk_class', 'elevated')->count(),
                'Excluded' => $scores->where('risk_class', 'excluded')->count(),
            ];

            // Methodology factor weights
            $methodology = InvestmentIndexMethodology::current();
            $weights = $methodology->weightsFor($categoryKey);
            $factorsConfig = config("investment_index.categories.{$categoryKey}.factors", []);
            $factorDetails = [];
            foreach ($weights as $factorKey => $weight) {
                $factorDetails[] = [
                    'key' => $factorKey,
                    'label' => $factorsConfig[$factorKey]['label'] ?? ucwords(str_replace('_', ' ', $factorKey)),
                    'weight' => $weight,
                ];
            }

            return [
                'kpis' => [
                    'total_assessed' => $scores->count(),
                    'total_ranked' => $rankedScores->count(),
                    'avg_score' => $rankedScores->count() > 0 ? round($rankedScores->avg('score'), 1) : 0,
                    'avg_confidence' => $rankedScores->count() > 0 ? round($rankedScores->avg('confidence_score'), 1) : 0,
                    'legal_verified' => $scores->whereIn('legal_status', ['approved', 'partially_approved', 'pending', 'excluded'])->count(),
                    'awaiting_evidence' => $scores->whereIn('status', ['insufficient', 'not_eligible'])->count(),
                    'excluded' => $scores->where('status', 'excluded')->count(),
                ],
                'leaderboard_projects' => $chartProjects,
                'risk_counts' => $riskCounts,
                'factor_details' => $factorDetails,
                'charts' => [
                    'score_comparison' => [
                        'labels' => array_column($chartProjects, 'short_name'),
                        'scores' => array_column($chartProjects, 'score'),
                        'confidence' => array_column($chartProjects, 'confidence'),
                    ],
                    'risk_distribution' => [
                        'labels' => array_keys($riskCounts),
                        'data' => array_values($riskCounts),
                        'colors' => ['#10B981', '#F59E0B', '#F97316', '#EF4444'],
                    ],
                    'factor_weights' => [
                        'labels' => array_column($factorDetails, 'label'),
                        'data' => array_column($factorDetails, 'weight'),
                        'colors' => ['#4B1592', '#D6A13D', '#0D9488', '#2563EB', '#6366F1', '#EC4899'],
                    ],
                ],
            ];
        });
    }

    protected function emptyNationalAnalytics(): array
    {
        return [
            'kpis' => [
                'total_projects' => 0,
                'ranked_projects' => 0,
                'verified_projects' => 0,
                'under_research' => 0,
                'awaiting_legal' => 0,
                'excluded_projects' => 0,
                'provisional' => 0,
                'published_at' => null,
                'methodology_version' => 1,
                'national_avg_score' => 0,
                'national_avg_confidence' => 0,
            ],
            'city_data' => [],
            'category_data' => [],
            'charts' => [],
        ];
    }
}
