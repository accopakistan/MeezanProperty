<?php

use App\Enums\DealerStatus;
use App\Models\AiAgent;
use App\Models\AiAgentVersion;
use App\Models\AiProviderSetting;
use App\Models\AiTask;
use App\Models\AiToolCall;
use App\Models\AiUsageRecord;
use App\Models\AppSetting;
use App\Models\AuditLog;
use App\Models\User;
use App\Notifications\AgentNeedsAttention;
use App\Services\Agents\AiProvider;
use App\Services\Agents\CentralAiOrchestrator;
use App\Services\Agents\ConnectorStatus;
use App\Services\Agents\OutputGuard;
use App\Services\Agents\PersonalDataMask;
use App\Services\Agents\Providers\GeminiAdapter;
use App\Services\Agents\Providers\ProviderAdapter;
use App\Services\Agents\Providers\ProviderConfig;
use App\Services\Agents\ToolDeclarations;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;

/*
| Google Gemini behind the central provider manager. AUTOMATED MOCK TESTS:
| every request here is faked; no real key exists and nothing reaches
| Google. They prove the request and response handling against Google's
| documented format, not a live account: the live test is the "Test Gemini
| connection" button once the owner's key is entered.
*/

const GEMINI_TEST_KEY = 'AIza'.'SyTestKey00000000000000000000000000';

beforeEach(function () {
    Http::preventStrayRequests();
    config(['services.gemini.key' => null, 'services.gemini.max_retries' => 0, 'meezan.agents.use_llm' => true, 'meezan.agents.provider' => 'gemini',
        'services.gemini.model' => 'gemini-3.8-flash', 'services.gemini.thinking_level' => 'low']);
    $this->admin = User::factory()->admin()->create();
});

function gemini(): AiProvider
{
    return app(AiProvider::class);
}

function withGeminiKey(string $model = 'gemini-3.8-flash'): void
{
    config(['services.gemini.key' => GEMINI_TEST_KEY, 'services.gemini.model' => $model]);
}

function geminiReply(string $text = 'Summary without figures.', array $extra = [], string $finish = 'STOP'): array
{
    return ['candidates' => [['content' => ['role' => 'model', 'parts' => [['text' => $text]]], 'finishReason' => $finish]],
        'usageMetadata' => ['promptTokenCount' => 100, 'candidatesTokenCount' => 20, 'thoughtsTokenCount' => 5, 'totalTokenCount' => 125], 'modelVersion' => 'gemini-3.8-flash'] + $extra;
}

function geminiError(int $status, string $state, string $message = 'x', array $details = []): array
{
    return ['error' => ['code' => $status, 'message' => $message, 'status' => $state, 'details' => $details]];
}

function geminiAgent(string $slug = 'seo-manager'): AiAgent
{
    return AiAgent::where('slug', $slug)->firstOrFail();
}

/*
| Assignment and configuration
*/

it('runs all 16 agents through Gemini, with the old providers disabled and their history kept', function () {
    expect(AiAgent::count())->toBe(16);
    foreach (AiAgent::all() as $agent) {
        expect($agent->primary_provider)->toBe('gemini')->and($agent->secondary_provider)->toBeNull()->and($agent->planning)->toBe('workflow');
        expect(gemini()->route($agent, $agent->model_tier)['primary'])->toBe(['provider' => 'gemini', 'model' => 'gemini-3.8-flash'])
            ->and(gemini()->route($agent, $agent->model_tier)['fallback'])->toBeFalse();
    }
    expect(AiAgentVersion::where('change_note', 'like', 'Moved to Google Gemini%')->count())->toBe(16)
        ->and(AiProviderSetting::where('provider', 'gemini')->value('enabled'))->toBeTrue()
        ->and(AiProviderSetting::whereIn('provider', ['openai', 'anthropic'])->pluck('enabled')->unique()->all())->toBe([false])
        ->and(array_keys(AiProvider::adapters()))->toBe(['gemini'])
        ->and(gemini()->adapter('gemini'))->toBeInstanceOf(ProviderAdapter::class)->toBeInstanceOf(GeminiAdapter::class)
        ->and(geminiAgent('investment-analyst')->tools)->toContain('investment_index.leaderboard', 'investment_index.project');
});

it('reads GEMINI_* settings, with saved settings taking priority', function () {
    withGeminiKey();
    $config = gemini()->config('gemini');
    expect($config->keySource)->toBe('env')->and($config->baseUrl)->toBe('https://generativelanguage.googleapis.com/v1beta')
        ->and($config->defaultModel)->toBe('gemini-3.8-flash')->and($config->timeout)->toBe(60);

    AiProviderSetting::where('provider', 'gemini')->first()->forceFill(['api_key' => 'AIza'.'SySavedKey1111111111111111111111111', 'default_model' => 'gemini-3.5-flash-lite'])->save();
    expect(gemini()->config('gemini')->keySource)->toBe('saved')->and(gemini()->config('gemini')->defaultModel)->toBe('gemini-3.5-flash-lite');
});

it('needs no key to run: agents stay in rules mode and say why', function () {
    Http::fake();
    expect(gemini()->available())->toBeFalse();

    $task = app(CentralAiOrchestrator::class)->submit('Run an SEO audit', 'seo-manager', $this->admin);

    expect($task->status)->toBe('completed')->and($task->mode)->toBe('rules')
        ->and($task->result['limitations'])->toContain('Written from data only: the language model is not configured.');
    expect(gemini()->completeGeneral([['role' => 'user', 'content' => 'x']], 'test')['error'])->toBe('Google Gemini API key not configured. Add your API key to activate AI services.');
    Http::assertNothingSent();
});

/*
| Request and response format (Google's documented generateContent)
*/

