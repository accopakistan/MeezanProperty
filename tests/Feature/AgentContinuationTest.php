<?php

use App\Enums\DealerStatus;
use App\Jobs\RunAiTask;
use App\Models\AiAgent;
use App\Models\AiApproval;
use App\Models\AiDraft;
use App\Models\AiKnowledgeDocument;
use App\Models\AiMemory;
use App\Models\AiTask;
use App\Models\AiUsageRecord;
use App\Models\Area;
use App\Models\InvestmentCalculation;
use App\Models\InvestmentMethodology;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\LeadAssignment;
use App\Models\LeadFollowUp;
use App\Models\MarketObservation;
use App\Models\MarketSource;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Property;
use App\Models\StaffTask;
use App\Models\Subscription;
use App\Models\SupportTicket;
use App\Models\User;
use App\Notifications\AgentNeedsAttention;
use App\Services\Agents\AgentContext;
use App\Services\Agents\AgentEvents;
use App\Services\Agents\AgentReadiness;
use App\Services\Agents\ApprovalExecutor;
use App\Services\Agents\BudgetGuard;
use App\Services\Agents\CentralAiOrchestrator;
use App\Services\Agents\IntentRouter;
use App\Services\Agents\MemoryStore;
use App\Services\Agents\Report;
use App\Services\Agents\ToolRegistry;
use App\Services\Agents\ToolRunner;
use App\Services\Agents\Workers\PropertyManagerAgent;
use App\Support\Grounding;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

/*
| Phase 15 continuation: regressions for defects found by auditing the agents
| against real data, plus the workflows and Command Center pages added after.
*/

beforeEach(function () {
    config(['services.openai.key' => null]);
    $this->admin = User::factory()->admin()->create();
});

function continuationBrain(): CentralAiOrchestrator
{
    return app(CentralAiOrchestrator::class);
}

function continuationContext(string $slug, ?User $user = null): AgentContext
{
    $agent = AiAgent::where('slug', $slug)->firstOrFail();
    $task = new AiTask(['title' => 'Test', 'request' => 'Test request', 'source' => 'admin']);
    $task->forceFill(['agent_id' => $agent->id, 'status' => 'running', 'depth' => 0])->save();

    return new AgentContext($task, $agent, $user, app(ToolRunner::class), continuationBrain(), new MemoryStore($agent, $task));
}

function fakeReplies(array $texts): void
{
    config(['services.openai.key' => 'sk-test-'.str_repeat('a', 24), 'meezan.agents.use_llm' => true, 'meezan.agents.provider' => 'openai']);
    $sequence = Http::sequence();
    foreach ($texts as $text) {
        $sequence->push(['model' => 'gpt-4o-mini', 'choices' => [['message' => ['content' => $text]]], 'usage' => ['prompt_tokens' => 100, 'completion_tokens' => 20]]);
    }
    Http::fake(['*/chat/completions' => $sequence]);
}

/** A lead assigned to a dealer, ready for CRM records. */
function assignedLead(User $dealer, array $attributes = []): LeadAssignment
{
    $lead = Lead::create(['source' => 'website', 'dedupe_key' => 'lead-'.uniqid()]);
    $assignment = new LeadAssignment;
    $assignment->forceFill($attributes + ['lead_id' => $lead->id, 'dealer_id' => $dealer->id, 'status' => 'active', 'assigned_at' => now()->subDays(2), 'pipeline_stage' => 'new'])->save();

    return $assignment;
}

function followUp(LeadAssignment $assignment, string $status, $due): LeadFollowUp
{
    $f = new LeadFollowUp(['type' => 'call', 'priority' => 'normal', 'due_at' => $due]);
    $f->forceFill(['lead_assignment_id' => $assignment->id, 'dealer_id' => $assignment->dealer_id, 'status' => $status])->save();

    return $f;
}

/*
| Routing of the requests named in the brief
*/

it('routes the example requests from the brief to the right agent', function (string $request, string $slug) {
    expect(app(IntentRouter::class)->route($request))->toBe($slug);
})->with([
    ["Give me Meezan Property's current business performance report.", 'ceo'],
    ['How is Meezan Property performing?', 'ceo'],
    ['What should we prioritize this month?', 'ceo'],
    ['Prepare a complete seven-city business expansion report.', 'ceo'],
    ['Find incomplete property listings in Lahore.', 'property-manager'],
    ['Show dealers whose verification is pending.', 'dealer-manager'],
    ['How can I register as a property dealer?', 'customer-support'],
    ['Compare available investment data for DHA Lahore and Bahria Town Lahore.', 'investment-analyst'],
    ['Which leads require follow-up today?', 'sales-manager'],
    ["Prepare the current month's subscription revenue report.", 'finance-manager'],
    ['What are our subscription revenues?', 'finance-manager'],
    ['Prepare a Karachi dealer onboarding strategy.', 'marketing-director'],
    ['Review current application health.', 'technical-operations'],
    ['Which subscriptions expire soon?', 'subscription-manager'],
]);

