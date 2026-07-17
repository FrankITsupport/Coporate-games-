@props(['title' => null, 'fullWidth' => false])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $title ?? config('app.name') }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="brand-stadium-bg min-h-screen font-sans text-brand-ink antialiased">
        <div class="brand-crowd-dots"></div>

        @if ($fullWidth)
            <div class="relative z-10 min-h-screen">
                {{ $slot }}
            </div>
        @else
            <div class="relative z-10 mx-auto flex min-h-screen max-w-md flex-col justify-center px-4 py-8">
                {{ $slot }}
            </div>
        @endif
    </body>
</html>