it('sends the documented generateContent request with the key in the header only', function () {
    withGeminiKey();
    Http::fake(['generativelanguage.googleapis.com/*' => Http::response(geminiReply('Hello'))]);

    $res = gemini()->completeGeneral([['role' => 'system', 'content' => 'Be brief.'], ['role' => 'user', 'content' => 'Hi'], ['role' => 'assistant', 'content' => 'Earlier answer'], ['role' => 'user', 'content' => 'Again']], 'test', maxTokens: 700, json: true);

    expect($res)->toMatchArray(['ok' => true, 'text' => 'Hello', 'provider' => 'gemini', 'model' => 'gemini-3.8-flash', 'tokens_in' => 100, 'tokens_out' => 25]);
    Http::assertSent(function (Request $r) {
        return $r->method() === 'POST'
            && $r->url() === 'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.8-flash:generateContent'
            && $r->hasHeader('x-goog-api-key', GEMINI_TEST_KEY) && ! str_contains($r->url(), GEMINI_TEST_KEY)
            && $r['systemInstruction'] === ['parts' => [['text' => 'Be brief.']]]
            && array_column($r['contents'], 'role') === ['user', 'model', 'user']
            && $r['generationConfig']['maxOutputTokens'] === 700 && $r['generationConfig']['responseMimeType'] === 'application/json'
            && $r['generationConfig']['thinkingConfig'] === ['thinkingLevel' => 'LOW']
            // Gemini 3.6+ ignores temperature, so it is not sent.
            && ! isset($r['generationConfig']['temperature']);
    });
    expect(AiUsageRecord::sole())->toMatchArray(['provider' => 'gemini', 'tokens_in' => 100, 'tokens_out' => 25, 'ok' => true]);
});

it('sends temperature only to models that use it, and thinking level only to Gemini 3 and later', function () {
    expect(GeminiAdapter::acceptsTemperature('gemini-3.8-flash'))->toBeFalse()->and(GeminiAdapter::acceptsTemperature('gemini-3.6-flash'))->toBeFalse()
        ->and(GeminiAdapter::acceptsTemperature('gemini-3.5-flash-lite'))->toBeTrue()->and(GeminiAdapter::acceptsTemperature('gemini-2.5-flash-lite'))->toBeTrue()
        ->and(GeminiAdapter::acceptsThinkingLevel('gemini-2.5-flash-lite'))->toBeFalse()->and(GeminiAdapter::acceptsThinkingLevel('gemini-3.1-flash-lite'))->toBeTrue()
        ->and(GeminiAdapter::acceptsTemperature('something-else'))->toBeFalse();

    withGeminiKey('gemini-2.5-flash-lite');
    Http::fake(['*' => Http::response(geminiReply())]);
    gemini()->completeGeneral([['role' => 'user', 'content' => 'x']], 'test', temperature: 0.3);
    Http::assertSent(fn (Request $r) => $r['generationConfig']['temperature'] === 0.3 && ! isset($r['generationConfig']['thinkingConfig']));
});

it('gives thinking models room to answer, within the administrator\'s limits', function () {
    withGeminiKey();
    Http::fake(['*' => Http::response(geminiReply())]);
    gemini()->completeGeneral([['role' => 'user', 'content' => 'x']], 'test', maxTokens: 10);
    Http::assertSent(fn (Request $r) => $r['generationConfig']['maxOutputTokens'] === 512);

    AppSetting::put('ai.controls', ['max_tokens_per_request' => 200] + AiProvider::controls());
    gemini()->completeGeneral([['role' => 'user', 'content' => 'x']], 'test', maxTokens: 10);
    Http::assertSent(fn (Request $r) => $r['generationConfig']['maxOutputTokens'] === 200);
});

it('reports a refusal and a cut-off reply clearly, without pretending to have an answer', function (array $reply, string $failure) {
    withGeminiKey();
    Http::fake(['*' => Http::response($reply)]);

    $res = gemini()->completeGeneral([['role' => 'user', 'content' => 'x']], 'test');

    expect($res['ok'])->toBeFalse()->and($res['failure'])->toBe($failure)->and($res['text'])->toBeNull();
})->with([
    'blocked prompt' => [['promptFeedback' => ['blockReason' => 'SAFETY'], 'usageMetadata' => ['promptTokenCount' => 5]], 'safety'],
    'safety stop' => [geminiReply('', [], 'SAFETY'), 'safety'],
    'token limit' => [['candidates' => [['content' => ['parts' => []], 'finishReason' => 'MAX_TOKENS']], 'usageMetadata' => ['promptTokenCount' => 5, 'thoughtsTokenCount' => 500]], 'truncated'],
    'empty' => [['candidates' => []], 'malformed'],
]);

it('classifies Gemini errors', function (int $status, array $body, string $failure, string $text) {
    withGeminiKey();
    Http::fake(['*' => Http::response($body, $status)]);

    $res = gemini()->completeGeneral([['role' => 'user', 'content' => 'x']], 'test');

    expect($res['failure'])->toBe($failure)->and($res['error'])->toContain($text);
})->with([
    'invalid key (400)' => [400, geminiError(400, 'INVALID_ARGUMENT', 'API key not valid. Please pass a valid API key.', [['@type' => 'type.googleapis.com/google.rpc.ErrorInfo', 'reason' => 'API_KEY_INVALID']]), 'auth', 'rejected the API key'],
    'unauthenticated (401)' => [401, geminiError(401, 'UNAUTHENTICATED'), 'auth', 'rejected the API key'],
    'permission (403)' => [403, geminiError(403, 'PERMISSION_DENIED'), 'auth', 'refused access'],
    'billing (400)' => [400, geminiError(400, 'FAILED_PRECONDITION', 'User location is not supported'), 'quota', 'billing'],
    'prepaid credit (402)' => [402, geminiError(402, 'PAYMENT_REQUIRED'), 'quota', 'prepaid credit'],
    'model (404)' => [404, geminiError(404, 'NOT_FOUND', 'models/gemini-x is not found'), 'model', 'Model not found'],
    'rate limit (429)' => [429, geminiError(429, 'RESOURCE_EXHAUSTED', 'Quota exceeded per minute', [['@type' => 'type.googleapis.com/google.rpc.QuotaFailure', 'violations' => [['quotaId' => 'GenerateRequestsPerMinutePerProjectPerModel-FreeTier']]]]), 'temporary', 'rate limit'],
    'daily quota (429)' => [429, geminiError(429, 'RESOURCE_EXHAUSTED', 'You exceeded your current quota', [['@type' => 'type.googleapis.com/google.rpc.QuotaFailure', 'violations' => [['quotaId' => 'GenerateRequestsPerDayPerProjectPerModel-FreeTier']]]]), 'quota', 'daily quota exhausted (free tier)'],
    'overloaded (503)' => [503, geminiError(503, 'UNAVAILABLE'), 'temporary', 'temporarily unavailable'],
    'timeout (504)' => [504, geminiError(504, 'DEADLINE_EXCEEDED'), 'temporary', 'temporarily unavailable'],
    'bad request' => [400, geminiError(400, 'INVALID_ARGUMENT', 'Invalid JSON payload'), 'invalid', 'Invalid JSON payload'],
]);

