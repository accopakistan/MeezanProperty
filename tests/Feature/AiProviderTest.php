<?php

use App\Enums\DealerStatus;
use App\Models\AiAgent;
use App\Models\AiProviderSetting;
use App\Models\AiTask;
use App\Models\AiUsageRecord;
use App\Models\AppSetting;
use App\Models\AuditLog;
use App\Models\User;
use App\Notifications\AgentNeedsAttention;
use App\Services\Agents\AgentReadiness;
use App\Services\Agents\AiProvider;
use App\Services\Agents\CentralAiOrchestrator;
use App\Services\Agents\ConnectorStatus;
use App\Services\Agents\OutputGuard;
use App\Services\Agents\Providers\AnthropicAdapter;
use App\Services\Agents\Providers\OpenAiAdapter;
use App\Services\Agents\Providers\ProviderAdapter;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;

/*
| Two providers behind one manager. Every test uses faked HTTP: no real key
| exists and no real request is made. These prove our side of the integration,
| not a live account; live tests wait for the owner's own API keys.
*/

const OPENAI_TEST_KEY = 'sk-openai-test-key-000000000000000000';
const ANTHROPIC_TEST_KEY = 'sk-ant-api03-test-key-00000000000000000';

beforeEach(function () {
    Http::preventStrayRequests();
    config(['services.openai.key' => null, 'services.anthropic.key' => null, 'services.openai.max_retries' => 0, 'services.anthropic.max_retries' => 0, 'meezan.agents.use_llm' => true, 'meezan.agents.provider' => 'openai']);
    $this->admin = User::factory()->admin()->create();
});

function manager(): AiProvider
{
    return app(AiProvider::class);
}

function bothKeys(string $anthropicModel = 'claude-test-model'): void
{
    config(['services.openai.key' => OPENAI_TEST_KEY, 'services.anthropic.key' => ANTHROPIC_TEST_KEY, 'services.anthropic.model' => $anthropicModel, 'services.openai.model' => 'gpt-test-model']);
}

function allowFallback(bool $on = true): void
{
    AppSetting::put('ai.controls', ['fallback_allowed' => $on] + AiProvider::controls());
}

function openAiReply(string $text = 'From OpenAI', array $extra = []): array
{
    return ['model' => 'gpt-test-model', 'choices' => [['message' => ['content' => $text], 'finish_reason' => 'stop']], 'usage' => ['prompt_tokens' => 100, 'completion_tokens' => 20]] + $extra;
}

function anthropicReply(string $text = 'From Claude', string $stop = 'end_turn'): array
{
    return ['id' => 'msg_1', 'type' => 'message', 'role' => 'assistant', 'model' => 'claude-test-model', 'content' => [['type' => 'thinking', 'thinking' => ''], ['type' => 'text', 'text' => $text]], 'stop_reason' => $stop, 'usage' => ['input_tokens' => 90, 'output_tokens' => 15]];
}

function agentNamed(string $slug = 'seo-manager'): AiAgent
{
    return AiAgent::where('slug', $slug)->firstOrFail();
}

/*
| Assignments and configuration
*/

it('assigns all 16 agents to OpenAI first and Anthropic second, with fallback off until switched on', function () {
    expect(AiAgent::count())->toBe(16);
    foreach (AiAgent::all() as $agent) {
        expect($agent->primary_provider)->toBe('openai')->and($agent->secondary_provider)->toBe('anthropic');
        expect(manager()->route($agent, $agent->model_tier)['fallback'])->toBeFalse();
    }
    expect(AiProviderSetting::pluck('provider')->sort()->values()->all())->toBe(['anthropic', 'openai']);
});

it('implements one common interface for both adapters', function () {
    expect(manager()->adapter('openai'))->toBeInstanceOf(ProviderAdapter::class)->toBeInstanceOf(OpenAiAdapter::class)
        ->and(manager()->adapter('anthropic'))->toBeInstanceOf(ProviderAdapter::class)->toBeInstanceOf(AnthropicAdapter::class);
});

it('reads settings from the environment, with saved settings taking priority', function () {
    bothKeys();
    expect(manager()->config('anthropic')->keySource)->toBe('env')->and(manager()->config('anthropic')->baseUrl)->toBe('https://api.anthropic.com');

    AiProviderSetting::where('provider', 'anthropic')->first()->forceFill(['api_key' => 'sk-ant-saved-key-111111111111111111', 'default_model' => 'claude-saved', 'base_url' => 'https://proxy.example.com'])->save();
    $config = manager()->config('anthropic');

    expect($config->apiKey)->toBe('sk-ant-saved-key-111111111111111111')->and($config->keySource)->toBe('saved')
        ->and($config->defaultModel)->toBe('claude-saved')->and($config->baseUrl)->toBe('https://proxy.example.com');
});

it('needs no key to run: agents stay in rules mode and say why', function () {
    Http::fake();
    expect(manager()->available())->toBeFalse()->and(manager()->anyReady())->toBeFalse();

    $task = app(CentralAiOrchestrator::class)->submit('Run an SEO audit', 'seo-manager', $this->admin);

    expect($task->status)->toBe('completed')->and($task->mode)->toBe('rules')
        ->and($task->result['limitations'])->toContain('Written from data only: the language model is not configured.');
    Http::assertNothingSent();
});

/*
| OpenAI adapter
*/

it('sends the OpenAI request format and reads usage', function () {
    bothKeys();
    Http::fake(['api.openai.com/v1/chat/completions' => Http::response(openAiReply())]);

    $res = manager()->completeFor(agentNamed(), [['role' => 'system', 'content' => 'Be brief'], ['role' => 'user', 'content' => 'Hello']], 'narrative', null, 0.3, 400);

    expect($res['ok'])->toBeTrue()->and($res['text'])->toBe('From OpenAI')->and($res['provider'])->toBe('openai')->and($res['tokens_in'])->toBe(100)->and($res['tokens_out'])->toBe(20);
    Http::assertSent(fn (Request $r) => $r->hasHeader('Authorization', 'Bearer '.OPENAI_TEST_KEY)
        && $r['model'] === 'gpt-test-model' && $r['max_completion_tokens'] === 400 && $r['temperature'] === 0.3 && $r['messages'][0]['role'] === 'system');
});

