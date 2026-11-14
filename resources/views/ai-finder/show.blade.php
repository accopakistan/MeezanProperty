@php
    use App\Support\Lookup;
    // Layout: public/Refrence/Meezan Property AI Search Dashboard.png (+ AI Platform Mockup 7A/7C)
    $whatsapp = config('meezan.contact.whatsapp');
    $user = auth()->user();
    // [icon, tone, short title, detail, the full prompt sent to the assistant, title language]
    $examples = [
        ['home', 'text-red-500 bg-red-50', '10 Marla house', 'DHA Lahore, around 4 crore', '10 marla house in DHA Lahore around 4 crore', null],
        ['key', 'text-emerald-600 bg-emerald-50', '2 bed flat to rent', 'Clifton Karachi, under 1.5 lakh', '2 bed flat on rent in Clifton Karachi under 1.5 lakh', null],
        ['plot', 'text-sky-600 bg-sky-50', '1 Kanal plot', 'Islamabad, for investment', '1 kanal plot in Islamabad for investment', null],
        ['message', 'text-brand-600 bg-brand-50', '5 marla ka ghar', 'Peshawar, asked in Roman Urdu', 'Mujhe Peshawar mein 5 marla ka ghar chahiye', null],
        ['message', 'text-gold-600 bg-gold-50', 'دس مرلہ مکان، ملتان', 'Multan, asked in Urdu', 'ملتان میں دس مرلہ مکان چاہیے', 'ur'],
    ];
    $signedInBuyer = $user?->hasRole('buyer');