it('never repeats Google\'s message on a key error, which can echo part of the key', function () {
    withGeminiKey();
    Http::fake(['*' => Http::response(geminiError(400, 'INVALID_ARGUMENT', 'API key not valid: '.GEMINI_TEST_KEY, [['reason' => 'API_KEY_INVALID']]), 400)]);

    expect(gemini()->completeGeneral([['role' => 'user', 'content' => 'x']], 'test')['error'])->not->toContain('AIza');
});

it('handles a connection failure or timeout as temporary', function () {
    withGeminiKey();
    Http::fake(fn () => throw new ConnectionException('cURL error 28: Operation timed out'));

    $res = gemini()->completeGeneral([['role' => 'user', 'content' => 'x']], 'test');

    expect($res['failure'])->toBe('temporary')->and($res['error'])->toContain('unreachable');
});

it('retries rate limits and outages with backoff, but never a daily quota, billing or a bad key', function () {
    withGeminiKey();
    config(['services.gemini.max_retries' => 2]);
    Http::fake(['*' => Http::sequence()->push(geminiError(429, 'RESOURCE_EXHAUSTED', 'per minute', [['@type' => 'type.googleapis.com/google.rpc.RetryInfo', 'retryDelay' => '0s']]), 429)
        ->push(geminiError(503, 'UNAVAILABLE'), 503)->push(geminiReply('Third time'))]);
    expect(gemini()->completeGeneral([['role' => 'user', 'content' => 'x']], 'test')['text'])->toBe('Third time');
    Http::assertSentCount(3);

    foreach ([[429, geminiError(429, 'RESOURCE_EXHAUSTED', 'x', [['violations' => [['quotaId' => 'GenerateRequestsPerDayPerProjectPerModel-FreeTier']]]])], [400, geminiError(400, 'FAILED_PRECONDITION')], [401, geminiError(401, 'UNAUTHENTICATED')]] as [$status, $body]) {
        AppSetting::put('ai.quota_block.gemini', null);
        Http::swap(new Factory(app('events')));
        Http::fake(['*' => Http::sequence()->push($body, $status)->push(geminiReply())]);
        gemini()->completeGeneral([['role' => 'user', 'content' => 'x']], 'test');
        Http::assertSentCount(1);
    }
});

it('pauses every request after the daily quota runs out, tells the admins once, and resumes at midnight Pacific', function () {
    Notification::fake();
    withGeminiKey();
    Http::fake(['*' => Http::response(geminiError(429, 'RESOURCE_EXHAUSTED', 'x', [['violations' => [['quotaId' => 'GenerateRequestsPerDayPerProjectPerModel-FreeTier']]]]), 429)]);

    gemini()->completeGeneral([['role' => 'user', 'content' => 'x']], 'test');
    $block = AiProvider::quotaBlock('gemini');
    expect($block['until'])->toBe(CarbonImmutable::now('America/Los_Angeles')->addDay()->startOfDay()->utc()->toIso8601String());

    // No further request reaches Google, and no answer is invented: tasks report from data only.
    $res = gemini()->completeGeneral([['role' => 'user', 'content' => 'x']], 'test');
    expect($res['failure'])->toBe('quota')->and($res['error'])->toContain('Paused until');
    $task = app(CentralAiOrchestrator::class)->submit('Run an SEO audit', 'seo-manager', $this->admin);
    expect($task->status)->toBe('completed')->and($task->mode)->toBe('rules');
    Http::assertSentCount(1);
    Notification::assertSentTo($this->admin, AgentNeedsAttention::class);
    $this->actingAs($this->admin)->get(route('admin.ai.providers'))->assertSee('AI requests paused.')->assertSee('daily quota exhausted');

    $this->travelTo(CarbonImmutable::parse($block['until'])->addMinute());
    expect(AiProvider::quotaBlock('gemini'))->toBeNull();
});

it('lists the models that can generate content, across pages, and warns about an unlisted model', function () {
    withGeminiKey();
    Http::fake(['generativelanguage.googleapis.com/v1beta/models?pageSize=1000' => Http::response(['models' => [
        ['name' => 'models/gemini-3.8-flash', 'supportedGenerationMethods' => ['generateContent', 'countTokens']],
        ['name' => 'models/gemini-embedding-2', 'supportedGenerationMethods' => ['embedContent']]], 'nextPageToken' => 'p2']),
        'generativelanguage.googleapis.com/v1beta/models?pageSize=1000&pageToken=p2' => Http::response(['models' => [['name' => 'models/gemini-3.5-flash-lite', 'supportedGenerationMethods' => ['generateContent']]]])]);

    expect(gemini()->check('gemini')['status'])->toBe('verified')
        ->and(AiProviderSetting::where('provider', 'gemini')->first()->available_models)->toBe(['gemini-3.5-flash-lite', 'gemini-3.8-flash'])
        ->and(gemini()->modelWarning('gemini', 'gemini-9-ultra'))->toContain('not in the model list')
        ->and(gemini()->modelWarning('gemini', 'gemini-3.8-flash'))->toBeNull();
});

