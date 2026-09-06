<x-app-layout title="{{ __('Mes favoris') }}">
    <section class="max-w-7xl mx-auto px-4 sm:px-6 pt-8 sm:pt-12">
        <div class="flex flex-wrap items-end justify-between gap-4 mb-6">
            <div>
                <p class="eyebrow mb-1.5">{{ __('Ma collection') }}</p>
                <h1 class="display text-4xl">{{ __('Mes favoris') }}</h1>
                <p class="mt-2 text-sm text-ink-muted">{{ $totalFavorites }} lieu{{ $totalFavorites > 1 ? 'x' : '' }} enregistré{{ $totalFavorites > 1 ? 's' : '' }}. Ajoute-les à un parcours en un clic depuis leur fiche.</p>
            </div>
            <form method="GET" action="{{ route('places.favorites') }}" class="card flex items-center gap-2 pl-4 pr-2 py-1.5 w-full sm:w-80">
                <span class="material-symbols-outlined text-ink-muted">search</span>
                <input type="search" name="q" value="{{ $search }}" placeholder="{{ __('Rechercher dans mes favoris') }}" class="flex-1 border-0 bg-transparent focus:ring-0 text-sm">
                @if($selectedCategoryId)<input type="hidden" name="category_id" value="{{ $selectedCategoryId }}">@endif
                <button class="btn btn-sm btn-ink">OK</button>
            </form>
        </div>

        <div class="flex gap-2 overflow-x-auto hide-scrollbar pb-2 mb-5">
            <a href="{{ route('places.favorites', array_filter(['q' => $search])) }}" class="chip shrink-0" data-active="{{ empty($selectedCategoryId) ? 'true' : 'false' }}">{{ __('Tous') }}</a>
            @foreach($categories as $cat)
                <a href="{{ route('places.favorites', array_filter(['q' => $search, 'category_id' => $cat->id])) }}" class="chip shrink-0" data-active="{{ $selectedCategoryId == $cat->id ? 'true' : 'false' }}">{{ $cat->name }}</a>
            @endforeach
        </div>

        @if($places->isNotEmpty())
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                @foreach($places as $place)
                    <x-place-card :place="$place" />
                @endforeach
            </div>
        @else
            <div class="card p-10 text-center">
                <span class="material-symbols-outlined text-4xl text-ink-muted">favorite</span>
                <p class="mt-3 font-semibold">{{ $totalFavorites > 0 ? 'Aucun favori ne correspond à ce filtre.' : 'Pas encore de favoris.' }}</p>
                <p class="text-sm text-ink-muted mt-1">{{ __('Explore la carte et enregistre les lieux qui te plaisent.') }}</p>
                <a href="{{ route('map.index') }}" class="btn btn-md btn-primary mt-4">{{ __('Ouvrir la carte') }}</a>
            </div>
        @endif
    </section>
</x-app-layout>
