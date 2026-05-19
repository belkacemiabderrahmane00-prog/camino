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

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased bg-background-light text-slate-900 dark:bg-background-dark dark:text-slate-100">
        <div class="min-h-screen flex flex-col">
            <!-- Header fixe -->
            <header
                class="fixed inset-x-0 top-0 z-50 border-b border-white/10 bg-slate-950/70 backdrop-blur-xl"
            >
                <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div class="flex items-center justify-between gap-4 py-4">
                        <a href="{{ url('/') }}" class="flex items-center gap-3 group">
                            <span
                                class="inline-flex h-10 w-10 items-center justify-center rounded-2xl bg-primary text-slate-900 shadow-lg shadow-primary/25 border border-cyan-400/50 dark:bg-slate-900 dark:border-slate-700 dark:text-slate-50 text-sm font-bold tracking-tight transition group-hover:shadow-primary/40"
                            >
                                C
                            </span>
                            <div>
                                <p class="text-xs font-bold tracking-tight text-slate-900 dark:text-slate-50">
                                    CAMINO
                                </p>
                                <p class="text-[10px] uppercase tracking-[0.28em] text-slate-500 dark:text-slate-400">
                                    GPS culturel intelligent
                                </p>
                            </div>
                        </a>

                        <nav class="hidden md:flex items-center gap-9 text-sm font-medium text-slate-200">
                            <a
                                href="{{ route('map.index') }}"
                                class="whitespace-nowrap text-slate-200 hover:text-cyan-300 transition-colors"
                            >
                                Découvrir
                            </a>
                            <a
                                href="#features"
                                class="whitespace-nowrap text-slate-200 hover:text-cyan-300 transition-colors"
                            >
                                Fonctionnalités
                            </a>
                            <a
                                href="#contact"
                                class="whitespace-nowrap text-slate-200 hover:text-cyan-300 transition-colors"
                            >
                                Contact
                            </a>
                        </nav>

                        <div class="hidden md:flex items-center gap-3">
                            @if (Route::has('login'))
                                @auth
                                    <a
                                        href="{{ url('/dashboard') }}"
                                        class="inline-flex items-center gap-2 rounded-full border border-slate-700 bg-slate-950/80 px-4 py-2 text-xs font-semibold text-slate-100 hover:border-cyan-300 hover:text-cyan-300 transition-colors"
                                    >
                                        <span class="material-symbols-outlined text-[18px]">space_dashboard</span>
                                        Espace CAMINO
                                    </a>
                                @else
                                    <a
                                        href="{{ route('login') }}"
                                        class="inline-flex items-center gap-2 rounded-full border border-slate-700 bg-slate-950/80 px-4 py-2 text-xs font-semibold text-slate-100 hover:border-cyan-300 hover:text-cyan-300 transition-colors"
                                    >
                                        Connexion
                                    </a>
                                    <a
                                        href="{{ route('register') }}"
                                        class="inline-flex items-center gap-2 rounded-full bg-primary text-slate-900 px-4 py-2 text-xs font-bold shadow-lg shadow-primary/25 hover:bg-cyan-300 transition-colors"
                                    >
                                        S'inscrire
                                    </a>
                                @endauth
                            @endif
                        </div>

                        <!-- Mobile menu button -->
                        <div class="md:hidden flex items-center">
                            <button
                                type="button"
                                class="inline-flex items-center justify-center rounded-full border border-slate-700 bg-slate-950/80 p-2 text-slate-200"
                                x-data="{ open: false }"
                                @click="open = !open"
                                :aria-expanded="open.toString()"
                            >
                                <span class="sr-only">Ouvrir le menu</span>
                                <span class="material-symbols-outlined text-[22px]" x-show="!open">menu</span>
                                <span class="material-symbols-outlined text-[22px]" x-show="open" x-cloak>close</span>

                                <div
                                    x-show="open"
                                    x-transition
                                    @click.away="open = false"
                                    class="absolute inset-x-0 top-full mt-3 px-4"
                                >
                                    <div
                                        class="rounded-3xl border border-slate-200/80 bg-white/95 dark:border-slate-800/80 dark:bg-slate-950/95 shadow-xl shadow-slate-200/60 dark:shadow-black/40 py-4"
                                    >
                                        <nav class="flex flex-col gap-1 px-3 text-sm font-medium">
                                            <a
                                                href="{{ route('map.index') }}"
                                                class="rounded-xl px-3 py-2 text-slate-800 hover:bg-slate-100 dark:text-slate-100 dark:hover:bg-slate-900"
                                            >
                                                Découvrir
                                            </a>
                                            <a
                                                href="#features"
                                                class="rounded-xl px-3 py-2 text-slate-800 hover:bg-slate-100 dark:text-slate-100 dark:hover:bg-slate-900"
                                            >
                                                Fonctionnalités
                                            </a>
                                            <a
                                                href="#contact"
                                                class="rounded-xl px-3 py-2 text-slate-800 hover:bg-slate-100 dark:text-slate-100 dark:hover:bg-slate-900"
                                            >
                                                Contact
                                            </a>
                                        </nav>
                                        <div class="mt-3 border-t border-slate-200/80 dark:border-slate-800/80 pt-3 px-3 space-y-2">
                                            @if (Route::has('login'))
                                                @auth
                                                    <a
                                                        href="{{ url('/dashboard') }}"
                                                        class="flex items-center justify-center gap-2 rounded-full border border-slate-300 dark:border-slate-700 bg-white/90 dark:bg-slate-900/90 px-4 py-2 text-xs font-semibold text-slate-800 dark:text-slate-100"
                                                    >
                                                        <span class="material-symbols-outlined text-[18px]">space_dashboard</span>
                                                        Espace CAMINO
                                                    </a>
                                                @else
                                                    <a
                                                        href="{{ route('login') }}"
                                                        class="flex items-center justify-center gap-2 rounded-full border border-slate-300 dark:border-slate-700 bg-white/90 dark:bg-slate-900/90 px-4 py-2 text-xs font-semibold text-slate-800 dark:text-slate-100"
                                                    >
                                                        Connexion
                                                    </a>
                                                    <a
                                                        href="{{ route('register') }}"
                                                        class="flex items-center justify-center gap-2 rounded-full bg-primary text-slate-900 px-4 py-2 text-xs font-bold shadow-md shadow-primary/30 hover:bg-cyan-300 transition"
                                                    >
                                                        S'inscrire
                                                    </a>
                                                @endauth
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </button>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Contenu principal -->
            <main class="flex-1 pt-24 md:pt-28 bg-gradient-to-b from-background-light via-background-light to-white dark:from-background-dark dark:via-camino-background-dark dark:to-black">
                <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
                    @yield('content')
                </div>
            </main>

            <!-- Footer -->
            <footer class="border-t border-slate-200/80 bg-slate-900 text-slate-100 dark:border-slate-800/80 dark:bg-black">
                <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-10 sm:py-12">
                    <div class="grid gap-8 md:grid-cols-4">
                        <div class="space-y-3 md:col-span-1">
                            <div class="flex items-center gap-3">
                                <span
                                    class="inline-flex h-9 w-9 items-center justify-center rounded-2xl bg-primary text-slate-900 text-xs font-bold shadow-lg shadow-primary/30"
                                >
                                    C
                                </span>
                                <div>
                                    <p class="text-xs font-bold tracking-tight">CAMINO</p>
                                    <p class="text-[10px] uppercase tracking-[0.24em] text-slate-400">
                                        GPS culturel intelligent
                                    </p>
                                </div>
                            </div>
                            <p class="text-xs text-slate-400 leading-relaxed max-w-xs">
                                Explorez l’Île-de-France avec des parcours culturels intelligents, pensés pour votre temps, vos envies et votre budget.
                            </p>
                        </div>

                        <div class="grid gap-8 md:grid-cols-3 md:col-span-3 text-sm">
                            <div>
                                <h3 class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400 mb-3">
                                    Produit
                                </h3>
                                <ul class="space-y-2 text-sm text-slate-300">
                                    <li>
                                        <a href="{{ route('map.index') }}" class="hover:text-primary transition">
                                            Découvrir la carte
                                        </a>
                                    </li>
                                    <li>
                                        <a href="{{ route('itineraries.create') }}" class="hover:text-primary transition">
                                            Créer un parcours
                                        </a>
                                    </li>
                                    <li>
                                        <a href="#features" class="hover:text-primary transition">
                                            Fonctionnalités
                                        </a>
                                    </li>
                                </ul>
                            </div>

                            <div>
                                <h3 class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400 mb-3">
                                    Société
                                </h3>
                                <ul class="space-y-2 text-sm text-slate-300">
                                    <li>
                                        <a href="#contact" class="hover:text-primary transition">
                                            Contact
                                        </a>
                                    </li>
                                    <li>
                                        <a href="{{ route('register') }}" class="hover:text-primary transition">
                                            Rejoindre la bêta
                                        </a>
                                    </li>
                                    <li>
                                        <a href="{{ route('login') }}" class="hover:text-primary transition">
                                            Accès partenaires
                                        </a>
                                    </li>
                                </ul>
                            </div>

                            <div>
                                <h3 class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400 mb-3">
                                    Légal
                                </h3>
                                <ul class="space-y-2 text-sm text-slate-300">
                                    <li>
                                        <a href="#" class="hover:text-primary transition">
                                            Mentions légales
                                        </a>
                                    </li>
                                    <li>
                                        <a href="#" class="hover:text-primary transition">
                                            Politique de confidentialité
                                        </a>
                                    </li>
                                    <li>
                                        <a href="#" class="hover:text-primary transition">
                                            Cookies
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div class="mt-8 border-t border-slate-800/80 pt-4 flex flex-col sm:flex-row items-center justify-between gap-3">
                        <p class="text-[11px] text-slate-500">
                            © {{ date('Y') }} CAMINO. Tous droits réservés.
                        </p>
                        <p class="text-[11px] text-slate-500">
                            Fait avec <span class="material-symbols-outlined text-[14px] align-middle text-primary">favorite</span> pour les explorateurs urbains.
                        </p>
                    </div>
                </div>
            </footer>
        </div>
    </body>
</html>