it('streams text as it arrives', function () {
    withGeminiKey();
    $sse = 'data: '.json_encode(['candidates' => [['content' => ['parts' => [['text' => 'Hel']]]]]])."\n\n"
        .'data: '.json_encode(['candidates' => [['content' => ['parts' => [['text' => 'lo']]], 'finishReason' => 'STOP']], 'usageMetadata' => ['promptTokenCount' => 4, 'candidatesTokenCount' => 2]])."\n\n";
    Http::fake(['*' => Http::response($sse, 200, ['Content-Type' => 'text/event-stream'])]);
    $chunks = [];

    $res = gemini()->streamGeneral([['role' => 'user', 'content' => 'x']], 'test', function ($t) use (&$chunks) {
        $chunks[] = $t;
    });

    expect($chunks)->toBe(['Hel', 'lo'])->and($res['text'])->toBe('Hello')->and($res['tokens_out'])->toBe(2);
    Http::assertSent(fn (Request $r) => str_ends_with($r->url(), ':streamGenerateContent?alt=sse'));
});

it('masks personal data before anything is sent', function () {
    withGeminiKey();
    Http::fake(['*' => Http::response(geminiReply())]);

    gemini()->completeGeneral([['role' => 'user', 'content' => 'Call me on 0300-1234567 or +92 321 7654321, mail ali@example.com, CNIC 35202-1234567-1, card 4111 1111 1111 1111. Budget 95000000.']], 'test');

    Http::assertSent(function (Request $r) {
        $sent = $r['contents'][0]['parts'][0]['text'];

        return ! str_contains($sent, '1234567') && ! str_contains($sent, 'ali@example.com') && ! str_contains($sent, '4111')
            && str_contains($sent, '[phone]') && str_contains($sent, '[email]') && str_contains($sent, '[cnic]') && str_contains($sent, '95000000');
    });
    expect(PersonalDataMask::text('5 Marla in DHA Phase 6 for 2.5 crore'))->toBe('5 Marla in DHA Phase 6 for 2.5 crore');
});

/*
| Function calling
*/

it('declares an agent\'s permitted tools as Gemini functions with schemas from their rules', function () {
    $declarations = collect(app(ToolDeclarations::class)->forAgent(geminiAgent('investment-analyst')))->keyBy('name');
    $advisor = $declarations['investment.advisor']['parametersJsonSchema'];

    expect($declarations->keys())->toContain('investment_index.leaderboard', 'investment.yield')->not->toContain('payments.refund')
        ->and($advisor['required'])->toBe(['question'])->and((array) $advisor['properties'])->toHaveKeys(['question', 'city', 'segment', 'focus'])
        ->and(((array) $advisor['properties'])['question'])->toBe(['type' => 'string', 'minLength' => 3, 'maxLength' => 300])
        ->and(((array) $advisor['properties'])['focus']['enum'])->toBe(['best', 'compare', 'rental', 'risk', 'forecast'])
        ->and(((array) $declarations['investment.yield']['parametersJsonSchema']['properties'])['price'])->toBe(['type' => 'integer', 'minimum' => 1])
        ->and($declarations->keys())->not->toContain(ToolDeclarations::DELEGATE);
    expect(collect(app(ToolDeclarations::class)->forAgent(geminiAgent('ceo')))->pluck('name'))->toContain(ToolDeclarations::DELEGATE);
});

it('lets a "model tool calls" agent call its own tools through Gemini, checked, logged and grounded', function () {
    withGeminiKey();
    $agent = geminiAgent('investment-analyst');
    $agent->forceFill(['planning' => 'model_tools'])->save();
    Http::fake(['*' => Http::sequence()
        // Gemini asks for a permitted tool, a forbidden one, and passes bad input to a third.
        ->push(['candidates' => [['content' => ['role' => 'model', 'parts' => [
            ['functionCall' => ['id' => 'c1', 'name' => 'investment.yield', 'args' => ['price' => 10000000, 'monthly_rent' => 50000]], 'thoughtSignature' => 'sig-1'],
            ['functionCall' => ['id' => 'c2', 'name' => 'payments.refund', 'args' => []]],
            ['functionCall' => ['id' => 'c3', 'name' => 'investment.yield', 'args' => ['price' => 'lots']]],
        ]], 'finishReason' => 'STOP']], 'usageMetadata' => ['promptTokenCount' => 50, 'candidatesTokenCount' => 10]])
        ->push(geminiReply('The gross yield is 6% on these figures.'))]);

    $task = app(CentralAiOrchestrator::class)->submit('What yield does a 1 crore flat renting at 50,000 a month give?', 'investment-analyst', $this->admin);

    expect($task->mode)->toBe('llm_tools')->and($task->result['narrative'])->toBe('The gross yield is 6% on these figures.');
    $calls = AiToolCall::where('task_id', $task->id)->orderBy('id')->get();
    expect($calls->pluck('status', 'tool')->all())->toHaveKey('investment.yield')
        ->and($calls->where('tool', 'payments.refund')->first()->status)->toBe('prohibited')
        ->and($calls->where('status', 'invalid')->count())->toBe(1);
    // The second request returns the model turn unchanged (thought signature included) and one functionResponse per call.
    Http::assertSent(function (Request $r) {
        $c = $r['contents'] ?? [];

        return count($c) === 3 && ($c[1]['parts'][0]['thoughtSignature'] ?? null) === 'sig-1'
            && $c[2]['role'] === 'user' && count($c[2]['parts']) === 3
            && $c[2]['parts'][0]['functionResponse']['id'] === 'c1' && isset($c[2]['parts'][0]['functionResponse']['response']['output'])
            && isset($c[2]['parts'][1]['functionResponse']['response']['error']) && isset($r['tools'][0]['functionDeclarations']);
    });
    expect(AiUsageRecord::where('purpose', 'tool_answer')->count())->toBe(2);
});

