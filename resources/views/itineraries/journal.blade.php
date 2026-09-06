@php
    /** Carnet de voyage : mise en page magazine (couverture, ouverture, chapitres, tracé, chiffres). */
    $modeLabel = match ($stats['mode']) { 'bike' => __('à vélo'), 'transit' => __('à pied et en transports'), default => __('à pied') };
    $hours = intdiv($stats['minutes'], 60);
    $mins = $stats['minutes'] % 60;
    $durationLabel = $hours > 0 ? $hours . ' h' . ($mins ? ' ' . str_pad((string) $mins, 2, '0', STR_PAD_LEFT) : '') : $mins . ' min';
    $startLabel = $result['start']['label'] ?? '';
    $startsAt = ! empty($result['starts_at']) ? \Illuminate\Support\Carbon::parse($result['starts_at']) : null;
    $endsAt = ! empty($result['ends_at']) ? \Illuminate\Support\Carbon::parse($result['ends_at']) : null;
    $shareUrl = $shared ? route('itineraries.shared-journal', $token) : ($itinerary->share_token ? route('itineraries.shared-journal', $itinerary->share_token) : null);
    $summary = __(':date. Départ de :start à :time. :n haltes, :km km :mode, retour vers :end.', [
        'date' => ucfirst($date->translatedFormat('l j F Y')), 'start' => $startLabel ?: __('Paris'), 'time' => $startsAt ? $startsAt->format('H\hi') : '10h00',
        'n' => $stats['places'], 'km' => number_format($stats['km'], 1, ',', ' '), 'mode' => $modeLabel, 'end' => $endsAt ? $endsAt->format('H\hi') : '',
    ]);
    $intro = __('Un parcours calculé par CAMINO selon le temps, la météo et les envies du jour. Voici ce qu\'il en reste : des lieux, des images, des heures.');
