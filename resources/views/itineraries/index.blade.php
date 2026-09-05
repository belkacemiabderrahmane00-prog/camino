<x-app-layout>
    <div class="max-w-4xl mx-auto px-4 py-6 sm:py-8 lg:py-10">
        <div class="mb-6 flex items-center justify-between gap-3">
            <div>
                <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-slate-500 dark:text-slate-400">Historique</p>
                <h1 class="text-2xl sm:text-3xl font-semibold tracking-tight text-slate-900 dark:text-slate-50">
                    Mes parcours
                </h1>
                <p class="mt-2 text-xs sm:text-sm text-slate-600 dark:text-slate-300">
                    Retrouve les parcours que tu as générés et relance-les en un clic.
                </p>
            </div>
            <a href="{{ route('itineraries.create') }}" class="inline-flex items-center gap-2 rounded-full bg-primary text-slate-900 px-4 py-2.5 text-xs font-semibold shadow-lg shadow-primary/30 transition-transform duration-150 hover:-translate-y-0.5">
                <span class="material-symbols-outlined text-[18px]">add</span>
                Nouveau
            </a>
        </div>

        @if(session('status'))
            <div class="mb-4 rounded-2xl border border-primary/40 bg-primary/10 px-4 py-2 text-xs text-slate-800 dark:text-slate-100">
                {{ session('status') }}
            </div>
        @endif

        <div class="space-y-3">
            @forelse($itineraries as $itinerary)
                @php
                    $result = $itinerary->result_json ?? [];
                    $steps = $result['steps'] ?? [];
                @endphp
                <x-ui.card glass class="bg-white border border-slate-300 shadow-lg shadow-slate-900/10 dark:bg-slate-950/95 dark:border-slate-800 dark:shadow-black/40 transition-colors duration-150">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-slate-900 dark:text-slate-50 truncate">{{ $itinerary->name }}</p>
                            <p class="mt-0.5 text-[11px] text-slate-500 dark:text-slate-400">
                                {{ $itinerary->created_at->translatedFormat('d F Y à H\hi') }}
                                · {{ count($steps) }} étape{{ count($steps) > 1 ? 's' : '' }}
                                · {{ $result['estimated_total_minutes'] ?? 0 }} min
                                · {{ number_format($result['estimated_total_budget'] ?? 0, 0, ',', ' ') }} €
                                @if(isset($result['total_distance_km']))
                                    · {{ number_format($result['total_distance_km'], 1, ',', ' ') }} km
                                @endif
                            </p>
                            @if(!empty($steps))
                                <p class="mt-1.5 text-[11px] text-slate-600 dark:text-slate-300 line-clamp-2">
                                    {{ collect($steps)->pluck('title')->implode(' → ') }}
                                </p>
                            @endif
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <form method="POST" action="{{ route('itineraries.replay', $itinerary) }}">
                                @csrf
                                <button type="submit" class="inline-flex items-center gap-1.5 rounded-full bg-primary/15 text-primary border border-primary/40 px-3 py-1.5 text-[11px] font-semibold hover:bg-primary/25 transition-colors">
                                    <span class="material-symbols-outlined text-[16px]">replay</span>
                                    Revoir
                                </button>
                            </form>
                            <form method="POST" action="{{ route('itineraries.destroy', $itinerary) }}" onsubmit="return confirm('Supprimer ce parcours ?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="inline-flex items-center justify-center rounded-full border border-slate-200 dark:border-slate-700 p-1.5 text-slate-500 hover:text-rose-500 hover:border-rose-400 transition-colors" title="Supprimer">
                                    <span class="material-symbols-outlined text-[16px]">delete</span>
                                </button>
                            </form>
                        </div>
                    </div>
                </x-ui.card>
            @empty
                <x-ui.card glass class="bg-white border border-slate-300 dark:bg-slate-950/95 dark:border-slate-800 text-center py-10">
                    <span class="material-symbols-outlined text-4xl text-slate-400 mb-2 block">route</span>
                    <p class="text-sm text-slate-700 dark:text-slate-300">Aucun parcours enregistré pour l'instant.</p>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Les parcours générés quand tu es connecté sont conservés ici.</p>
                    <a href="{{ route('itineraries.create') }}" class="mt-4 inline-flex items-center gap-2 rounded-full bg-primary text-slate-900 px-4 py-2 text-xs font-semibold">
                        <span class="material-symbols-outlined text-[16px]">route</span>
                        Générer un parcours
                    </a>
                </x-ui.card>
            @endforelse
        </div>

        @if($itineraries->hasPages())
            <div class="mt-6">{{ $itineraries->links() }}</div>
        @endif
    </div>
</x-app-layout>