it('discards a tool-calling answer with figures the data does not contain, and stops after the round limit', function () {
    withGeminiKey();
    $agent = geminiAgent('investment-analyst');
    $agent->forceFill(['planning' => 'model_tools'])->save();
    Http::fake(['*' => Http::response(geminiReply('Expect a 37% return next year.'))]);

    $task = app(CentralAiOrchestrator::class)->submit('Best mall in Lahore?', 'investment-analyst', $this->admin);
    expect($task->mode)->not->toBe('llm_tools')->and(implode(' ', $task->result['limitations']))->toContain('figures not in the data');

    config(['meezan.agents.max_model_tool_rounds' => 2]);
    $loop = ['candidates' => [['content' => ['role' => 'model', 'parts' => [['functionCall' => ['name' => 'market_data.quality', 'args' => []]]]], 'finishReason' => 'STOP']]];
    Http::swap(new Factory(app('events')));
    Http::fake(['*' => Http::sequence()->push($loop)->push($loop)->push(geminiReply('Summary without figures.'))]);
    $res = gemini()->completeWithTools($agent, [['role' => 'user', 'content' => 'x']], app(ToolDeclarations::class)->forAgent($agent), fn () => ['ok' => true, 'data' => [], 'error' => null], 'tool_answer');
    expect($res['ok'])->toBeFalse()->and($res['failure'])->toBe('limit')->and($res['rounds'])->toBe(2);
});

it('lets the CEO delegate through the Brain by function call, within the depth limit', function () {
    withGeminiKey();
    geminiAgent('ceo')->forceFill(['planning' => 'model_tools'])->save();
    Http::fake(['*' => Http::sequence()
        ->push(['candidates' => [['content' => ['role' => 'model', 'parts' => [['functionCall' => ['name' => 'agents.delegate', 'args' => ['agent' => 'seo-manager', 'request' => 'Run an SEO audit']]]]], 'finishReason' => 'STOP']]])
        ->push(geminiReply('The SEO audit is attached.'))->push(geminiReply('The SEO audit is attached.'))->push(geminiReply('The SEO audit is attached.'))]);

    $task = app(CentralAiOrchestrator::class)->submit('Give me the executive overview', 'ceo', $this->admin);

    $child = AiTask::where('parent_id', $task->id)->where('agent_id', geminiAgent('seo-manager')->id)->latest('id')->first();
    expect($child)->not->toBeNull()->and($child->depth)->toBe(1)->and($child->source)->toBe('agent');
});

/*
| Budgets, limits and cost
*/

it('estimates cost from Google\'s published prices by date, and lets entered prices override them', function () {
    expect(AiProvider::costOf('gemini-3.8-flash', 1_000_000, 1_000_000))->toBe(4.5)
        ->and(AiProvider::pricing('2027-01-01')['gemini-3.8-flash'])->toMatchArray(['input' => 1.5, 'output' => 7.5])
        ->and(AiProvider::costOf('gemini-3.8-flash-001', 1_000_000, 0))->toBe(0.75)
        ->and(AiProvider::costOf('unknown-model', 10, 10))->toBeNull();

    AppSetting::put('ai.pricing', ['gemini-3.8-flash' => ['input' => 1, 'output' => 1]]);
    expect(AiProvider::costOf('gemini-3.8-flash', 1_000_000, 1_000_000))->toBe(2.0);
});

it('stops requests when a monthly budget would be exceeded, and keeps the task and portal working', function () {
    Notification::fake();
    withGeminiKey();
    Http::fake();
    AppSetting::put('ai.controls', ['monthly_total_usd' => 1.0] + AiProvider::controls());
    AiUsageRecord::create(['provider' => 'gemini', 'model' => 'gemini-3.8-flash', 'purpose' => 'x', 'tokens_in' => 0, 'tokens_out' => 0, 'cost_usd' => 0.999, 'ok' => true, 'created_at' => now()]);

    $task = app(CentralAiOrchestrator::class)->submit('Run an SEO audit', 'seo-manager', $this->admin);

    expect($task->mode)->toBe('rules')->and(implode(' ', $task->result['limitations']))->toContain('monthly AI budget is used up');
    Http::assertNothingSent();
    Notification::assertSentTo($this->admin, AgentNeedsAttention::class, fn ($n) => $n->title === 'AI budget reached');
    $this->get('/')->assertOk();
});

it('refuses to spend on a model with no price while a budget is set', function () {
    withGeminiKey('gemini-unpriced-model');
    Http::fake();
    AppSetting::put('ai.controls', ['monthly_total_usd' => 10.0] + AiProvider::controls());

    expect(gemini()->completeGeneral([['role' => 'user', 'content' => 'x']], 'test')['error'])->toContain('has no price');
    Http::assertNothingSent();
});

