<?php

use App\Models\AiAgent;
use App\Models\AiProviderSetting;
use App\Models\InvestmentLegalRecord;
use App\Models\MarketSource;
use App\Models\Project;
use App\Models\User;

function createTestProject(string $name, string $city, string $status = 'approved', array $extra = []): Project
{
    $source = MarketSource::firstOrCreate(
        ['name' => 'LDA — approved private housing schemes'],
        ['type' => 'official_record', 'url' => 'https://lda.gop.pk/approved-schemes', 'reliability' => 'high']
    );
    $source->forceFill(['is_active' => true])->save();

    $project = new Project(array_merge([
        'name' => $name,
        'city' => $city,
        'category' => 'residential_society',
        'status' => 'under_construction',
        'developer' => 'Test Developers Ltd',
        'description' => 'Official verified housing scheme on authority records.',
        'approval_status' => $status,
        'approval_note' => "Official regulatory status: {$status} by development authority.",
        'specifications' => [
            'authority' => 'Lahore Development Authority (LDA)',
            'jurisdiction' => "{$city} District",
            'approved_area_kanals' => 216.19,
            'total_plots' => 182,
            'residential_plots' => 134,
            'commercial_plots' => 48,
            'approval_stage' => 'Technically Approved',
        ],
        'research_facts' => [
            [
                'label' => 'Official Authority',
                'value' => 'Lahore Development Authority (LDA)',
                'source' => $source->name,
                'url' => 'https://lda.gop.pk/approved-schemes#100',
                'date' => '2026-11-06',
                'basis' => 'Official statutory regulator',
            ],
            [
                'label' => 'Approved Area',
                'value' => '216.19 Kanals',
                'source' => $source->name,
                'url' => 'https://lda.gop.pk/approved-schemes#100',
                'date' => '2026-11-06',
                'basis' => 'Sanctioned layout area',
            ],
        ],
    ], $extra));

    $project->forceFill(['is_published' => true, 'is_demo' => false])->save();

    $lr = new InvestmentLegalRecord([
        'project_id' => $project->id,
        'authority' => 'LDA',
        'record_type' => $status === 'cancelled' ? 'illegal_listing' : 'approval',
        'status' => $status,
        'severity' => $status === 'cancelled' ? 'serious' : 'none',
        'reference' => 'LDA entry 100',
        'title' => 'LDA Official Approval',
        'source_url' => 'https://lda.gop.pk/approved-schemes#100',
        'collected_at' => now(),
        'collector' => 'batch1_real_data_importer',
        'verification_method' => 'official_authority_matching',
        'source_id' => $source->id,
    ]);
    $lr->forceFill(['verification_status' => 'verified', 'verified_at' => now()])->save();

    return $project;
}

test('project directory index renders with category, city, and status filters', function () {
    createTestProject('Al-Rehman Garden', 'Lahore');
    createTestProject('Gulberg Greens', 'Islamabad');

    $response = $this->get(route('projects.index'));
    $response->assertOk()
        ->assertSee('Pakistan Project Directory')
        ->assertSee('Factual directory profiles')
        ->assertSee('Housing Societies')
        ->assertSee('Lahore')
        ->assertSee('Islamabad');
});

test('project directory filters by city and category properly', function () {
    createTestProject('Lahore Palm City', 'Lahore');
    createTestProject('Islamabad Enclave', 'Islamabad');

    $response = $this->get(route('projects.index', ['city' => 'Lahore', 'category' => 'housing-societies']));
    $response->assertOk()
        ->assertSee('Lahore Palm City')
        ->assertDontSee('Islamabad Enclave');
});

test('project directory filters by authority status', function () {
    createTestProject('Approved Society', 'Lahore', 'approved');
    createTestProject('Cancelled Society', 'Islamabad', 'cancelled');

    $response = $this->get(route('projects.index', ['legal' => 'approved']));
    $response->assertOk()
        ->assertSee('Approved Society')
        ->assertDontSee('Cancelled Society');
});

test('project directory show page displays all required factual and qualification sections', function () {
    $project = createTestProject('Prime Valley Housing', 'Lahore', 'partially_approved', [
        'approval_note' => 'Technical Approval granted by LDA on record. Area: 216.19 Kanals (182 sanctioned plots).',
    ]);
    
    $response = $this->get(route('projects.show', $project));
    $response->assertOk()
        ->assertSee('Prime Valley Housing')
        ->assertSee('Verified Planning and Development Facts')
        ->assertSee('216.19 Kanals')
        ->assertSee('182 total')
        ->assertSee('Official Regulatory Qualification')
        ->assertSee('Missing Evidence Gaps')
        ->assertSee('Investment Index Eligibility')
        ->assertSee('Directory Profile (Unranked)')
        ->assertSee('Official Source References')
        ->assertSee('Lahore Development Authority (LDA)');
});

test('cancelled projects display regulatory notice and exclusion from recommendations', function () {
    $project = createTestProject('Illegal Enclave Scheme', 'Islamabad', 'cancelled', [
        'approval_note' => 'REGULATORY NOTICE: This scheme has an adverse finding on CDA records. Excluded from Meezan investment recommendations.',
        'specifications' => [
            'authority' => 'Capital Development Authority (CDA)',
            'jurisdiction' => 'Islamabad Capital Territory (ICT)',
            'approved_area_kanals' => 1114.14,
        ],
    ]);
    
    $response = $this->get(route('projects.show', $project));
    $response->assertOk()
        ->assertSee('Illegal Enclave Scheme')
        ->assertSee('Regulatory Notice: Cancelled / Adverse')
        ->assertSee('Excluded from Meezan investment recommendations')
        ->assertSee('Capital Development Authority (CDA)')
        ->assertSee('1114.14 Kanals');
});

test('admin evidence gap analysis is accessible to admins', function () {
    $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
    createTestProject('Model Housing Lahore', 'Lahore');
    
    $response = $this->actingAs($admin)->get(route('admin.investment-index.evidence-gaps'));
    $response->assertOk()
        ->assertSee('Investment Evidence Gap Analysis')
        ->assertSee('Project Evidence Gap Register')
        ->assertSee('Evaluation Weight Matrix');
});

test('google gemini remains active ai provider with 16 configured agents', function () {
    $gemini = AiProviderSetting::where('provider', 'gemini')->first();
    expect($gemini)->not->toBeNull();
    expect($gemini->enabled)->toBeTrue();
    
    $agentCount = AiAgent::count();
    expect($agentCount)->toBe(16);
});
