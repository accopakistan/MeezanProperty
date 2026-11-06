@php
    // Layout: public/Refrence/Meezan Property Dealer Platform UI Mockup.png — 5A. Dealer Registration
    $type = old('business_type', 'individual');
@endphp
<x-layouts.app title="Dealer registration" description="Join Meezan Property as a property dealer in Lahore, Karachi, Islamabad, Multan, Faisalabad, Quetta and Peshawar. List properties, get verified and receive relevant buyer leads." noindex>
    <section class="relative isolate overflow-hidden bg-brand-900">
        <img src="{{ asset('images/ref/hero-home.jpg') }}" alt="" class="absolute inset-0 -z-20 size-full object-cover opacity-50">
        <div class="absolute inset-0 -z-10 bg-gradient-to-r from-brand-900 via-brand-900/85 to-brand-900/40"></div>

        <div class="container-page grid items-start gap-10 py-10 lg:grid-cols-[minmax(0,1fr)_minmax(0,460px)] lg:py-14">
            <div class="pt-4 text-white lg:pt-10">
                <h1 class="text-4xl leading-[1.08] text-white sm:text-5xl">Join as a<br>Property Dealer</h1>
                <p class="mt-4 max-w-md text-lg text-white/85">Grow your business on a real estate platform built on trust, in seven cities.</p>
                <ul class="mt-9 space-y-6">
                    @foreach ([
                        ['home', 'List Your Properties', 'Reach serious buyers and tenants in the cities you serve'],
                        ['shield', 'Get Verified', 'Build trust with a verified dealer profile'],
                        ['target', 'Receive Qualified Leads', 'Get relevant requirements in your areas'],
                        ['dashboard', 'Manage Your Listings', 'Easy-to-use dashboard for complete control'],
                    ] as [$icon, $title, $body])
                        <li class="flex items-center gap-4">
                            <span class="grid size-14 shrink-0 place-items-center rounded-full border-2 border-gold-500 bg-brand-700/60 text-gold-300"><x-icon :name="$icon" class="size-6" /></span>
                            <span><span class="block text-[17px] font-semibold">{{ $title }}</span><span class="text-sm text-white/75">{{ $body }}</span></span>
                        </li>
                    @endforeach
                </ul>
            </div>

            <div class="rounded-xl bg-white p-6 shadow-lift sm:p-7">
                <h2 class="font-serif text-2xl font-bold">Dealer Registration</h2>

                <form method="POST" action="{{ route('dealer.register.store') }}" class="mt-5 space-y-3.5" data-dealer-form>
                    @csrf
                    <div class="grid grid-cols-2 rounded-md border border-line p-1" role="radiogroup" aria-label="Dealer type">
                        @foreach (['individual' => 'Individual Dealer', 'agency' => 'Agency / Company'] as $value => $label)
                            <label class="cursor-pointer">
                                <input type="radio" name="business_type" value="{{ $value }}" class="peer sr-only" @checked($type === $value)>
                                <span class="block rounded py-2.5 text-center text-sm font-medium text-ink peer-checked:bg-brand-600 peer-checked:text-white peer-focus-visible:ring-2 peer-focus-visible:ring-brand-300">{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>

                    @foreach ([
                        ['name', 'text', 'Full Name', 'Enter your full name', 'user', 'name'],
                        ['email', 'email', 'Email Address', 'Enter your email address', 'mail', 'email'],
                        ['phone', 'tel', 'Mobile Number', 'Enter your mobile number', 'phone', 'tel'],
                        ['agency_name', 'text', $type === 'agency' ? 'Agency / Company Name' : 'Business Name', $type === 'agency' ? 'e.g. Ahmed Estate & Builders' : 'e.g. Imran Real Estate', 'briefcase', 'organization'],
                        ['password', 'password', 'Password', 'Create a password', 'lock', 'new-password'],
                        ['password_confirmation', 'password', 'Confirm Password', 'Repeat your password', 'lock', 'new-password'],
                    ] as [$name, $inputType, $label, $placeholder, $icon, $auto])
                        <div>
                            <div @class(['flex items-center gap-3 rounded-[var(--radius-control)] border bg-white px-3.5 py-1.5 focus-within:border-brand-400 focus-within:ring-3 focus-within:ring-brand-100', 'border-red-400' => $errors->has($name), 'border-line' => ! $errors->has($name)])>
                                <x-icon :name="$icon" class="size-5 text-ink/60" />
                                <div class="min-w-0 flex-1">
                                    <label for="dr-{{ $name }}" class="block text-[11px] font-medium text-ink" @if ($name === 'agency_name') data-agency-label @endif>{{ $label }}</label>
                                    <input id="dr-{{ $name }}" type="{{ $inputType }}" name="{{ $name }}" value="{{ $inputType === 'password' ? '' : old($name) }}" placeholder="{{ $placeholder }}" autocomplete="{{ $auto }}" required
                                           class="w-full border-0 bg-transparent p-0 text-sm placeholder:text-muted/70 focus:ring-0 focus:outline-none" @if ($name === 'agency_name') data-agency-input @endif>
                                </div>
                            </div>
                            @error($name)<p class="field-error">{{ $message }}</p>@enderror
                        </div>
                    @endforeach

                    <div>
                        <label for="dr-city" class="label">Head office city</label>
                        <select id="dr-city" name="city" class="input h-12">
                            @foreach (config('meezan.cities') as $city)
                                <option value="{{ $city }}" @selected(old('city', config('meezan.default_city')) === $city)>{{ $city }}</option>
                            @endforeach
                        </select>
                    </div>

                    <x-coverage-picker :home-city="old('city', config('meezan.default_city'))" :cities="(array) old('cities', [])" :areas="$selectedAreas" :suggestions="$suggestions" />

                    <details class="rounded-md border border-line px-3 py-2 text-sm">
                        <summary class="cursor-pointer font-medium">Office & registration details (optional)</summary>
                        <div class="mt-3 space-y-3">
                            <x-field name="office_address" label="Office address" placeholder="e.g. Office 12, Main Boulevard, Gulberg III" />
                            <div class="grid grid-cols-2 gap-3">
                                <x-field name="registration_number" label="Registration / licence no." />
                                <x-field name="years_in_business" type="number" min="0" max="80" label="Years in business" />
                            </div>
                        </div>
                    </details>

                    <div>
                        @include('auth.partials.terms-consent', ['class' => 'text-[13px] text-ink'])
                        @error('terms')<p class="field-error">{{ $message }}</p>@enderror
                    </div>

                    <button class="btn btn-primary h-12 w-full text-[15px]">Create Dealer Account</button>
                    <p class="text-center text-sm text-ink/80">Already have an account? <a href="{{ route('login') }}" class="link">Login</a></p>
                </form>
            </div>
        </div>
    </section>

    <section class="container-page mt-10" aria-labelledby="steps-title">
        <h2 id="steps-title" class="section-title text-2xl">How dealer verification works</h2>
        <ol class="mt-5 grid gap-4 md:grid-cols-3">
            @foreach ([['Submitted', 'Create your account. You can prepare listings straight away.'], ['Under Review', 'Our team checks your agency details, usually within 1–2 working days.'], ['Verified', 'The verified badge appears on your listings and area leads start.']] as $i => [$title, $body])
                <li class="card flex gap-4 p-5">
                    <span class="grid size-10 shrink-0 place-items-center rounded-full {{ $i === 2 ? 'bg-emerald-500' : 'bg-brand-600' }} font-bold text-white">{{ $i + 1 }}</span>
                    <span><span class="block font-semibold">{{ $title }}</span><span class="text-sm text-muted">{{ $body }}</span></span>
                </li>
            @endforeach
        </ol>
    </section>
</x-layouts.app>
