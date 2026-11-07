<?php

namespace Tests\Feature;

use App\Models\InvestmentIndexMethodology;
use App\Models\InvestmentIndexRun;
use App\Models\InvestmentIndexScore;
use App\Models\Project;
use App\Support\Cities;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CityWiseRankingsMatrixTest extends TestCase
{
    use RefreshDatabase;

    protected InvestmentIndexRun $run;

    protected array $categories = [
        'housing-societies' => 'residential_society',
        'commercial-plazas' => 'commercial_plaza',
        'apartment-buildings' => 'apartment_complex',
        'shopping-malls' => 'shopping_mall',
        'business-towers' => 'business_tower',
    ];

    protected array $cities = [
        'lahore' => 'Lahore',
        'karachi' => 'Karachi',
        'islamabad' => 'Islamabad',
        'multan' => 'Multan',
        'faisalabad' => 'Faisalabad',
        'peshawar' => 'Peshawar',
        'quetta' => 'Quetta',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        \Illuminate\Support\Facades\Bus::fake([\App\Jobs\RecalculateInvestmentIndex::class]);

        $methodology = InvestmentIndexMethodology::current();

        $this->run = new InvestmentIndexRun();
        $this->run->forceFill([
            'methodology_id' => $methodology->id,
            'methodology_version' => $methodology->version,
            'trigger' => 'test',
            'status' => 'published',
            'published_at' => now(),
            'started_at' => now(),
            'finished_at' => now(),
        ])->save();

        // Seed 1 ranked project for every city x category combination (35 projects total)
        $rankCounter = 1;
        foreach ($this->cities as $citySlug => $cityName) {
            foreach ($this->categories as $catKey => $projCat) {
                $p = new Project([
                    'name' => "{$cityName} Premier {$catKey}",
                    'slug' => "{$citySlug}-premier-{$catKey}",
                    'city' => $cityName,
                    'category' => $projCat,
                    'status' => 'operational',
                    'description' => "Verified real test development in {$cityName}.",
                ]);
                $p->forceFill(['is_published' => true, 'is_demo' => false])->save();

                (new InvestmentIndexScore)->forceFill([
                    'run_id' => $this->run->id,
                    'project_id' => $p->id,
                    'category' => $catKey,
                    'status' => 'ranked',
                    'score' => 90.0 + ($rankCounter % 10),
                    'normalized_score' => 85.0 + ($rankCounter % 10),
                    'confidence_score' => 80,
                    'confidence_level' => 'high',
                    'coverage' => 0.85,
                    'category_rank' => ($rankCounter % 5) + 1,
                    'overall_rank' => $rankCounter,
                    'overall_index' => 80.0 + ($rankCounter % 15),
                    'legal_status' => 'approved',
                    'risk_class' => 'low',
                    'factors' => [],
                    'reasons' => [],
                    'risks' => [],
                    'source_ids' => [],
                    'research_depth' => 8,
                ])->save();

                $rankCounter++;
            }
        }

        $this->app->forgetInstance(\App\Services\InvestmentIndex\IndexBrowser::class);
    }

    public function test_all_seven_general_city_indexes_render_successfully_with_overall_rankings(): void
    {
        foreach ($this->cities as $slug => $name) {
            $response = $this->get("/investment-rankings/{$slug}");

            $response->assertStatus(200);
            $response->assertSee("Smart Real Estate Investments in", false);
            $response->assertSee($name, false);
            $response->assertSee("Overall Investment Index in {$name}", false);
            $response->assertSee("Meezan Investment Index in {$name}", false);
            $response->assertDontSee("Insufficient Verified Data for {$name}", false);
            $response->assertSee("{$name} Premier", false);
        }
    }

    public function test_all_thirty_five_city_category_views_render_leaderboards_with_qualifying_projects(): void
    {
        foreach ($this->cities as $citySlug => $cityName) {
            foreach ($this->categories as $catSlug => $projCat) {
                $url = "/investment-rankings/{$citySlug}/{$catSlug}";
                $response = $this->get($url);

                $response->assertStatus(200);
                $response->assertSee($cityName, false);
                $response->assertSee("Ranked", false);
                $response->assertSee("{$cityName} Premier {$catSlug}", false);
            }
        }
    }
}
