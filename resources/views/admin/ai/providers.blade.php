<x-layouts.dashboard title="Gemini Settings" section="admin" heading="Gemini Settings" subheading="Google Gemini powers the Central AI Brain, all 16 agents, website AI and WhatsApp AI. Requests are sent from the server over HTTPS; the API key never reaches a browser. A Gemini app subscription does not include API access: the API key comes from Google AI Studio.">
    @include('admin.ai.partials.nav')

    @if ($controls['emergency_stop'])
        <div class="mb-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="status"><strong>Emergency AI stop is on.</strong> No AI requests are being made. The portal and data-only reports keep working. Turn it off in <a href="{{ route('admin.ai.usage') }}" class="link">Usage &amp; Cost</a>.</div>
    @endif

    @foreach ($providers as $key => $p)
        @php $config = $p['config']; @endphp
        <section class="card card-body min-w-0">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="text-base font-bold">{{ $config->label }}</h2>
                @include('admin.ai.partials.status', ['status' => $p['status'], 'label' => $p['statusLabel']])
            </div>

            @if ($config->keyUnreadable)
                <p class="mt-3 rounded-lg bg-red-50 px-3 py-2 text-[13px] text-red-800">The saved key can no longer be read, probably because APP_KEY changed. Enter it again below.</p>
            @elseif (! $config->hasKey())
                <p class="mt-3 rounded-lg bg-amber-50 px-3 py-2 text-[14px] font-semibold text-amber-900">Gemini API key not configured. Add your API key to activate AI services.</p>
            @endif
            @if ($p['quota'])
                <p class="mt-3 rounded-lg bg-red-50 px-3 py-2 text-[13px] text-red-800" role="status"><strong>AI requests paused.</strong> {{ $p['quota']['reason'] }}
                    {{ $p['quota']['until'] ? 'They resume automatically at '.\Carbon\CarbonImmutable::parse($p['quota']['until'])->setTimezone(config('meezan.timezone'))->format('j M Y, H:i').'.' : 'Test the connection once the problem is fixed to resume.' }}
                    No paid provider is used instead, and no answer is made up: agents keep working from data only.</p>
            @endif
            @if ($p['warning'])
                <p class="mt-3 rounded-lg bg-amber-50 px-3 py-2 text-[13px] text-amber-900">{{ $p['warning'] }}</p>
            @endif

            <dl class="mt-4 grid grid-cols-1 gap-x-6 gap-y-1.5 text-[13px] sm:grid-cols-[auto_1fr]">
                <dt class="text-muted">Provider</dt><dd class="font-semibold">Google Gemini API</dd>
                <dt class="text-muted">API key configured</dt>
                <dd class="font-semibold">{{ $config->hasKey() ? 'Yes' : 'No' }}@if ($config->keySource === 'saved') <span class="font-normal text-muted">· saved here, encrypted</span>@elseif ($config->keySource === 'env') <span class="font-normal text-muted">· from GEMINI_API_KEY in the environment</span>@endif</dd>
                <dt class="text-muted">Active model</dt><dd class="break-all">{{ $config->defaultModel ?? 'Not chosen' }}@if (! $p['row']?->default_model && $config->defaultModel) <span class="text-muted">(default from configuration)</span>@endif</dd>
                <dt class="text-muted">Model availability</dt>
                <dd>{{ match ($p['modelAvailable']) { true => 'Available to this key', false => 'Not in this key\'s model list', default => 'Not checked yet: test the connection' } }}@if ($p['row']?->available_models) <span class="text-muted">· {{ count($p['row']->available_models) }} models can generate content</span>@endif</dd>
                <dt class="text-muted">Base URL</dt><dd class="break-all">{{ $config->baseUrl }}</dd>
                <dt class="text-muted">Timeout · retries</dt><dd>{{ $config->timeout }} s · {{ $config->retries }} (rate limits and outages only, with exponential backoff)</dd>
                <dt class="text-muted">Thinking level</dt><dd>{{ $config->options['thinking_level'] ? ucfirst($config->options['thinking_level']) : 'Model default' }} <span class="text-muted">(GEMINI_THINKING_LEVEL; Gemini 3 and later)</span></dd>
                <dt class="text-muted">Connection status</dt><dd>{{ $p['statusLabel'] }}@if ($p['row']?->last_checked_at) <span class="text-muted">· checked {{ $p['row']->last_checked_at->localTime()->format('j M Y, H:i') }}</span>@endif</dd>
                <dt class="text-muted">Last successful connection</dt><dd>{{ $p['row']?->last_success_at?->localTime()->format('j M Y, H:i') ?? 'Never' }}</dd>
                <dt class="text-muted">Last error</dt><dd class="break-words">{{ $p['row']?->last_error ?? 'None' }}@if ($p['row']?->last_error_at) <span class="text-muted">({{ $p['row']->last_error_at->localTime()->format('j M, H:i') }})</span>@endif</dd>
                <dt class="text-muted">Today</dt><dd>{{ (int) $p['today']->requests }} requests ({{ (int) $p['today']->ok }} successful) · {{ number_format((int) $p['today']->tokens_in) }} tokens in · {{ number_format((int) $p['today']->tokens_out) }} out · <a href="{{ route('admin.ai.usage') }}" class="link">Usage &amp; Cost</a></dd>
                <dt class="text-muted">Price used for estimates</dt>
                <dd>@if ($p['price']) USD {{ $p['price']['input'] }} in / {{ $p['price']['output'] }} out per 1M tokens <span class="text-muted">({{ $p['price']['source'] === 'entered' ? 'entered in Usage & Cost' : 'Google\'s published price, checked '.config('services.gemini.pricing_checked_on') }})</span>@else No price known for this model: enter one in Usage &amp; Cost @endif</dd>
                <dt class="text-muted">Billing tier</dt><dd>{{ config('services.gemini.billing_tier') === 'paid' ? 'Paid (Google bills usage)' : 'Free tier (GEMINI_BILLING_TIER=free): Google does not charge; costs shown are the paid-tier equivalent' }}</dd>
            </dl>

            @if (config('services.gemini.billing_tier') !== 'paid')
                <p class="mt-3 rounded-lg bg-surface px-3 py-2 text-[13px] text-muted">On the free tier Google may use the content of requests to improve its products, and limits are low (requests per minute and per day, reset at midnight Pacific time). Personal data such as phone numbers, e-mail addresses and CNIC numbers is masked before anything is sent. Review Google's terms before enabling sensitive production workflows, and use a paid tier for those.</p>
            @endif

            <form method="POST" action="{{ route('admin.ai.providers.test', $key) }}" class="mt-4">
                @csrf
                <button class="btn btn-outline btn-sm" @disabled(! $config->ready())>Test Gemini connection</button>
                <span class="ml-1 text-[12px] text-muted">{{ $config->ready() ? 'Reads your model list, then sends one very small real request to the active model. Success is shown only after Google answers.' : 'Available once Gemini is enabled and has a key.' }}</span>
            </form>

            <form method="POST" action="{{ route('admin.ai.providers.update', $key) }}" class="mt-4 space-y-3 border-t border-line pt-4" autocomplete="off">
                @csrf @method('PATCH')
                <label class="flex items-center gap-2 text-sm"><input type="hidden" name="enabled" value="0"><input type="checkbox" name="enabled" value="1" @checked($config->enabled)> Enabled</label>
                <div>
                    <label for="key-{{ $key }}" class="label">{{ $config->hasKey() ? 'Replace API key' : 'API key' }}</label>
                    <input id="key-{{ $key }}" type="password" name="api_key" class="input @error('api_key') input-error @enderror" autocomplete="new-password" spellcheck="false" placeholder="{{ $config->hasKey() ? 'Leave empty to keep the current key' : 'Paste your Gemini API key' }}">
                    <p class="mt-1 text-[12px] text-muted">Saved encrypted with APP_KEY. It is never shown again, and leaving this empty never removes a key. A key saved here takes priority over GEMINI_API_KEY.</p>
                    @error('api_key')<p class="mt-1 text-[13px] text-red-700">{{ $message }}</p>@enderror
                </div>
                @if ($p['row']?->hasSavedKey())
                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="remove_key" value="1"> Remove the saved key{{ filled(config("services.{$key}.key")) ? ' and use GEMINI_API_KEY' : '' }}</label>
                @endif
                <div>
                    <label for="model-{{ $key }}" class="label">Active model</label>
                    <input id="model-{{ $key }}" name="default_model" class="input @error('default_model') input-error @enderror" value="{{ old('default_model', $p['row']?->default_model) }}" list="models-{{ $key }}" placeholder="{{ config("services.{$key}.model") }}">
                    <datalist id="models-{{ $key }}">@foreach ($p['row']?->available_models ?? [] as $m)<option value="{{ $m }}"></option>@endforeach</datalist>
                    @error('default_model')<p class="mt-1 text-[13px] text-red-700">{{ $message }}</p>@enderror
                    <p class="mt-1 text-[12px] text-muted">Empty uses {{ config("services.{$key}.model") }} (GEMINI_MODEL). {{ $p['row']?->available_models ? 'Choose from the models your key can use.' : 'Your key\'s model list appears here after a successful test.' }}</p>
                </div>
                <x-field name="base_url" label="Base URL (optional)" :value="$p['row']?->base_url" :placeholder="config('services.'.$key.'.base_url')" hint="The API key is sent here. Must be HTTPS." />
                <x-field name="monthly_budget_usd" type="number" step="0.01" min="0" label="Monthly spending limit (USD, optional)" :value="$p['row']?->monthly_budget_usd" />
                <button class="btn btn-primary btn-sm">Save Gemini settings</button>
            </form>
        </section>
    @endforeach

    <form method="POST" action="{{ route('admin.ai.controls') }}" class="card card-body mt-6">
        @csrf @method('PATCH')
        <h2 class="text-base font-bold">Website AI and WhatsApp AI</h2>
        <p class="mt-1 text-[13px] text-muted">Both channels use the same Central AI Brain. Leave the model empty to use each agent's model; set one only if a channel should use a different Gemini model. WhatsApp replies are delivered only once WhatsApp itself is set up and tested.</p>
        <div class="mt-3 grid gap-4 md:grid-cols-2">
            @foreach (['website' => 'Website AI', 'whatsapp' => 'WhatsApp AI'] as $channel => $title)
                <fieldset class="rounded-lg border border-line p-3">
                    <legend class="px-1 text-sm font-semibold">{{ $title }}</legend>
                    <x-field :name="'channels['.$channel.'][model]'" label="Gemini model (optional)" :value="$controls['channels'][$channel]['model'] ?? ''" placeholder="Agent setting" />
                </fieldset>
            @endforeach
        </div>
        <button class="btn btn-primary btn-sm mt-4">Save channel settings</button>
    </form>

    <section class="card card-body mt-6 min-w-0">
        <h2 class="text-base font-bold">The 16 agents</h2>
        <p class="mt-1 text-[13px] text-muted">Every agent runs through this Gemini connection. Change an agent's model, limits and planning mode on its own page: its own model, then its workload tier's (GEMINI_MODEL_ROUTINE and so on), then the active model.</p>
        <div class="relative mt-2 overflow-x-auto">
            <table class="table table-compact min-w-[760px]">
                <thead><tr><th scope="col">Agent</th><th scope="col">Provider and model</th><th scope="col">Uses the model for</th><th scope="col">Right now</th></tr></thead>
                <tbody>
                    @foreach ($agents as $a)
                        @php $route = $routes[$a->slug]; @endphp
                        <tr>
                            <td><a href="{{ route('admin.ai.agents.show', $a) }}" class="link">{{ $a->name }}</a></td>
                            <td class="text-[13px]">{{ $labels[$route['primary']['provider']] ?? $route['primary']['provider'] }}<br><span class="break-all text-muted">{{ $route['primary']['model'] ?? 'No model chosen' }}</span></td>
                            <td class="text-[13px]">{{ ($a->planning ?? 'workflow') === 'model_tools' ? 'Routing, wording and tool calls' : 'Routing and wording' }}</td>
                            <td class="max-w-64 text-[13px]">{{ $readiness[$a->slug]['model'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
</x-layouts.dashboard>