it('asks OpenAI for low reasoning, and drops settings a model refuses', function () {
    bothKeys();
    Http::fake(['api.openai.com/*' => Http::sequence()
        ->push(['error' => ['message' => 'Unsupported parameter: temperature is not supported with this model.']], 400)
        ->push(['error' => ['message' => 'Unrecognized request argument supplied: reasoning_effort']], 400)
        ->push(openAiReply())]);

    expect(manager()->completeGeneral([['role' => 'user', 'content' => 'Hello']], 'test', 0.3, 400)['ok'])->toBeTrue();

    $sent = Http::recorded()->map(fn ($pair) => $pair[0]->data());
    expect($sent[0])->toHaveKey('temperature')->toHaveKey('reasoning_effort', 'low')
        ->and($sent[1])->not->toHaveKey('temperature')->toHaveKey('reasoning_effort')
        ->and($sent[2])->not->toHaveKey('temperature')->not->toHaveKey('reasoning_effort');
});

it('uses max_tokens on OpenAI-compatible servers, and retries without a refused temperature', function () {
    bothKeys();
    config(['services.openai.base_url' => 'https://compatible.example.com/v1']);
    Http::fake(['compatible.example.com/*' => Http::sequence()
        ->push(['error' => ['message' => 'Unsupported value: temperature does not support 0.3 with this model.']], 400)
        ->push(openAiReply())]);

    $res = manager()->completeGeneral([['role' => 'user', 'content' => 'Hello']], 'test', 0.3, 400);

    expect($res['ok'])->toBeTrue();
    $sent = Http::recorded()->map(fn ($pair) => $pair[0]->data());
    expect($sent[0])->toHaveKey('temperature')->toHaveKey('max_tokens')->not->toHaveKey('max_completion_tokens')
        ->and($sent[1])->not->toHaveKey('temperature');
});

/*
| Anthropic adapter
*/

it('sends the Anthropic request format: key header, version, top-level system, no sampling settings', function () {
    bothKeys();
    Http::fake(['api.anthropic.com/v1/messages' => Http::response(anthropicReply())]);
    $agent = agentNamed();
    $agent->forceFill(['primary_provider' => 'anthropic', 'secondary_provider' => 'openai'])->save();

    $res = manager()->completeFor($agent, [['role' => 'system', 'content' => 'Be brief'], ['role' => 'user', 'content' => 'Hello']], 'narrative', null, 0.3, 2000);

    expect($res['ok'])->toBeTrue()->and($res['text'])->toBe('From Claude')->and($res['provider'])->toBe('anthropic')
        ->and($res['tokens_in'])->toBe(90)->and($res['tokens_out'])->toBe(15);
    Http::assertSent(function (Request $r) {
        return $r->hasHeader('x-api-key', ANTHROPIC_TEST_KEY) && $r->hasHeader('anthropic-version', '2026-10-21') && ! $r->hasHeader('Authorization')
            && $r['model'] === 'claude-test-model' && $r['max_tokens'] === 2000 && $r['system'] === 'Be brief'
            && $r['messages'] === [['role' => 'user', 'content' => 'Hello']] && $r['output_config'] === ['effort' => 'low']
            && ! isset($r['temperature'], $r['top_p'], $r['top_k']);
    });
});

it('gives thinking models room to answer, within the administrator\'s limits', function () {
    bothKeys();
    Http::fake(['api.anthropic.com/*' => Http::response(anthropicReply())]);
    $agent = agentNamed();
    $agent->forceFill(['primary_provider' => 'anthropic', 'secondary_provider' => null])->save();

    manager()->completeFor($agent, [['role' => 'user', 'content' => 'x']], 'narrative', null, 0.2, 10);
    $agent->forceFill(['max_tokens' => 300])->save();
    manager()->completeFor($agent, [['role' => 'user', 'content' => 'x']], 'narrative', null, 0.2, 10);
    AppSetting::put('ai.controls', ['max_tokens_per_request' => 200] + AiProvider::controls());
    manager()->completeFor($agent, [['role' => 'user', 'content' => 'x']], 'narrative', null, 0.2, 10);

    expect(Http::recorded()->map(fn ($p) => $p[0]['max_tokens'])->all())->toBe([1024, 300, 200]);
});

it('retries without effort on models that refuse it', function () {
    bothKeys();
    Http::fake(['api.anthropic.com/*' => Http::sequence()
        ->push(['type' => 'error', 'error' => ['type' => 'invalid_request_error', 'message' => 'output_config.effort: this model does not support the effort parameter']], 400)
        ->push(anthropicReply())]);
    $agent = agentNamed();
    $agent->forceFill(['primary_provider' => 'anthropic'])->save();

    expect(manager()->completeFor($agent, [['role' => 'user', 'content' => 'x']], 'test')['ok'])->toBeTrue();
    expect(Http::recorded()[1][0]->data())->not->toHaveKey('output_config');
});

it('extracts JSON replies from Anthropic, with or without a code fence', function () {
    bothKeys();
    Http::fake(['api.anthropic.com/*' => Http::response(anthropicReply("```json\n{\"intent\":\"audit\"}\n```"))]);
    $agent = agentNamed();
    $agent->forceFill(['primary_provider' => 'anthropic'])->save();

    $res = manager()->completeFor($agent, [['role' => 'system', 'content' => 'Choose'], ['role' => 'user', 'content' => 'x']], 'intent', null, 0.0, 30, json: true);

    expect(json_decode($res['text'], true))->toBe(['intent' => 'audit']);
    Http::assertSent(fn (Request $r) => str_contains($r['system'], 'single JSON object'));
});

it('reports a refusal and a cut-off reply clearly, without pretending to have an answer', function (array $reply, string $failure, string $text) {
    bothKeys();
    Http::fake(['api.anthropic.com/*' => Http::response($reply)]);
    $agent = agentNamed();
    $agent->forceFill(['primary_provider' => 'anthropic'])->save();

    $res = manager()->completeFor($agent, [['role' => 'user', 'content' => 'x']], 'test');

    expect($res['ok'])->toBeFalse()->and($res['failure'])->toBe($failure)->and($res['error'])->toContain($text)->and($res['tokens_out'])->toBe(15);
})->with([
    'refusal' => [['content' => [], 'stop_reason' => 'refusal', 'usage' => ['input_tokens' => 90, 'output_tokens' => 15]], 'safety', 'declined'],
    'out of tokens' => [['content' => [['type' => 'thinking', 'thinking' => '']], 'stop_reason' => 'max_tokens', 'usage' => ['input_tokens' => 90, 'output_tokens' => 15]], 'truncated', 'token limit'],
]);

/*
| Failure handling
*/

