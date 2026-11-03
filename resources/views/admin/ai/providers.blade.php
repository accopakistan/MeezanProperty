<x-layouts.dashboard title="AI Providers" section="admin" heading="AI Providers" subheading="The language models behind the agents, website AI and WhatsApp AI. API access is billed by each provider separately: a ChatGPT or Claude subscription does not include API credit.">
    @include('admin.ai.partials.nav')

    @if ($controls['emergency_stop'])
        <div class="mb-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="status"><strong>Emergency stop is on.</strong> No paid AI requests are being made. The portal and data-only reports keep working. Turn it off in <a href="{{ route('admin.ai.usage') }}" class="link">Usage &amp; Cost</a>.</div>
    @endif

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
        @foreach ($providers as $key => $p)
            <section class="card card-body min-w-0">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 class="text-base font-bold">{{ $p['config']->label }} <span class="text-sm font-medium text-muted">({{ $key === 'openai' ? 'primary' : 'secondary' }})</span></h2>
                    @include('admin.ai.partials.status', ['status' => $p['status'], 'label' => $p['statusLabel']])
                </div>

                <dl class="mt-3 grid grid-cols-[auto_1fr] gap-x-4 gap-y-1.5 text-[13px]">
                    <dt class="text-muted">API key configured</dt>
                    <dd class="font-semibold">{{ $p['config']->hasKey() ? 'Yes' : 'No' }}@if ($p['config']->keySource === 'saved') <span class="font-normal text-muted">· saved here, encrypted</span>@elseif ($p['config']->keySource === 'env') <span class="font-normal text-muted">· from .env</span>@endif</dd>
                    <dt class="text-muted">Default model</dt><dd class="break-all">{{ $p['config']->defaultModel ?? 'Not chosen' }}</dd>
                    <dt class="text-muted">Base URL</dt><dd class="break-all">{{ $p['config']->baseUrl }}</dd>
                    <dt class="text-muted">Last successful connection</dt><dd>{{ $p['row']?->last_success_at?->localTime()->format('j M Y, H:i') ?? 'Never' }}</dd>
                    <dt class="text-muted">Last error</dt><dd class="break-words">{{ $p['row']?->last_error ?? 'None' }}@if ($p['row']?->last_error_at) <span class="text-muted">({{ $p['row']->last_error_at->localTime()->format('j M, H:i') }})</span>@endif</dd>
                </dl>

                @if ($p['config']->keyUnreadable)
                    <p class="mt-3 rounded-lg bg-red-50 px-3 py-2 text-[13px] text-red-800">The saved key can no longer be read, probably because APP_KEY changed. Enter it again below.</p>
                @elseif (! $p['config']->hasKey())
                    <p class="mt-3 rounded-lg bg-amber-50 px-3 py-2 text-[13px] text-amber-900">API key not configured. Add your API key to activate this provider.</p>
                @endif
                @if ($p['warning'])
                    <p class="mt-3 rounded-lg bg-amber-50 px-3 py-2 text-[13px] text-amber-900">{{ $p['warning'] }}</p>
                @endif

                <form method="POST" action="{{ route('admin.ai.providers.test', $key) }}" class="mt-3">
                    @csrf
                    <button class="btn btn-outline btn-sm" @disabled(! $p['config']->ready())>Test connection</button>
                    <span class="ml-1 text-[12px] text-muted">{{ $p['config']->ready() ? 'Lists your models, then sends one very small paid request if a model is chosen.' : 'Available once this provider is enabled and has a key.' }}</span>
                </form>

                <form method="POST" action="{{ route('admin.ai.providers.update', $key) }}" class="mt-4 space-y-3 border-t border-line pt-4" autocomplete="off">
                    @csrf @method('PATCH')
                    <label class="flex items-center gap-2 text-sm"><input type="hidden" name="enabled" value="0"><input type="checkbox" name="enabled" value="1" @checked($p['config']->enabled)> Enabled</label>
                    <div>
                        <label for="key-{{ $key }}" class="label">{{ $p['config']->hasKey() ? 'Replace API key' : 'API key' }}</label>
                        <input id="key-{{ $key }}" type="password" name="api_key" class="input @error('api_key') input-error @enderror" autocomplete="new-password" spellcheck="false" placeholder="{{ $p['config']->hasKey() ? 'Leave empty to keep the current key' : 'Paste your key' }}">
                        <p class="mt-1 text-[12px] text-muted">Saved encrypted. It is never shown again, and leaving this empty never removes a key. A key saved here takes priority over .env.</p>
                        @error('api_key')<p class="mt-1 text-[13px] text-red-700">{{ $message }}</p>@enderror
                    </div>
                    @if ($p['row']?->hasSavedKey())
                        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="remove_key" value="1"> Remove the saved key{{ filled(config("services.{$key}.key")) ? ' and use the one in .env' : '' }}</label>
                    @endif
                    <div>
                        <label for="model-{{ $key }}" class="label">Default model</label>
                        <input id="model-{{ $key }}" name="default_model" class="input @error('default_model') input-error @enderror" value="{{ old('default_model', $p['row']?->default_model) }}" list="models-{{ $key }}" placeholder="{{ config("services.{$key}.model") ?: 'Test the connection, then choose from your list' }}">
                        <datalist id="models-{{ $key }}">@foreach ($p['row']?->available_models ?? [] as $m)<option value="{{ $m }}"></option>@endforeach</datalist>
                        @error('default_model')<p class="mt-1 text-[13px] text-red-700">{{ $message }}</p>@enderror
                        <p class="mt-1 text-[12px] text-muted">{{ $p['row']?->available_models ? count($p['row']->available_models).' models available to this account.' : 'Your account\'s model list appears here after a successful test.' }}</p>
                    </div>
                    <x-field name="base_url" label="Base URL (optional)" :value="$p['row']?->base_url" :placeholder="config('services.'.$key.'.base_url')" hint="The API key is sent here. Must be HTTPS." />
                    <x-field name="monthly_budget_usd" type="number" step="0.01" min="0" label="Monthly budget (USD, optional)" :value="$p['row']?->monthly_budget_usd" />
                    <button class="btn btn-primary btn-sm">Save {{ $p['config']->label }}</button>
                </form>
            </section>
        @endforeach
    </div>

    <form method="POST" action="{{ route('admin.ai.controls') }}" class="card card-body mt-6">
        @csrf @method('PATCH')
        <h2 class="text-base font-bold">Routing and fallback</h2>
        <label class="mt-3 flex items-start gap-2 text-sm"><input type="hidden" name="fallback_allowed" value="0"><input type="checkbox" name="fallback_allowed" value="1" class="mt-0.5" @checked($controls['fallback_allowed'])>
            <span><span class="font-semibold">Allow fallback to the secondary provider</span><br><span class="text-[13px] text-muted">When a primary provider has a temporary failure or is unavailable, the same request and its data go to the second company, at its prices. It is recorded on the task and in Usage &amp; Cost. Never used for a rejected key, no credit, an invalid request, a declined request or a used-up budget.</span></span></label>
        <div class="mt-4 grid gap-4 md:grid-cols-3">
            <x-field name="default_provider" type="select" label="Default provider (no agent assigned)" :options="$labels" :value="$default" />
        </div>
        <h3 class="mt-5 text-sm font-bold">Website AI and WhatsApp AI</h3>
        <p class="mt-1 text-[13px] text-muted">Leave on "Use the default or agent setting" unless a channel should use something else. WhatsApp replies are only delivered once WhatsApp itself is set up.</p>
        <div class="mt-3 grid gap-4 md:grid-cols-2">
            @foreach (['website' => 'Website AI', 'whatsapp' => 'WhatsApp AI'] as $channel => $title)
                <fieldset class="rounded-lg border border-line p-3">
                    <legend class="px-1 text-sm font-semibold">{{ $title }}</legend>
                    <div class="space-y-2">
                        <x-field :name="'channels['.$channel.'][provider]'" type="select" label="Provider" :options="['' => 'Use the default or agent setting'] + $labels" :value="$controls['channels'][$channel]['provider'] ?? ''" />
                        <x-field :name="'channels['.$channel.'][model]'" label="Model (optional)" :value="$controls['channels'][$channel]['model'] ?? ''" />
                        <label class="flex items-center gap-2 text-sm"><input type="hidden" name="channels[{{ $channel }}][fallback]" value="0"><input type="checkbox" name="channels[{{ $channel }}][fallback]" value="1" @checked($controls['channels'][$channel]['fallback'] ?? true)> Allow fallback on this channel</label>
                    </div>
                </fieldset>
            @endforeach
        </div>
        <button class="btn btn-primary btn-sm mt-4">Save routing</button>
    </form>

    <section class="card card-body mt-6 min-w-0">
        <h2 class="text-base font-bold">The 16 agents</h2>
        <p class="mt-1 text-[13px] text-muted">Change an agent's provider, model and limits on its own page. Each agent's model is its own choice, then its workload tier's, then the provider default.</p>
        <div class="relative mt-2 overflow-x-auto">
            <table class="table table-compact min-w-[860px]">
                <thead><tr><th scope="col">Agent</th><th scope="col">Primary</th><th scope="col">Secondary</th><th scope="col">Fallback</th><th scope="col">Right now</th></tr></thead>
                <tbody>
                    @foreach ($agents as $a)
                        @php $route = $routes[$a->slug]; @endphp
                        <tr>
                            <td><a href="{{ route('admin.ai.agents.show', $a) }}" class="link">{{ $a->name }}</a></td>
                            <td class="text-[13px]">{{ $labels[$route['primary']['provider']] }}<br><span class="break-all text-muted">{{ $route['primary']['model'] ?? 'No model chosen' }}</span></td>
                            <td class="text-[13px]">@if ($route['secondary']){{ $labels[$route['secondary']['provider']] }}<br><span class="break-all text-muted">{{ $route['secondary']['model'] ?? 'No model chosen' }}</span>@else None @endif</td>
                            <td class="text-[13px]">{{ $route['fallback'] ? 'On' : ($a->fallback_enabled && $route['secondary'] ? 'Off (global switch)' : 'Off') }}</td>
                            <td class="max-w-64 text-[13px]">{{ $readiness[$a->slug]['model'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
</x-layouts.dashboard>
