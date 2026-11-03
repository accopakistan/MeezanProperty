@props(['title', 'heading', 'subheading' => null, 'wide' => false])
<!DOCTYPE html>
<html lang="en">
<head>
    <x-layouts.head :title="$title" noindex />
</head>
<body class="min-h-screen bg-white">
    <div class="grid min-h-screen lg:grid-cols-[minmax(0,1fr)_minmax(0,520px)] xl:grid-cols-[minmax(0,1fr)_minmax(0,600px)]">
        <div class="flex flex-col px-4 py-6 sm:px-10 lg:px-16">
            <a href="{{ route('home') }}" aria-label="Meezan Property home"><x-logo class="h-16 w-auto" /></a>

            <div @class(['mx-auto flex w-full flex-1 flex-col justify-center py-10', 'max-w-2xl' => $wide, 'max-w-md' => ! $wide])>
                <h1 class="text-[28px] font-extrabold sm:text-3xl">{{ $heading }}</h1>
                @if ($subheading)
                    <p class="mt-2 text-[15px] text-muted">{{ $subheading }}</p>
                @endif
                <x-flash class="mt-6" />
                <div class="mt-8">{{ $slot }}</div>
            </div>

            <p class="text-xs text-muted">&copy; {{ now()->year }} Meezan Property · <a href="{{ route('pages.terms') }}" class="hover:text-brand-700">Terms</a> · <a href="{{ route('pages.privacy') }}" class="hover:text-brand-700">Privacy</a></p>
        </div>

        <aside class="relative hidden overflow-hidden bg-brand-800 lg:block">
            <x-site.skyline class="absolute inset-x-0 bottom-0 w-full text-brand-700" />
            <div class="absolute -top-24 -right-24 size-96 rounded-full bg-brand-500/30 blur-3xl"></div>
            <div class="relative flex h-full flex-col justify-center p-12 xl:p-16">
                {{ $aside ?? '' }}
                @if (! isset($aside))
                    <p class="eyebrow text-gold-300">Meezan Property</p>
                    <p class="mt-4 text-3xl leading-tight font-extrabold text-white">Property across Pakistan, with balance and trust.</p>
                    <ul class="mt-8 space-y-4 text-[15px] text-white/80">
                        <li class="flex gap-3"><x-icon name="shield" class="mt-0.5 text-gold-300" /> Every listing is reviewed before it goes live.</li>
                        <li class="flex gap-3"><x-icon name="badge-check" class="mt-0.5 text-gold-300" /> Dealer verification is shown only after our checks.</li>
                        <li class="flex gap-3"><x-icon name="lock" class="mt-0.5 text-gold-300" /> Your contact details are shared only when you choose.</li>
                    </ul>
                @endif
            </div>
        </aside>
    </div>
</body>
</html>