it('classifies provider errors', function (string $provider, int $status, array $body, string $failure, string $text) {
    bothKeys();
    Http::fake(['*' => Http::response($body, $status)]);
    $agent = agentNamed();
    $agent->forceFill(['primary_provider' => $provider, 'secondary_provider' => null])->save();

    $res = manager()->completeFor($agent, [['role' => 'user', 'content' => 'x']], 'test');

    expect($res['ok'])->toBeFalse()->and($res['failure'])->toBe($failure)->and($res['error'])->toContain($text);
})->with([
    'openai bad key' => ['openai', 401, ['error' => ['message' => 'Incorrect API key provided: sk-abc***']], 'auth', 'rejected the credentials'],
    'openai no credit' => ['openai', 429, ['error' => ['code' => 'insufficient_quota', 'message' => 'You exceeded your current quota']], 'quota', 'No API credit'],
    'openai no credit, as OpenAI really answers' => ['openai', 429, ['error' => ['type' => 'insufficient_quota', 'code' => 'credit_balance_exhausted', 'message' => 'You have no credits remaining.']], 'quota', 'No API credit'],
    'openai rate limit' => ['openai', 429, ['error' => ['code' => 'rate_limit_exceeded', 'message' => 'slow down']], 'temporary', 'rate limit'],
    'openai missing model' => ['openai', 404, ['error' => ['code' => 'model_not_found', 'message' => 'no such model']], 'model', 'Model not found'],
    'openai outage' => ['openai', 503, [], 'temporary', 'HTTP 503'],
    'openai bad request' => ['openai', 400, ['error' => ['message' => 'messages is required']], 'invalid', 'messages is required'],
    'anthropic bad key' => ['anthropic', 401, ['type' => 'error', 'error' => ['type' => 'authentication_error', 'message' => 'invalid x-api-key']], 'auth', 'rejected the credentials'],
    'anthropic no credit' => ['anthropic', 400, ['type' => 'error', 'error' => ['type' => 'invalid_request_error', 'message' => 'Your credit balance is too low to access the API.']], 'quota', 'No API credit'],
    'anthropic billing' => ['anthropic', 402, ['type' => 'error', 'error' => ['type' => 'billing_error', 'message' => 'billing']], 'quota', 'No API credit'],
    'anthropic missing model' => ['anthropic', 404, ['type' => 'error', 'error' => ['type' => 'not_found_error', 'message' => 'model: x']], 'model', 'Model not found'],
    'anthropic overloaded' => ['anthropic', 529, ['type' => 'error', 'error' => ['type' => 'overloaded_error', 'message' => 'Overloaded']], 'temporary', 'HTTP 529'],
    'anthropic bad request' => ['anthropic', 400, ['type' => 'error', 'error' => ['type' => 'invalid_request_error', 'message' => 'messages: empty']], 'invalid', 'messages: empty'],
]);

it('never repeats a provider message on a rejected key, which can echo part of the key', function () {
    bothKeys();
    Http::fake(['*' => Http::response(['error' => ['message' => 'Incorrect API key provided: sk-abc123***']], 401)]);

    $res = manager()->completeGeneral([['role' => 'user', 'content' => 'x']], 'test');

    expect($res['error'])->not->toContain('sk-abc');
    expect(AiUsageRecord::sole()->error)->not->toContain('sk-abc');
    expect(AiProviderSetting::where('provider', 'openai')->value('last_error'))->not->toContain('sk-abc');
});

it('handles a connection failure and a timeout as a temporary failure', function () {
    bothKeys();
    Http::fake(fn () => throw new ConnectionException('cURL error 28: timed out'));

    $res = manager()->completeGeneral([['role' => 'user', 'content' => 'x']], 'test');

    expect($res['failure'])->toBe('temporary')->and($res['error'])->toContain('unreachable');
});

it('retries temporary failures up to the limit, but not a rejected key or missing credit', function () {
    bothKeys();
    config(['services.openai.max_retries' => 2]);
    Http::fake(['*' => Http::sequence()->push([], 503)->push([], 503)->push(openAiReply())]);
    expect(manager()->completeGeneral([['role' => 'user', 'content' => 'x']], 'test')['ok'])->toBeTrue();
    Http::assertSentCount(3);
});

it('does not retry a rejected key', function () {
    bothKeys();
    config(['services.openai.max_retries' => 2]);
    Http::fake(['*' => Http::response(['error' => ['message' => 'bad']], 401)]);
    manager()->completeGeneral([['role' => 'user', 'content' => 'x']], 'test');
    Http::assertSentCount(1);
});

it('does not retry when there is no API credit', function () {
    bothKeys();
    config(['services.openai.max_retries' => 2]);
    Http::fake(['*' => Http::response(['error' => ['code' => 'insufficient_quota', 'message' => 'quota']], 429)]);
    manager()->completeGeneral([['role' => 'user', 'content' => 'x']], 'test');
    Http::assertSentCount(1);
});

it('explains a missing model instead of calling the provider', function () {
    Http::fake();
    config(['services.anthropic.key' => ANTHROPIC_TEST_KEY, 'services.anthropic.model' => null]);
    $agent = agentNamed();
    $agent->forceFill(['primary_provider' => 'anthropic', 'secondary_provider' => null])->save();

    $res = manager()->completeFor($agent, [['role' => 'user', 'content' => 'x']], 'test');

    expect($res['failure'])->toBe('no_model')->and($res['error'])->toContain('No model is selected for Anthropic');
    Http::assertNothingSent();
});

it('tells the administrators once about a rejected key or missing credit', function () {
    Notification::fake();
    bothKeys();
    Http::fake(['*' => Http::response(['error' => ['message' => 'bad']], 401)]);

    manager()->completeGeneral([['role' => 'user', 'content' => 'x']], 'test');
    manager()->completeGeneral([['role' => 'user', 'content' => 'x']], 'test');

    Notification::assertSentToTimes($this->admin, AgentNeedsAttention::class, 1);
});

/*
| Fallback
*/

it('falls back on a temporary failure when allowed, recording both calls and showing it on the task', function () {
    bothKeys();
    allowFallback();
    Http::fake(['api.openai.com/*' => Http::response([], 503), 'api.anthropic.com/*' => Http::response(anthropicReply('Summary without figures.'))]);

    $task = app(CentralAiOrchestrator::class)->submit('Run an SEO audit', 'seo-manager', $this->admin);

    expect($task->mode)->toBe('llm')->and($task->result['narrative'])->toBe('Summary without figures.')
        ->and(implode(' ', $task->result['limitations']))->toContain('fallback provider Anthropic Claude')->toContain('OpenAI failed');

    $failed = AiUsageRecord::where('provider', 'openai')->sole();
    $used = AiUsageRecord::where('provider', 'anthropic')->sole();
    expect($failed->ok)->toBeFalse()->and($failed->error)->toContain('HTTP 503')->and($failed->fallback_from)->toBeNull()
        ->and($used->ok)->toBeTrue()->and($used->fallback_from)->toBe('openai')->and($used->model)->toBe('claude-test-model')->and($used->task_id)->toBe($task->id);

    $this->actingAs($this->admin)->get(route('admin.ai.tasks.show', $task))->assertOk()->assertSee('fallback from openai');
    $this->get(route('admin.ai.logs'))->assertOk()->assertSee('openai → anthropic');
});

