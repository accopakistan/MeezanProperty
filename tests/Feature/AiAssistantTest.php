<?php

use App\Enums\DealerStatus;
use App\Enums\PropertyType;
use App\Enums\Purpose;
use App\Models\Area;
use App\Models\AuditLog;
use App\Models\BuyerRequirement;
use App\Models\City;
use App\Models\ContactConsent;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\Lead;
use App\Models\Property;
use App\Models\User;
use App\Models\WebhookEvent;
use App\Notifications\AssistantNeedsAttention;
use App\Notifications\NewRequirementMatches;
use App\Services\Ai\LlmRequirementParser;
use App\Services\Ai\RequirementParser;
use App\Services\Ai\RulesRequirementParser;
use App\Services\Matching\MatchCriteria;
use App\Services\Matching\PropertyMatch;
use App\Services\Matching\PropertyMatcher;
use App\Services\WhatsApp\WebhookProcessor;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

/* Helpers --------------------------------------------------------------- */

function aiPlace(string $city, string $name, array $attributes = []): Area
{
    return Area::create($attributes + ['city' => $city, 'name' => $name, 'slug' => Str::slug($name.' '.$city.' '.uniqid())]);
}

function chat($test, string $message): TestResponse
{
    return $test->postJson(route('ai-finder.match'), ['message' => $message])->assertOk();
}

function aiDealer(string $city, array $areaIds): User
{
    $dealer = User::factory()->dealer(DealerStatus::Verified, $areaIds)->create();
    $dealer->dealerProfile->update(['city' => $city]);
    $dealer->dealerProfile->cities()->sync(City::where('name', $city)->pluck('id'));

    return $dealer;
}

function waConfigure(bool $send = false): void
{
    config([
        'services.whatsapp.phone_number_id' => '1234567890',
        'services.whatsapp.access_token' => 'test-token',
        'services.whatsapp.app_secret' => 'test-secret',
        'services.whatsapp.verify_token' => 'verify-me',
        'services.whatsapp.send_enabled' => $send,
    ]);
}

function waSend($test, string $id, string $text, string $from = '923001112233')
{
    $body = json_encode(['object' => 'whatsapp_business_account', 'entry' => [['changes' => [['value' => [
        'contacts' => [['wa_id' => $from, 'profile' => ['name' => 'Ayesha']]],
        'messages' => [['id' => $id, 'from' => $from, 'type' => 'text', 'text' => ['body' => $text]]],
    ]]]]]]);

    return $test->call('POST', '/webhooks/whatsapp', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $body, 'test-secret'),
    ], $body);
}

/* Conversations and memory ---------------------------------------------- */

it('keeps the conversation and remembers the city across messages', function () {
    chat($this, 'I want a house in Lahore')->assertJsonPath('follow_up', 'Are you looking to buy or to rent?');
    chat($this, '10 marla and around 4 crore');

    $conversation = Conversation::sole();
    expect($conversation->channel)->toBe('website')
        ->and($conversation->state)->toMatchArray(['city' => 'Lahore', 'type' => 'house', 'min_area_sqft' => 2250, 'max_price' => 40_000_000, 'budget_approx' => true, 'purpose' => 'sale'])
        ->and($conversation->messages()->where('role', 'user')->pluck('body')->all())->toBe(['I want a house in Lahore', '10 marla and around 4 crore'])
        ->and($conversation->messages()->where('role', 'assistant')->count())->toBe(2)
        ->and($conversation->messages()->where('role', 'user')->first()->intent)->toBe('requirement');
});

it('shows the saved history after a reload and starts fresh on request', function () {
    chat($this, 'flat on rent in Gulberg');
    $this->get(route('ai-finder'))->assertOk()->assertSee('flat on rent in Gulberg');

    $this->postJson(route('ai-finder.reset'))->assertOk();
    expect(Conversation::sole()->status)->toBe('closed');

    chat($this, 'plot in Islamabad');
    expect(Conversation::count())->toBe(2)
        ->and(Conversation::latest('id')->first()->state['city'])->toBe('Islamabad');
});

it('never shows one visitor another visitor\'s conversation', function () {
    chat($this, 'house in Gulberg around 3 crore');

    $this->flushSession();
    $this->get(route('ai-finder'))->assertOk()->assertDontSee('house in Gulberg around 3 crore');
    chat($this, 'hello');

    expect(Conversation::count())->toBe(2);
});