it('applies the Gemini, agent and daily request limits', function () {
    withGeminiKey();
    Http::fake(['*' => Http::response(geminiReply())]);
    $agent = geminiAgent();
    $call = fn () => gemini()->completeFor($agent, [['role' => 'user', 'content' => 'x']], 'test');
    $spend = fn ($agentId) => AiUsageRecord::create(['agent_id' => $agentId, 'provider' => 'gemini', 'model' => 'gemini-3.8-flash', 'purpose' => 'x', 'tokens_in' => 0, 'tokens_out' => 0, 'cost_usd' => 5, 'ok' => true, 'created_at' => now()]);

    AiProviderSetting::where('provider', 'gemini')->update(['monthly_budget_usd' => 4]);
    $spend(null);
    expect($call()['error'])->toContain('gemini monthly budget');

    AiProviderSetting::query()->update(['monthly_budget_usd' => null]);
    AiUsageRecord::query()->delete();
    $agent->forceFill(['monthly_budget_usd' => 4])->save();
    $spend($agent->id);
    expect($call()['error'])->toContain('SEO Manager monthly budget');

    $agent->forceFill(['monthly_budget_usd' => null, 'daily_request_limit' => 1])->save();
    expect($call()['error'])->toContain('daily request limit');

    $agent->forceFill(['daily_request_limit' => null])->save();
    AppSetting::put('ai.controls', ['daily_requests_total' => 1] + AiProvider::controls());
    expect($call()['error'])->toContain('Daily AI request limit');
});

it('stops every request with the emergency stop, and the portal keeps working', function () {
    withGeminiKey();
    Http::fake();
    $this->actingAs($this->admin)->patch(route('admin.ai.controls'), ['emergency_stop' => '1'])->assertSessionHas('status');

    $task = app(CentralAiOrchestrator::class)->submit('Run an SEO audit', 'seo-manager', $this->admin);

    expect($task->mode)->toBe('rules')->and(implode(' ', $task->result['limitations']))->toContain('emergency stop');
    Http::assertNothingSent();
    $this->get(route('admin.ai.providers'))->assertOk()->assertSee('Emergency AI stop is on');
    auth()->logout();
    $this->postJson(route('ai-finder.match'), ['message' => 'How can I register as a property dealer?'])->assertOk();
});

it('records usage per agent and task with the estimated cost, and shows earlier providers\' history', function () {
    withGeminiKey();
    Http::fake(['*' => Http::response(geminiReply())]);
    AiUsageRecord::create(['provider' => 'openai', 'model' => 'gpt-old', 'purpose' => 'narrative', 'tokens_in' => 10, 'tokens_out' => 5, 'ok' => false, 'error' => 'No credit', 'created_at' => now()]);

    $task = app(CentralAiOrchestrator::class)->submit('Run an SEO audit', 'seo-manager', $this->admin);

    // 100 in + 25 out at the published USD 0.75 / 3.75 per million.
    $usage = AiUsageRecord::where('provider', 'gemini')->where('task_id', $task->id)->sole();
    expect($usage->agent_id)->toBe(geminiAgent()->id)->and((float) $usage->cost_usd)->toBe(0.000169)->and((float) $task->cost_usd)->toBe(0.000169);
    $this->actingAs($this->admin)->get(route('admin.ai.usage'))->assertOk()->assertSee('Google Gemini')->assertSee('OpenAI (no longer used)')
        ->assertSee('Cost per agent')->assertSee('Daily use')->assertSee('Most expensive tasks');
});

/*
| Website, WhatsApp and investment
*/

it('sends website requirement parsing through Gemini, with usage recorded', function () {
    withGeminiKey();
    config(['meezan.ai.driver' => 'llm']);
    Http::fake(['*' => Http::response(geminiReply('{"purpose":"sale","type":"house","areas":[],"max_price":null,"min_size":null,"bedrooms":3,"bathrooms":null,"amenities":[]}'))]);

    $this->postJson(route('ai-finder.match'), ['message' => 'house in Lahore'])->assertOk();

    expect(AiUsageRecord::where('purpose', 'requirement_parsing')->sole()->provider)->toBe('gemini');
});

it('keeps website answers working when Gemini does not respond', function () {
    withGeminiKey();
    config(['meezan.ai.driver' => 'llm']);
    Http::fake(['*' => Http::response(geminiError(503, 'UNAVAILABLE'), 503)]);

    $this->postJson(route('ai-finder.match'), ['message' => 'house in Lahore'])->assertOk()->assertJsonStructure(['reply']);
});

it('uses a channel\'s Gemini model for WhatsApp and website work only', function () {
    withGeminiKey();
    $this->actingAs($this->admin)->patch(route('admin.ai.controls'), ['channels' => ['whatsapp' => ['model' => 'gemini-3.5-flash-lite'], 'website' => ['model' => '']]])->assertSessionHas('status');

    expect(gemini()->route(geminiAgent(), 'research', 'whatsapp')['primary']['model'])->toBe('gemini-3.5-flash-lite')
        ->and(gemini()->route(geminiAgent(), 'research', 'website')['primary']['model'])->toBe('gemini-3.8-flash')
        ->and(gemini()->inChannel('whatsapp', fn () => gemini()->route(null)['primary']['model']))->toBe('gemini-3.5-flash-lite')
        ->and(gemini()->route(null)['primary']['model'])->toBe('gemini-3.8-flash');
});

it('has only the Gemini adapter talking to Google, and no feature calling a model API directly', function () {
    $files = collect(File::allFiles(app_path()))->map(fn ($f) => str_replace('\\', '/', $f->getRelativePathname()))
        ->filter(fn ($path) => preg_match('/generativelanguage|generateContent|chat\/completions|\/v1\/messages/', file_get_contents(app_path($path))))->values()->all();

    expect($files)->toBe(['Services/Agents/Providers/GeminiAdapter.php'])
        ->and(File::exists(app_path('Services/Agents/Providers/OpenAiAdapter.php')))->toBeFalse()
        ->and(File::exists(app_path('Services/Agents/Providers/AnthropicAdapter.php')))->toBeFalse();
});

/*
| Admin: Gemini Settings
*/

it('shows Gemini Settings and asks for a key when none is configured', function () {
    $this->actingAs($this->admin)->get(route('admin.ai.providers'))->assertOk()
        ->assertSee('Gemini Settings')->assertSee('Google Gemini API')->assertSee('Gemini API key not configured. Add your API key to activate AI services.')
        ->assertSee('Active model')->assertSee('gemini-3.8-flash')->assertSee('https://generativelanguage.googleapis.com/v1beta')
        ->assertSee('Model availability')->assertSee('Last successful connection')->assertSee('Test Gemini connection')->assertSee('The 16 agents')
        ->assertDontSee('Connection verified')->assertDontSee('Anthropic');
});