@endphp
<x-layouts.app title="AI Property Finder" description="Describe the property you need in Lahore, Karachi, Islamabad or another launch city and get the best-fitting reviewed listings, with a clear explanation of what each one meets.">
    <div data-ai-finder>
        {{-- Hero --}}
        <section class="relative isolate overflow-hidden bg-brand-900">
            <img src="{{ asset('images/ref/hero-home.jpg') }}" alt="" class="absolute inset-0 -z-20 size-full object-cover opacity-45">
            <div class="absolute inset-0 -z-10 bg-gradient-to-r from-brand-900 via-brand-900/80 to-brand-900/60"></div>
            <img src="{{ asset('images/ref/ai-woman.jpg') }}" alt="" class="absolute bottom-0 left-0 hidden h-[94%] [mask-image:linear-gradient(to_right,black_70%,transparent)] xl:block">

            <div class="mx-auto grid max-w-[1440px] gap-8 px-4 pt-9 pb-14 sm:px-6 lg:px-10 xl:grid-cols-[minmax(0,1fr)_270px] xl:pl-[300px]">
                <div class="relative">
                    <span class="inline-flex items-center gap-1.5 rounded-md bg-brand-600/80 px-3 py-1 text-[13px] font-medium text-white"><x-icon name="sparkles" class="size-4 text-gold-300" /> AI Powered</span>
                    <h1 class="mt-4 text-4xl leading-[1.08] text-white sm:text-5xl">Find Your Dream Property<br><span class="text-gold-500">with AI Assistant</span></h1>
                    <p class="mt-4 max-w-xl text-[17px] leading-7 text-white/90">Just tell us what you’re looking for. Our AI will find the best published listings for you in Lahore, Karachi, Islamabad, Multan, Faisalabad, Quetta and Peshawar.</p>

                    <img src="{{ asset('images/ref/ai-robot.jpg') }}" alt="" class="absolute top-0 right-0 hidden w-36 rounded-full [mask-image:radial-gradient(circle,black_55%,transparent_72%)] 2xl:block">
                    <div class="absolute top-2 right-40 hidden space-y-2 min-[1760px]:block" aria-hidden="true">
                        @foreach (['I understand your needs', 'I find best matching properties', 'I connect you with dealers'] as $i => $bubble)
                            <p class="flex items-center gap-2 rounded-lg bg-white px-3 py-2 text-xs font-medium text-ink shadow {{ ['', 'translate-x-6', 'translate-x-2'][$i] }}"><x-icon name="sparkles" class="size-3.5 text-gold-500" /> {{ $bubble }}</p>
                        @endforeach
                    </div>

                    <div class="mt-6 inline-flex flex-wrap gap-1 rounded-lg border border-white/15 bg-brand-900/60 p-1 backdrop-blur">
                        @foreach ([['home', 'Houses', 'I am looking for a house in Lahore'], ['plot', 'Plots', 'I am looking for a plot in Islamabad'], ['building', 'Apartments', 'I am looking for an apartment in Lahore'], ['store', 'Commercial', 'I am looking for a commercial shop or office in Lahore']] as [$icon, $label, $prompt])
                            <button type="button" class="flex items-center gap-1.5 rounded-md px-3 py-1.5 text-[13px] font-medium text-white hover:bg-white/10" data-prompt="{{ $prompt }}"><x-icon :name="$icon" class="size-4" /> {{ $label }}</button>
                        @endforeach
                    </div>

                    <form method="GET" action="{{ route('ai-finder') }}" class="mt-3 flex max-w-3xl items-center gap-2 rounded-full bg-white py-2 pr-2 pl-5 shadow-lift" data-prompt-form>
                        <x-icon name="sparkles" class="size-5 text-brand-600" />
                        <label for="ai-hero-input" class="sr-only">Describe your property requirement</label>
                        <input id="ai-hero-input" name="message" value="{{ request('message') }}" maxlength="500" class="min-w-0 flex-1 border-0 bg-transparent py-2 text-[15px] focus:ring-0 focus:outline-none" placeholder="Describe your property requirement…">
                        <button class="grid size-11 shrink-0 place-items-center rounded-full bg-brand-600 text-white hover:bg-brand-700" aria-label="Find matches"><x-icon name="send" class="size-5" /></button>
                    </form>
                    <p class="mt-2 pl-5 text-[13px] text-white/75">e.g. 10 Marla house in DHA Lahore under 6 crore with 5 bedrooms</p>
                </div>

                <aside class="hidden self-start rounded-xl border border-white/15 bg-brand-900/70 p-2 backdrop-blur xl:block" aria-label="About the AI finder">
                    @foreach (array_filter([
                        ['home', number_format($publishedCount), 'Published Listings'],
                        ['users', number_format($verifiedDealers), 'Verified Dealers'],
                        ['target', 'AI Matched', 'Results in seconds'],
                        ['clock', 'Available 24/7', 'On the website'],
                    ]) as [$icon, $title, $body])
                        <div class="flex items-center gap-3 border-b border-white/10 px-3 py-3.5 last:border-0">
                            <span class="grid size-11 shrink-0 place-items-center rounded-full bg-gold-500/15 text-gold-500"><x-icon :name="$icon" class="size-6" /></span>
                            <span><span class="block font-serif text-lg font-bold text-white">{{ $title }}</span><span class="text-[13px] text-white/75">{{ $body }}</span></span>
                        </div>
                    @endforeach
                </aside>
            </div>
        </section>

        <div class="mx-auto grid max-w-[1440px] gap-5 px-4 sm:px-6 lg:px-10 xl:grid-cols-[minmax(0,1fr)_380px]">
            <div class="min-w-0">
                {{-- Examples --}}
                <section class="card relative z-10 p-4 sm:p-5 xl:-mt-6" aria-labelledby="examples-title">
                    <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                        <h2 id="examples-title" class="flex items-center gap-2 font-serif text-lg font-bold"><x-icon name="sparkles" class="size-5 text-gold-500" /> Try These Examples</h2>
                        <p class="text-[13px] text-muted">Tap one to ask the assistant. English, Roman Urdu and Urdu all work.</p>
                    </div>
                    <div class="mt-3.5 grid gap-2.5 md:grid-cols-5">
                        @foreach ($examples as [$icon, $tone, $title, $detail, $prompt, $lang])
                            <button type="button" data-prompt="{{ $prompt }}" aria-label="Ask: {{ $prompt }}" title="{{ $prompt }}"
                                    class="group flex items-center gap-3 rounded-xl border border-line bg-white p-3 text-left transition hover:-translate-y-0.5 hover:border-brand-300 hover:shadow-card focus-visible:outline-2 focus-visible:outline-brand-500 md:flex-col md:items-start md:gap-2.5">
                                <span class="grid size-10 shrink-0 place-items-center rounded-full {{ $tone }}"><x-icon :name="$icon" class="size-5" /></span>
                                <span class="min-w-0">
                                    <span class="block text-left text-[13.5px] leading-snug font-semibold text-ink group-hover:text-brand-700" @if ($lang) lang="{{ $lang }}" dir="rtl" @endif>{{ $title }}</span>
                                    <span class="mt-0.5 block text-[12px] leading-snug text-muted">{{ $detail }}</span>
                                </span>
                            </button>
                        @endforeach
                    </div>
                </section>

                {{-- Results --}}
                <section class="mt-6" aria-labelledby="matched-title">
                    <h2 id="matched-title" class="flex items-center gap-2 font-serif text-2xl font-bold"><x-icon name="sparkles" class="size-6 text-brand-600" /> AI Matched Properties</h2>
                    <div class="mt-4" data-understood>
                        @if ($result)
                            @include('ai-finder.partials.understood', ['criteria' => $result->criteria])
                        @else
                            @include('ai-finder.partials.requirement-summary', ['summary' => $summary])
                        @endif
                    </div>
                    <div data-results aria-live="polite">
                        @include('ai-finder.partials.results', ['result' => $result])
                    </div>
                </section>

                {{-- How it works --}}
                <section class="card mt-8 p-6" aria-labelledby="how-title">
                    <h2 id="how-title" class="flex items-center gap-2 font-serif text-2xl font-bold"><x-icon name="sparkles" class="size-6 text-gold-500" /> How Meezan AI Works</h2>
                    <ol class="mt-5 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                        @foreach ([
                            ['search', 'Tell Us Your Needs', 'Describe your ideal property in simple language.'],
                            ['settings', 'AI Finds Best Matches', 'We search every published listing in seconds.'],
                            ['user', 'Get Reviewed Options', 'Only listings that passed our review are shown.'],
                            ['message', 'Connect & Visit', 'Contact the dealer and arrange a visit.'],
                        ] as $i => [$icon, $title, $body])
                            <li class="flex gap-3">
                                <span class="grid size-14 shrink-0 place-items-center rounded-full bg-brand-50 text-brand-600"><x-icon :name="$icon" class="size-6" /></span>
                                <span><span class="block font-serif font-bold text-brand-700">{{ $i + 1 }}. {{ $title }}</span><span class="mt-0.5 block text-[13px] text-muted">{{ $body }}</span></span>
                            </li>
                        @endforeach
                    </ol>
                </section>
            </div>

            {{-- Chat panel --}}
            <aside class="order-first -mt-6 space-y-5 xl:order-none">
                <div class="card relative z-10 flex flex-col overflow-hidden xl:sticky xl:top-[104px] xl:max-h-[calc(100vh-120px)]">
                    <div class="flex items-center gap-3 border-b border-line px-4 py-3">
                        <span class="grid size-10 place-items-center rounded-full bg-brand-600 text-white"><x-icon name="bot" class="size-5" /></span>
                        <div class="min-w-0 flex-1">
                            <p class="font-semibold text-brand-700">Meezan AI Assistant <span class="ml-1 inline-flex items-center gap-1 text-xs font-medium text-emerald-600"><span class="size-2 rounded-full bg-emerald-500"></span> Online</span></p>
                            <p class="text-xs text-muted">Your personal property assistant</p>
                        </div>
                    </div>

                    <span data-assistant-avatar hidden class="grid size-8 shrink-0 place-items-center rounded-full bg-brand-600 text-white"><x-icon name="bot" class="size-4" /></span>

                    <div class="max-h-[60vh] min-h-72 flex-1 space-y-3 overflow-y-auto bg-page/60 p-4 xl:max-h-none" data-thread aria-live="polite">
                        <div class="flex gap-3">
                            <span class="grid size-8 shrink-0 place-items-center rounded-full bg-brand-600 text-white"><x-icon name="bot" class="size-4" /></span>
                            <div class="max-w-[85%] rounded-2xl rounded-tl-md border border-line bg-white px-4 py-3 text-sm">
                                Assalamualaikum! I’m Meezan AI. Tell me what you need in English, Urdu or Roman Urdu — I’ll remember it as we talk.
                            </div>
                        </div>
                        {{-- Earlier messages in this conversation (last 50) --}}
                        @foreach ($history as $m)
                            @if ($m->speaker() === 'user')
                                <div class="flex justify-end"><div class="max-w-[85%] rounded-2xl rounded-br-md bg-brand-600 px-4 py-3 text-sm whitespace-pre-line text-white" dir="auto">{{ $m->body }}</div></div>
                            @else
                                <div class="flex gap-3">
                                    <span class="grid size-8 shrink-0 place-items-center rounded-full bg-brand-600 text-white"><x-icon name="bot" class="size-4" /></span>
                                    <div class="max-w-[85%] rounded-2xl rounded-bl-md border border-line bg-white px-4 py-3 text-sm whitespace-pre-line">{{ \Illuminate\Support\Str::limit($m->body, 600) }}</div>
                                </div>
                            @endif
                        @endforeach
                    </div>

                    {{-- Quick replies and next steps --}}
                    <div class="border-t border-line bg-white px-3 pt-3" data-assistant-tools
                         data-reset-url="{{ route('ai-finder.reset') }}" data-save-url="{{ route('ai-finder.save') }}" data-contact-url="{{ route('ai-finder.contact') }}"
                         data-login-url="{{ route('login') }}" data-signed-in="{{ $signedInBuyer ? '1' : '0' }}">
                        <div class="flex flex-wrap gap-1.5" data-choices></div>
                        <div class="mt-2 flex flex-wrap gap-2" data-actions>
                            <button type="button" class="btn btn-secondary btn-sm" data-ai-action="save" @if (! $substantial) hidden @endif><x-icon name="heart" class="size-4" /> Save requirement</button>
                            <button type="button" class="btn btn-gold btn-sm" data-ai-action="contact" @if (! $substantial) hidden @endif><x-icon name="users" class="size-4" /> Contact dealers</button>
                            <button type="button" class="btn btn-ghost btn-sm" data-ai-action="reset" @if (! $conversation) hidden @endif><x-icon name="refresh" class="size-4" /> New search</button>
                        </div>
                        <div class="mt-2 rounded-lg border border-gold-200 bg-gold-50 p-3 text-[13px]" data-consent hidden>
                            <label class="flex items-start gap-2">
                                <input type="checkbox" class="checkbox mt-0.5" data-consent-box>
                                <span>Share my requirement, name, email and phone with up to {{ (int) config('meezan.leads.max_dealers_per_requirement', 3) }} verified dealers who cover this city and area.</span>
                            </label>
                            <div class="mt-2 flex gap-2">
                                <button type="button" class="btn btn-primary btn-sm" data-consent-confirm>Share with dealers</button>
                                <button type="button" class="btn btn-ghost btn-sm" data-consent-cancel>Cancel</button>
                            </div>
                        </div>
                    </div>

                    <form method="GET" action="{{ route('ai-finder') }}" class="bg-white p-3" data-composer data-post-url="{{ route('ai-finder.match') }}">
                        <div class="flex items-end gap-2">
                            <label for="ai-message" class="sr-only">Type your message</label>
                            <textarea id="ai-message" name="message" rows="1" maxlength="500" dir="auto" class="input min-h-11 resize-none" placeholder="English, اردو or Roman Urdu…"></textarea>
                            <button type="submit" class="grid size-11 shrink-0 place-items-center rounded-md bg-brand-600 text-white hover:bg-brand-700" aria-label="Send"><x-icon name="send" class="size-5" /></button>
                        </div>

                        <details class="group mt-2 rounded-md border border-line" @if (request()->hasAny(['purpose', 'type', 'areas', 'max_price'])) open @endif>
                            <summary class="flex cursor-pointer list-none items-center justify-between px-3 py-2 text-[13px] font-semibold">
                                <span class="flex items-center gap-2"><x-icon name="sliders" class="size-4 text-brand-600" /> Refine preferences</span>
                                <x-icon name="chevron-down" class="size-4 transition group-open:rotate-180" />
                            </summary>
                            <div class="grid gap-3 border-t border-line p-3 sm:grid-cols-2 xl:grid-cols-1">
                                <x-field name="purpose" type="select" label="Looking to" :value="request('purpose')" placeholder="Buy or rent" :options="['sale' => 'Buy', 'rent' => 'Rent']" />
                                <x-field name="type" type="select" label="Property type" :value="request('type')">
                                    <option value="">Any type</option>
                                    @foreach (Lookup::typesByGroup() as $label => $types)
                                        <optgroup label="{{ $label }}">
                                            @foreach ($types as $t)
                                                <option value="{{ $t->value }}" @selected(request('type') === $t->value)>{{ $t->label() }}</option>
                                            @endforeach
                                        </optgroup>
                                    @endforeach
                                </x-field>
                                <x-field name="max_price" type="number" label="Max budget (PKR)" :value="request('max_price')" min="0" step="100000" placeholder="e.g. 30000000" />
                                <x-field name="bedrooms" type="select" label="Bedrooms" :value="request('bedrooms')" placeholder="Any" :options="[1 => '1+', 2 => '2+', 3 => '3+', 4 => '4+', 5 => '5+', 6 => '6+']" />
                                <div class="grid grid-cols-[1fr_auto] gap-2">
                                    <x-field name="min_size" type="number" label="Minimum size" :value="request('min_size')" min="0" step="0.5" />
                                    <x-field name="size_unit" type="select" label="Unit" :value="request('size_unit', 'marla')" :options="collect(\App\Enums\AreaUnit::cases())->mapWithKeys(fn ($u) => [$u->value => $u->label()])->all()" />
                                </div>
                                <div>
                                    <label for="ai-areas" class="label">Preferred areas</label>
                                    <select id="ai-areas" name="areas[]" multiple size="4" class="input">
                                        @foreach (Lookup::areas() as $area)
                                            <option value="{{ $area->id }}" @selected(in_array($area->id, (array) request('areas', [])))>{{ $area->name }}</option>
                                        @endforeach
                                    </select>
                                    <p class="field-hint">Hold Ctrl (or ⌘) to choose several.</p>
                                </div>
                            </div>
                        </details>
                    </form>
                </div>

                <div class="rounded-xl bg-brand-800 p-5 text-white">
                    <div class="flex items-center gap-4">
                        <span class="grid size-14 shrink-0 place-items-center rounded-full bg-[#16a34a]"><x-icon name="whatsapp" class="size-8" /></span>
                        <div>
                            <p class="font-serif text-lg font-bold">Chat on WhatsApp</p>
                            <p class="text-[13px] text-white/80">{{ $whatsapp ? 'Message our team about your requirement. The automated WhatsApp assistant is not live yet.' : 'Our WhatsApp assistant is being set up and will be available soon.' }}</p>
                        </div>
                    </div>
                    @if ($whatsapp)
                        <a href="{{ \App\Support\Contact::whatsappUrl('Assalam o Alaikum, I am looking for a property.') }}" target="_blank" rel="noopener" class="btn btn-gold mt-4 w-full">Start WhatsApp Chat <x-icon name="arrow-right" class="size-4" /></a>
                    @else
                        <a href="{{ $user?->hasRole('buyer') ? route('account.requirements.create') : route('register') }}" class="btn btn-gold mt-4 w-full">Save My Requirement Instead <x-icon name="arrow-right" class="size-4" /></a>
                    @endif
                </div>
            </aside>
        </div>
    </div>
</x-layouts.app>
