<nav x-data="{ open: false }" class="border-b border-slate-800/80 bg-slate-950/90 backdrop-blur-xl">
    <!-- Primary Navigation Menu -->
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16 gap-4">
            <div class="flex items-center gap-6">
                <!-- Logo + tagline -->
                <a href="{{ url('/') }}" class="flex items-center gap-3">
                    <span class="inline-flex h-9 w-9 items-center justify-center rounded-2xl bg-primary text-slate-900 border border-cyan-400/50 dark:bg-slate-800 dark:border-slate-700 dark:text-slate-50 text-sm font-semibold tracking-tight shadow-camino-chip">
                        C
                    </span>
                    <div class="hidden sm:block">
                        <p class="text-xs font-semibold tracking-tight text-slate-900 dark:text-slate-50">CAMINO</p>
                        <p class="text-[10px] uppercase tracking-[0.26em] text-slate-400 dark:text-slate-400">
                            GPS culturel intelligent
                        </p>
                    </div>
                </a>

                <!-- Navigation Links -->
                <div class="hidden md:flex items-center gap-3 text-xs font-medium text-slate-300">
                    @php
                        $isDashboard = request()->routeIs('dashboard');
                        $isMap = request()->routeIs('map.*');
                        $isItinerary = request()->routeIs('itineraries.*');
                    @endphp

                    <a
                        href="{{ auth()->check() ? route('dashboard') : url('/') }}"
                        class="inline-flex items-center gap-1 px-3 py-1.5 rounded-full border {{ $isDashboard ? 'bg-slate-100 text-slate-900 border-slate-100 shadow-camino-chip' : 'bg-slate-900/80 border-slate-700/80 text-slate-100 shadow-sm shadow-slate-900/60 hover:border-primary/70 hover:text-primary' }}"
                    >
                        <span class="material-symbols-outlined text-[16px]">space_dashboard</span>
                        <span>Tableau de bord</span>
                    </a>

                    <a
                        href="{{ route('map.index') }}"
                        class="inline-flex items-center gap-1 px-3 py-1.5 rounded-full border {{ $isMap ? 'bg-primary text-slate-900 border-primary shadow-camino-chip' : 'bg-slate-900/60 border-slate-800/80 text-slate-400 hover:border-primary/70 hover:text-primary' }}"
                    >
                        <span class="material-symbols-outlined text-[16px]">explore</span>
                        <span>Carte culturelle</span>
                    </a>

                    <a
                        href="{{ route('itineraries.create') }}"
                        class="inline-flex items-center gap-1 px-3 py-1.5 rounded-full border {{ $isItinerary ? 'bg-camino-accent text-slate-900 border-camino-accent shadow-camino-chip' : 'bg-slate-900/60 border-slate-800/80 text-slate-400 hover:border-camino-accent/70 hover:text-camino-accent' }}"
                    >
                        <span class="material-symbols-outlined text-[16px]">route</span>
                        <span>Parcours</span>
                    </a>
                </div>
            </div>

            <!-- Settings Dropdown -->
            <div class="hidden sm:flex sm:items-center sm:ms-6 gap-4">
                <button
                    type="button"
                    class="hidden md:inline-flex h-9 w-9 items-center justify-center rounded-full border border-slate-200 bg-white/80 text-slate-600 hover:text-primary hover:border-primary dark:border-slate-800 dark:bg-slate-900/80 dark:text-slate-300 transition-colors duration-150"
                    onclick="window.CaminoTheme && window.CaminoTheme.toggle()"
                    aria-label="Basculer le thème"
                >
                    <span class="material-symbols-outlined text-[18px]">dark_mode</span>
                </button>
                @auth
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button class="inline-flex items-center gap-2 px-3 py-1.5 text-xs leading-4 font-medium rounded-full text-slate-200 bg-slate-900/80 border border-slate-700/80 hover:border-primary hover:text-primary focus:outline-none transition ease-in-out duration-150">
                            <div class="flex flex-col items-start">
                                <span class="text-[11px] font-semibold truncate max-w-[120px]">
                                    {{ Auth::user()->name }}
                                </span>
                                <span class="text-[10px] text-slate-400">Profil</span>
                            </div>

                            <div class="ms-1">
                                <svg class="h-3.5 w-3.5 text-slate-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </div>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <x-dropdown-link :href="route('itineraries.index')">
                            Mes parcours
                        </x-dropdown-link>
                        <x-dropdown-link :href="route('places.favorites')">
                            Mes favoris
                        </x-dropdown-link>
                        <x-dropdown-link :href="route('profile.edit')">
                            {{ __('Profile') }}
                        </x-dropdown-link>

                        <!-- Authentication -->
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf

                            <x-dropdown-link
                                :href="route('logout')"
                                onclick="event.preventDefault(); this.closest('form').submit();"
                            >
                                {{ __('Log Out') }}
                            </x-dropdown-link>
                        </form>
                    </x-slot>
                </x-dropdown>
                @else
                <a href="{{ route('login') }}" class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-full text-slate-200 border border-slate-700/80 hover:border-primary hover:text-primary transition-colors duration-150">Connexion</a>

                <a href="{{ route('register') }}" class="inline-flex items-center px-3 py-1.5 text-xs font-semibold rounded-full bg-primary text-slate-900 hover:brightness-110 transition duration-150">Inscription</a>
                @endauth
            </div>

            <!-- Hamburger -->
            <div class="-me-2 flex items-center sm:hidden">
                <button
                    @click="open = ! open"
                    class="inline-flex items-center justify-center p-2 rounded-full text-slate-300 hover:text-primary hover:bg-slate-800 focus:outline-none focus:bg-slate-800 focus:text-primary transition duration-150 ease-in-out"
                >
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Responsive Navigation Menu -->
    <div :class="{'block': open, 'hidden': ! open}" class="hidden sm:hidden border-t border-slate-800 bg-slate-950/95">
        <div class="pt-3 pb-3 space-y-1 px-4 text-sm">
            <x-responsive-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                Tableau de bord
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('map.index')" :active="request()->routeIs('map.*')">
                Carte culturelle
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('itineraries.create')" :active="request()->routeIs('itineraries.*')">
                Parcours
            </x-responsive-nav-link>
        </div>
        @auth
        <!-- Responsive Settings Options -->
        <div class="pt-3 pb-4 border-t border-slate-800">
            <div class="px-4">
                <div class="font-medium text-sm text-slate-50">{{ Auth::user()->name }}</div>
                <div class="font-medium text-xs text-slate-400">{{ Auth::user()->email }}</div>
            </div>

            <div class="mt-3 space-y-1 px-4 pb-2">
                <x-responsive-nav-link :href="route('itineraries.index')">Mes parcours</x-responsive-nav-link>
                <x-responsive-nav-link :href="route('places.favorites')">Mes favoris</x-responsive-nav-link>
                <x-responsive-nav-link :href="route('profile.edit')">
                    {{ __('Profile') }}
                </x-responsive-nav-link>

                <!-- Authentication -->
                <form method="POST" action="{{ route('logout') }}">
                    @csrf

                    <x-responsive-nav-link
                        :href="route('logout')"
                        onclick="event.preventDefault(); this.closest('form').submit();"
                    >
                        {{ __('Log Out') }}
                    </x-responsive-nav-link>
                </form>
            </div>
        </div>
        @else
        <div class="pt-3 pb-4 border-t border-slate-800 space-y-1 px-4">

            <x-responsive-nav-link :href="route('login')">Connexion</x-responsive-nav-link>

            <x-responsive-nav-link :href="route('register')">Inscription</x-responsive-nav-link>

        </div>
        @endauth
    </div>
</nav>