it('makes no request and claims no success when testing without a key', function () {
    Http::fake();
    $this->actingAs($this->admin)->post(route('admin.ai.providers.test', 'gemini'))->assertSessionHas('error', fn ($m) => str_contains($m, 'API key not configured'));
    Http::assertNothingSent();
    expect(AiProviderSetting::where('provider', 'gemini')->value('connection_status'))->not->toBe('verified');
});

it('verifies a connection only after the model list and a real test request succeed', function () {
    withGeminiKey();
    Http::fake([
        'generativelanguage.googleapis.com/v1beta/models?*' => Http::response(['models' => [['name' => 'models/gemini-3.8-flash', 'supportedGenerationMethods' => ['generateContent']]]]),
        'generativelanguage.googleapis.com/v1beta/models/gemini-3.8-flash:generateContent' => Http::response(geminiReply('OK')),
    ]);

    $this->actingAs($this->admin)->post(route('admin.ai.providers.test', 'gemini'))->assertSessionHas('status', fn ($m) => str_contains($m, 'Connected: gemini-3.8-flash answered'));

    $row = AiProviderSetting::where('provider', 'gemini')->first();
    expect($row->connection_status)->toBe('verified')->and($row->last_success_at)->not->toBeNull()
        ->and(app(ConnectorStatus::class)->all()['gemini']['status'])->toBe('verified')
        ->and(AiUsageRecord::where('purpose', 'connection_test')->sole()->provider)->toBe('gemini');
    $this->get(route('admin.ai.providers'))->assertSee('Connection verified')->assertSee('Available to this key');
});

it('shows a clear error for a bad key, an exhausted quota and an unavailable model', function () {
    withGeminiKey();
    Http::fake([
        'generativelanguage.googleapis.com/v1beta/models?*' => Http::sequence()->push(geminiError(400, 'INVALID_ARGUMENT', 'API key not valid', [['reason' => 'API_KEY_INVALID']]), 400)
            ->push(['models' => [['name' => 'models/gemini-3.5-flash-lite', 'supportedGenerationMethods' => ['generateContent']]]])
            ->push(['models' => [['name' => 'models/gemini-3.8-flash', 'supportedGenerationMethods' => ['generateContent']]]]),
        '*:generateContent' => Http::response(geminiError(429, 'RESOURCE_EXHAUSTED', 'x', [['violations' => [['quotaId' => 'GenerateRequestsPerDayPerProjectPerModel-FreeTier']]]]), 429),
    ]);
    $this->actingAs($this->admin);

    $this->post(route('admin.ai.providers.test', 'gemini'))->assertSessionHas('error', fn ($m) => str_contains($m, 'rejected the API key'));
    $this->post(route('admin.ai.providers.test', 'gemini'))->assertSessionHas('error', fn ($m) => str_contains($m, 'not in the model list'));
    $this->post(route('admin.ai.providers.test', 'gemini'))->assertSessionHas('error', fn ($m) => str_contains($m, 'daily quota exhausted'));
    expect(AiProviderSetting::where('provider', 'gemini')->value('connection_status'))->toBe('error');
});

it('saves a key encrypted, never shows it, and keeps it out of the session, logs and output', function () {
    $key = 'AIza'.'SySecretKeyValue2222222222222222222';
    Log::spy();
    $this->actingAs($this->admin)->from(route('admin.ai.providers'))->patch(route('admin.ai.providers.update', 'gemini'), ['enabled' => '1', 'api_key' => $key, 'base_url' => 'http://evil.example.com'])->assertSessionHasErrors('base_url');
    expect(json_encode(session()->all()))->not->toContain($key)->and(gemini()->config('gemini')->hasKey())->toBeFalse();

    $this->patch(route('admin.ai.providers.update', 'gemini'), ['enabled' => '1', 'api_key' => $key, 'default_model' => 'gemini-3.8-flash'])->assertSessionHas('status');
    $row = AiProviderSetting::where('provider', 'gemini')->first();
    expect($row->savedKey())->toBe($key)->and($row->getRawOriginal('api_key'))->not->toContain($key)->and($row->toArray())->not->toHaveKey('api_key')
        ->and(print_r(gemini()->config('gemini'), true))->not->toContain($key)
        ->and(json_encode(AuditLog::where('action', 'ai.provider_updated')->latest('id')->first()->toArray()))->not->toContain($key)->toContain('api_key_changed');
    foreach ([route('admin.ai.providers'), route('admin.ai.connectors'), route('admin.ai.overview'), route('admin.ai.usage')] as $url) {
        $this->get($url)->assertOk()->assertDontSee($key);
    }
    expect(OutputGuard::text("key {$key} and AQ.".'Ab8Example0000000000000000'))->not->toContain('AIza'.'Sy')->not->toContain('AQ.Ab8');
    Log::shouldNotHaveReceived('warning', fn ($m, $c = []) => str_contains(json_encode([$m, $c]), $key));

    // An empty field never removes it; removing on request falls back to GEMINI_API_KEY.
    config(['services.gemini.key' => GEMINI_TEST_KEY]);
    $this->patch(route('admin.ai.providers.update', 'gemini'), ['enabled' => '1', 'api_key' => '']);
    expect(gemini()->config('gemini')->keySource)->toBe('saved');
    $this->patch(route('admin.ai.providers.update', 'gemini'), ['enabled' => '1', 'remove_key' => '1']);
    expect(gemini()->config('gemini')->keySource)->toBe('env');
});

