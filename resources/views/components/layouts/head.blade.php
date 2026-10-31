@props(['title' => null, 'description' => null, 'canonical' => null, 'image' => null, 'noindex' => false])
@php
    $fullTitle = $title ? $title.' | Meezan Property' : 'Meezan Property — Homes, Plots & Commercial Property in Pakistan';
    $description ??= 'Search homes, apartments, plots and commercial property for sale and rent in Lahore, Karachi, Islamabad, Multan, Faisalabad, Quetta and Peshawar. Listings reviewed before publishing, with verified dealers and an AI Property Finder.';
@endphp
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ $fullTitle }}</title>
<meta name="description" content="{{ $description }}">
@if ($noindex)
    <meta name="robots" content="noindex, nofollow">
@else
    <link rel="canonical" href="{{ $canonical ?? url()->current() }}">
@endif
<meta property="og:site_name" content="Meezan Property">
<meta property="og:type" content="website">
<meta property="og:title" content="{{ $fullTitle }}">
<meta property="og:description" content="{{ $description }}">
<meta property="og:url" content="{{ $canonical ?? url()->current() }}">
@if ($image)
    <meta property="og:image" content="{{ $image }}">
@endif
<meta name="twitter:card" content="summary_large_image">
<meta name="theme-color" content="#321064">
<link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=inter:400,500,600,700|playfair-display:600,700&display=swap" rel="stylesheet">
@vite(['resources/css/app.css', 'resources/js/app.js'])
{{ $slot }}