it('does not fall back while the global switch is off, even if the agent allows it', function () {
    bothKeys();
    Http::fake(['api.openai.com/*' => Http::response([], 503), 'api.anthropic.com/*' => Http::response(anthropicReply())]);

    $res = manager()->completeFor(agentNamed(), [['role' => 'user', 'content' => 'x']], 'test');

    expect($res['ok'])->toBeFalse();
    Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'anthropic'));
});

it('does not fall back when the agent forbids it', function () {
    bothKeys();
    allowFallback();
    $agent = agentNamed();
    $agent->forceFill(['fallback_enabled' => false])->save();
    Http::fake(['api.openai.com/*' => Http::response([], 503), 'api.anthropic.com/*' => Http::response(anthropicReply())]);

    expect(manager()->completeFor($agent, [['role' => 'user', 'content' => 'x']], 'test')['ok'])->toBeFalse();
    Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'anthropic'));
});

it('never falls back for a rejected key, missing credit, a missing model, a bad request or a declined request', function (int $status, array $body) {
    bothKeys();
    allowFallback();
    Http::fake(['api.openai.com/*' => Http::response($body, $status), 'api.anthropic.com/*' => Http::response(anthropicReply())]);

    expect(manager()->completeFor(agentNamed(), [['role' => 'user', 'content' => 'x']], 'test')['ok'])->toBeFalse();
    Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'anthropic'));
})->with([
    'rejected key' => [401, ['error' => ['message' => 'bad']]],
    'no credit' => [429, ['error' => ['code' => 'insufficient_quota']]],
    'missing model' => [404, ['error' => ['code' => 'model_not_found']]],
    'bad request' => [400, ['error' => ['message' => 'bad']]],
    'declined' => [200, ['choices' => [['message' => ['content' => null, 'refusal' => 'No.'], 'finish_reason' => 'stop']], 'usage' => ['prompt_tokens' => 1, 'completion_tokens' => 1]]],
]);

it('never falls back past a used-up budget, and the fallback provider\'s budget also applies', function () {
    bothKeys();
    allowFallback();
    AppSetting::put('ai.pricing', ['gpt-test-model' => ['input' => 1, 'output' => 1], 'claude-test-model' => ['input' => 1, 'output' => 1]]);
    Http::fake(['api.openai.com/*' => Http::response([], 503), 'api.anthropic.com/*' => Http::response(anthropicReply())]);
    AiProviderSetting::where('provider', 'anthropic')->update(['monthly_budget_usd' => 0.00001]);

    $res = manager()->completeFor(agentNamed(), [['role' => 'user', 'content' => 'x']], 'test');

    expect($res['ok'])->toBeFalse()->and($res['error'])->toContain('also failed')->toContain('monthly budget');
    Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'anthropic'));

    // A used-up primary budget is not a reason to try the other company.
    AiProviderSetting::query()->update(['monthly_budget_usd' => null]);
    AiProviderSetting::where('provider', 'openai')->update(['monthly_budget_usd' => 0.00001]);
    expect(manager()->completeFor(agentNamed(), [['role' => 'user', 'content' => 'x']], 'test')['failure'])->toBe('budget');
    Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'anthropic'));
});

it('routes to the secondary when the primary has no key, only if fallback is allowed', function () {
    Http::fake(['api.anthropic.com/*' => Http::response(anthropicReply())]);
    config(['services.anthropic.key' => ANTHROPIC_TEST_KEY, 'services.anthropic.model' => 'claude-test-model']);

    expect(manager()->completeFor(agentNamed(), [['role' => 'user', 'content' => 'x']], 'test')['failure'])->toBe('not_configured');
    Http::assertNothingSent();

    allowFallback();
    $res = manager()->completeFor(agentNamed(), [['role' => 'user', 'content' => 'x']], 'test');
    expect($res['ok'])->toBeTrue()->and($res['provider'])->toBe('anthropic')->and($res['fallback_from'])->toBe('openai');
});

it('does not use a disabled provider', function () {
    bothKeys();
    AiProviderSetting::where('provider', 'openai')->update(['enabled' => false]);
    Http::fake();

    expect(manager()->completeFor(agentNamed(), [['role' => 'user', 'content' => 'x']], 'test')['failure'])->toBe('disabled');
    Http::assertNothingSent();
});

it('reports both errors when the fallback also fails', function () {
    bothKeys();
    allowFallback();
    Http::fake(['api.openai.com/*' => Http::response([], 503), 'api.anthropic.com/*' => Http::response(['error' => ['type' => 'overloaded_error', 'message' => 'x']], 529)]);

    $task = app(CentralAiOrchestrator::class)->submit('Run an SEO audit', 'seo-manager', $this->admin);

    expect($task->status)->toBe('completed')->and($task->mode)->toBe('rules')
        ->and(implode(' ', $task->result['limitations']))->toContain('HTTP 503')->toContain('The fallback also failed')->toContain('HTTP 529');
});

/*
| Provider choice per agent
*/

it('lets each agent use its own provider and model', function () {
    bothKeys();
    Http::fake(['api.anthropic.com/*' => Http::response(anthropicReply('Summary without figures.')), 'api.openai.com/*' => Http::response(openAiReply('Summary without figures.'))]);
    agentNamed('hr-admin')->forceFill(['primary_provider' => 'anthropic', 'model' => 'claude-custom', 'secondary_provider' => 'openai'])->save();

    app(CentralAiOrchestrator::class)->submit('Staff directory', 'hr-admin', $this->admin);
    app(CentralAiOrchestrator::class)->submit('Run an SEO audit', 'seo-manager', $this->admin);

    expect(AiUsageRecord::where('agent_id', agentNamed('hr-admin')->id)->sole()->provider)->toBe('anthropic');
    Http::assertSent(fn (Request $r) => str_contains($r->url(), 'anthropic') && $r['model'] === 'claude-custom');
    expect(AiUsageRecord::where('agent_id', agentNamed('seo-manager')->id)->sole()->provider)->toBe('openai');
});

