@php
    /** @var \Illuminate\Database\Eloquent\Collection|\App\Models\Place[] $places */
@endphp

<x-app-layout>
    <div class="max-w-5xl mx-auto px-4 pt-8 pb-24">
        <header class="sticky top-0 z-10 bg-gradient-to-b from-white via-white/95 to-transparent dark:from-slate-950 dark:via-slate-950/95 dark:to-transparent -mx-4 px-4 pt-2 pb-4 transition-colors duration-150">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-50">Mes favoris</h1>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                        Retrouve rapidement tes lieux culturels préférés.
                    </p>
                </div>
            </div>

            <form method="GET" action="{{ route('places.favorites') }}" class="mt-3">
                @if($selectedCategoryId)
                    <input type="hidden" name="category_id" value="{{ $selectedCategoryId }}">
                @endif
                <div class="flex gap-2 items-center">
                    <div class="flex-1 relative">
                        <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-500 dark:text-slate-400 text-[18px]">search</span>
                        <input
                            type="search"
                            name="q"
                            value="{{ $search }}"
                            placeholder="Rechercher par nom ou adresse..."
                            class="w-full pl-10 pr-4 py-2.5 rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900/80 text-slate-900 dark:text-slate-100 text-sm placeholder:text-slate-500 focus:ring-2 focus:ring-primary focus:border-primary"
                        >
                    </div>
                    <button type="submit" class="p-2.5 rounded-full bg-primary text-slate-900 shrink-0">
                        <span class="material-symbols-outlined text-[18px]">search</span>
                    </button>
                </div>
            </form>

                <div class="flex gap-2 overflow-x-auto hide-scrollbar pb-2 text-xs">
                @php $queryParams = $search ? ['q' => $search] : []; @endphp
                <a href="{{ route('places.favorites', $queryParams) }}" class="shrink-0 px-4 py-1.5 rounded-full whitespace-nowrap transition-colors duration-150 {{ empty($selectedCategoryId) ? 'bg-primary text-slate-900 font-semibold shadow-sm shadow-primary/40' : 'bg-slate-100 border border-slate-200 text-slate-700 dark:bg-slate-900/80 dark:border-slate-700 dark:text-slate-200 hover:border-primary/70' }}">
                    Tous les lieux
                </a>
                @foreach($categories as $cat)
                    <a href="{{ route('places.favorites', array_merge($queryParams, ['category_id' => $cat->id])) }}" class="shrink-0 px-4 py-1.5 rounded-full whitespace-nowrap transition-colors duration-150 {{ $selectedCategoryId == $cat->id ? 'bg-primary text-slate-900 font-semibold shadow-sm shadow-primary/40' : 'bg-slate-100 border border-slate-200 text-slate-700 dark:bg-slate-900/80 dark:border-slate-700 dark:text-slate-200 hover:border-primary/70' }}">
                        {{ $cat->name }}
                    </a>
                @endforeach
            </div>
        </header>

        <main class="mt-4 space-y-5">
            @forelse($places as $place)
                <div class="group relative bg-white/95 dark:bg-slate-950/95 rounded-full p-4 border border-slate-200 dark:border-slate-800 shadow-camino-soft flex flex-col md:flex-row items-center gap-4 transition-colors duration-150">
                    <div class="relative w-full md:w-40 h-40 shrink-0 overflow-hidden rounded-full bg-gradient-to-tr from-slate-200 to-slate-100 dark:from-slate-800 dark:to-slate-700 flex items-center justify-center text-[11px] text-slate-700 dark:text-slate-200">
                        <span>{{ $place->category->name ?? 'Lieu culturel' }}</span>
                        <button class="absolute top-3 right-3 size-9 bg-slate-950/90 rounded-full flex items-center justify-center text-primary border border-slate-700 shadow-lg">
                            <span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1">favorite</span>
                        </button>
                    </div>

                    <div class="flex-1 flex flex-col items-center md:items-start text-center md:text-left pr-2">
                        <span class="text-[11px] font-bold uppercase tracking-[0.22em] text-primary mb-1">
                            {{ $place->category->name ?? 'Culture' }}
                        </span>
                        <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-50 mb-1">
                            {{ $place->title }}
                        </h2>
                        <div class="flex items-center justify-center md:justify-start gap-2 text-slate-500 dark:text-slate-400 text-[11px]">
                            <span class="material-symbols-outlined text-[14px]">location_on</span>
                            <span>{{ $place->address ?? 'Adresse à venir' }}</span>
                        </div>

                        <div class="mt-4 flex flex-wrap justify-center md:justify-start gap-2 text-[11px]">
                            <a href="{{ route('places.show', $place) }}" class="px-4 py-1.5 rounded-full bg-primary/10 text-primary font-semibold transition-transform duration-150 hover:-translate-y-0.5">
                                Voir le détail
                            </a>
                            @php $gmUrl = $place->getGoogleMapsUrl(); $wazeUrl = $place->getWazeUrl(); @endphp
                            @if($gmUrl)
                                <a href="{{ $gmUrl }}" target="_blank" rel="noopener" class="px-4 py-1.5 rounded-full bg-slate-100 text-slate-800 border border-slate-200 font-semibold inline-flex items-center gap-1 dark:bg-slate-900 dark:text-slate-200 dark:border-slate-700 transition-transform duration-150 hover:-translate-y-0.5 hover:border-primary/70 hover:text-primary">
                                    <span class="material-symbols-outlined text-[14px]">map</span>
                                    Google Maps
                                </a>
                            @endif
                            @if($wazeUrl)
                                <a href="{{ $wazeUrl }}" target="_blank" rel="noopener" class="px-4 py-1.5 rounded-full bg-slate-100 text-slate-800 border border-slate-200 font-semibold inline-flex items-center gap-1 dark:bg-slate-900 dark:text-slate-200 dark:border-slate-700 transition-transform duration-150 hover:-translate-y-0.5 hover:border-primary/70 hover:text-primary">
                                    <span class="material-symbols-outlined text-[14px]">directions_car</span>
                                    Waze
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <div class="rounded-3xl bg-white/95 dark:bg-slate-950/95 border border-slate-200 dark:border-slate-800 px-5 py-10 text-center transition-colors duration-150">
                    @if($totalFavorites > 0 && $selectedCategoryId > 0)
                        <span class="material-symbols-outlined text-4xl text-slate-400 dark:text-slate-500 mb-3 block">filter_alt_off</span>
                        <p class="text-sm text-slate-600 dark:text-slate-300 mb-3">Aucun favori dans cette catégorie.</p>
                        <a href="{{ route('places.favorites') }}" class="inline-flex items-center gap-2 rounded-full bg-primary/10 text-primary font-semibold px-4 py-2 text-xs hover:bg-primary/20 transition">
                            <span class="material-symbols-outlined text-[18px]">refresh</span>
                            Voir tous les favoris
                        </a>
                    @else
                        <span class="material-symbols-outlined text-4xl text-slate-400 dark:text-slate-500 mb-3 block">favorite</span>
                        <p class="text-sm text-slate-600 dark:text-slate-300 mb-3">Pas encore de favoris.</p>
                        <a href="{{ route('map.index') }}" class="inline-flex items-center gap-2 rounded-full bg-primary/10 text-primary font-semibold px-4 py-2 text-xs hover:bg-primary/20 transition">
                            <span class="material-symbols-outlined text-[18px]">explore</span>
                            Explorer la carte
                        </a>
                    @endif
                </div>
            @endforelse
        </main>
    </div>
</x-app-layout>

