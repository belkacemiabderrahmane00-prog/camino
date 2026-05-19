@php
    /** @var \App\Models\User $user */
    $user = auth()->user();
@endphp

<x-app-layout>
    <div class="max-w-4xl mx-auto px-4 pt-10 pb-24">
        <!-- Hero profile header -->
        <header class="flex flex-col items-center text-center mb-8">
            <div class="relative mb-4">
                <div class="w-28 h-28 sm:w-32 sm:h-32 rounded-full border-4 border-primary p-1 bg-slate-900 shadow-xl overflow-hidden">
                    <div class="w-full h-full rounded-full bg-gradient-to-tr from-cyan-400 via-primary to-sky-500 flex items-center justify-center text-2xl font-semibold text-slate-900">
                        {{ strtoupper(mb_substr($user->name, 0, 1)) }}
                    </div>
                </div>
                <button
                    type="button"
                    onclick="window.CaminoTheme && window.CaminoTheme.toggle()"
                    class="absolute -bottom-1 -right-1 bg-primary text-slate-900 rounded-full p-1.5 shadow-lg border-2 border-slate-900"
                    aria-label="Basculer le thème"
                >
                    <span class="material-symbols-outlined text-[16px]">dark_mode</span>
                </button>
            </div>

            <h1 class="text-2xl sm:text-3xl font-semibold tracking-tight text-slate-900 dark:text-slate-50">
                {{ $user->name ?? 'Explorateur CAMINO' }}
            </h1>
            <p class="text-xs sm:text-sm text-slate-600 dark:text-slate-400 mt-1">
                Membre depuis {{ optional($user->created_at)->translatedFormat('F Y') }}
            </p>

            <div class="mt-5 flex items-center justify-center gap-6 text-xs text-slate-600 dark:text-slate-300">
                <div class="flex flex-col items-center">
                    <span class="text-lg font-semibold">
                        {{ $user->itineraries()->count() }}
                    </span>
                    <span class="uppercase tracking-[0.14em] text-[10px] text-slate-600 dark:text-slate-400">Parcours</span>
                </div>
                <div class="h-9 w-px bg-slate-700/70"></div>
                <div class="flex flex-col items-center">
                    <span class="text-lg font-semibold">
                        {{ $user->savedPlaces()->count() }}
                    </span>
                    <span class="uppercase tracking-[0.14em] text-[10px] text-slate-600 dark:text-slate-400">Lieux</span>
                </div>
                <div class="h-9 w-px bg-slate-700/70"></div>
                <div class="flex flex-col items-center">
                    <span class="text-lg font-semibold">5</span>
                    <span class="uppercase tracking-[0.14em] text-[10px] text-slate-600 dark:text-slate-400">Niveau</span>
                </div>
            </div>
        </header>

        <div class="grid gap-6 lg:grid-cols-[minmax(0,1.1fr)_minmax(0,1fr)]">
            <!-- Preferences & about (UI) -->
            <div class="space-y-5">
                @if($itineraries->isEmpty())
                    <x-ui.card glass class="bg-white dark:bg-slate-950/90 space-y-4 border border-slate-200 dark:border-slate-800">
                        <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-100 flex items-center gap-2">
                            <span class="material-symbols-outlined text-primary text-[20px]">route</span>
                            Mes parcours
                        </h2>
                        <p class="text-[11px] text-slate-600 dark:text-slate-400">
                            Tu n'as pas encore créé de parcours. Génère ton premier itinéraire culturel en quelques clics.
                        </p>
                        <a href="{{ route('itineraries.create') }}" class="inline-flex items-center gap-2 text-xs font-semibold text-primary hover:underline">
                            <span class="material-symbols-outlined text-[18px]">add_circle</span>
                            Créer un parcours
                        </a>
                    </x-ui.card>
                @else
                    <x-ui.card glass class="bg-white dark:bg-slate-950/90 space-y-4 border border-slate-200 dark:border-slate-800">
                        <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-100 flex items-center gap-2">
                            <span class="material-symbols-outlined text-primary text-[20px]">route</span>
                            Mes parcours
                        </h2>
                        <div class="space-y-2 max-h-40 overflow-y-auto hide-scrollbar">
                            @foreach($itineraries as $itinerary)
                                <div class="rounded-2xl bg-slate-50 dark:bg-slate-900/80 border border-slate-200 dark:border-slate-800 px-3 py-2 text-xs">
                                    <p class="font-medium text-slate-900 dark:text-slate-100">{{ $itinerary->name }}</p>
                                    @php $r = $itinerary->result_json ?? []; @endphp
                                    <p class="text-[11px] text-slate-600 dark:text-slate-400 mt-0.5">
                                        {{ $r['estimated_total_minutes'] ?? '–' }} min · {{ number_format($r['estimated_total_budget'] ?? 0, 2, ',', ' ') }} €
                                    </p>
                                </div>
                            @endforeach
                        </div>
                        <a href="{{ route('itineraries.create') }}" class="block text-[11px] text-primary font-medium hover:underline">
                            Créer un parcours
                        </a>
                    </x-ui.card>
                @endif

                <x-ui.card glass class="bg-white dark:bg-slate-950/90 space-y-4 border border-slate-200 dark:border-slate-800">
                    <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-100 flex items-center gap-2">
                        <span class="material-symbols-outlined text-primary text-[20px]">tune</span>
                        Préférences
                    </h2>

                    <div class="space-y-4 text-xs">
                        <!-- Dark mode toggle (visuel, le vrai toggle est dans la nav) -->
                        <div class="flex items-center justify-between rounded-2xl bg-slate-50 dark:bg-slate-900/80 border border-slate-200 dark:border-slate-800 px-3 py-3">
                            <div class="flex items-center gap-3">
                                <div class="h-9 w-9 rounded-full bg-primary/15 flex items-center justify-center text-primary">
                                    <span class="material-symbols-outlined text-[18px]">dark_mode</span>
                                </div>
                                <div>
                                    <p class="text-slate-900 dark:text-slate-100 text-xs font-semibold">Mode sombre</p>
                                    <p class="text-[11px] text-slate-600 dark:text-slate-400">Adapter l’affichage pour la nuit</p>
                                </div>
                            </div>
                            <div>
                                <button
                                    type="button"
                                    onclick="window.CaminoTheme && window.CaminoTheme.toggle()"
                                    class="relative inline-flex h-6 w-11 items-center rounded-full bg-slate-700"
                                >
                                    <span class="absolute left-0.5 h-5 w-5 rounded-full bg-white shadow transition-transform translate-x-0 dark:translate-x-5"></span>
                                </button>
                            </div>
                        </div>

                        <!-- Preferred categories (UI only pour l’instant) -->
                        <div class="rounded-2xl bg-slate-50 dark:bg-slate-900/80 border border-slate-200 dark:border-slate-800 px-3 py-3">
                            <p class="text-xs font-semibold text-slate-900 dark:text-slate-100 mb-2">Catégories préférées</p>
                            <div class="flex flex-wrap gap-2">
                                <x-ui.badge tone="primary" size="md">Nature</x-ui.badge>
                                <x-ui.badge tone="neutral" size="md">Culture</x-ui.badge>
                                <x-ui.badge tone="primary" size="md">Histoire</x-ui.badge>
                                <x-ui.badge tone="neutral" size="md">Street Art</x-ui.badge>
                                <x-ui.badge tone="neutral" size="md">Gastronomie</x-ui.badge>
                            </div>
                        </div>
                    </div>
                </x-ui.card>

                <x-ui.card glass class="bg-white dark:bg-slate-950/90 space-y-3 border border-slate-200 dark:border-slate-800">
                    <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-100 flex items-center gap-2">
                        <span class="material-symbols-outlined text-primary text-[20px]">info</span>
                        À propos
                    </h2>
                    <div class="divide-y divide-slate-200 dark:divide-slate-800 text-xs">
                        <a href="#" class="flex items-center justify-between py-3 hover:bg-slate-50 dark:hover:bg-slate-900/80 rounded-2xl px-2 transition">
                            <div class="flex items-center gap-3">
                                <span class="material-symbols-outlined text-slate-500 dark:text-slate-400 text-[18px]">help</span>
                                <span class="font-medium text-slate-900 dark:text-slate-100">Aide &amp; support</span>
                            </div>
                            <span class="material-symbols-outlined text-slate-500 text-[18px]">chevron_right</span>
                        </a>
                        <a href="#" class="flex items-center justify-between py-3 hover:bg-slate-50 dark:hover:bg-slate-900/80 rounded-2xl px-2 transition">
                            <div class="flex items-center gap-3">
                                <span class="material-symbols-outlined text-slate-500 dark:text-slate-400 text-[18px]">gavel</span>
                                <span class="font-medium text-slate-900 dark:text-slate-100">Mentions légales</span>
                            </div>
                            <span class="material-symbols-outlined text-slate-500 text-[18px]">chevron_right</span>
                        </a>
                        <div class="flex items-center justify-between py-3 px-2">
                            <div class="flex items-center gap-3">
                                <span class="material-symbols-outlined text-slate-500 dark:text-slate-400 text-[18px]">verified</span>
                                <span class="font-medium text-slate-900 dark:text-slate-100">Version</span>
                            </div>
                            <span class="text-[11px] text-slate-600 dark:text-slate-400 font-mono">v0.1.0 (MVP)</span>
                        </div>
                    </div>
                </x-ui.card>

                <form method="POST" action="{{ route('logout') }}" class="mt-1">
                    @csrf
                    <x-ui.button
                        variant="outline"
                        size="lg"
                        class="w-full rounded-2xl border-rose-500/40 text-rose-400 hover:bg-rose-500/10 hover:border-rose-400 text-xs"
                    >
                        <span class="material-symbols-outlined text-[18px]">logout</span>
                        Déconnexion
                    </x-ui.button>
                </form>
            </div>

            <!-- Account forms (Breeze) -->
            <div class="space-y-5">
                <x-ui.card glass class="bg-white dark:bg-slate-950/90 border border-slate-200 dark:border-slate-800">
                    @include('profile.partials.update-profile-information-form')
                </x-ui.card>

                <x-ui.card glass class="bg-white dark:bg-slate-950/90 border border-slate-200 dark:border-slate-800">
                    @include('profile.partials.update-password-form')
                </x-ui.card>

                <x-ui.card glass class="bg-white dark:bg-slate-950/90 border border-slate-200 dark:border-slate-800">
                    @include('profile.partials.delete-user-form')
                </x-ui.card>
            </div>
        </div>
    </div>
</x-app-layout>

