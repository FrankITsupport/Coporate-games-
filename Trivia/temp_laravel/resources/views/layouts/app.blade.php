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
    <body class="font-sans antialiased" x-data="{ sidebarOpen: false }">
        <div class="flex h-screen overflow-hidden bg-gray-50">

            <!-- Desktop sidebar -->
            <div class="hidden shrink-0 lg:block">
                @include('layouts.sidebar')
            </div>

            <!-- Mobile sidebar (off-canvas) -->
            <div x-show="sidebarOpen" x-cloak class="fixed inset-0 z-40 lg:hidden" role="dialog" aria-modal="true">
                <div x-show="sidebarOpen" x-transition:enter="ease-linear duration-200" x-transition:enter-start="opacity-0"
                    x-transition:enter-end="opacity-100" x-transition:leave="ease-linear duration-150"
                    x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                    class="fixed inset-0 bg-black/50" @click="sidebarOpen = false"></div>

                <div x-show="sidebarOpen" x-transition:enter="ease-out duration-200" x-transition:enter-start="-translate-x-full"
                    x-transition:enter-end="translate-x-0" x-transition:leave="ease-in duration-150"
                    x-transition:leave-start="translate-x-0" x-transition:leave-end="-translate-x-full"
                    class="relative z-10 flex h-full">
                    @include('layouts.sidebar')
                    <button @click="sidebarOpen = false" class="absolute right-0 top-4 -mr-10 flex h-8 w-8 items-center justify-center rounded-full bg-black/40 text-white">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Main column -->
            <div class="flex min-w-0 flex-1 flex-col overflow-hidden">
                <!-- Header -->
                <header class="brand-topbar-glow relative z-10 flex h-16 shrink-0 items-center gap-4 bg-brand-night px-4 shadow-sm sm:px-6">
                    <button @click="sidebarOpen = true" class="text-brand-muted hover:text-white lg:hidden">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>

                    <div class="min-w-0 flex-1">
                        @isset($header)
                            <div class="truncate text-base font-semibold text-white">{{ $header }}</div>
                        @else
                            <div class="truncate text-base font-semibold text-white">{{ config('app.name') }}</div>
                        @endisset
                    </div>

                    <x-dropdown align="right" width="48">
                        <x-slot name="trigger">
                            <button class="inline-flex items-center gap-2 rounded-full bg-white/5 py-1.5 pl-1.5 pr-3 text-sm font-medium text-white ring-1 ring-white/10 transition hover:bg-white/10 focus:outline-none">
                                <span class="flex h-7 w-7 items-center justify-center rounded-full bg-brand-lime text-xs font-bold text-brand-night">
                                    {{ Str::of(Auth::user()->name)->substr(0, 1)->upper() }}
                                </span>
                                <span class="hidden sm:inline">{{ Auth::user()->name }}</span>
                                <svg class="h-4 w-4 text-brand-muted" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </button>
                        </x-slot>

                        <x-slot name="content">
                            <x-dropdown-link :href="route('profile.edit')">
                                {{ __('Profile') }}
                            </x-dropdown-link>

                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <x-dropdown-link :href="route('logout')"
                                        onclick="event.preventDefault(); this.closest('form').submit();">
                                    {{ __('Log Out') }}
                                </x-dropdown-link>
                            </form>
                        </x-slot>
                    </x-dropdown>
                </header>

                <!-- Page Content -->
                <main class="flex-1 overflow-y-auto">
                    {{ $slot }}
                </main>
            </div>
        </div>
    </body>
</html>