it('links the conversation to a signed-in buyer', function () {
    $buyer = User::factory()->create();

    $this->actingAs($buyer);
    chat($this, 'house in Model Town');

    expect(Conversation::sole()->user_id)->toBe($buyer->id);
});

/* Requirement parsing ----------------------------------------------------- */

it('understands the full English example', function () {
    $c = app(RulesRequirementParser::class)->parse('I need a 10 marla house in DHA Lahore. My budget is around 4 crore.');
    $dha = aiPlace('Lahore', 'DHA Lahore', ['type' => 'defence_scheme']);
    $c = app(RulesRequirementParser::class)->parse('I need a 10 marla house in DHA Lahore. My budget is around 4 crore.');

    expect($c->city)->toBe('Lahore')
        ->and($c->areaIds)->toContain($dha->id)
        ->and($c->type)->toBe(PropertyType::House)
        ->and($c->minAreaSqft)->toBe(2250)
        ->and($c->maxPrice)->toBe(40_000_000)
        ->and($c->budgetApprox)->toBeTrue();
});

it('reads English, Roman Urdu and Urdu the same way', function () {
    aiPlace('Lahore', 'DHA Lahore', ['type' => 'defence_scheme']);
    $parser = app(RulesRequirementParser::class);

    $english = $parser->parse('I need a 10 marla house in DHA Lahore');
    $roman = $parser->parse('Mujhe DHA Lahore mein 10 marla ka ghar chahiye');
    $urdu = $parser->parse('مجھے ڈی ایچ اے لاہور میں دس مرلہ گھر چاہیے');

    foreach ([$roman, $urdu] as $c) {
        expect($c->city)->toBe($english->city)
            ->and($c->areaIds)->toBe($english->areaIds)
            ->and($c->type)->toBe(PropertyType::House)
            ->and($c->minAreaSqft)->toBe(2250);
    }
});

it('converts Pakistani sizes and budgets', function (string $text, string $field, int $expected) {
    $c = app(RulesRequirementParser::class)->parse($text);

    expect($c->{$field})->toBe($expected);
})->with([
    ['5 marla house', 'minAreaSqft', 1125],
    ['1 kanal house', 'minAreaSqft', 4500],
    ['half kanal plot', 'minAreaSqft', 2250],
    ['derh kanal house', 'minAreaSqft', 6750],
    ['250 square yards bungalow', 'minAreaSqft', 2250],
    ['250 gaz house', 'minAreaSqft', 2250],
    ['budget 80 lakh', 'maxPrice', 8_000_000],
    ['1.5 crore tak', 'maxPrice', 15_000_000],
    ['3 crore', 'maxPrice', 30_000_000],
    ['25 million', 'maxPrice', 25_000_000],
    ['rent 85 thousand', 'maxPrice', 85_000],
    ['PKR 40000000', 'maxPrice', 40_000_000],
    ['تین کروڑ تک', 'maxPrice', 30_000_000],
    ['2 to 3 crore', 'minPrice', 20_000_000],
]);

it('reads furnishing and stated reasons without scoring the reasons', function () {
    $c = app(RulesRequirementParser::class)->parse('semi furnished flat on rent for my family, also as an investment');

    expect($c->furnishing)->toBe('semi_furnished')
        ->and($c->tags)->toEqualCanonicalizing(['family', 'investment'])
        ->and($c->purpose)->toBe(Purpose::Rent);
});

it('asks a follow-up for a partial requirement and updates it in place', function () {
    chat($this, 'house in Johar Town')->assertJsonPath('follow_up', 'Are you looking to buy or to rent?');
    chat($this, 'to buy, budget 2 crore');
    chat($this, 'actually 3 crore');

    $state = Conversation::sole()->state;
    expect($state['max_price'])->toBe(30_000_000)
        ->and($state['purpose'])->toBe('sale')
        ->and($state['area_ids'])->toBe([area('Johar Town')->id]);
});

it('does not repeat a question it already asked', function () {
    chat($this, 'house in Gulberg')->assertJsonPath('follow_up', 'Are you looking to buy or to rent?');
    chat($this, '4 bedrooms')->assertJsonPath('follow_up', fn ($q) => $q !== 'Are you looking to buy or to rent?');
});