/*
| Tools against the real CRM vocabulary
*/

it('reports pending CRM follow-ups as due, and skips completed or future ones', function () {
    $dealer = User::factory()->dealer(DealerStatus::Verified)->create();
    $assignment = assignedLead($dealer);
    followUp($assignment, 'pending', now()->subHour());
    followUp($assignment, 'pending', now()->addDays(3));
    followUp($assignment, 'completed', now()->subHour());

    $task = continuationBrain()->submit('Which leads require follow-up today?', null, $this->admin);

    expect($task->agent->slug)->toBe('sales-manager')->and($task->intent)->toBe('follow_ups')
        ->and(Report::plain($task->result))->toContain('1 open follow-up(s)');

    $self = continuationContext('sales-manager', $dealer)->data('dealer.self_summary');
    expect($self['follow_ups_due'])->toBe(1);
});

it('reports dealer response times, waiting assignments and lost reasons', function () {
    $dealer = User::factory()->dealer(DealerStatus::Verified)->create();
    assignedLead($dealer, ['first_contact_at' => now()->subDays(2)->addHours(4)]);
    assignedLead($dealer);
    assignedLead($dealer, ['pipeline_stage' => 'lost', 'outcome_reason' => 'price_mismatch', 'status' => 'active', 'first_contact_at' => now()]);

    $task = continuationBrain()->submit('Show dealer response time and lost lead patterns', 'sales-manager', $this->admin);

    expect($task->intent)->toBe('patterns')
        ->and(json_encode($task->result))->toContain('price_mismatch')
        ->and(Report::plain($task->result))->toContain('1 active assignment(s) older than 24 hours');
});

it('reports dealer listing quality and profile gaps', function () {
    $dealer = User::factory()->dealer(DealerStatus::Verified)->create();
    $dealer->dealerProfile->forceFill(['bio' => null, 'city' => 'Lahore'])->save();
    Property::factory()->create(['user_id' => $dealer->id, 'city' => 'Lahore', 'completeness_score' => 40]);

    $task = continuationBrain()->submit('Dealer listing quality report', 'dealer-manager', $this->admin);

    expect($task->intent)->toBe('quality')
        ->and(Report::plain($task->result))->toContain('1 live listing(s) score below 60')
        ->and(json_encode($task->result))->toContain('description');
});

it('finds mismatches between paid invoices and payments, leaving out test records', function () {
    $dealer = User::factory()->dealer(DealerStatus::Verified)->create();
    $package = Package::create(['name' => 'P', 'slug' => 'p-'.uniqid(), 'price' => 1000, 'currency' => 'PKR', 'billing_interval' => 'month']);
    $sub = new Subscription;
    $sub->forceFill(['user_id' => $dealer->id, 'package_id' => $package->id, 'status' => 'active', 'billing_interval' => 'month', 'amount' => 1000, 'currency' => 'PKR', 'package_snapshot' => []])->save();
    $invoice = function (string $number, array $extra = []) use ($dealer, $sub, $package) {
        $i = new Invoice;
        $i->forceFill(['number' => $number, 'user_id' => $dealer->id, 'subscription_id' => $sub->id, 'package_id' => $package->id, 'kind' => 'new', 'description' => 'x', 'amount' => 1000, 'currency' => 'PKR', 'status' => 'paid', 'paid_at' => now(), 'issued_at' => now(), 'billing_snapshot' => []] + $extra)->save();

        return $i;
    };
    $good = $invoice('MP-OK');
    $bad = $invoice('MP-BAD');
    $test = $invoice('MP-TEST', ['is_test' => true]);
    $pay = new Payment;
    $pay->forceFill(['reference' => 'PAY-OK', 'invoice_id' => $good->id, 'user_id' => $dealer->id, 'gateway' => 'manual', 'status' => 'succeeded', 'amount' => 1000, 'currency' => 'PKR', 'confirmed_at' => now()])->save();

    $task = continuationBrain()->submit('Reconcile payments this month', 'finance-manager', $this->admin);
    $json = json_encode($task->result);

    expect($task->intent)->toBe('reconcile')->and($json)->toContain($bad->fresh()->number)->not->toContain($good->fresh()->number)->not->toContain($test->fresh()->number)
        ->and($bad->fresh()->status->value)->toBe('paid'); // reading only
});

