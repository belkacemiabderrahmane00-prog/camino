<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <div>
                <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-slate-600 dark:text-slate-500">Accueil</p>
                <h2 class="text-xl font-semibold text-slate-900 dark:text-slate-50 leading-tight">
                    Ton espace CAMINO
                </h2>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-5xl mx-auto px-4 space-y-5">
            <x-ui.card glass class="bg-white dark:bg-slate-950/95 transition-colors duration-150">
                <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                    <div>
                        <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">
                            {{ auth()->user()->name ?? 'Explorateur CAMINO' }},
                        </p>
                        <p class="text-xs text-slate-600 dark:text-slate-400 mt-1">
                            commence par explorer la carte, générer un parcours ou retrouver tes favoris.
                        </p>
                    </div>
                    <div class="flex gap-2">
                        <a href="{{ route('map.index') }}">
                            <x-ui.button size="sm" variant="primary" class="rounded-full text-xs transition-transform duration-150 hover:-translate-y-0.5">
                                <span class="material-symbols-outlined text-[16px]">explore</span>
                                Carte culturelle
                            </x-ui.button>
                        </a>
                        <a href="{{ route('itineraries.create') }}">
                            <x-ui.button size="sm" variant="accent" class="rounded-full text-xs transition-transform duration-150 hover:-translate-y-0.5">
                                <span class="material-symbols-outlined text-[16px]">route</span>
                                Nouveau parcours
                            </x-ui.button>
                        </a>
                    </div>
                </div>
            </x-ui.card>

            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.card class="bg-white dark:bg-slate-950/95 transition-colors duration-150 border border-slate-200 dark:border-slate-800">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="text-[11px] uppercase tracking-[0.18em] text-slate-700 dark:text-slate-500">Explorer</p>
                            <h3 class="text-sm font-semibold text-slate-900 dark:text-slate-50 mt-1">Carte culturelle</h3>
                            <p class="mt-1 text-[11px] text-slate-600 dark:text-slate-400">
                                Découvre les lieux et événements autour de toi en temps réel.
                            </p>
                        </div>
                        <span class="material-symbols-outlined text-primary text-[26px]">map</span>
                    </div>
                    <div class="mt-3">
                        <a href="{{ route('map.index') }}">
                            <x-ui.button size="sm" variant="outline" class="rounded-full text-[11px]">
                                Ouvrir la carte
                            </x-ui.button>
                        </a>
                    </div>
                </x-ui.card>

                <x-ui.card class="bg-white dark:bg-slate-950/95 transition-colors duration-150 border border-slate-200 dark:border-slate-800">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="text-[11px] uppercase tracking-[0.18em] text-slate-700 dark:text-slate-500">Planifier</p>
                            <h3 class="text-sm font-semibold text-slate-900 dark:text-slate-50 mt-1">Parcours & itinéraires</h3>
                            <p class="mt-1 text-[11px] text-slate-600 dark:text-slate-400">
                                Génère un parcours adapté à ton temps et ton budget.
                            </p>
                        </div>
                        <span class="material-symbols-outlined text-camino-accent text-[26px]">route</span>
                    </div>
                    <div class="mt-3">
                        <a href="{{ route('itineraries.create') }}">
                            <x-ui.button size="sm" variant="outline" class="rounded-full text-[11px]">
                                Créer un parcours
                            </x-ui.button>
                        </a>
                    </div>
                </x-ui.card>

                <x-ui.card class="bg-white dark:bg-slate-950/95 transition-colors duration-150 border border-slate-200 dark:border-slate-800">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="text-[11px] uppercase tracking-[0.18em] text-slate-700 dark:text-slate-500">Mémoire</p>
                            <h3 class="text-sm font-semibold text-slate-900 dark:text-slate-50 mt-1">Mes favoris</h3>
                            <p class="mt-1 text-[11px] text-slate-600 dark:text-slate-400">
                                Retrouve les lieux que tu as enregistrés pour plus tard.
                            </p>
                        </div>
                        <span class="material-symbols-outlined text-pink-400 text-[26px]">favorite</span>
                    </div>
                    <div class="mt-3">
                        <a href="{{ route('places.favorites') }}">
                            <x-ui.button size="sm" variant="outline" class="rounded-full text-[11px]">
                                Voir mes favoris
                            </x-ui.button>
                        </a>
                    </div>
                </x-ui.card>

                <x-ui.card class="bg-white dark:bg-slate-950/95 transition-colors duration-150 border border-slate-200 dark:border-slate-800">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="text-[11px] uppercase tracking-[0.18em] text-slate-600 dark:text-slate-500">Profil</p>
                            <h3 class="text-sm font-semibold text-slate-900 dark:text-slate-50 mt-1">Compte & préférences</h3>
                            <p class="mt-1 text-[11px] text-slate-600 dark:text-slate-400">
                                Gère ton profil, tes préférences et la sécurité de ton compte.
                            </p>
                        </div>
                        <span class="material-symbols-outlined text-primary text-[26px]">person</span>
                    </div>
                    <div class="mt-3">
                        <a href="{{ route('profile.edit') }}">
                            <x-ui.button size="sm" variant="outline" class="rounded-full text-[11px]">
                                Gérer mon profil
                            </x-ui.button>
                        </a>
                    </div>
                </x-ui.card>
            </div>
        </div>
    </div>
</x-app-layout>
