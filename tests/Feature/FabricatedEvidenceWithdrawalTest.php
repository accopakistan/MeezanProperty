<?php

use App\Models\MarketSource;
use App\Models\Project;
use App\Services\InvestmentIndex\IndexBrowser;
use App\Services\InvestmentIndex\IndexEngine;
use Illuminate\Support\Facades\DB;

// 19 Nov 2026: the Index records invented on 7 Nov are withdrawn; ranking pages then show researched
// projects as market data, never as a ranking.

function fakeDayProject(string $name, string $city = 'Quetta', string $category = 'business_tower'): Project
{
    $p = new Project(['name' => $name, 'city' => $city, 'category' => $category, 'status' => 'completed', 'description' => 'x']);
    $p->forceFill(['is_published' => true, 'is_demo' => false, 'created_at' => '2026-11-07 06:00:00'])->save();

    return $p;
}

it('rejects invented evidence and legal records, unpublishes invented projects, and keeps real records', function () {
    $fakeSource = MarketSource::create(['name' => 'Meezan Property Physical Site Inspection & Occupancy Census', 'type' => 'site_inspection', 'url' => 'https://meezanproperty.com/verification/inspections', 'reliability' => 'high', 'is_active' => true]);
    $realSource = MarketSource::create(['name' => 'LDA list', 'type' => 'official_record', 'url' => 'https://lda.gop.pk/regular-schemes', 'reliability' => 'high', 'is_active' => true]);
    $invented = fakeDayProject('Jinnah Corporate Center');
    $real = fakeDayProject('Jubilee Town', 'Lahore', 'residential_society');
    $real->forceFill(['created_at' => '2026-11-04 06:00:00'])->save();

    $row = fn (array $v) => DB::table('investment_evidence')->insertGetId($v + ['fingerprint' => uniqid('fp', true), 'observed_on' => '2026-11-01', 'collected_at' => now(), 'created_at' => '2026-11-07 06:00:00', 'updated_at' => now(), 'verification_status' => 'verified']);
    $fake = $row(['project_id' => $invented->id, 'factor' => 'occupancy', 'value_numeric' => 94, 'source_id' => $fakeSource->id, 'source_url' => $fakeSource->url]);
    $officialNameFake = $row(['project_id' => $real->id, 'factor' => 'location', 'value_level' => 'good', 'source_id' => $realSource->id, 'source_url' => $realSource->url]);
    // A real 5 Nov reading that was re-pointed and inflated on 7 Nov (LDA published 65%).
    $edited = $row(['project_id' => $real->id, 'factor' => 'occupancy', 'value_numeric' => 90, 'source_id' => $fakeSource->id, 'source_url' => $fakeSource->url,
        'excerpt' => 'LDA publishes "colonized: 65%"', 'collector' => 'official_list', 'created_at' => '2026-11-05 10:00:00']);
    $legal = DB::table('investment_legal_records')->insertGetId(['project_id' => $invented->id, 'authority' => 'QDA', 'record_type' => 'approval', 'status' => 'approved', 'severity' => 'none',
        'reference' => 'QDA/BP/ZH-2019/71', 'title' => 'x', 'source_id' => $realSource->id, 'source_url' => $realSource->url, 'collected_at' => now(), 'verification_status' => 'verified',
        'fingerprint' => uniqid('lr', true), 'created_at' => '2026-11-07 06:00:00', 'updated_at' => now()]);

    $this->artisan('meezan:withdraw-fabricated-evidence')->expectsOutputToContain('Dry run')->assertSuccessful();
    expect(DB::table('investment_evidence')->where('id', $fake)->value('verification_status'))->toBe('verified');

    $this->artisan('meezan:withdraw-fabricated-evidence --commit')->assertSuccessful();

    expect(DB::table('investment_evidence')->where('id', $fake)->value('verification_status'))->toBe('rejected')
        ->and(DB::table('investment_evidence')->where('id', $officialNameFake)->value('verification_status'))->toBe('rejected')
        ->and(DB::table('investment_legal_records')->where('id', $legal)->value('verification_status'))->toBe('rejected')
        ->and($invented->fresh()->is_published)->toBeFalse()
        ->and($real->fresh()->is_published)->toBeTrue()
        ->and(MarketSource::find($fakeSource->id)->is_active)->toBeFalse();

    $restored = DB::table('investment_evidence')->where('id', $edited)->first();
    expect((float) $restored->value_numeric)->toBe(65.0)->and($restored->source_id)->toBe($realSource->id)->and($restored->verification_status)->toBe('unverified');

    // Running again changes nothing.
    $this->artisan('meezan:withdraw-fabricated-evidence')->expectsOutputToContain('| Invented evidence rejected')->assertSuccessful();
    expect(DB::table('audit_logs')->where('action', 'investment.fabricated_evidence_withdrawn')->count())->toBe(1);
});

it('shows researched projects as market data, not a ranking, when nothing is ranked', function () {
    $p = new Project(['name' => 'Market Town', 'city' => 'Lahore', 'category' => 'residential_society', 'status' => 'unverified', 'description' => 'x']);
    $p->forceFill(['is_published' => true, 'is_demo' => false, 'research_facts' => [
        ['label' => 'Advertised price (10 marla plot)', 'value' => 'PKR 2 Crore (Sep 2026)', 'source' => 'Zameen.com price index — Market Town', 'url' => 'https://example.test', 'basis' => 'Advertised (asking) price index, not verified sales',
            'series' => [['Sep 2024', 'PKR 1.5 Crore'], ['Sep 2025', 'PKR 1.8 Crore'], ['Sep 2026', 'PKR 2 Crore']]],
        ['label' => 'Price change, 1 year (10 marla plot)', 'value' => '+11%', 'source' => 'Zameen', 'basis' => 'x'],
    ]])->save();
    app(IndexEngine::class)->run('test');

    expect(app(IndexBrowser::class)->leaderboard('housing-societies', 'Lahore'))->toBeEmpty()
        ->and(app(IndexBrowser::class)->marketBoard('housing-societies', 'Lahore')->pluck('project.name')->all())->toContain('Market Town');

    $html = $this->get(route('rankings.city', 'lahore'))->assertOk()->getContent();
    expect($html)->toContain('Residential Societies in Lahore: Market Data')->toContain('Not a ranking.')->toContain('Not ranked')
        ->toContain('Market Snapshot – Market Town')->toContain('Every point is a monthly average the source publishes.')
        ->not->toContain('Top Residential Societies in Lahore');
});