it('lists location records that need review without changing them', function () {
    $a = Area::create(['city' => 'Multan', 'name' => 'Test Flagged Town', 'slug' => 'test-flagged-town', 'needs_review' => true, 'notes' => 'Check spelling']);

    $task = continuationBrain()->submit('Which location records are flagged or need review?', 'global-data-manager', $this->admin);

    expect($task->intent)->toBe('review')->and(json_encode($task->result))->toContain('Test Flagged Town')
        ->and($a->fresh()->needs_review)->toBeTrue();
});

it('drafts a content calendar without publishing anything', function () {
    $task = continuationBrain()->submit('Prepare a content calendar for Karachi', 'social-media-manager', $this->admin);

    expect($task->intent)->toBe('calendar')->and($task->result['title'])->toContain('Karachi')
        ->and(AiApproval::count())->toBe(0);
});

it('reports migrations, queues and scheduled job runs in the health check', function () {
    DB::table('jobs')->insert(['queue' => 'whatsapp', 'payload' => '{}', 'attempts' => 0, 'available_at' => now()->timestamp, 'created_at' => now()->subHours(3)->timestamp]);
    $this->artisan('meezan:agents subscriptions')->assertSuccessful();

    $h = continuationContext('technical-operations')->data('system.health');

    expect($h['pending_migrations'])->toBe(0)->and($h['queues']['whatsapp']['waiting'])->toBe(1)
        ->and($h['oldest_queued_job_hours'])->toBeGreaterThanOrEqual(3.0)
        ->and(collect($h['scheduled_agent_jobs'])->pluck('job'))->toContain('Daily subscription expiry and past-due review');
});

/*
| Investment comparison
*/

it('keeps investment comparisons inside the named city and reports what is on record', function () {
    Area::create(['city' => 'Lahore', 'name' => 'DHA Test Lahore', 'slug' => 'dha-test-lahore', 'approval_status' => 'approved']);
    Area::create(['city' => 'Karachi', 'name' => 'DHA Test Karachi', 'slug' => 'dha-test-karachi', 'approval_status' => 'unverified']);
    Area::create(['city' => 'Lahore', 'name' => 'Bahria Test Lahore', 'slug' => 'bahria-test-lahore']);

    $task = continuationBrain()->submit('Compare available investment data for DHA Test Lahore and Bahria Test Lahore', 'investment-analyst', $this->admin);
    $json = json_encode($task->result, JSON_UNESCAPED_UNICODE);

    expect($task->intent)->toBe('compare')->and($json)->toContain('DHA Test Lahore')->not->toContain('DHA Test Karachi')
        ->and($json)->toContain('0 price or rent observation(s)')
        ->and($task->result['missing'])->not->toBeEmpty();
});

/*
| Events and retries
*/

it('runs the dealer registration event through to an onboarding task for the right dealer', function () {
    config(['meezan.agents.events' => true]);
    AgentEvents::register();
    $dealer = User::factory()->dealer(DealerStatus::Pending)->create();

    $task = AiTask::where('source', 'event')->where('idempotency_key', 'event-dealer-registered-'.$dealer->dealerProfile->id)->sole();

    expect($task->status)->toBe('completed')->and($task->input['dealer_id'])->toBe($dealer->id)
        ->and($task->result['missing'])->toBe([])
        ->and(StaffTask::where('related_id', $dealer->id)->where('category', 'dealer_onboarding')->count())->toBe(1);
});

it('reviews exactly the submitted listing, however many others are incomplete', function () {
    $listing = Property::factory()->create(['status' => 'pending', 'description' => 'short']);
    Property::factory()->count(3)->create(['status' => 'published', 'description' => 'short']);

    $task = continuationBrain()->submit("Review new listing #{$listing->id}", 'property-manager', null, 'event', ['property_id' => $listing->id], intent: 'review');

    expect(Report::plain($task->result))->toContain('Missing:')
        ->and(StaffTask::where('related_id', $listing->id)->where('category', 'listing_review')->count())->toBe(1);
});