it('asks which place is meant and accepts a city or a number as the answer', function () {
    aiPlace('Quetta', 'Garden Town', ['type' => 'neighbourhood']);

    chat($this, 'house in Garden Town')
        ->assertJsonPath('intent', 'clarify_location')
        ->assertJsonPath('choices.0.value', 'Lahore')
        ->assertJsonPath('choices.1.value', 'Quetta');

    chat($this, 'Quetta');
    expect(Conversation::sole()->state)->toMatchArray(['city' => 'Quetta', 'type' => 'house']);

    $this->flushSession();
    chat($this, 'house in Garden Town');
    chat($this, '1');
    expect(Conversation::latest('id')->first()->state['area_ids'])->toBe([area('Garden Town')->id]);
});

/* Seven cities ------------------------------------------------------------ */

it('recognises all seven launch cities and refuses others politely', function () {
    foreach (['Karachi', 'Islamabad', 'Multan', 'Faisalabad', 'Quetta', 'Peshawar', 'Lahore'] as $city) {
        expect(app(RulesRequirementParser::class)->parse("house in {$city}")->city)->toBe($city);
    }

    chat($this, 'I need a house in Rawalpindi')
        ->assertJsonPath('intent', 'unsupported_city')
        ->assertJsonPath('reply', fn ($r) => str_contains($r, 'Lahore, Karachi, Islamabad, Multan, Faisalabad, Quetta and Peshawar') || str_contains($r, 'Lahore, Karachi, Islamabad, Multan, Faisalabad, Quetta, Peshawar'));
    expect(Conversation::sole()->state['city'] ?? null)->toBeNull();
});

it('understands phase abbreviations and sector codes', function () {
    $phase = aiPlace('Islamabad', 'F-7', ['type' => 'sector']);

    expect(app(RulesRequirementParser::class)->parse('house in DHA Ph V')->areaIds)->toBe([area('DHA Phase 5')->id])
        ->and(app(RulesRequirementParser::class)->parse('flat in F7 Islamabad')->areaIds)->toBe([$phase->id]);
});

it('never shows listings from another city', function () {
    $clifton = aiPlace('Karachi', 'Clifton');
    Property::factory()->create(['city' => 'Karachi', 'area_id' => $clifton->id, 'title' => 'Karachi Villa', 'price' => 50_000_000]);
    Property::factory()->create(['area_id' => area('Gulberg')->id, 'title' => 'Lahore Villa', 'price' => 50_000_000]);

    chat($this, 'house to buy in Karachi under 6 crore')
        ->assertJson(fn ($json) => $json->where('html', fn ($html) => str_contains($html, 'Karachi Villa') && ! str_contains($html, 'Lahore Villa'))->etc());
});

/* Matching, budget and explanations --------------------------------------- */

it('labels budget fit honestly and keeps alternatives only when on target', function () {
    $gulberg = area('Gulberg')->id;
    Property::factory()->create(['title' => 'Within', 'area_id' => $gulberg, 'price' => 38_000_000]);
    Property::factory()->create(['title' => 'Close', 'area_id' => $gulberg, 'price' => 42_000_000]);
    Property::factory()->create(['title' => 'Alternative', 'area_id' => $gulberg, 'price' => 48_000_000]);
    Property::factory()->create(['title' => 'Off Target Pricey', 'area_id' => area('Johar Town')->id, 'price' => 48_000_000]);

    $result = app(PropertyMatcher::class)->match(new MatchCriteria(purpose: Purpose::Sale, type: PropertyType::House, city: 'Lahore', areaIds: [$gulberg], maxPrice: 40_000_000), 10);
    $by = $result->matches->keyBy(fn ($m) => $m->property->title);

    expect($result->matches->pluck('property.title')->all())->toBe(['Within', 'Close', 'Alternative'])
        ->and($by['Within']->budgetStatus)->toBe(PropertyMatch::BUDGET_WITHIN)
        ->and($by['Within']->explanation())->toContain('within your PKR 4 Crore budget')
        ->and($by['Close']->label())->toBe('Close match — slightly above budget')
        ->and($by['Close']->explanation())->not->toContain('within your')
        ->and($by['Alternative']->label())->toBe('Alternative — above budget')
        ->and($by['Alternative']->explanation())->toContain('above your PKR 4 Crore budget');
});

