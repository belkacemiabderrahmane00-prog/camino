<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'CAMINO') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet" />
        <link
            rel="stylesheet"
            href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL,GRAD,opsz@400,0,0,24"
        />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased bg-white text-slate-900 dark:bg-camino-background-dark dark:text-slate-100">
        @if($home)
            {{-- Page d'accueil : layout immersif, fond enrichi --}}
            <div class="min-h-screen flex flex-col items-center px-4 py-8 guest-bg-home overflow-x-hidden">
                {{ $slot }}
            </div>
        @else
            {{-- Auth (login, register, etc.) : layout centré avec carte --}}
            <div class="min-h-screen flex flex-col items-center justify-center px-4 py-8 bg-gradient-to-b from-slate-100 via-slate-100 to-slate-50 dark:from-slate-950 dark:via-slate-950 dark:to-slate-900">
                <div class="flex flex-col items-center gap-3">
                    <a href="/" class="inline-flex items-center gap-3">
                        <span class="inline-flex h-11 w-11 items-center justify-center rounded-2xl bg-primary text-slate-900 shadow-camino-chip border border-cyan-400/50 dark:bg-slate-800 dark:text-slate-50 dark:border-slate-700">
                            <span class="text-lg font-semibold tracking-tight">C</span>
                        </span>
                        <div class="text-left">
                            <p class="text-sm font-semibold tracking-tight text-slate-900 dark:text-slate-50">CAMINO</p>
                            <p class="text-[11px] uppercase tracking-[0.25em] text-slate-600 dark:text-slate-400">GPS culturel intelligent</p>
                        </div>
                    </a>
                </div>
                <div class="w-full sm:max-w-md mt-8">
                    <div class="rounded-3xl border border-slate-200 dark:border-slate-800/80 bg-white/95 dark:bg-slate-900/80 backdrop-blur-xl shadow-camino-soft px-6 py-6 sm:px-8 sm:py-7">
                        {{ $slot }}
                    </div>
                    <p class="mt-4 text-[11px] text-center text-slate-600 dark:text-slate-500">
                        Connecte-toi pour créer et gérer tes parcours culturels personnalisés.
                    </p>
                </div>
            </div>
        @endif
    </body>
</html>