@endphp
<x-app-layout :title="__('Carnet de voyage') . ' · ' . $itinerary->name" :bottom-nav="false" :description="$summary">
    @push('meta')
        @if($cover && $cover['photo'])<meta property="og:image" content="{{ $cover['photo'] }}">@endif
        <meta property="og:title" content="{{ $itinerary->name }} · {{ __('Carnet de voyage') }}">
        <style>
            @media print { header, footer, nav, .no-print { display: none !important; } .journal-page { break-inside: avoid; } body { background: #fff !important; } }
        </style>
    @endpush

    <article class="journal">
        {{-- ===================================================== Couverture --}}
        <section class="relative min-h-[92vh] flex items-end overflow-hidden bg-ink-fixed text-white">
            @if($cover && $cover['photo'])
                <img src="{{ $cover['photo'] }}" alt="" class="absolute inset-0 w-full h-full object-cover kenburns">
            @else
                <div class="absolute inset-0 placeholder-cover" style="--c1:#0F8B8D;--c2:#12161C"></div>
            @endif
            <div class="absolute inset-0 bg-gradient-to-t from-ink-fixed via-ink-fixed/35 to-transparent"></div>
            <div class="absolute inset-0 bg-gradient-to-r from-ink-fixed/40 to-transparent"></div>
            <div class="relative max-w-6xl mx-auto w-full px-5 sm:px-8 pb-12 sm:pb-16 pt-32">
                <p class="text-[11px] font-bold uppercase tracking-[0.28em] text-sun">{{ __('Carnet de voyage') }} · CAMINO</p>
                <h1 class="display text-[13vw] sm:text-7xl lg:text-8xl mt-3 max-w-5xl leading-[0.95]">{{ $itinerary->name }}</h1>
                <p class="mt-5 font-display italic text-xl sm:text-2xl text-white/85">{{ ucfirst($date->translatedFormat('l j F Y')) }}</p>
                <div class="mt-6 flex flex-wrap gap-x-6 gap-y-2 text-sm text-white/80">
                    <span class="inline-flex items-center gap-1.5"><span class="material-symbols-outlined text-sun" style="font-size:18px">museum</span>{{ trans_choice(':n lieu|:n lieux', $stats['places'], ['n' => $stats['places']]) }}</span>
                    <span class="inline-flex items-center gap-1.5"><span class="material-symbols-outlined text-sun" style="font-size:18px">{{ $stats['mode'] === 'bike' ? 'directions_bike' : ($stats['mode'] === 'transit' ? 'directions_subway' : 'directions_walk') }}</span>{{ number_format($stats['km'], 1, ',', ' ') }} km {{ $modeLabel }}</span>
                    <span class="inline-flex items-center gap-1.5"><span class="material-symbols-outlined text-sun" style="font-size:18px">schedule</span>{{ $durationLabel }}</span>
                    @if($stats['photos'])<span class="inline-flex items-center gap-1.5"><span class="material-symbols-outlined text-sun" style="font-size:18px">photo_camera</span>{{ trans_choice(':n photo|:n photos', $stats['photos'], ['n' => $stats['photos']]) }}</span>@endif
                </div>
                @if($cover && $cover['title'])<p class="mt-8 text-[11px] text-white/50 uppercase tracking-widest">{{ __('En couverture') }} · {{ $cover['title'] }}</p>@endif
            </div>
            <a href="#ouverture" class="absolute bottom-5 left-1/2 -translate-x-1/2 text-white/70 animate-bounce no-print" aria-label="{{ __('Défiler') }}"><span class="material-symbols-outlined" style="font-size:30px">keyboard_arrow_down</span></a>
        </section>

        {{-- ===================================================== Ouverture --}}
        <section id="ouverture" class="max-w-6xl mx-auto px-5 sm:px-8 py-16 sm:py-24 grid gap-10 lg:grid-cols-[1.1fr_1fr] items-center">
            <div>
                <p class="eyebrow">{{ __('Ouverture') }}</p>
                <p class="mt-4 font-display text-2xl sm:text-4xl leading-snug text-ink first-letter:text-6xl sm:first-letter:text-8xl first-letter:font-display first-letter:float-left first-letter:mr-3 first-letter:leading-[0.8] first-letter:text-coral">{{ $summary }}</p>
                <p class="mt-6 text-ink-soft max-w-xl leading-relaxed">{{ $intro }}</p>
                @if(!empty($result['weather']['label']))
                    <p class="mt-4 inline-flex items-center gap-2 rounded-full bg-sun-soft px-3 py-1.5 text-xs font-semibold text-amber-800"><span class="material-symbols-outlined filled" style="font-size:16px">{{ $result['weather']['icon'] ?? 'partly_cloudy_day' }}</span>{{ __($result['weather']['label']) }}{{ isset($result['weather']['temp']) && $result['weather']['temp'] !== null ? ' · ' . round($result['weather']['temp']) . '°' : '' }}</p>
                @endif
            </div>
            <div class="card overflow-hidden rounded-4xl">
                <div id="journal-map" class="h-72 sm:h-96"></div>
                <div class="px-5 py-3 text-xs text-ink-muted flex items-center justify-between"><span>{{ __('Le tracé') }}</span><span>{{ $startLabel }}</span></div>
            </div>
        </section>

        {{-- ===================================================== Chapitres --}}
        @foreach($pages as $page)
            @php $even = $loop->index % 2 === 1; $lunch = $page['kind'] === 'lunch'; @endphp
            <section class="journal-page max-w-6xl mx-auto px-5 sm:px-8 py-14 sm:py-20 border-t border-ink/5">
                <div class="grid gap-8 lg:gap-14 lg:grid-cols-12 items-start">
                    <div class="lg:col-span-7 {{ $even ? 'lg:order-2' : '' }}">
                        <figure class="relative rounded-4xl overflow-hidden shadow-float aspect-[4/3] placeholder-cover" style="--c1:{{ \App\Services\ColorHelper::forSlug($page['slug'] ?? null) }};--c2:#12161C">
                            @if($page['photo'])<img src="{{ $page['photo'] }}" alt="{{ $page['title'] }}" loading="lazy" class="absolute inset-0 w-full h-full object-cover">@else<span class="absolute inset-0 flex items-center justify-center"><span class="material-symbols-outlined text-white/80" style="font-size:64px">{{ $lunch ? 'restaurant' : 'place' }}</span></span>@endif
                            <figcaption class="absolute bottom-0 inset-x-0 p-4 sm:p-5 text-white bg-gradient-to-t from-ink-fixed/80 to-transparent flex items-end justify-between gap-3">
                                <span class="text-xs sm:text-sm">@if($page['arrive_at']){{ __('Arrivée') }} {{ $page['arrive_at'] }}@endif @if($page['visit_minutes'])· {{ $page['visit_minutes'] }} {{ __('min sur place') }}@endif</span>
                                @if($page['is_free'])<span class="badge bg-white/90 text-ink-fixed">{{ __('Gratuit') }}</span>@endif
                            </figcaption>
                        </figure>
                        @if($page['photos']->isNotEmpty())
                            <div class="mt-3 grid grid-cols-3 gap-3">
                                @foreach($page['photos'] as $photo)
                                    <img src="{{ $photo->url }}" alt="{{ $photo->caption }}" loading="lazy" class="aspect-square w-full object-cover rounded-2xl shadow-card {{ $loop->index === 0 && $page['photos']->count() === 1 ? 'col-span-2' : '' }}">
                                @endforeach
                            </div>
                            <p class="mt-2 text-[11px] text-ink-muted">{{ __('Tes photos') }}</p>
                        @endif
                    </div>
                    <div class="lg:col-span-5 {{ $even ? 'lg:order-1 lg:text-right' : '' }}">
                        <p class="font-display text-7xl sm:text-8xl leading-none text-ink/10 select-none">{{ str_pad((string) $page['index'], 2, '0', STR_PAD_LEFT) }}</p>
                        <p class="eyebrow -mt-4">{{ $lunch ? __('Pause déjeuner') : $page['category'] }}</p>
                        <h2 class="display text-3xl sm:text-5xl mt-2 leading-[1.02]"><a href="{{ $page['place'] ? route('places.show', $page['place']) : '#' }}" class="hover:text-coral transition">{{ $page['title'] }}</a></h2>
                        @if($page['address'])<p class="mt-2 text-sm text-ink-muted">{{ $page['address'] }}</p>@endif
                        @if($page['excerpt'])
                            <p class="mt-6 font-display italic text-lg sm:text-xl leading-relaxed text-ink-soft {{ $even ? 'lg:border-r-2 lg:pr-5' : 'border-l-2 pl-5' }} border-coral">{{ $page['excerpt'] }}</p>
                        @endif
                        <div class="mt-5 flex flex-wrap gap-1.5 {{ $even ? 'lg:justify-end' : '' }}">
                            @if($page['reason'])<span class="badge bg-teal-soft text-teal-dark"><span class="material-symbols-outlined" style="font-size:12px">auto_awesome</span>{{ __($page['reason']) }}</span>@endif
                            @if($page['travel_minutes'])<span class="badge badge-paid"><span class="material-symbols-outlined" style="font-size:12px">{{ $page['travel_mode'] === 'transit' ? 'directions_subway' : ($page['travel_mode'] === 'bike' ? 'directions_bike' : 'directions_walk') }}</span>{{ $page['travel_minutes'] }} {{ __('min de trajet') }}</span>@endif
                        </div>
                    </div>
                </div>
            </section>
        @endforeach

        {{-- ===================================================== Dernière page --}}
        <section class="bg-ink text-white">
            <div class="max-w-6xl mx-auto px-5 sm:px-8 py-16 sm:py-24">
                <p class="text-[11px] font-bold uppercase tracking-[0.28em] text-sun">{{ __('En chiffres') }}</p>
                <div class="mt-6 grid grid-cols-2 sm:grid-cols-4 gap-4">
                    @foreach([[$stats['places'], __('lieux')], [number_format($stats['km'], 1, ',', ' ') . ' km', $modeLabel], [$durationLabel, __('de balade')], [$stats['cost'] > 0 ? number_format($stats['cost'], 0, ',', ' ') . ' €' : __('0 €'), __('dépensés')]] as [$value, $label])
                        <div class="rounded-3xl bg-white/10 p-5"><p class="font-display text-4xl sm:text-5xl">{{ $value }}</p><p class="text-xs text-white/60 mt-1">{{ $label }}</p></div>
                    @endforeach
                </div>
                <div class="mt-12 grid gap-8 lg:grid-cols-[1fr_auto] items-end">
                    <div>
                        <p class="font-display text-3xl sm:text-4xl leading-tight">{{ __('Une journée signée CAMINO.') }}</p>
                        <p class="mt-3 text-white/70 max-w-lg">{{ __('Génère ton propre parcours selon ton temps, ton budget et la météo, puis laisse-toi guider à la voix.') }}</p>
                    </div>
                    <div class="flex flex-wrap gap-2 no-print" x-data="{ copied: false }">
                        @if($shareUrl)
                            <button type="button" @click="navigator.clipboard.writeText(@js($shareUrl)); copied = true; setTimeout(() => copied = false, 2500)" class="btn btn-md bg-white/10 text-white border border-white/15 hover:bg-white/20"><span class="material-symbols-outlined" style="font-size:18px" x-text="copied ? 'check' : 'link'"></span><span x-text="copied ? @js(__('Lien copié')) : @js(__('Copier le lien'))"></span></button>
                            <a href="https://wa.me/?text={{ rawurlencode($itinerary->name . ' · ' . $shareUrl) }}" target="_blank" rel="noopener" class="btn btn-md bg-white/10 text-white border border-white/15 hover:bg-white/20"><span class="material-symbols-outlined" style="font-size:18px">chat</span>WhatsApp</a>
                        @elseif(!$shared)
                            <form method="POST" action="{{ route('itineraries.share', $itinerary) }}">@csrf<button class="btn btn-md bg-white/10 text-white border border-white/15 hover:bg-white/20"><span class="material-symbols-outlined" style="font-size:18px">share</span>{{ __('Créer le lien de partage') }}</button></form>
                        @endif
                        <button type="button" onclick="window.print()" class="btn btn-md bg-white/10 text-white border border-white/15 hover:bg-white/20"><span class="material-symbols-outlined" style="font-size:18px">picture_as_pdf</span>{{ __('Imprimer / PDF') }}</button>
                        @if($shared)
                            <form method="POST" action="{{ route('itineraries.shared-open', $token) }}">@csrf<button class="btn btn-md btn-primary"><span class="material-symbols-outlined" style="font-size:18px">navigation</span>{{ __('Refaire ce parcours') }}</button></form>
                        @else
                            <a href="{{ route('itineraries.show', $itinerary) }}" class="btn btn-md btn-primary"><span class="material-symbols-outlined" style="font-size:18px">arrow_back</span>{{ __('Retour au parcours') }}</a>
                        @endif
                    </div>
                </div>
            </div>
        </section>
    </article>

    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const el = document.getElementById('journal-map'); if (!el || !window.L) return;
            const C = window.Camino, r = @js(['start' => $result['start'] ?? null, 'end' => $result['end'] ?? null, 'steps' => collect($pages)->map(fn ($p) => ['lat' => $p['lat'], 'lng' => $p['lng'], 'order' => $p['index'], 'kind' => $p['kind']])->all(), 'geometry' => $result['geometry'] ?? [], 'mode' => $result['mode'] ?? 'walk']);
            if (!r.start) return;
            const map = L.map(el, { zoomControl: false, scrollWheelZoom: false, dragging: false, touchZoom: false, doubleClickZoom: false, attributionControl: true }); C.tileLayer().addTo(map);
            const pts = r.geometry.length > 1 ? r.geometry : [[r.start.lat, r.start.lng], ...r.steps.map(s => [s.lat, s.lng])];
            const line = L.polyline(pts, { color: r.mode === 'bike' ? '#0F8B8D' : (r.mode === 'transit' ? '#1D4ED8' : '#FF5A3C'), weight: 5, opacity: 0.9 }).addTo(map);
            L.marker([r.start.lat, r.start.lng], { icon: C.stepIcon(0, true) }).addTo(map);
            r.steps.forEach(s => L.marker([s.lat, s.lng], { icon: s.kind === 'lunch' ? C.placeIcon('restauration', { size: 30 }) : C.stepIcon(s.order) }).addTo(map));
            if (r.end) L.marker([r.end.lat, r.end.lng], { icon: C.stepIcon('<span class="material-symbols-outlined" style="font-size:16px">sports_score</span>') }).addTo(map);
            map.fitBounds(line.getBounds(), { padding: [28, 28] });
        });
    </script>
    @endpush
</x-app-layout>