it('uses a model per workload tier on either provider', function () {
    bothKeys();
    config(['meezan.agents.models.routine' => 'gpt-routine', 'meezan.agents.anthropic_models.reasoning' => 'claude-reasoning']);

    expect(manager()->route(agentNamed('hr-admin'), 'routine')['primary']['model'])->toBe('gpt-routine')
        ->and(manager()->route(agentNamed('hr-admin'), 'reasoning')['primary']['model'])->toBe('gpt-test-model')
        ->and(manager()->route(agentNamed('ceo'), 'reasoning')['secondary']['model'])->toBe('claude-reasoning');
});

it('saves an agent\'s provider settings as a new version and refuses a bad combination', function () {
    $agent = agentNamed('hr-admin');
    $base = ['instructions' => $agent->instructions, 'model_tier' => 'routine', 'temperature' => 0.2, 'status' => 'active', 'tools' => $agent->tools, 'change_note' => 'Move to Claude'];

    $this->actingAs($this->admin)->patch(route('admin.ai.agents.update', $agent), $base + ['primary_provider' => 'anthropic', 'secondary_provider' => 'openai', 'secondary_model' => 'gpt-x', 'fallback_enabled' => '0', 'max_tokens' => 500, 'daily_request_limit' => 20, 'monthly_budget_usd' => 5])->assertSessionHas('status');

    $agent->refresh();
    expect($agent->primary_provider)->toBe('anthropic')->and($agent->secondary_model)->toBe('gpt-x')->and($agent->fallback_enabled)->toBeFalse()
        ->and($agent->max_tokens)->toBe(500)->and($agent->version)->toBe(2)
        ->and($agent->versions()->latest('version')->first()->snapshot['primary_provider'])->toBe('anthropic');

    $this->patch(route('admin.ai.agents.update', $agent), $base + ['primary_provider' => 'anthropic', 'secondary_provider' => 'anthropic'])->assertSessionHasErrors('secondary_provider');
    $this->patch(route('admin.ai.agents.update', $agent), $base + ['primary_provider' => 'gemini'])->assertSessionHasErrors('primary_provider');
});

it('shows each agent\'s real route or why it runs on rules', function () {
    expect(app(AgentReadiness::class)->all()['hr-admin']['model'])->toContain('Rules mode');

    bothKeys();
    expect(app(AgentReadiness::class)->all()['hr-admin']['model'])->toBe('OpenAI: gpt-test-model');

    AiProviderSetting::where('provider', 'openai')->update(['connection_status' => 'error']);
    expect(app(AgentReadiness::class)->all()['hr-admin']['model'])->toContain('failed its last connection test');

    agentNamed('hr-admin')->forceFill(['primary_provider' => 'anthropic'])->save();
    config(['services.anthropic.model' => null]);
    expect(app(AgentReadiness::class)->all()['hr-admin']['model'])->toContain('no model is selected for Anthropic');
});

/*
| Budgets
*/

it('stops paid requests when a monthly budget would be exceeded, and keeps the task and portal working', function () {
    Notification::fake();
    bothKeys();
    Http::fake(['*' => Http::response(openAiReply('Summary without figures.'))]);
    AppSetting::put('ai.pricing', ['gpt-test-model' => ['input' => 10, 'output' => 10]]);
    AppSetting::put('ai.controls', ['monthly_total_usd' => 1.0] + AiProvider::controls());
    AiUsageRecord::create(['provider' => 'openai', 'model' => 'gpt-test-model', 'purpose' => 'x', 'tokens_in' => 0, 'tokens_out' => 0, 'cost_usd' => 0.999, 'ok' => true, 'created_at' => now()]);

    $task = app(CentralAiOrchestrator::class)->submit('Run an SEO audit', 'seo-manager', $this->admin);

    expect($task->status)->toBe('completed')->and($task->mode)->toBe('rules')->and(implode(' ', $task->result['limitations']))->toContain('monthly AI budget is used up');
    Http::assertNothingSent();
    Notification::assertSentTo($this->admin, AgentNeedsAttention::class, fn ($n) => $n->title === 'AI budget reached');
    $this->get('/')->assertOk();
});

it('counts the most the next request could cost, so a budget is never overrun', function () {
    bothKeys();
    Http::fake(['*' => Http::response(openAiReply())]);
    AppSetting::put('ai.pricing', ['gpt-test-model' => ['input' => 0, 'output' => 100]]);
    // 256 tokens of output at USD 100 per million is at most USD 0.0256.
    AppSetting::put('ai.controls', ['monthly_total_usd' => 0.02] + AiProvider::controls());

    expect(manager()->completeGeneral([['role' => 'user', 'content' => 'x']], 'test', maxTokens: 10)['failure'])->toBe('budget');

    AppSetting::put('ai.controls', ['monthly_total_usd' => 0.03] + AiProvider::controls());
    expect(manager()->completeGeneral([['role' => 'user', 'content' => 'x']], 'test', maxTokens: 10)['ok'])->toBeTrue();
});

it('refuses to spend on a model with no price while a budget is set', function () {
    bothKeys();
    Http::fake();
    AppSetting::put('ai.controls', ['monthly_total_usd' => 10.0] + AiProvider::controls());

    $res = manager()->completeGeneral([['role' => 'user', 'content' => 'x']], 'test');

    expect($res['failure'])->toBe('budget')->and($res['error'])->toContain('has no price');
    Http::assertNothingSent();
});

