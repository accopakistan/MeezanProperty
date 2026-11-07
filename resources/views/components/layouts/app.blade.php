@props(['title' => null, 'description' => null, 'canonical' => null, 'image' => null, 'imageAlt' => null, 'noindex' => false, 'robots' => null, 'type' => 'website'])
<!DOCTYPE html>
<html lang="en">
<head>
    <x-layouts.head :title="$title" :description="$description" :canonical="$canonical" :image="$image" :image-alt="$imageAlt" :noindex="$noindex" :robots="$robots" :type="$type">
        {{ $head ?? '' }}
    </x-layouts.head>
</head>
<body class="flex min-h-screen flex-col">
    <a href="#main" class="sr-only z-50 rounded-lg bg-white px-4 py-2 focus:not-sr-only focus:fixed focus:top-3 focus:left-3">Skip to content</a>

    <x-site.header />

    <main id="main" class="flex-1">
        <x-flash class="container-page mt-4" />
        {{ $slot }}
    </main>

    <x-site.footer />
    <x-site.whatsapp-float />
    @stack('scripts')
</body>
</html>
