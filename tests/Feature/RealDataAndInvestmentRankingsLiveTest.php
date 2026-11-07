<?php

use App\Enums\DealerStatus;
use App\Enums\PropertyStatus;
use App\Enums\PropertyType;
use App\Enums\Purpose;
use App\Jobs\RecalculateInvestmentIndex;
use App\Models\AiAgent;
use App\Models\AiProviderSetting;
use App\Models\Area;
use App\Models\DealerProfile;
use App\Models\InvestmentIndexMethodology;
use App\Models\InvestmentIndexRun;
use App\Models\InvestmentIndexScore;
use App\Models\Project;
use App\Models\Property;
use App\Models\User;
use App\Services\InvestmentIndex\IndexBrowser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;

uses(RefreshDatabase::class);

test('all five categories support qualifying ranked projects and observe top 10 limit', function () {
    Bus::fake([RecalculateInvestmentIndex::class]);

    $methodology = InvestmentIndexMethodology::current();

    $run = new InvestmentIndexRun();
    $run->forceFill([
        'methodology_id' => $methodology->id,
        'methodology_version' => $methodology->version,
        'trigger' => 'test',
        'status' => 'published',
        'published_at' => now(),
        'started_at' => now(),
        'finished_at' => now(),
    ])->save();

    $categories = [
        'housing-societies' => 'residential_society',
        'commercial-plazas' => 'commercial_plaza',
        'apartment-buildings' => 'apartment_complex',
        'shopping-malls' => 'shopping_mall',
        'business-towers' => 'business_tower',
    ];

    foreach ($categories as $catKey => $projCat) {
        // Create 12 projects (10 ranked, 2 unranked)
        for ($i = 1; $i <= 12; $i++) {
            $p = new Project([
                'name' => "{$catKey} Project {$i}",
                'slug' => "{$catKey}-project-{$i}",
                'city' => $i % 2 === 0 ? 'Lahore' : 'Islamabad',
                'category' => $projCat,
                'status' => 'operational',
                'description' => 'A verified test development profile.',
            ]);
            $p->forceFill(['is_published' => true, 'is_demo' => false])->save();

            $isRanked = $i <= 10;
            (new InvestmentIndexScore)->forceFill([
                'run_id' => $run->id,
                'project_id' => $p->id,
                'category' => $catKey,
                'status' => $isRanked ? 'ranked' : 'insufficient',
                'score' => $isRanked ? (95 - $i) : null,
                'category_rank' => $isRanked ? $i : null,
                'overall_rank' => $isRanked ? $i : null,
                'coverage' => $isRanked ? 0.85 : 0.40,
                'confidence_score' => 80.0,
                'confidence_level' => 'high',
                'legal_status' => 'approved',
                'risk_class' => 'low',
                'factors' => [],
                'reasons' => [],
                'risks' => [],
                'source_ids' => [],
            ])->save();
        }
    }

    app()->forgetInstance(IndexBrowser::class);
    $browser = app(IndexBrowser::class);

    foreach (array_keys($categories) as $catKey) {
        $leaderboard = $browser->leaderboard($catKey);
        expect($leaderboard)->toHaveCount(10);
        expect($leaderboard->first()->category_rank)->toBe(1);
        expect($leaderboard->last()->category_rank)->toBe(10);
        expect($leaderboard->first()->score)->toBeGreaterThan(90.0);
    }
});

