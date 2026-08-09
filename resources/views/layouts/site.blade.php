<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head', ['title' => $title ?? null])
        <meta name="description" content="{{ $description ?? 'Live lowest-unique-bid auctions on phones, TVs, motorbikes and more. Pay bid fees via M-Pesa. Instant SMS & WhatsApp confirmations.' }}">
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
