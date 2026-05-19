@php
    $isDashboard = request()->routeIs('dashboard');
    $isMap = request()->routeIs('map.*');
    $isItinerary = request()->routeIs('itineraries.*');
    $isFavorites = request()->routeIs('places.favorites');
    $isProfile = request()->routeIs('profile.*');
@endphp

<nav class="fixed bottom-0 left-0 right-0 z-40 bg-white/95 border-t border-slate-200 px-6 pb-4 pt-2 backdrop-blur-xl dark:bg-slate-950/95 dark:border-slate-800 transition-colors duration-150 sm:hidden">
    <div class="max-w-md mx-auto flex justify-between items-center text-[11px]">
        <a
            href="{{ route('map.index') }}"
            class="flex flex-col items-center gap-1 min-w-[60px] py-2 {{ $isMap ? 'text-primary' : 'text-slate-600 dark:text-slate-400' }} active:opacity-80 transition"
        >
            <span
                class="material-symbols-outlined"
                style="font-variation-settings: 'FILL' {{ $isMap ? 1 : 0 }}"
            >
                explore
            </span>
            <span class="font-semibold uppercase tracking-[0.18em]">Carte</span>
        </a>

        <a
            href="{{ route('places.favorites') }}"
            class="flex flex-col items-center gap-1 {{ $isFavorites ? 'text-primary' : 'text-slate-500' }}"
        >
            <span
                class="material-symbols-outlined"
                style="font-variation-settings: 'FILL' {{ $isFavorites ? 1 : 0 }}"
            >
                favorite
            </span>
            <span class="font-medium uppercase tracking-[0.18em]">Favoris</span>
        </a>

        <a
            href="{{ route('itineraries.create') }}"
            class="flex flex-col items-center gap-1 min-w-[60px] py-2 {{ $isItinerary ? 'text-primary' : 'text-slate-600 dark:text-slate-400' }} active:opacity-80 transition"
        >
            <span
                class="material-symbols-outlined"
                style="font-variation-settings: 'FILL' {{ $isItinerary ? 1 : 0 }}"
            >
                route
            </span>
            <span class="font-medium uppercase tracking-[0.18em]">Parcours</span>
        </a>

        <a
            href="{{ route('profile.edit') }}"
            class="flex flex-col items-center gap-1 min-w-[60px] py-2 {{ $isProfile ? 'text-primary' : 'text-slate-600 dark:text-slate-400' }} active:opacity-80 transition"
        >
            <span
                class="material-symbols-outlined"
                style="font-variation-settings: 'FILL' {{ $isProfile ? 1 : 0 }}"
            >
                person
            </span>
            <span class="font-medium uppercase tracking-[0.18em]">Profil</span>
        </a>
    </div>
</nav>