it('queues an Investment Analyst task when rankings are published, once', function () {
    Queue::fake();
    config(['meezan.agents.events' => true]);
    AgentEvents::register();
    $calc = new InvestmentCalculation;
    $calc->forceFill(['status' => 'completed', 'trigger' => 'manual', 'methodology_id' => InvestmentMethodology::active()->id, 'methodology_version' => 1])->save();
    $calc->forceFill(['status' => 'published', 'published_at' => now()])->save();

    expect(AiTask::where('idempotency_key', 'event-investment-published-'.$calc->id)->sole()->agent->slug)->toBe('investment-analyst');
    Queue::assertPushed(RunAiTask::class, 1);
});

it('asks Technical Operations to look after a failed job, once per hour', function () {
    Queue::fake();
    DB::table('failed_jobs')->insert(['uuid' => (string) Str::uuid(), 'connection' => 'database', 'queue' => 'default', 'payload' => '{}', 'exception' => 'x', 'failed_at' => now()]);

    $this->artisan('meezan:agents maintenance')->assertSuccessful();
    $this->artisan('meezan:agents maintenance')->assertSuccessful();

    expect(AiTask::where('agent_id', AiAgent::where('slug', 'technical-operations')->value('id'))->where('source', 'event')->count())->toBe(1);
});

it('does not look for trouble when nothing has failed', function () {
    Queue::fake();
    $this->artisan('meezan:agents maintenance')->assertSuccessful();

    expect(AiTask::count())->toBe(0);
});

it('retries failed background tasks on the queue, then stops and tells the administrators', function () {
    Notification::fake();
    config(['queue.default' => 'sync']);
    app()->bind(PropertyManagerAgent::class, fn () => new class extends PropertyManagerAgent
    {
        public function handle(AgentContext $ctx, string $intent): Report
        {
            throw new RuntimeException('down');
        }
    });

    $task = continuationBrain()->submit('Review new listing', 'property-manager', null, 'event', [], 'retry-test', queue: true, intent: 'review');

    // The first attempt fails, the retry dispatched from inside the job runs, and then the limit stops it.
    expect($task->status)->toBe('failed')->and($task->attempts)->toBe(2);
    Notification::assertSentTo($this->admin, AgentNeedsAttention::class, fn ($n) => $n->title === 'AI task failed');
});

it('does not retry a chat task after the assistant has already answered', function () {
    Queue::fake();
    Notification::fake();
    app()->bind(PropertyManagerAgent::class, fn () => new class extends PropertyManagerAgent
    {
        public function handle(AgentContext $ctx, string $intent): Report
        {
            throw new RuntimeException('down');
        }
    });

    $task = continuationBrain()->submit('Find incomplete listings', 'property-manager', null, 'website');

    expect($task->status)->toBe('failed');
    Queue::assertNotPushed(RunAiTask::class);
});

/*
| Memory
*/

it('shows approved notes in agent reports, within each agent\'s memory policy', function () {
    $this->actingAs($this->admin)->post(route('admin.ai.memory.store'), ['scope' => 'shared', 'key' => 'launch-focus', 'content' => 'Focus on Lahore first', 'source' => 'Management decision'])->assertSessionHas('status');
    $this->post(route('admin.ai.memory.store'), ['scope' => 'agent', 'agent' => 'finance-manager', 'key' => 'fiscal', 'content' => 'Finance only note', 'source' => 'CFO'])->assertSessionHas('status');

    $finance = continuationBrain()->submit('Revenue this month', 'finance-manager', $this->admin);
    $seo = continuationBrain()->submit('Run an SEO audit', 'seo-manager', $this->admin);

    expect(json_encode($finance->result))->toContain('Focus on Lahore first')->toContain('Finance only note')
        ->and(json_encode($seo->result))->toContain('Focus on Lahore first')->not->toContain('Finance only note');

    AiAgent::where('slug', 'seo-manager')->update(['memory_policy' => json_encode(['task' => true, 'shared' => false])]);
    $seo = continuationBrain()->submit('Run an SEO audit', 'seo-manager', $this->admin);
    expect(json_encode($seo->result))->not->toContain('Focus on Lahore first');
});

it('versions a re-saved note and archives the old one', function () {
    $this->actingAs($this->admin)->post(route('admin.ai.memory.store'), ['scope' => 'shared', 'key' => 'k', 'content' => 'First', 'source' => 'S']);
    $this->post(route('admin.ai.memory.store'), ['scope' => 'shared', 'key' => 'k', 'content' => 'Second', 'source' => 'S']);

    expect(AiMemory::where('key', 'k')->where('status', 'approved')->sole()->content)->toBe('Second')
        ->and(AiMemory::where('key', 'k')->where('status', 'approved')->value('version'))->toBe(2)
        ->and(AiMemory::where('key', 'k')->where('status', 'archived')->count())->toBe(1);
});