it('ranks the requested area first and explains matches from listing data only', function () {
    Property::factory()->create(['title' => 'Elsewhere Cheap', 'area_id' => area('Johar Town')->id, 'price' => 10_000_000]);
    Property::factory()->create(['title' => 'In Area', 'area_id' => area('Gulberg')->id, 'price' => 30_000_000]);

    $result = app(PropertyMatcher::class)->match(new MatchCriteria(purpose: Purpose::Sale, city: 'Lahore', areaIds: [area('Gulberg')->id], maxPrice: 40_000_000));
    $text = $result->matches->map->explanation()->implode(' ');

    expect($result->matches->first()->property->title)->toBe('In Area')
        ->and($result->matches->last()->label())->toBe('Partial match — outside your area')
        ->and(strtolower($text))->not->toContain('investment')->not->toContain('guarantee')->not->toContain('appreciation');
});

it('explains a zero-result search and offers next steps', function () {
    Property::factory()->create(['area_id' => area('Gulberg')->id, 'purpose' => Purpose::Rent, 'price' => 150_000]);

    chat($this, '10 marla house to buy in Gulberg under 1 crore')
        ->assertJsonPath('intent', 'no_results')
        ->assertJsonPath('reply', fn ($r) => str_contains($r, 'No exact match is available right now') && str_contains($r, 'for rent instead') && str_contains($r, 'save this requirement'))
        ->assertJsonPath('actions', ['save', 'contact_dealers', 'new_search']);
});

/* Saving requirements ----------------------------------------------------- */

it('asks guests to sign in before saving', function () {
    chat($this, 'house to buy in Gulberg under 5 crore');

    $this->postJson(route('ai-finder.save'))->assertUnauthorized();
    expect(BuyerRequirement::count())->toBe(0);
});

it('saves one requirement per conversation and keeps it current', function () {
    $buyer = User::factory()->create();
    $this->actingAs($buyer);

    chat($this, 'house to buy in Gulberg under 5 crore');
    $this->postJson(route('ai-finder.save'))->assertOk()->assertJsonPath('intent', 'save');
    $this->postJson(route('ai-finder.save'))->assertOk();
    chat($this, 'make it 6 crore');

    $requirement = BuyerRequirement::sole();
    expect($requirement->user_id)->toBe($buyer->id)
        ->and($requirement->conversation_id)->toBe(Conversation::sole()->id)
        ->and($requirement->source)->toBe('ai')
        ->and($requirement->max_price)->toBe(60_000_000)
        ->and($requirement->area_ids)->toBe([area('Gulberg')->id])
        ->and($requirement->match_status)->not->toBeNull()
        ->and(Conversation::sole()->status)->toBe('qualified');
});

/* Leads and dealers ------------------------------------------------------- */

it('does not create a lead for small talk', function () {
    chat($this, 'hello');
    chat($this, 'thanks');

    expect(Lead::count())->toBe(0)->and(BuyerRequirement::count())->toBe(0);
});

it('needs explicit consent before sharing with dealers', function () {
    $this->actingAs(User::factory()->create());
    chat($this, 'house to buy in Gulberg under 5 crore');

    $this->postJson(route('ai-finder.contact'))->assertUnprocessable()->assertJsonValidationErrors('consent');
    expect(Lead::count())->toBe(0);
});

it('shares a consented requirement with at most three dealers in that city, once', function () {
    Notification::fake();
    $gulberg = area('Gulberg')->id;
    $lahore = collect(range(1, 5))->map(fn () => aiDealer('Lahore', [$gulberg]));
    $karachi = aiDealer('Karachi', [aiPlace('Karachi', 'Clifton')->id]);
    $buyer = User::factory()->create();
    $this->actingAs($buyer);

    chat($this, 'house to buy in Gulberg under 5 crore');
    $this->postJson(route('ai-finder.contact'), ['consent' => '1'])->assertOk()->assertJsonPath('intent', 'dealers_connected');
    $this->postJson(route('ai-finder.contact'), ['consent' => '1'])->assertOk();

    $lead = Lead::sole();
    $assigned = $lead->activeAssignments()->pluck('dealer_id');
    expect($assigned)->toHaveCount(3)
        ->and($assigned->diff($lahore->pluck('id')))->toBeEmpty()
        ->and($assigned)->not->toContain($karachi->id)
        ->and(ContactConsent::where('purpose', 'share_with_dealers')->where('user_id', $buyer->id)->exists())->toBeTrue()
        ->and(Conversation::sole()->status)->toBe('lead')
        ->and(AuditLog::where('action', 'lead.assigned')->count())->toBe(3);
});

