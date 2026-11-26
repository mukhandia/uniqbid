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
        @php($ga4MeasurementId = config('analytics.ga4_measurement_id'))
        @if (is_string($ga4MeasurementId) && preg_match('/^G-[A-Z0-9]+$/', $ga4MeasurementId))
            <script async src="https://www.googletagmanager.com/gtag/js?id={{ $ga4MeasurementId }}"></script>
            <script>
                window.dataLayer = window.dataLayer || [];
                window.gtag = window.gtag || function () { window.dataLayer.push(arguments); };
                window.gtag('js', new Date());
                window.gtag('config', @js($ga4MeasurementId), { send_page_view: false });

                document.addEventListener('livewire:navigated', function () {
                    window.gtag('event', 'page_view', {
                        page_title: document.title,
                        page_location: window.location.href,
                        page_path: window.location.pathname + window.location.search,
                    });
                });
            </script>
        @endif
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
