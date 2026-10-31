{{-- Standalone error page: no database, session or auth calls, so it renders even when those fail. --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>@yield('title') | Meezan Property</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    @vite(['resources/css/app.css'])
</head>
<body class="grid min-h-screen place-items-center bg-page px-4 py-10 text-ink">
    <main class="w-full max-w-lg text-center">
        <a href="{{ url('/') }}" aria-label="Meezan Property home"><img src="{{ asset('images/ref/logo.png') }}" alt="Meezan Property" width="322" height="116" class="mx-auto h-14 w-auto"></a>
        <p class="mt-10 font-serif text-6xl font-bold text-brand-600">@yield('code')</p>
        <h1 class="mt-3 text-2xl font-bold">@yield('title')</h1>
        <p class="mt-3 text-[15px] text-muted">@yield('message')</p>
        <div class="mt-8 flex flex-wrap justify-center gap-3">
            <a href="{{ url('/') }}" class="btn btn-primary">Go to homepage</a>
            <a href="{{ url('/properties') }}" class="btn btn-secondary">Search properties</a>
        </div>
    </main>
</body>
</html>