it('keeps dealers and guests away from the buyer-only actions', function () {
    chat($this, 'house to buy in Gulberg under 5 crore');
    $this->postJson(route('ai-finder.contact'), ['consent' => '1'])->assertUnauthorized();

    $this->actingAs(User::factory()->dealer(DealerStatus::Verified)->create())
        ->postJson(route('ai-finder.contact'), ['consent' => '1'])->assertForbidden();

    expect(Lead::count())->toBe(0);
});

/* LLM fallback ------------------------------------------------------------ */

it('uses the rules engine when no LLM key is configured', function () {
    config(['meezan.ai.driver' => 'llm', 'services.openai.key' => null]);

    expect(app(RequirementParser::class))->toBeInstanceOf(RulesRequirementParser::class);
    chat($this, 'house in Gulberg under 5 crore')->assertJsonPath('reply', fn ($r) => str_contains($r, 'best option') || str_contains($r, 'No exact match'));
});

it('falls back to the rules when the LLM fails', function () {
    config(['meezan.ai.driver' => 'llm', 'services.openai.key' => 'test-key']);
    Http::fake(['*/chat/completions' => Http::response('down', 500)]);
    Property::factory()->create(['title' => 'Fallback Match', 'area_id' => area('Gulberg')->id, 'price' => 40_000_000]);

    expect(app(RequirementParser::class))->toBeInstanceOf(LlmRequirementParser::class);
    chat($this, 'house to buy in Gulberg under 5 crore')
        ->assertJson(fn ($json) => $json->where('html', fn ($html) => str_contains($html, 'Fallback Match'))->etc());
});

it('never shows a technical error when the parser throws', function () {
    app()->bind(RequirementParser::class, fn () => new class implements RequirementParser
    {
        public function parse(string $message): MatchCriteria
        {
            throw new RuntimeException('provider exploded');
        }
    });

    chat($this, 'house in Gulberg')->assertJsonPath('reply', fn ($r) => ! str_contains($r, 'exploded'));
});

/* WhatsApp ---------------------------------------------------------------- */

it('runs the same assistant on WhatsApp, with consent before sharing', function () {
    waConfigure();
    Notification::fake();
    aiDealer('Lahore', [area('Gulberg')->id]);

    waSend($this, 'wamid.1', 'Assalamualaikum, mujhe Gulberg Lahore mein 10 marla ka ghar chahiye 5 crore tak')->assertOk();
    waSend($this, 'wamid.2', 'dealer se baat karwa dein')->assertOk();
    expect(Lead::count())->toBe(0);
    waSend($this, 'wamid.3', 'YES')->assertOk();

    $conversation = Conversation::where('channel', 'whatsapp')->sole();
    $requirement = BuyerRequirement::sole();
    expect($conversation->state['city'])->toBe('Lahore')
        ->and($requirement->source)->toBe('whatsapp')
        ->and($requirement->contact_phone)->toBe('923001112233')
        ->and(Lead::sole()->activeAssignments()->count())->toBe(1)
        ->and(ContactConsent::granted('share_with_dealers', 'whatsapp', '923001112233'))->toBeTrue()
        ->and($conversation->messages()->where('role', 'assistant')->reorder()->latest('id')->first()->body)->toContain('shared with 1 verified dealer')
        ->and($conversation->messages()->where('role', 'assistant')->reorder()->latest('id')->first()->delivery_status)->toBe('not_sent');
});

it('stops and revokes consent on WhatsApp STOP', function () {
    waConfigure();
    ContactConsent::record('whatsapp_replies', 'whatsapp', 'whatsapp_keyword', null, '923001112233');

    waSend($this, 'wamid.S1', 'STOP');

    expect(ContactConsent::granted('whatsapp_replies', 'whatsapp', '923001112233'))->toBeFalse()
        ->and(Conversation::sole()->mode)->toBe('closed');
});

it('rejects malformed webhook payloads and ignores repeated deliveries', function () {
    waConfigure();
    $body = json_encode(['object' => 'page', 'entry' => 'nope']);
    $this->call('POST', '/webhooks/whatsapp', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $body, 'test-secret')], $body)
        ->assertStatus(422);

    waSend($this, 'wamid.D1', 'house in Gulberg')->assertJson(['received' => 1]);
    waSend($this, 'wamid.D1', 'house in Gulberg')->assertJson(['received' => 0, 'duplicate' => true]);

    expect(WebhookEvent::pluck('status')->sort()->values()->all())->toBe(['processed', 'rejected'])
        ->and(ConversationMessage::where('role', 'user')->count())->toBe(1);
});

