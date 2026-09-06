<x-app-layout title="{{ __('Modération') }}">
    <section class="max-w-7xl mx-auto px-4 sm:px-6 pt-8 sm:pt-12" x-data="{ tab: 'places' }">
        <div class="mb-6">
            <p class="eyebrow mb-1.5">{{ __('Administration') }}</p>
            <h1 class="display text-4xl">{{ __('Modération') }}</h1>
        </div>
        <div class="flex gap-2 overflow-x-auto hide-scrollbar pb-2 mb-5">
            @foreach([['places', __('Lieux proposés'), 'add_location_alt'], ['photos', __('Photos'), 'photo_library'], ['alerts', __('Alertes actives'), 'campaign'], ['reports', __('Signalements'), 'flag']] as [$k, $l, $i])
                <button @click="tab = '{{ $k }}'" class="chip shrink-0" :data-active="tab === '{{ $k }}'"><span class="material-symbols-outlined" style="font-size:16px">{{ $i }}</span>{{ $l }}<span class="ml-1 rounded-full bg-ink/10 px-1.5 text-[10px]" :class="tab === '{{ $k }}' && 'bg-white/20'">{{ $counts[$k] }}</span></button>
            @endforeach
        </div>

        <div x-show="tab === 'places'" class="space-y-3">
            @forelse($pendingPlaces as $place)
                <div class="card p-4 flex flex-col sm:flex-row gap-4">
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2"><x-category-pill :category="$place->category" size="xs" /><span class="text-xs text-ink-muted">par {{ $place->creator->name ?? '?' }} · {{ $place->created_at->diffForHumans() }}</span></div>
                        <a href="{{ route('places.show', $place) }}" class="font-semibold mt-1 block hover:text-coral">{{ $place->title }}</a>
                        <p class="text-xs text-ink-muted">{{ $place->address }} · {{ $place->lat }}, {{ $place->lng }}</p>
                        <p class="text-sm text-ink-soft mt-1 line-clamp-3">{{ $place->description }}</p>
                    </div>
                    <div class="flex sm:flex-col gap-2 shrink-0">
                        <form method="POST" action="{{ route('moderation.places.update', $place) }}">@csrf<input type="hidden" name="action" value="approve"><button class="btn btn-sm btn-teal w-full">{{ __('Publier') }}</button></form>
                        <form method="POST" action="{{ route('moderation.places.update', $place) }}">@csrf<input type="hidden" name="action" value="reject"><button class="btn btn-sm btn-soft w-full">{{ __('Refuser') }}</button></form>
                    </div>
                </div>
            @empty
                <div class="card p-8 text-center text-sm text-ink-muted">{{ __('Aucun lieu en attente.') }}</div>
            @endforelse
        </div>

        <div x-show="tab === 'photos'" x-cloak class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
            @forelse($pendingPhotos as $photo)
                <div class="card overflow-hidden">
                    <img src="{{ $photo->url }}" alt="" class="w-full h-48 object-cover">
                    <div class="p-3">
                        <p class="text-sm font-semibold truncate">{{ $photo->place->title ?? '' }}</p>
                        <p class="text-xs text-ink-muted">{{ $photo->caption }} · {{ $photo->user->name ?? '?' }} · {{ round($photo->bytes / 1024) }} Ko</p>
                        <div class="flex gap-2 mt-2">
                            <form method="POST" action="{{ route('moderation.photos.update', $photo) }}" class="flex-1">@csrf<input type="hidden" name="action" value="approve"><button class="btn btn-sm btn-teal w-full">{{ __('Publier') }}</button></form>
                            <form method="POST" action="{{ route('moderation.photos.update', $photo) }}" class="flex-1">@csrf<input type="hidden" name="action" value="reject"><button class="btn btn-sm btn-soft w-full">{{ __('Refuser') }}</button></form>
                        </div>
                    </div>
                </div>
            @empty
                <div class="card p-8 text-center text-sm text-ink-muted sm:col-span-3">{{ __('Aucune photo en attente.') }}</div>
            @endforelse
        </div>

        <div x-show="tab === 'alerts'" x-cloak class="space-y-2">
            @forelse($alerts as $alert)
                <div class="card p-3 flex items-center gap-3">
                    <span class="h-9 w-9 rounded-full flex items-center justify-center shrink-0 text-white" style="background: {{ $alert->type_color }}"><span class="material-symbols-outlined" style="font-size:18px">{{ $alert->type_icon }}</span></span>
                    <div class="min-w-0 flex-1 text-sm"><p class="font-semibold">{{ $alert->type_label }} · {{ $alert->title }}</p><p class="text-xs text-ink-muted">{{ $alert->place->title ?? 'Position libre' }} · {{ $alert->user->name ?? '?' }} · expire {{ $alert->expires_at->diffForHumans() }}</p></div>
                    <form method="POST" action="{{ route('moderation.alerts.hide', $alert) }}">@csrf<button class="btn btn-sm btn-soft">{{ __('Masquer') }}</button></form>
                </div>
            @empty
                <div class="card p-8 text-center text-sm text-ink-muted">{{ __('Aucune alerte active.') }}</div>
            @endforelse
        </div>

        <div x-show="tab === 'reports'" x-cloak class="space-y-2">
            @forelse($reports as $report)
                <div class="card p-3 flex items-center gap-3">
                    <span class="material-symbols-outlined text-coral">flag</span>
                    <div class="min-w-0 flex-1 text-sm"><a href="{{ route('places.show', $report->place) }}" class="font-semibold hover:text-coral">{{ $report->place->title ?? '' }}</a><p class="text-xs text-ink-muted">{{ $report->reason }}{{ $report->message ? ' · ' . $report->message : '' }} · {{ $report->user->name ?? 'anonyme' }} · {{ $report->created_at->diffForHumans() }}</p></div>
                    <form method="POST" action="{{ route('moderation.reports.resolve', $report) }}">@csrf @method('DELETE')<button class="btn btn-sm btn-soft">{{ __('Traité') }}</button></form>
                </div>
            @empty
                <div class="card p-8 text-center text-sm text-ink-muted">{{ __('Aucun signalement.') }}</div>
            @endforelse
        </div>
    </section>
</x-app-layout>
