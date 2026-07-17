<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased">
        <div class="relative flex min-h-screen items-center justify-center overflow-hidden bg-brand-night px-4 py-10 sm:px-6"
            style="background-image: linear-gradient(120deg, rgba(12,40,16,.94) 0%, rgba(12,40,16,.72) 38%, rgba(12,40,16,.35) 68%, rgba(12,40,16,.15) 100%), url('{{ asset('images/brand/stadium-hero-landscape.jpg') }}'); background-size: cover; background-position: center;">

            <div class="w-full max-w-md">
                <div class="mb-8 flex flex-col items-center gap-3 text-center sm:items-start sm:text-left">
                    <div class="flex items-center gap-3">
                        <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-white/10 text-xl font-black text-brand-lime ring-1 ring-white/20">
                            IO
                        </div>
                        <div class="leading-tight">
                            <p class="text-lg font-extrabold tracking-wide text-white">GAME IKO ON</p>
                            <p class="text-[11px] font-semibold uppercase tracking-widest text-brand-lime">Admin Console</p>
                        </div>
                    </div>
                    <p class="max-w-sm text-sm text-brand-muted">
                        Run trivia and fan predictions for the watch party — powered by M-PESA GlobalPay Virtual Visa Card.
                    </p>
                </div>

                <div class="overflow-hidden rounded-2xl bg-white/95 shadow-2xl ring-1 ring-black/5 backdrop-blur">
                    <div class="brand-topbar-glow h-1.5 w-full bg-gradient-to-r from-brand-green via-brand-lime to-brand-visa"></div>
                    <div class="px-6 py-8 sm:px-8">
                        {{ $slot }}
                    </div>
                </div>

                <p class="mt-6 text-center text-xs text-brand-muted sm:text-left">
                    &copy; {{ date('Y') }} Game Iko On. All rights reserved.
                </p>
            </div>
        </div>
    </body>
</html>
