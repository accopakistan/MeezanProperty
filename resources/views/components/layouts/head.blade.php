@props(['title' => null, 'description' => null, 'canonical' => null, 'image' => null, 'imageAlt' => null, 'noindex' => false, 'robots' => null, 'type' => 'website'])
{{--
    One place for search and social metadata.
    - noindex: private pages (dashboards, sign-in): "noindex, nofollow", no canonical.
    - robots: override for public pages that should not be indexed but whose links
      are useful to follow, e.g. "noindex, follow" for filtered searches.
    - Otherwise the page is indexable with a canonical URL (defaults to the
      current URL without query string).
--}}
@php
    $fullTitle = $title ? $title.' | Meezan Property' : 'Meezan Property — Homes, Plots & Commercial Property in Pakistan';
    $description = \Illuminate\Support\Str::limit(trim(preg_replace('/\s+/', ' ', $description ?? 'Search homes, apartments, plots and commercial property for sale and rent in Lahore, Karachi, Islamabad, Multan, Faisalabad, Quetta and Peshawar. Listings reviewed before publishing, with verified dealers and an AI Property Finder.')), 160);
    $robots = $noindex ? 'noindex, nofollow' : ($robots ?? 'index, follow, max-image-preview:large');
    $indexable = str_starts_with($robots, 'index');
    $canonical ??= url()->current();
    $image ??= \App\Support\Seo::defaultImage();
@endphp
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ $fullTitle }}</title>
<meta name="description" content="{{ $description }}">
<meta name="robots" content="{{ $robots }}">
@if ($indexable)
    <link rel="canonical" href="{{ $canonical }}">
@endif
@unless ($noindex)
    <meta property="og:site_name" content="Meezan Property">
    <meta property="og:locale" content="en_PK">
    <meta property="og:type" content="{{ $type }}">
    <meta property="og:title" content="{{ $fullTitle }}">
    <meta property="og:description" content="{{ $description }}">
    <meta property="og:url" content="{{ $canonical }}">
    <meta property="og:image" content="{{ $image }}">
    <meta property="og:image:alt" content="{{ $imageAlt ?? $title ?? 'Meezan Property' }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $fullTitle }}">
    <meta name="twitter:description" content="{{ $description }}">
    <meta name="twitter:image" content="{{ $image }}">
@endunless
<meta name="theme-color" content="#321064">
<link rel="icon" href="{{ asset('favicon.ico') }}" sizes="48x48">
<link rel="icon" href="{{ asset('images/logo/meezan-icon-32.png') }}" type="image/png" sizes="32x32">
<link rel="icon" href="{{ asset('images/logo/meezan-icon-192.png') }}" type="image/png" sizes="192x192">
<link rel="apple-touch-icon" href="{{ asset('images/logo/meezan-apple-touch-icon.png') }}">
<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=inter:400,500,600,700|playfair-display:600,700&display=swap" rel="stylesheet">
@vite(['resources/css/app.css', 'resources/js/app.js'])
{{ $slot }}