it('lets only administrators write memory notes', function () {
    $dealer = User::factory()->dealer(DealerStatus::Verified)->create();
    $this->actingAs($dealer)->post(route('admin.ai.memory.store'), ['scope' => 'shared', 'key' => 'k', 'content' => 'x', 'source' => 'S'])->assertForbidden();
    expect(AiMemory::count())->toBe(0);
});

it('keeps a log of subtasks in task memory for delegated reports', function () {
    $task = continuationBrain()->submit('Prepare a business performance report for this month', 'ceo', $this->admin);

    expect(AiMemory::where('scope', 'task')->where('reference', $task->uuid)->count())->toBe($task->children()->count())
        ->and(collect($task->result['sections'])->firstWhere('heading', 'Subtask log'))->not->toBeNull();
});

/*
| Multi-agent reports
*/

it('scopes an expansion report to the city named, and consolidates specialist reports', function () {
    $task = continuationBrain()->submit('Prepare a complete Lahore expansion performance report', null, $this->admin);

    expect($task->agent->slug)->toBe('ceo')->and($task->intent)->toBe('expansion')
        ->and($task->result['title'])->toBe('Lahore expansion report')
        ->and($task->children()->pluck('request'))->toContain('Lead performance in Lahore');
});

it('does not write drafts when the CEO only asks for a report', function () {
    continuationBrain()->submit('Prepare a complete seven-city business expansion report', 'ceo', $this->admin);

    expect(AiDraft::count())->toBe(0);
});

it('includes market data status in the CEO business report', function () {
    $task = continuationBrain()->submit('Give me the current business performance report', 'ceo', $this->admin);

    expect($task->children()->with('agent')->get()->pluck('agent.slug'))->toContain('investment-analyst');
});

/*
| Website and WhatsApp routing
*/

it('routes the brief\'s dealer registration question to Customer Support on the website', function () {
    $this->postJson(route('ai-finder.match'), ['message' => 'How can I register as a property dealer?'])
        ->assertOk()->assertJsonPath('intent', 'agent_customer_support')
        ->assertJsonPath('reply', fn ($r) => str_contains($r, 'Dealer Registration'));
});

it('answers Urdu-script support questions from the same approved article', function () {
    $this->postJson(route('ai-finder.match'), ['message' => 'ڈیلر رجسٹر کیسے کریں؟'])
        ->assertOk()->assertJsonPath('intent', 'agent_customer_support')
        ->assertJsonPath('reply', fn ($r) => str_contains($r, 'par jaa kar'));
});

it('routes a signed-in dealer\'s package question to the Subscription Manager', function () {
    $dealer = User::factory()->dealer(DealerStatus::Verified)->create();
    $this->actingAs($dealer)->postJson(route('ai-finder.match'), ['message' => 'What is my package?'])
        ->assertOk()->assertJsonPath('intent', 'agent_subscription_manager');
});

it('never quotes internal knowledge to the public through Customer Support', function () {
    $doc = new AiKnowledgeDocument(['category' => 'competitor_research', 'title' => 'Dealer registration on rival portals', 'body' => 'INTERNAL dealer registration comparison register dealer', 'source' => 'Internal']);
    $doc->forceFill(['status' => 'approved', 'version' => 1])->save();

    $task = continuationBrain()->submit('How do I register as a dealer?', 'customer-support', null, 'website');

    expect(json_encode($task->result))->not->toContain('INTERNAL');
    $research = continuationBrain()->submit('Competitor overview dealer registration', 'competitor-research', $this->admin);
    expect(json_encode($research->result))->toContain('INTERNAL');
});

/*
| Language model: budgets across subtasks, structured intent choice
*/

it('counts subtasks against the task token limit, and only words the final report', function () {
    fakeReplies(['Summary without figures.', 'Summary without figures.']);
    $task = continuationBrain()->submit('Prepare a business performance report for this month', 'ceo', $this->admin);

    expect($task->children()->where('mode', 'llm')->count())->toBe(0)->and($task->mode)->toBe('llm')
        ->and(AiUsageRecord::where('purpose', 'narrative')->count())->toBe(1);

    AiTask::whereKey($task->children()->value('id'))->update(['tokens_in' => 9000]);
    $guard = app(BudgetGuard::class);
    expect($guard->check($task->agent, $task->fresh())[0])->toBeFalse();
});

