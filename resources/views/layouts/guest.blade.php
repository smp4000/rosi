<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'ROSI' }} - Tankstellen-Partner-Plattform</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-gradient-to-br from-primary-50 via-white to-accent-400/10">

    {{-- Hintergrund-Dekoration --}}
    <div class="fixed inset-0 -z-10 overflow-hidden">
        <div class="absolute -top-40 -right-40 h-80 w-80 rounded-full bg-primary-200/30 blur-3xl"></div>
        <div class="absolute -bottom-40 -left-40 h-80 w-80 rounded-full bg-accent-400/20 blur-3xl"></div>
    </div>

    <div class="flex min-h-screen flex-col items-center justify-center px-4 py-8 sm:px-6 lg:px-8">
        {{-- Logo --}}
        <a href="/" class="mb-8 flex items-center gap-3 transition-transform hover:scale-105">
            <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-gradient-to-br from-primary-500 to-accent-600 shadow-lg shadow-primary-500/25">
                <svg class="h-7 w-7 text-white" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21" />
                </svg>
            </div>
            <span class="text-2xl font-bold tracking-tight text-gray-900">ROSI</span>
        </a>

        {{-- Inhalt --}}
        <div class="w-full max-w-md">
            {{ $slot }}
        </div>

        {{-- Footer --}}
        <p class="mt-8 text-center text-sm text-gray-400">
            &copy; {{ date('Y') }} ROSI &middot; Tankstellen-Partner-Plattform
        </p>
    </div>

    @livewireScripts
</body>
</html>