it('applies the provider, agent and daily request limits', function () {
    bothKeys();
    Http::fake(['*' => Http::response(openAiReply())]);
    AppSetting::put('ai.pricing', ['gpt-test-model' => ['input' => 1, 'output' => 1]]);
    $agent = agentNamed();
    $call = fn () => manager()->completeFor($agent, [['role' => 'user', 'content' => 'x']], 'test');
    $spend = fn ($agentId) => AiUsageRecord::create(['agent_id' => $agentId, 'provider' => 'openai', 'model' => 'gpt-test-model', 'purpose' => 'x', 'tokens_in' => 0, 'tokens_out' => 0, 'cost_usd' => 5, 'ok' => true, 'created_at' => now()]);

    AiProviderSetting::where('provider', 'openai')->update(['monthly_budget_usd' => 4]);
    $spend(null);
    expect($call()['error'])->toContain('openai monthly budget');

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

it('stops every paid request with the emergency stop, and the portal keeps working', function () {
    bothKeys();
    Http::fake();
    $this->actingAs($this->admin)->patch(route('admin.ai.controls'), ['emergency_stop' => '1'])->assertSessionHas('status');

    $task = app(CentralAiOrchestrator::class)->submit('Run an SEO audit', 'seo-manager', $this->admin);

    expect($task->status)->toBe('completed')->and($task->mode)->toBe('rules')->and(implode(' ', $task->result['limitations']))->toContain('emergency stop');
    Http::assertNothingSent();
    $this->get(route('admin.ai.overview'))->assertOk()->assertSee('Emergency stop is on');
    auth()->logout();
    $this->get('/')->assertOk();
    $this->postJson(route('ai-finder.match'), ['message' => 'How can I register as a property dealer?'])->assertOk();
});

it('counts cost from entered prices per model, and records usage per provider', function () {
    bothKeys();
    allowFallback();
    AppSetting::put('ai.pricing', ['claude-test-model' => ['input' => 1, 'output' => 2]]);
    Http::fake(['api.openai.com/*' => Http::response([], 503), 'api.anthropic.com/*' => Http::response(anthropicReply('Summary without figures.'))]);

    $task = app(CentralAiOrchestrator::class)->submit('Run an SEO audit', 'seo-manager', $this->admin);

    // 90 in and 15 out at USD 1 and 2 per million tokens.
    expect($task->cost_usd)->toEqual(0.00012);
    $this->actingAs($this->admin)->get(route('admin.ai.usage'))->assertOk()->assertSee('Anthropic Claude')->assertSee('fallback from OpenAI')->assertSee('0.0001');
});

/*
| Website and WhatsApp AI
*/

it('sends website requirement parsing through the manager, with its provider and usage recorded', function () {
    bothKeys();
    config(['meezan.ai.driver' => 'llm']);
    AppSetting::put('ai.controls', ['channels' => ['website' => ['provider' => 'anthropic', 'model' => 'claude-website', 'fallback' => false]]] + AiProvider::controls());
    Http::fake(['api.anthropic.com/*' => Http::response(anthropicReply('{"purpose":"sale","type":"house","areas":[],"max_price":null,"min_size":null,"bedrooms":3,"bathrooms":null,"amenities":[]}'))]);

    $this->postJson(route('ai-finder.match'), ['message' => 'house in Lahore'])->assertOk();

    Http::assertSent(fn (Request $r) => str_contains($r->url(), 'anthropic') && $r['model'] === 'claude-website');
    expect(AiUsageRecord::where('purpose', 'requirement_parsing')->sole()->provider)->toBe('anthropic');
});

it('keeps website answers working when no provider responds', function () {
    bothKeys();
    config(['meezan.ai.driver' => 'llm']);
    Http::fake(['*' => Http::response([], 500)]);

    $this->postJson(route('ai-finder.match'), ['message' => 'house in Lahore'])->assertOk()->assertJsonStructure(['reply']);
});

it('uses the channel setting for agent tasks from WhatsApp and website, and for nothing else', function () {
    bothKeys();
    AppSetting::put('ai.controls', ['channels' => ['whatsapp' => ['provider' => 'anthropic', 'model' => null, 'fallback' => true]]] + AiProvider::controls());

    expect(manager()->route(agentNamed(), 'research', 'whatsapp')['primary']['provider'])->toBe('anthropic')
        ->and(manager()->route(agentNamed(), 'research', 'website')['primary']['provider'])->toBe('openai')
        ->and(manager()->route(agentNamed(), 'research')['primary']['provider'])->toBe('openai');

    Http::fake(['api.anthropic.com/*' => Http::response(anthropicReply('Summary without figures.'))]);
    $task = new AiTask(['title' => 't', 'request' => 'r', 'source' => 'whatsapp']);
    $task->forceFill(['agent_id' => agentNamed()->id, 'status' => 'running', 'depth' => 0])->save();
    expect(manager()->completeFor(agentNamed(), [['role' => 'user', 'content' => 'x']], 'test', $task)['provider'])->toBe('anthropic');
});

it('routes channel work done inside the assistant, and resets afterwards', function () {
    bothKeys();
    AppSetting::put('ai.controls', ['channels' => ['whatsapp' => ['provider' => 'anthropic', 'model' => null, 'fallback' => true]]] + AiProvider::controls());

    $inside = manager()->inChannel('whatsapp', fn () => manager()->route(null)['primary']['provider']);

    expect($inside)->toBe('anthropic')->and(manager()->route(null)['primary']['provider'])->toBe('openai');
});

it('sends investment wording through the manager', function () {
    expect(file_get_contents(app_path('Services/Investment/InvestmentInsights.php')))->not->toContain('chat/completions')->not->toContain('services.openai');
    expect(file_get_contents(app_path('Services/Ai/LlmRequirementParser.php')))->not->toContain('chat/completions')->not->toContain('services.openai');
});

it('has only the adapters talking to providers', function () {
    $files = collect(File::allFiles(app_path()))->map(fn ($f) => $f->getRelativePathname())
        ->filter(fn ($path) => str_contains(file_get_contents(app_path($path)), 'chat/completions') || str_contains(file_get_contents(app_path($path)), '/v1/messages'))
        ->map(fn ($path) => str_replace('\\', '/', $path))->values()->all();

    expect($files)->toEqualCanonicalizing(['Services/Agents/Providers/AnthropicAdapter.php', 'Services/Agents/Providers/OpenAiAdapter.php']);
});

/*
| Admin provider settings
*/

it('shows both providers and asks for a key when none is configured', function () {
    $this->actingAs($this->admin)->get(route('admin.ai.providers'))->assertOk()
        ->assertSee('OpenAI')->assertSee('Anthropic Claude')->assertSee('API key not configured. Add your API key to activate this provider.')
        ->assertSee('Not configured')->assertDontSee('Connection verified');
});

it('makes no request and claims no success when testing without a key', function () {
    Http::fake();
    $this->actingAs($this->admin)->post(route('admin.ai.providers.test', 'anthropic'))->assertSessionHas('error', fn ($m) => str_contains($m, 'API key not configured'));
    Http::assertNothingSent();
    expect(AiProviderSetting::where('provider', 'anthropic')->value('connection_status'))->toBe('untested');
});

it('saves a key encrypted, never shows it, and hides it from serialisation', function () {
    $key = 'sk-ant-api03-secret-value-222222222222222';
    $this->actingAs($this->admin)->patch(route('admin.ai.providers.update', 'anthropic'), ['enabled' => '1', 'api_key' => $key, 'default_model' => 'claude-chosen'])->assertSessionHas('status');

    $row = AiProviderSetting::where('provider', 'anthropic')->first();
    expect($row->savedKey())->toBe($key)->and($row->getRawOriginal('api_key'))->not->toContain($key)
        ->and($row->toArray())->not->toHaveKey('api_key');
    expect(manager()->config('anthropic')->apiKey)->toBe($key);
    expect(print_r(manager()->config('anthropic'), true))->not->toContain($key);

    foreach ([route('admin.ai.providers'), route('admin.ai.connectors'), route('admin.ai.overview'), route('admin.ai.usage')] as $url) {
        $this->get($url)->assertOk()->assertDontSee($key);
    }
    $this->get(route('admin.ai.providers'))->assertSee('saved here, encrypted')->assertSee('Key added, not tested');
});

it('never removes a key with an empty field, and removes it only on request', function () {
    $key = 'sk-ant-api03-secret-value-333333333333333';
    $this->actingAs($this->admin)->patch(route('admin.ai.providers.update', 'anthropic'), ['enabled' => '1', 'api_key' => $key]);
    $this->patch(route('admin.ai.providers.update', 'anthropic'), ['enabled' => '1', 'api_key' => '']);
    expect(manager()->config('anthropic')->apiKey)->toBe($key);

    $this->patch(route('admin.ai.providers.update', 'anthropic'), ['enabled' => '1', 'remove_key' => '1']);
    expect(manager()->config('anthropic')->hasKey())->toBeFalse();
});

it('falls back to the .env key after a saved key is removed', function () {
    config(['services.openai.key' => OPENAI_TEST_KEY]);
    $this->actingAs($this->admin)->patch(route('admin.ai.providers.update', 'openai'), ['enabled' => '1', 'api_key' => 'sk-saved-key-44444444444444444444']);
    expect(manager()->config('openai')->keySource)->toBe('saved');

    $this->patch(route('admin.ai.providers.update', 'openai'), ['enabled' => '1', 'remove_key' => '1']);
    expect(manager()->config('openai')->keySource)->toBe('env');
});

it('never puts a key in the session, the audit log or the application log', function () {
    $key = 'sk-ant-api03-secret-value-555555555555555';
    Log::spy();

    // A failed form must not flash the key back.
    $this->actingAs($this->admin)->from(route('admin.ai.providers'))->patch(route('admin.ai.providers.update', 'anthropic'), ['enabled' => '1', 'api_key' => $key, 'base_url' => 'http://evil.example.com'])->assertSessionHasErrors('base_url');
    expect(json_encode(session()->all()))->not->toContain($key);
    expect(manager()->config('anthropic')->hasKey())->toBeFalse();

    $this->patch(route('admin.ai.providers.update', 'anthropic'), ['enabled' => '1', 'api_key' => $key]);
    $entry = AuditLog::where('action', 'ai.provider_updated')->latest('id')->first();
    expect(json_encode($entry->toArray()))->not->toContain($key)->toContain('api_key_changed');

    Log::shouldNotHaveReceived('warning', fn ($m, $c = []) => str_contains(json_encode([$m, $c]), $key));
});

it('removes saved and environment keys from agent output', function () {
    config(['services.anthropic.key' => 'plainenvkeywithoutprefix0000000000']);
    AiProviderSetting::where('provider', 'openai')->first()->forceFill(['api_key' => 'plainsavedkeywithoutprefix11111111'])->save();

    expect(OutputGuard::text('a plainenvkeywithoutprefix0000000000 b plainsavedkeywithoutprefix11111111 c sk-ant-api03-abcdefghijklmnopqrstuvwxyz'))
        ->not->toContain('plainenvkey')->not->toContain('plainsavedkey')->not->toContain('abcdefghijkl');
});

it('reports an unreadable saved key instead of failing', function () {
    AiProviderSetting::where('provider', 'openai')->update(['api_key' => 'not-valid-ciphertext']);

    expect(manager()->config('openai')->keyUnreadable)->toBeTrue()
        ->and(manager()->completeFor(agentNamed(), [['role' => 'user', 'content' => 'x']], 'test')['error'])->toContain('can no longer be read');
    $this->actingAs($this->admin)->get(route('admin.ai.providers'))->assertOk()->assertSee('Key unreadable');
});

it('verifies a connection only after a real model list and test request succeed', function () {
    bothKeys();
    Http::fake([
        'api.anthropic.com/v1/models*' => Http::response(['data' => [['id' => 'claude-test-model'], ['id' => 'claude-other']]]),
        'api.anthropic.com/v1/messages' => Http::response(anthropicReply('OK')),
    ]);

    $this->actingAs($this->admin)->post(route('admin.ai.providers.test', 'anthropic'))->assertSessionHas('status', fn ($m) => str_contains($m, 'Connected'));

    $row = AiProviderSetting::where('provider', 'anthropic')->first();
    expect($row->connection_status)->toBe('verified')->and($row->available_models)->toBe(['claude-test-model', 'claude-other'])->and($row->last_success_at)->not->toBeNull();
    expect(app(ConnectorStatus::class)->all()['anthropic']['status'])->toBe('verified');
    expect(AiUsageRecord::where('purpose', 'connection_test')->sole()->provider)->toBe('anthropic');
    Http::assertSent(fn (Request $r) => str_contains($r->url(), '/v1/models') && $r->hasHeader('x-api-key', ANTHROPIC_TEST_KEY));
    $this->get(route('admin.ai.providers'))->assertSee('Connection verified')->assertSee('2 models available');
});

it('asks for a model after the key is proved, without sending a paid request', function () {
    config(['services.anthropic.key' => ANTHROPIC_TEST_KEY, 'services.anthropic.model' => null]);
    Http::fake(['api.anthropic.com/v1/models*' => Http::response(['data' => [['id' => 'claude-a']]])]);

    $this->actingAs($this->admin)->post(route('admin.ai.providers.test', 'anthropic'))->assertSessionHas('status', fn ($m) => str_contains($m, 'Choose a default model'));

    Http::assertNotSent(fn (Request $r) => str_contains($r->url(), '/v1/messages'));
});

it('shows a clear error for a bad key, no credit and an unavailable model', function () {
    bothKeys();
    Http::fake(['api.openai.com/v1/models' => Http::sequence()->push(['error' => ['message' => 'bad']], 401)->push(['data' => [['id' => 'gpt-other']]])->push(['data' => [['id' => 'gpt-test-model']]]),
        'api.openai.com/v1/chat/completions' => Http::sequence()->push(['error' => ['code' => 'model_not_found', 'message' => 'x']], 404)->push(['error' => ['code' => 'insufficient_quota', 'message' => 'x']], 429)]);
    $this->actingAs($this->admin);

    $this->post(route('admin.ai.providers.test', 'openai'))->assertSessionHas('error', fn ($m) => str_contains($m, 'HTTP 401'));
    expect(AiProviderSetting::where('provider', 'openai')->value('connection_status'))->toBe('error');

    $this->post(route('admin.ai.providers.test', 'openai'))->assertSessionHas('error', fn ($m) => str_contains($m, 'Model not found'));
    $this->get(route('admin.ai.providers'))->assertSee('is not in the model list this account returned');

    $this->post(route('admin.ai.providers.test', 'openai'))->assertSessionHas('error', fn ($m) => str_contains($m, 'No API credit'));
    expect(AiProviderSetting::where('provider', 'openai')->value('connection_status'))->toBe('error');
});

it('forgets a verified connection when the key or base URL changes', function () {
    bothKeys();
    AiProviderSetting::where('provider', 'openai')->update(['connection_status' => 'verified', 'available_models' => json_encode(['gpt-test-model']), 'last_success_at' => now()]);
    app(ConnectorStatus::class)->remember('openai', ['status' => 'verified', 'detail' => 'ok']);

    $this->actingAs($this->admin)->patch(route('admin.ai.providers.update', 'openai'), ['enabled' => '1', 'base_url' => 'https://proxy.example.com/v1']);

    $row = AiProviderSetting::where('provider', 'openai')->first();
    expect($row->connection_status)->toBe('untested')->and($row->available_models)->toBeNull();
    expect(app(ConnectorStatus::class)->all()['openai']['status'])->toBe('configured');
});

it('asks for a new test after the model changes, keeping the model list', function () {
    bothKeys();
    AiProviderSetting::where('provider', 'openai')->update(['connection_status' => 'verified', 'available_models' => json_encode(['gpt-a', 'gpt-b'])]);

    $this->actingAs($this->admin)->patch(route('admin.ai.providers.update', 'openai'), ['enabled' => '1', 'default_model' => 'gpt-b']);

    $row = AiProviderSetting::where('provider', 'openai')->first();
    expect($row->connection_status)->toBe('untested')->and($row->available_models)->toBe(['gpt-a', 'gpt-b']);
    $this->get(route('admin.ai.providers'))->assertDontSee('Connection verified');
});

it('says when the key works but no model has been chosen, without claiming a full connection', function () {
    config(['services.openai.key' => OPENAI_TEST_KEY, 'services.openai.model' => null]);
    AiProviderSetting::where('provider', 'openai')->update(['connection_status' => 'verified']);

    $this->actingAs($this->admin)->get(route('admin.ai.providers'))->assertSee('Key works, no model chosen')->assertDontSee('Connection verified');
});

it('refuses a plain HTTP base URL, because the key would be sent over it', function () {
    $this->actingAs($this->admin)->patch(route('admin.ai.providers.update', 'openai'), ['enabled' => '1', 'base_url' => 'http://example.com/v1'])->assertSessionHasErrors('base_url');
    $this->patch(route('admin.ai.providers.update', 'openai'), ['enabled' => '1', 'base_url' => 'http://localhost:9000/v1'])->assertSessionHasNoErrors();
});

it('lets an admin disable a provider, and records changes without secrets', function () {
    $this->actingAs($this->admin)->patch(route('admin.ai.providers.update', 'anthropic'), ['enabled' => '0'])->assertSessionHas('status');

    expect(manager()->config('anthropic')->enabled)->toBeFalse();
    $entry = AuditLog::where('action', 'ai.provider_updated')->latest('id')->first();
    expect(json_encode($entry->toArray()))->toContain('enabled');
});

it('keeps provider settings and controls admin only', function () {
    Http::fake();
    $dealer = User::factory()->dealer(DealerStatus::Verified)->create();
    $buyer = User::factory()->create();

    foreach ([$dealer, $buyer] as $user) {
        $this->actingAs($user)->get(route('admin.ai.providers'))->assertForbidden();
        $this->patch(route('admin.ai.providers.update', 'openai'), ['enabled' => '1', 'api_key' => 'sk-attacker-key-000000000000000000'])->assertForbidden();
        $this->post(route('admin.ai.providers.test', 'openai'))->assertForbidden();
        $this->patch(route('admin.ai.controls'), ['emergency_stop' => '1'])->assertForbidden();
    }
    auth()->logout();
    $this->get(route('admin.ai.providers'))->assertRedirect();

    expect(manager()->config('openai')->hasKey())->toBeFalse()->and(AiProvider::controls()['emergency_stop'])->toBeFalse();
    Http::assertNothingSent();
});

it('saves each controls form without losing the other\'s settings', function () {
    $this->actingAs($this->admin)->patch(route('admin.ai.controls'), ['fallback_allowed' => '1', 'default_provider' => 'anthropic', 'channels' => ['website' => ['provider' => 'anthropic', 'model' => '', 'fallback' => '0']]])->assertSessionHas('status');
    $this->patch(route('admin.ai.controls'), ['emergency_stop' => '0', 'monthly_total_usd' => '25', 'daily_requests_total' => '', 'max_tokens_per_request' => '900'])->assertSessionHas('status');

    $c = AiProvider::controls();
    expect($c['fallback_allowed'])->toBeTrue()->and($c['default_provider'])->toBe('anthropic')->and($c['channels']['website']['fallback'])->toBeFalse()
        ->and((float) $c['monthly_total_usd'])->toBe(25.0)->and((int) $c['max_tokens_per_request'])->toBe(900);
    expect(manager()->defaultProvider())->toBe('anthropic');

    $this->patch(route('admin.ai.controls'), ['default_provider' => 'gemini'])->assertSessionHasErrors('default_provider');
});

it('shows the provider dashboard with data and all the new columns on the database', function () {
    expect(Schema::hasColumns('ai_agents', ['primary_provider', 'secondary_provider', 'secondary_model', 'fallback_enabled', 'max_tokens', 'daily_request_limit', 'monthly_budget_usd']))->toBeTrue()
        ->and(Schema::hasColumn('ai_usage_records', 'fallback_from'))->toBeTrue();

    AiUsageRecord::create(['provider' => 'anthropic', 'fallback_from' => 'openai', 'model' => 'claude-x', 'purpose' => 'narrative', 'tokens_in' => 10, 'tokens_out' => 5, 'cost_usd' => 0.5, 'ok' => true, 'created_at' => now()]);
    $this->actingAs($this->admin)->get(route('admin.ai.usage'))->assertOk()->assertSee('By provider')->assertSee('Cost per agent')->assertSee('Daily use')->assertSee('Most expensive tasks')->assertSee('Emergency stop');
    $this->get(route('admin.ai.providers'))->assertOk()->assertSee('The 16 agents')->assertSee('Allow fallback');
    $this->get(route('admin.ai.agents.show', agentNamed()))->assertOk()->assertSee('Primary provider')->assertSee('Secondary provider');
});