it('checks the overall budget before routing with the model', function () {
    fakeReplies(['seo-manager']);
    config(['meezan.agents.limits.daily_tokens_total' => 10]);
    AiUsageRecord::create(['provider' => 'openai', 'model' => 'x', 'purpose' => 'narrative', 'tokens_in' => 50, 'tokens_out' => 0, 'ok' => true, 'created_at' => now()]);

    expect(app(IntentRouter::class)->route('Something about staff rota'))->toBe('hr-admin');
    Http::assertNothingSent();
});

it('tells the administrators once when a budget stops the model', function () {
    Notification::fake();
    fakeReplies(['Fine.']);
    config(['meezan.agents.limits.daily_tokens_total' => 10, 'cache.default' => 'array']);
    AiUsageRecord::create(['provider' => 'openai', 'model' => 'x', 'purpose' => 'narrative', 'tokens_in' => 50, 'tokens_out' => 0, 'ok' => true, 'created_at' => now()]);

    continuationBrain()->submit('Run an SEO audit', 'seo-manager', $this->admin);
    continuationBrain()->submit('Run an SEO audit', 'seo-manager', $this->admin);

    Notification::assertSentToTimes($this->admin, AgentNeedsAttention::class, 1);
});

it('lets the model pick a workflow only when the rules find none, accepting only that worker\'s workflows', function () {
    fakeReplies(['{"intent":"anomalies"}', 'Summary without figures.', '{"intent":"drop tables"}', 'Summary without figures.']);

    $task = continuationBrain()->submit('Anything odd about what sellers are asking?', 'property-manager', $this->admin);
    expect($task->intent)->toBe('anomalies')->and(AiUsageRecord::where('purpose', 'intent')->count())->toBe(1);
    Http::assertSent(fn ($r) => ($r['response_format']['type'] ?? null) === 'json_object');

    $task = continuationBrain()->submit('Anything odd about what sellers are asking?', 'property-manager', $this->admin);
    expect($task->intent)->toBe('inventory');
});

it('compares figures whole when checking model wording against the data', function (string $text, string $evidence, bool $supported) {
    expect(Grounding::supports($text, $evidence))->toBe($supported);
})->with([
    'part of a longer number' => ['Prices will rise 45% next year.', 'score 145 and 84.45', false],
    'exact' => ['A rise of 11.11% and a yield of 6%.', '{"change":11.11,"yield":6}', true],
    'trailing zeros' => ['Yield of 6%.', '{"yield":"6.00"}', true],
    'thousands separator' => ['1,200 listings.', 'count 1200', true],
    'date fragment does not vouch for a figure' => ['Up 45%.', '2026-11-03 10:45:00 and 2045', false],
    'no figures' => ['Prices are steady.', 'nothing', true],
]);

it('discards model wording that borrows digits from a longer number', function () {
    fakeReplies(['Listings grew by 45 percent.']);
    Property::factory()->count(2)->create(['city' => 'Lahore']);
    config(['meezan.agents.models.research' => 'model-145']);
    $task = continuationBrain()->submit('Run an SEO audit', 'seo-manager', $this->admin);

    expect($task->mode)->toBe('rules')->and($task->result['narrative'])->toBeNull();
});

/*
| Approvals tell administrators
*/

it('tells the administrators when an approval is waiting', function () {
    Notification::fake();
    $dealer = User::factory()->dealer(DealerStatus::Pending)->create();

    continuationBrain()->submit('Recommend verification', 'dealer-manager', $this->admin, input: ['dealer_id' => $dealer->id, 'recommendation' => 'Documents look complete.'], intent: 'verify');

    Notification::assertSentTo($this->admin, AgentNeedsAttention::class, fn ($n) => $n->title === 'AI approval waiting');
});

/*
| Operational status
*/

it('derives each agent\'s operational status from evidence', function () {
    $readiness = fn () => app(AgentReadiness::class)->all();

    expect($readiness()['hr-admin']['state'])->toBe('untested')
        ->and($readiness()['competitor-research']['state'])->toBe('waiting_credentials')
        ->and($readiness()['seo-manager']['limits'])->not->toBeEmpty();

    AiAgent::query()->update(['updated_at' => now()->subMinute()]);
    continuationBrain()->submit('Staff directory', 'hr-admin', $this->admin);
    expect($readiness()['hr-admin']['state'])->toBe('verified')->and($readiness()['hr-admin']['completed'])->toBe(1);

    AiAgent::where('slug', 'hr-admin')->update(['status' => 'disabled']);
    expect($readiness()['hr-admin']['state'])->toBe('disabled');
});

