<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>@yield('title', config('app.name', 'CAMINO'))</title>

        @stack('meta')
        <style>[x-cloak]{display:none!important}</style>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet" />
        <link
            rel="stylesheet"
            href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL,GRAD,opsz@400,0,0,24"
        />

        <!-- Leaflet (map) -->
        <link
            rel="stylesheet"
            href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
            integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
            crossorigin=""
        />
        <script
            src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
            integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
            crossorigin=""
            defer
        ></script>

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased bg-white text-slate-900 dark:bg-camino-background-dark dark:text-slate-100">
        <div class="min-h-screen flex flex-col bg-gradient-to-b from-slate-100 via-slate-100 to-slate-50 dark:from-slate-950 dark:via-slate-950 dark:to-slate-900">
            @include('layouts.navigation')

            <!-- Page Heading -->
            @isset($header)
                <header class="border-b border-slate-200/80 bg-white/80 backdrop-blur-xl dark:border-slate-800/80 dark:bg-slate-900/70">
                    <div class="max-w-6xl mx-auto py-4 px-4 sm:px-6 lg:px-8 flex items-center justify-between gap-3">
                        <div class="space-y-1">
                            {{ $header }}
                        </div>
                    </div>
                </header>
            @endisset

            @if((session('status') && !in_array(session('status'), ['profile-updated', 'password-updated'])) || session('favorite_status'))
                <div class="max-w-6xl mx-auto px-4 py-2" x-data="{ show: true }" x-show="show" x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" x-init="setTimeout(() => show = false, 4000)">
                    @if(session('status') && !in_array(session('status'), ['profile-updated', 'password-updated']))
                        <div class="rounded-2xl bg-primary/15 border border-primary/40 px-4 py-3 text-sm text-primary font-medium flex items-center gap-2">
                            <span class="material-symbols-outlined text-[18px]">check_circle</span>
                            {{ session('status') }}
                        </div>
                    @endif
                    @if(session('favorite_status'))
                        <div class="rounded-2xl bg-primary/15 border border-primary/40 px-4 py-3 text-sm text-primary font-medium {{ session('status') ? 'mt-2' : '' }} flex items-center gap-2">
                            <span class="material-symbols-outlined text-[18px]" style="font-variation-settings: 'FILL' {{ session('favorite_status') === 'added' ? 1 : 0 }}">favorite</span>
                            {{ session('favorite_status') === 'added' ? 'Ajouté aux favoris' : 'Retiré des favoris' }}
                        </div>
                    @endif
                </div>
            @endif

            <!-- Page Content -->
            <main class="flex-1 {{ request()->routeIs('places.show') ? '' : 'pb-24 sm:pb-0' }}">
                {{ $slot }}
            </main>

            @auth
                @unless(request()->routeIs('places.show'))
                    @include('layouts.mobile-nav')
                @endunless
            @endauth
        </div>
    </body>
</html>