it('records a failed webhook and alerts admins', function () {
    waConfigure();
    Notification::fake();
    $admin = User::factory()->admin()->create();
    app()->bind(WebhookProcessor::class, fn () => new class extends WebhookProcessor
    {
        public function __construct() {}

        public function process(array $payload): int
        {
            throw new RuntimeException('database unavailable');
        }
    });

    waSend($this, 'wamid.F1', 'hello')->assertStatus(500);

    expect(WebhookEvent::sole()->status)->toBe('failed');
    Notification::assertSentTo($admin, AssistantNeedsAttention::class, fn ($n) => $n->reason === 'webhook_failed');
});

/* Escalation, admin and housekeeping --------------------------------------- */

it('escalates to admins when a buyer asks for a person or is not understood', function () {
    Notification::fake();
    $admin = User::factory()->admin()->create();

    chat($this, 'I want to talk to a real person');
    expect(Conversation::sole())->status->toBe('escalated')->escalation_reason->toBe('human');
    Notification::assertSentTo($admin, AssistantNeedsAttention::class, fn ($n) => $n->reason === 'human');

    $this->flushSession();
    foreach (['asdf', 'qwerty', 'zxcv'] as $nonsense) {
        chat($this, $nonsense);
    }
    expect(Conversation::latest('id')->first()->status)->toBe('failed');
});

it('flags a burst of messages once', function () {
    Notification::fake();
    config(['meezan.ai.burst_limit' => 3]);
    $admin = User::factory()->admin()->create();

    foreach (range(1, 6) as $i) {
        chat($this, 'house in Gulberg '.$i.' crore');
    }

    Notification::assertSentToTimes($admin, AssistantNeedsAttention::class, 1);
    expect(Conversation::sole()->escalation_reason)->toBe('suspicious');
});

it('lets admins review conversations with masked numbers and audits a reveal', function () {
    waConfigure();
    waSend($this, 'wamid.A1', 'house in Gulberg');
    $admin = User::factory()->admin()->create();
    $conversation = Conversation::sole();

    $this->actingAs($admin)->get(route('admin.conversations.index'))->assertOk()->assertSee('•••• 2233')->assertDontSee('923001112233');
    $this->actingAs($admin)->get(route('admin.conversations.show', $conversation))->assertOk()->assertDontSee('923001112233');
    $this->actingAs($admin)->post(route('admin.conversations.reveal', $conversation))->assertSessionHas('revealed_contact_'.$conversation->id, '+923001112233');

    expect(AuditLog::where('action', 'conversation.contact_revealed')->exists())->toBeTrue();
    $this->actingAs(User::factory()->create())->get(route('admin.conversations.index'))->assertForbidden();
});

it('keeps conversations until a retention policy is set, then prunes unlinked ones', function () {
    chat($this, 'hello');
    $this->flushSession();
    $this->actingAs(User::factory()->create());
    chat($this, 'house to buy in Gulberg under 5 crore');
    $this->postJson(route('ai-finder.save'));
    Conversation::query()->update(['last_message_at' => now()->subDays(400)]);

    $this->artisan('meezan:prune-conversations')->assertSuccessful();
    expect(Conversation::count())->toBe(2);

    config(['meezan.ai.retention_days' => 365]);
    $this->artisan('meezan:prune-conversations')->assertSuccessful();
    expect(Conversation::count())->toBe(1)->and(Conversation::sole()->buyer_requirement_id)->not->toBeNull();
});

it('tells buyers about new listings for an AI-saved requirement', function () {
    Notification::fake();
    $buyer = User::factory()->create();
    $this->actingAs($buyer);
    chat($this, 'house to buy in Gulberg under 5 crore');
    $this->postJson(route('ai-finder.save'));
    BuyerRequirement::query()->update(['matched_at' => now()->subDay()]);
    Property::factory()->create(['area_id' => area('Gulberg')->id, 'price' => 45_000_000, 'published_at' => now()]);

    $this->artisan('meezan:requirement-matches')->assertSuccessful();

    Notification::assertSentTo($buyer, NewRequirementMatches::class, fn ($n) => $n->count === 1);
});