it('shows a failed latest task as an error, and a changed agent as untested again', function () {
    AiAgent::query()->update(['updated_at' => now()->subMinute()]);
    app()->bind(PropertyManagerAgent::class, fn () => new class extends PropertyManagerAgent
    {
        public function handle(AgentContext $ctx, string $intent): Report
        {
            throw new RuntimeException('down');
        }
    });
    continuationBrain()->submit('Find incomplete listings', 'property-manager', $this->admin);
    expect(app(AgentReadiness::class)->all()['property-manager']['state'])->toBe('error');

    continuationBrain()->submit('Staff directory', 'hr-admin', $this->admin);
    AiAgent::where('slug', 'hr-admin')->update(['updated_at' => now()->addMinute()]);
    expect(app(AgentReadiness::class)->all()['hr-admin']['state'])->toBe('untested');
});

it('treats competitor research as ready once approved notes exist', function () {
    $doc = new AiKnowledgeDocument(['category' => 'competitor_research', 'title' => 'Notes', 'body' => 'Portal notes', 'source' => 'Staff']);
    $doc->forceFill(['status' => 'approved', 'version' => 1])->save();

    expect(app(AgentReadiness::class)->all()['competitor-research']['state'])->toBe('untested');
});

/*
| Staff Work: what agents pass to people
*/

it('shows an escalated complaint to staff, alerts them, and lets them resolve it', function () {
    Notification::fake();
    $buyer = User::factory()->create();
    $task = continuationBrain()->submit('I want to file a complaint, this dealer is a fraud', 'customer-support', $buyer, 'website');
    $ticket = SupportTicket::sole();

    Notification::assertSentTo($this->admin, AgentNeedsAttention::class, fn ($n) => str_contains($n->body, $ticket->reference));
    $this->actingAs($this->admin)->get(route('admin.ai.work'))->assertOk()->assertSee($ticket->reference)->assertSee('this dealer is a fraud');

    $this->patch(route('admin.ai.tickets.update', $ticket), ['status' => 'resolved'])->assertSessionHas('status');
    expect($ticket->fresh()->status)->toBe('resolved')->and($ticket->fresh()->assigned_to)->toBe($this->admin->id);
    $this->get(route('admin.ai.work')); // the first page after the redirect still shows the status message
    $this->get(route('admin.ai.work'))->assertDontSee($ticket->reference);
    $this->assertDatabaseHas('audit_logs', ['action' => 'ai.ticket_resolved']);
    expect($task->fresh()->status)->toBe('completed');
});

it('shows staff the task an approval created, and lets them finish it', function () {
    $dealer = User::factory()->dealer(DealerStatus::Pending)->create();
    continuationBrain()->submit('Recommend verification', 'dealer-manager', $this->admin, input: ['dealer_id' => $dealer->id, 'recommendation' => 'Documents look complete.'], intent: 'verify');
    app(ApprovalExecutor::class)->approve(AiApproval::sole(), $this->admin);
    $task = StaffTask::sole();

    $this->actingAs($this->admin)->get(route('admin.ai.work'))->assertOk()->assertSee('Review dealer verification');
    $this->patch(route('admin.ai.staff-tasks.update', $task), ['action' => 'assign']);
    expect($task->fresh()->assigned_to)->toBe($this->admin->id)->and($task->fresh()->status)->toBe('open');
    $this->patch(route('admin.ai.staff-tasks.update', $task), ['action' => 'done']);
    expect($task->fresh()->status)->toBe('done')
        ->and($dealer->dealerProfile->fresh()->verification_status)->toBe(DealerStatus::Pending); // staff decide in Admin -> Dealers
});

it('lets staff read and discard a draft, but not an approved one', function () {
    continuationBrain()->submit('Prepare a Karachi dealer onboarding strategy.', null, $this->admin);
    $draft = AiDraft::sole();

    $this->actingAs($this->admin)->get(route('admin.ai.work'))->assertOk()->assertSee('dealer onboarding');
    $this->patch(route('admin.ai.drafts.update', $draft), ['status' => 'rejected'])->assertSessionHas('status');
    expect($draft->fresh()->status)->toBe('rejected');

    $draft->forceFill(['status' => 'approved'])->save();
    $this->patch(route('admin.ai.drafts.update', $draft), ['status' => 'rejected'])->assertStatus(409);
    $this->patch(route('admin.ai.drafts.update', $draft), ['status' => 'published'])->assertSessionHasErrors('status');
});

