@props([
    'title' => null,
    'fullscreen' => false,
    'description' => 'CAMINO, le GPS culturel intelligent : carte vivante, parcours sur mesure et bons plans culturels en Île-de-France.',
])
<!DOCTYPE html>
<html lang="fr" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#FF5A3C">
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
    <link rel="apple-touch-icon" href="{{ asset('icons/icon-192.png') }}">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="CAMINO">
    <meta name="description" content="{{ $description }}">
    <title>{{ $title ? $title . ' · CAMINO' : 'CAMINO — GPS culturel intelligent' }}</title>
    @stack('meta')

    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64'%3E%3Crect width='64' height='64' rx='16' fill='%23FF5A3C'/%3E%3Cpath d='M32 12c-8.3 0-15 6.6-15 14.8C17 38.4 32 52 32 52s15-13.6 15-25.2C47 18.6 40.3 12 32 12zm0 20a5.5 5.5 0 1 1 0-11 5.5 5.5 0 0 1 0 11z' fill='%23fff'/%3E%3C/svg%3E">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700&family=Fraunces:ital,opsz,wght@0,9..144,500;0,9..144,600;1,9..144,500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,300..600,0..1,0">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-full flex flex-col {{ $fullscreen ? 'h-full overflow-hidden' : '' }}">

    {{-- ======================================================= Header --}}
    <header
        x-data="{ open: false, user: false }"
        class="sticky top-0 z-[1000] {{ $fullscreen ? 'absolute inset-x-0' : '' }}"
    >
        <div class="{{ $fullscreen ? 'pointer-events-none' : '' }}">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 pt-3">
                <div class="glass rounded-full pl-4 pr-2 py-2 flex items-center gap-3 pointer-events-auto">
                    <a href="{{ auth()->check() ? route('dashboard') : route('home') }}" class="flex items-center gap-2 shrink-0 group" aria-label="CAMINO — accueil">
                        <span class="h-8 w-8 rounded-xl bg-coral text-white flex items-center justify-center shadow-card group-hover:rotate-6 transition-transform">
                            <span class="material-symbols-outlined filled" style="font-size:18px">location_on</span>
                        </span>
                        <span class="font-display text-[22px] font-semibold tracking-tight leading-none">CAMINO</span>
                    </a>

                    <nav class="hidden md:flex items-center gap-1 ml-4 text-sm font-medium">
                        @php
                            $links = [
                                ['route' => 'map.index', 'label' => 'Explorer', 'icon' => 'map', 'active' => request()->routeIs('map.*')],
                                ['route' => 'itineraries.create', 'label' => 'Parcours', 'icon' => 'route', 'active' => request()->routeIs('itineraries.create')],
                                ['route' => 'map.index', 'label' => 'Événements', 'icon' => 'celebration', 'active' => false, 'query' => ['filtre' => 'evenements']],
                            ];
                        @endphp
                        @foreach($links as $link)
                            <a href="{{ route($link['route'], $link['query'] ?? []) }}"
                               class="px-3.5 py-2 rounded-full transition-colors {{ $link['active'] ? 'bg-ink text-white' : 'text-ink-soft hover:bg-ink/5 hover:text-ink' }}">
                                {{ $link['label'] }}
                            </a>
                        @endforeach
                    </nav>

                    <div class="ml-auto flex items-center gap-2">
                        {{ $actions ?? '' }}

                        @auth
                            <a href="{{ route('community.propose') }}" class="hidden lg:inline-flex btn btn-sm btn-soft">
                                <span class="material-symbols-outlined" style="font-size:16px">add_location_alt</span>
                                Proposer un lieu
                            </a>
                            <div class="relative" @click.outside="user = false">
                                <button @click="user = !user" class="flex items-center gap-2 rounded-full pl-1 pr-3 py-1 hover:bg-ink/5 transition" aria-label="Menu utilisateur">
                                    @if(auth()->user()->avatar_url)
                                        <img src="{{ auth()->user()->avatar_url }}" alt="" class="h-8 w-8 rounded-full object-cover">
                                    @else
                                        <span class="h-8 w-8 rounded-full bg-teal text-white flex items-center justify-center text-sm font-bold">{{ auth()->user()->initial }}</span>
                                    @endif
                                    <span class="hidden sm:block text-sm font-medium max-w-[120px] truncate">{{ auth()->user()->name }}</span>
                                    <span class="material-symbols-outlined text-ink-muted" style="font-size:18px">expand_more</span>
                                </button>
                                <div x-cloak x-show="user" x-transition.origin.top.right
                                     class="absolute right-0 mt-2 w-56 card p-1.5 text-sm">
                                    @foreach([
                                        ['dashboard', 'space_dashboard', 'Mon espace'],
                                        ['itineraries.index', 'history', 'Mes parcours'],
                                        ['places.favorites', 'favorite', 'Mes favoris'],
                                        ['community.propose', 'add_location_alt', 'Proposer un lieu'],
                                        ['profile.edit', 'person', 'Profil'],
                                    ] as [$r, $i, $l])
                                        <a href="{{ route($r) }}" class="flex items-center gap-3 px-3 py-2 rounded-xl hover:bg-paper transition">
                                            <span class="material-symbols-outlined text-ink-muted" style="font-size:18px">{{ $i }}</span>{{ $l }}
                                        </a>
                                    @endforeach
                                    @can('admin')
                                        <a href="{{ route('moderation.index') }}" class="flex items-center gap-3 px-3 py-2 rounded-xl hover:bg-paper transition text-coral-dark">
                                            <span class="material-symbols-outlined" style="font-size:18px">shield</span>Modération
                                        </a>
                                    @endcan
                                    <form method="POST" action="{{ route('logout') }}" class="border-t border-ink/5 mt-1 pt-1">
                                        @csrf
                                        <button type="submit" class="w-full flex items-center gap-3 px-3 py-2 rounded-xl hover:bg-paper transition text-left text-ink-muted">
                                            <span class="material-symbols-outlined" style="font-size:18px">logout</span>Déconnexion
                                        </button>
                                    </form>
                                </div>
                            </div>
                        @else
                            <a href="{{ route('login') }}" class="hidden sm:inline-flex btn btn-sm btn-ghost">Connexion</a>
                            <a href="{{ route('register') }}" class="btn btn-sm btn-ink">Créer un compte</a>
                        @endauth

                        <button @click="open = !open" class="md:hidden btn btn-icon btn-ghost" aria-label="Menu">
                            <span class="material-symbols-outlined" x-text="open ? 'close' : 'menu'">menu</span>
                        </button>
                    </div>
                </div>

                {{-- Menu mobile --}}
                <div x-cloak x-show="open" x-transition class="md:hidden mt-2 card p-2 text-sm pointer-events-auto">
                    @foreach($links as $link)
                        <a href="{{ route($link['route'], $link['query'] ?? []) }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl hover:bg-paper">
                            <span class="material-symbols-outlined text-ink-muted" style="font-size:20px">{{ $link['icon'] }}</span>{{ $link['label'] }}
                        </a>
                    @endforeach
                    @guest
                        <a href="{{ route('login') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl hover:bg-paper">
                            <span class="material-symbols-outlined text-ink-muted" style="font-size:20px">login</span>Connexion
                        </a>
                    @endguest
                </div>
            </div>
        </div>
    </header>

    {{-- ======================================================= Flash --}}
    @if(session('status') || session('favorite_status') || $errors->any())
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 6000)" x-transition
             class="fixed z-[1100] top-20 inset-x-4 sm:inset-x-auto sm:right-6 sm:w-96 pointer-events-auto">
            <div class="card p-4 flex items-start gap-3 border-l-4 {{ $errors->any() ? 'border-l-coral' : 'border-l-teal' }}">
                <span class="material-symbols-outlined {{ $errors->any() ? 'text-coral' : 'text-teal' }}">{{ $errors->any() ? 'error' : 'check_circle' }}</span>
                <div class="text-sm flex-1">
                    @if($errors->any())
                        <p class="font-semibold">Vérifie le formulaire</p>
                        <ul class="mt-1 text-ink-muted space-y-0.5">
                            @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                        </ul>
                    @elseif(session('status'))
                        <p>{{ session('status') }}</p>
                    @elseif(session('favorite_status') === 'added')
                        <p>Ajouté à tes favoris.</p>
                    @else
                        <p>Retiré de tes favoris.</p>
                    @endif
                </div>
                <button @click="show = false" class="text-ink-muted hover:text-ink"><span class="material-symbols-outlined" style="font-size:18px">close</span></button>
            </div>
        </div>
    @endif

    {{-- ======================================================= Contenu --}}
    <main class="flex-1 {{ $fullscreen ? 'relative min-h-0' : 'pb-24 md:pb-0' }}">
        {{ $slot }}
    </main>

    {{-- ======================================================= Footer --}}
    @unless($fullscreen)
        <footer class="mt-16 border-t border-ink/5 bg-paper-deep/60">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 py-10 grid gap-8 md:grid-cols-4 text-sm">
                <div class="md:col-span-2">
                    <p class="font-display text-2xl font-semibold">CAMINO</p>
                    <p class="mt-2 text-ink-muted max-w-md">Le GPS culturel intelligent. Une carte vivante, des parcours générés selon ton temps, ton budget et la météo, et une communauté qui enrichit la carte.</p>
                </div>
                <div>
                    <p class="font-semibold mb-3">Explorer</p>
                    <ul class="space-y-2 text-ink-muted">
                        <li><a href="{{ route('map.index') }}" class="hover:text-ink">Carte culturelle</a></li>
                        <li><a href="{{ route('itineraries.create') }}" class="hover:text-ink">Générer un parcours</a></li>
                        <li><a href="{{ route('map.index', ['filtre' => 'free']) }}" class="hover:text-ink">Lieux gratuits</a></li>
                        <li><a href="{{ route('map.index', ['filtre' => 'evenements']) }}" class="hover:text-ink">Événements</a></li>
                    </ul>
                </div>
                <div>
                    <p class="font-semibold mb-3">Communauté</p>
                    <ul class="space-y-2 text-ink-muted">
                        <li><a href="{{ route('community.propose') }}" class="hover:text-ink">Proposer un lieu</a></li>
                        <li><a href="{{ route('register') }}" class="hover:text-ink">Créer un compte</a></li>
                        <li class="pt-2 text-xs">Données : DATAtourisme, OpenStreetMap, Wikimedia Commons, Open-Meteo.</li>
                    </ul>
                </div>
            </div>
        </footer>
    @endunless

    {{-- ======================================================= Nav mobile --}}
    <nav class="md:hidden fixed bottom-0 inset-x-0 z-[1000] pb-[env(safe-area-inset-bottom)]">
        <div class="mx-3 mb-3 glass rounded-3xl px-2 py-1.5 grid grid-cols-5 text-[10px] font-semibold">
            @php
                $tabs = [
                    auth()->check()
                        ? ['route' => 'dashboard', 'icon' => 'home', 'label' => 'Accueil', 'active' => request()->routeIs('dashboard')]
                        : ['route' => 'home', 'icon' => 'home', 'label' => 'Accueil', 'active' => request()->routeIs('home')],
                    ['route' => 'map.index', 'icon' => 'map', 'label' => 'Carte', 'active' => request()->routeIs('map.*')],
                    ['route' => 'itineraries.create', 'icon' => 'route', 'label' => 'Parcours', 'active' => request()->routeIs('itineraries.*')],
                    auth()->check()
                        ? ['route' => 'places.favorites', 'icon' => 'favorite', 'label' => 'Favoris', 'active' => request()->routeIs('places.favorites')]
                        : ['route' => 'login', 'icon' => 'login', 'label' => 'Connexion', 'active' => request()->routeIs('login')],
                    auth()->check()
                        ? ['route' => 'profile.edit', 'icon' => 'person', 'label' => 'Profil', 'active' => request()->routeIs('profile.*')]
                        : ['route' => 'register', 'icon' => 'person_add', 'label' => 'Compte', 'active' => request()->routeIs('register')],
                ];
            @endphp
            @foreach($tabs as $tab)
                <a href="{{ route($tab['route']) }}" class="flex flex-col items-center gap-0.5 py-1.5 rounded-2xl transition {{ $tab['active'] ? 'text-coral' : 'text-ink-muted' }}">
                    <span class="material-symbols-outlined {{ $tab['active'] ? 'filled' : '' }}" style="font-size:22px">{{ $tab['icon'] }}</span>
                    {{ $tab['label'] }}
                </a>
            @endforeach
        </div>
    </nav>

    @stack('scripts')
</body>
</html>
