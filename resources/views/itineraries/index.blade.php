<x-app-layout title="{{ __('Mes parcours') }}">
    <section class="max-w-4xl mx-auto px-4 sm:px-6 pt-8 sm:pt-12">
        <div class="flex items-end justify-between gap-4 mb-6">
            <div>
                <p class="eyebrow mb-1.5">{{ __('Historique') }}</p>
                <h1 class="display text-4xl">{{ __('Mes parcours') }}</h1>
                <p class="mt-2 text-sm text-ink-muted">{{ __('Tes parcours générés, prêts à être relancés.') }}</p>
            </div>
            <a href="{{ route('itineraries.create') }}" class="btn btn-md btn-primary"><span class="material-symbols-outlined" style="font-size:18px">add</span>{{ __('Nouveau') }}</a>
        </div>

        <div class="space-y-3">
            @forelse($itineraries as $itinerary)
                @php $r = $itinerary->result_json ?? []; $steps = $r['steps'] ?? []; @endphp
                <div class="card p-4 sm:p-5 flex flex-col sm:flex-row gap-4">
                    <div class="flex -space-x-3 shrink-0">
                        @foreach(array_slice($steps, 0, 3) as $s)
                            <div class="h-14 w-14 rounded-2xl overflow-hidden border-2 border-white placeholder-cover flex items-center justify-center shadow-card">
                                @if(!empty($s['cover']))<img src="{{ $s['cover'] }}" alt="" class="w-full h-full object-cover">@else<span class="material-symbols-outlined text-white/80" style="font-size:18px">place</span>@endif
                            </div>
                        @endforeach
                    </div>
                    <div class="min-w-0 flex-1">
                        <a href="{{ route('itineraries.show', $itinerary) }}" class="font-semibold hover:text-coral transition">{{ $itinerary->name }}</a>
                        <p class="text-xs text-ink-muted mt-0.5">
                            {{ $itinerary->created_at->translatedFormat('j F Y à H\hi') }} · {{ count($steps) }} étape{{ count($steps) > 1 ? 's' : '' }}
                            · {{ isset($r['total_minutes']) ? floor($r['total_minutes'] / 60) . ' h ' . str_pad($r['total_minutes'] % 60, 2, '0', STR_PAD_LEFT) : ($r['estimated_total_minutes'] ?? 0) . ' min' }}
                            · {{ number_format($r['total_distance_km'] ?? 0, 1, ',', ' ') }} km
                            · {{ number_format($r['total_cost_eur'] ?? $r['estimated_total_budget'] ?? 0, 0) }} €
                        </p>
                        <p class="text-sm text-ink-soft mt-1.5 line-clamp-2">{{ collect($steps)->pluck('title')->implode(' → ') }}</p>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <form method="POST" action="{{ route('itineraries.replay', $itinerary) }}">@csrf<button class="btn btn-sm btn-soft"><span class="material-symbols-outlined" style="font-size:16px">replay</span>{{ __('Revoir') }}</button></form>
                        <form method="POST" action="{{ route('itineraries.destroy', $itinerary) }}" onsubmit="return confirm('Supprimer ce parcours ?');">@csrf @method('DELETE')<button class="btn btn-icon btn-ghost text-ink-muted hover:text-coral" title="{{ __('Supprimer') }}"><span class="material-symbols-outlined" style="font-size:18px">delete</span></button></form>
                    </div>
                </div>
            @empty
                <div class="card p-10 text-center">
                    <span class="material-symbols-outlined text-4xl text-ink-muted">route</span>
                    <p class="mt-3 font-semibold">{{ __('Aucun parcours enregistré.') }}</p>
                    <p class="text-sm text-ink-muted mt-1">{{ __('Les parcours générés quand tu es connecté sont conservés ici.') }}</p>
                    <a href="{{ route('itineraries.create') }}" class="btn btn-md btn-primary mt-4">{{ __('Générer un parcours') }}</a>
                </div>
            @endforelse
        </div>
        @if($itineraries->hasPages())<div class="mt-6">{{ $itineraries->links() }}</div>@endif
    </section>
</x-app-layout>