it('never sends the key to the browser', function () {
    withGeminiKey();
    foreach (['/', route('ai-finder')] as $url) {
        $this->get($url)->assertOk()->assertDontSee(GEMINI_TEST_KEY);
    }
    expect(file_get_contents(resource_path('js/app.js')))->not->toContain('GEMINI');
});

it('lets an admin disable Gemini and change the model, which asks for a new test and clears a quota pause', function () {
    withGeminiKey();
    AiProviderSetting::where('provider', 'gemini')->update(['connection_status' => 'verified', 'available_models' => json_encode(['gemini-3.8-flash', 'gemini-3.5-flash-lite'])]);
    AiProvider::setQuotaBlock('gemini', 'Gemini daily quota exhausted.', now()->addHours(5)->toIso8601String());

    $this->actingAs($this->admin)->patch(route('admin.ai.providers.update', 'gemini'), ['enabled' => '1', 'default_model' => 'gemini-3.5-flash-lite'])->assertSessionHas('status');
    expect(AiProviderSetting::where('provider', 'gemini')->value('connection_status'))->toBe('untested')->and(AiProvider::quotaBlock('gemini'))->toBeNull();

    $this->patch(route('admin.ai.providers.update', 'gemini'), ['enabled' => '0'])->assertSessionHas('status');
    expect(gemini()->config('gemini')->enabled)->toBeFalse()->and(gemini()->available())->toBeFalse();
    $this->patch(route('admin.ai.providers.update', 'gemini'), ['enabled' => '1', 'base_url' => 'http://example.com/v1'])->assertSessionHasErrors('base_url');
});

it('keeps Gemini settings and controls admin only', function () {
    Http::fake();
    foreach ([User::factory()->dealer(DealerStatus::Verified)->create(), User::factory()->create()] as $user) {
        $this->actingAs($user)->get(route('admin.ai.providers'))->assertForbidden();
        $this->patch(route('admin.ai.providers.update', 'gemini'), ['enabled' => '1', 'api_key' => 'AIza'.'SyAttackerKey000000000000000000000'])->assertForbidden();
        $this->post(route('admin.ai.providers.test', 'gemini'))->assertForbidden();
        $this->patch(route('admin.ai.controls'), ['emergency_stop' => '1'])->assertForbidden();
    }
    auth()->logout();
    $this->get(route('admin.ai.providers'))->assertRedirect();
    $this->patch(route('admin.ai.providers.update', 'openai'), ['enabled' => '1'])->assertNotFound();

    expect(gemini()->config('gemini')->hasKey())->toBeFalse()->and(AiProvider::controls()['emergency_stop'])->toBeFalse();
    Http::assertNothingSent();
});

it('shows an agent\'s Gemini settings and saves its planning mode as a new version', function () {
    $agent = geminiAgent('investment-analyst');
    $this->actingAs($this->admin)->get(route('admin.ai.agents.show', $agent))->assertOk()->assertSee('Google Gemini')->assertSee('How the model is used')->assertDontSee('Secondary provider');

    $this->patch(route('admin.ai.agents.update', $agent), ['instructions' => $agent->instructions, 'model_tier' => $agent->model_tier, 'temperature' => $agent->temperature,
        'status' => 'active', 'tools' => $agent->tools, 'planning' => 'model_tools', 'primary_provider' => 'gemini', 'change_note' => 'Try tool calls'])->assertSessionHas('status');

    expect($agent->fresh()->planning)->toBe('model_tools')->and($agent->versions()->latest('version')->first()->snapshot['planning'])->toBe('model_tools');
    expect(Schema::hasColumns('ai_usage_records', ['failure']))->toBeTrue()->and(Schema::hasColumns('ai_agents', ['planning']))->toBeTrue();
});

it('falls back to a second registered provider only when allowed', function () {
    config(['meezan.agents.adapters' => ['gemini' => GeminiAdapter::class, 'backup' => BackupTestAdapter::class], 'services.backup.key' => 'backup-test-key-000000000000', 'services.backup.model' => 'backup-model']);
    withGeminiKey();
    Http::fake(['*' => Http::response(geminiError(503, 'UNAVAILABLE'), 503)]);
    $agent = geminiAgent();
    $agent->forceFill(['secondary_provider' => 'backup'])->save();

    expect(gemini()->completeFor($agent, [['role' => 'user', 'content' => 'x']], 'test')['ok'])->toBeFalse();

    AppSetting::put('ai.controls', ['fallback_allowed' => true] + AiProvider::controls());
    $res = gemini()->completeFor($agent, [['role' => 'user', 'content' => 'x']], 'test');
    expect($res['ok'])->toBeTrue()->and($res['provider'])->toBe('backup')->and($res['fallback_from'])->toBe('gemini');

    // Never for a daily quota.
    Http::swap(new Factory(app('events')));
    Http::fake(['*' => Http::response(geminiError(429, 'RESOURCE_EXHAUSTED', 'x', [['violations' => [['quotaId' => 'GenerateRequestsPerDayPerProjectPerModel']]]]), 429)]);
    expect(gemini()->completeFor($agent, [['role' => 'user', 'content' => 'x']], 'test')['provider'])->toBe('gemini');
});

/** A stand-in second provider, to prove the provider layer stays modular. */
class BackupTestAdapter implements ProviderAdapter
{
    public function key(): string
    {
        return 'backup';
    }

    public function label(): string
    {
        return 'Backup';
    }

    public function minOutputTokens(): int
    {
        return 1;
    }

    public function send(ProviderConfig $config, string $model, array $messages, float $temperature, int $maxTokens, bool $json, ?int $timeout = null, ?int $retries = null): array
    {
        return ['ok' => true, 'text' => 'From backup', 'model' => 'backup-model', 'tokens_in' => 1, 'tokens_out' => 1, 'error' => null, 'failure' => null];
    }

    public function listModels(ProviderConfig $config): array
    {
        return ['ok' => true, 'models' => ['backup-model'], 'error' => null, 'failure' => null];
    }
}
