<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @php($seo = \App\Support\PublicSeoMetadata::forRequest(request()))
        @include('partials.head', ['title' => $seo['title']])
        <meta name="description" content="{{ $seo['description'] }}">
        <link rel="canonical" href="{{ $seo['canonical'] }}">
        @if (in_array(request()->route()?->getName(), ['account', 'cart', 'checkout', 'orders.track', 'orders.show', 'mobile-login'], true))
            <meta name="robots" content="noindex, follow">
        @endif
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    </head>
    <body class="min-h-screen bg-white text-zinc-900">
        <div class="flex min-h-screen flex-col">
            <x-site-header />

            <main class="flex-1">
                {{ $slot }}
            </main>

            <x-site-footer />
        </div>

        @fluxScripts
    </body>
</html>