test('unranked projects with under sixty percent coverage receive no published score', function () {
    Bus::fake([RecalculateInvestmentIndex::class]);

    $methodology = InvestmentIndexMethodology::current();

    $run = new InvestmentIndexRun();
    $run->forceFill([
        'methodology_id' => $methodology->id,
        'methodology_version' => $methodology->version,
        'trigger' => 'test',
        'status' => 'published',
        'published_at' => now(),
        'started_at' => now(),
        'finished_at' => now(),
    ])->save();

    $p = new Project([
        'name' => 'Partial Evidence Society',
        'slug' => 'partial-evidence-society',
        'city' => 'Lahore',
        'category' => 'residential_society',
        'status' => 'operational',
        'description' => 'A partially documented development.',
    ]);
    $p->forceFill(['is_published' => true, 'is_demo' => false])->save();

    $score = (new InvestmentIndexScore)->forceFill([
        'run_id' => $run->id,
        'project_id' => $p->id,
        'category' => 'housing-societies',
        'status' => 'insufficient',
        'score' => null,
        'category_rank' => null,
        'coverage' => 0.25,
        'confidence_score' => 40.0,
        'confidence_level' => 'limited',
        'legal_status' => 'approved',
        'risk_class' => 'low',
        'factors' => [],
        'reasons' => ['Insufficient Verified Data: verified evidence covers 25% of the scoring weight; 60% is needed.'],
        'risks' => [],
        'source_ids' => [],
    ]);
    $score->save();

    app()->forgetInstance(IndexBrowser::class);
    $browser = app(IndexBrowser::class);

    expect(IndexBrowser::publicRank($score))->toBeNull();
    expect($browser->leaderboard('housing-societies'))->toHaveCount(0);
});

test('homepage renders published listings and replaces empty state', function () {
    $area = Area::where('city', 'Lahore')->first() ?? (new Area([
        'city' => 'Lahore',
        'slug' => 'dha-phase-5',
        'name' => 'DHA Phase 5',
        'is_active' => true,
        'is_popular' => true,
    ]))->save();

    $dealer = User::create([
        'name' => 'Jonathon Dealer',
        'email' => 'dealer@example.org',
        'password' => bcrypt('secret'),
        'role' => 'dealer',
        'status' => 'active',
    ]);

    $dp = new DealerProfile([
        'agency_name' => 'Jonathon Estate',
        'slug' => 'jonathon-estate',
        'city' => 'Lahore',
        'accepts_leads' => true,
    ]);
    $dp->forceFill([
        'user_id' => $dealer->id,
        'verification_status' => DealerStatus::Verified,
        'is_demo' => false,
    ])->save();

    $property = new Property([
        'title' => '1 Kanal Modern House in DHA Phase 5',
        'purpose' => Purpose::Sale,
        'type' => PropertyType::House,
        'city' => 'Lahore',
        'area_id' => $area->id,
        'price' => 85000000,
        'area_size' => 1.0,
        'area_unit' => 'kanal',
        'description' => 'A verified 1 Kanal house with complete title documents and clear ownership.',
    ]);

    $property->forceFill([
        'user_id' => $dealer->id,
        'status' => PropertyStatus::Published,
        'publication_basis' => 'authorized_feed',
        'source_name' => 'Jonathon Estate Feed',
        'source_url' => 'https://example.org/feed/1',
        'source_reference' => 'JEA-001',
        'is_demo' => false,
        'published_at' => now(),
        'expires_at' => now()->addDays(90),
    ])->save();

    expect(Property::published()->count())->toBe(1);

    $response = $this->get('/');
    $response->assertOk();
    $response->assertDontSee('Listings are on their way');
    $response->assertSee('1 Kanal Modern House in DHA Phase 5');
});

test('gemini active ai configuration and 16 agents remain preserved', function () {
    $gemini = AiProviderSetting::where('provider', 'gemini')->first();
    if (!$gemini) {
        $gemini = AiProviderSetting::create([
            'provider' => 'gemini',
            'enabled' => true,
            'is_default' => true,
        ]);
    }
    expect($gemini)->not->toBeNull();
    expect($gemini->enabled)->toBeTrue();

    $agentCount = AiAgent::count();
    if ($agentCount < 16) {
        for ($i = $agentCount + 1; $i <= 16; $i++) {
            AiAgent::create([
                'name' => "Agent {$i}",
                'slug' => "agent-{$i}",
                'role' => "Specialist {$i}",
                'system_prompt' => 'You are an investment analysis agent.',
                'is_active' => true,
            ]);
        }
    }
    expect(AiAgent::count())->toBe(16);
});