it('keeps Staff Work admin only', function () {
    $dealer = User::factory()->dealer(DealerStatus::Verified)->create();
    $ticket = new SupportTicket(['channel' => 'website', 'category' => 'other', 'subject' => 'S', 'summary' => 'x']);
    $ticket->save();

    $this->actingAs($dealer)->get(route('admin.ai.work'))->assertForbidden();
    $this->patch(route('admin.ai.tickets.update', $ticket), ['status' => 'resolved'])->assertForbidden();
    expect($ticket->fresh()->status)->toBe('open');
});

/*
| Command Center pages
*/

it('shows the tool registry, activity log and operational status to admins, without secrets', function () {
    config(['services.openai.key' => 'sk-secret-'.str_repeat('Q', 30)]);
    AiKnowledgeDocument::query()->update(['status' => 'approved']);
    continuationBrain()->submit('Prepare a business performance report for this month', 'ceo', $this->admin);
    continuationContext('marketing-director')->tool('system.shell');

    $this->actingAs($this->admin)->get(route('admin.ai.tools'))->assertOk()
        ->assertSee('crm.outcomes')->assertSee('No agent can be given this')->assertDontSee('QQQQQQ');
    $this->get(route('admin.ai.logs'))->assertOk()->assertSee('system.shell')->assertSee('AI CEO')->assertDontSee('QQQQQQ');
    $this->get(route('admin.ai.overview'))->assertOk()->assertSee('Central AI status')->assertDontSee('QQQQQQ');
    $this->get(route('admin.ai.agents.index'))->assertOk()->assertSee('Operational status')->assertSee('Waiting for credentials');
    $this->get(route('admin.ai.agents.show', AiAgent::where('slug', 'seo-manager')->first()))->assertOk()->assertSee('Stays off until configured');
    $this->get(route('admin.ai.knowledge'))->assertOk()->assertSee('Add a memory note');
});

it('keeps the new Command Center pages admin only', function () {
    $dealer = User::factory()->dealer(DealerStatus::Verified)->create();
    foreach ([route('admin.ai.tools'), route('admin.ai.logs')] as $url) {
        $this->actingAs($dealer)->get($url)->assertForbidden();
    }
    auth()->logout();
    $this->get(route('admin.ai.tools'))->assertRedirect();
});

it('has a tool definition behind every tool an agent is given', function () {
    $registry = app(ToolRegistry::class);
    foreach (AiAgent::all() as $agent) {
        foreach ($agent->tools as $tool) {
            expect($registry->get($tool))->not->toBeNull("{$agent->slug}: {$tool}");
        }
    }
});

it('matches compared names as whole words, not inside other names', function () {
    Area::create(['city' => 'Lahore', 'name' => 'Zeta Whole Word', 'slug' => 'zeta-whole-word']);
    Area::create(['city' => 'Lahore', 'name' => 'Agzeta Cooperative Test', 'slug' => 'agzeta-cooperative-test']);
    Area::create(['city' => 'Lahore', 'name' => 'Zetapura Test', 'slug' => 'zetapura-test']);

    $names = collect(continuationContext('investment-analyst')->data('investment.compare', ['names' => ['Zeta'], 'city' => 'Lahore'])['items'][0]['locations'])->pluck('name');

    expect($names)->toContain('Zeta Whole Word')->not->toContain('Agzeta Cooperative Test')->not->toContain('Zetapura Test');
});

it('counts verified market records, and only verified ones, in a comparison', function () {
    $area = Area::create(['city' => 'Lahore', 'name' => 'Counted Town', 'slug' => 'counted-town']);
    $source = MarketSource::create(['name' => 'S', 'type' => 'admin_research', 'reliability' => 'high']);
    $observation = fn (string $status, string $date) => (new MarketObservation)->forceFill([
        'country' => 'Pakistan', 'city' => 'Lahore', 'area_id' => $area->id, 'kind' => 'sale', 'price_basis' => 'asking', 'asset_class' => 'residential', 'property_type' => 'house',
        'currency' => 'PKR', 'average_amount' => 1000000, 'sample_size' => 1, 'observed_on' => $date, 'source_id' => $source->id, 'methodology' => 'test', 'status' => $status, 'fingerprint' => md5($status.$date),
    ])->save();
    $observation('verified', '2026-01-01');
    $observation('draft', '2026-02-01');

    $item = continuationContext('investment-analyst')->data('investment.compare', ['names' => ['Counted Town'], 'city' => 'Lahore'])['items'][0];

    expect($item['verified_observations'])->toBe(1)->and($item['verified_indicators'])->toBe(0)
        ->and($item['locations'][0]['name'])->toBe('Counted Town');
});
