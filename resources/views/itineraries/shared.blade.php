@php $steps = $result['steps'] ?? []; $startsAt = !empty($result['starts_at']) ? \Illuminate\Support\Carbon::parse($result['starts_at']) : null; @endphp
<x-app-layout :title="$itinerary->name" :description="'Parcours partagé sur CAMINO : ' . $itinerary->name">
    <section class="max-w-4xl mx-auto px-4 sm:px-6 pt-6 sm:pt-12">
        <div class="card overflow-hidden">
            <div id="itinerary-map" class="h-[300px] sm:h-[420px]"></div>
            <div class="p-5 sm:p-8">
                <p class="eyebrow">Parcours partagé{{ $itinerary->user ? ' par ' . $itinerary->user->name : '' }}</p>
                <h1 class="display text-3xl sm:text-4xl mt-1">{{ $itinerary->name }}</h1>
                <p class="text-sm text-ink-muted mt-1">
                    {{ $result['start']['label'] ?? '' }}{{ !empty($result['end']['label']) ? ' → ' . $result['end']['label'] : '' }}
                    @if($startsAt) · {{ ucfirst($startsAt->translatedFormat('l j F')) }} à {{ $startsAt->format('H\hi') }} @endif
                    · {{ ($result['mode'] ?? 'walk') === 'bike' ? 'à vélo' : 'à pied' }} · {{ floor(($result['total_minutes'] ?? 0) / 60) }} h {{ str_pad(($result['total_minutes'] ?? 0) % 60, 2, '0', STR_PAD_LEFT) }} · {{ number_format($result['total_distance_km'] ?? 0, 1, ',', ' ') }} km
                </p>
                <ol class="mt-6 space-y-3">
                    @foreach($steps as $i => $step)
                        <li>
                            <a href="{{ route('places.show', $step['place_id']) }}" class="card card-hover flex gap-3 p-2.5">
                                <div class="w-16 h-16 rounded-xl overflow-hidden shrink-0 placeholder-cover flex items-center justify-center">
                                    @if(!empty($step['cover']))<img src="{{ $step['cover'] }}" alt="" loading="lazy" class="w-full h-full object-cover">@else<span class="material-symbols-outlined text-white/80">place</span>@endif
                                </div>
                                <div class="min-w-0 flex-1 self-center">
                                    <p class="text-[10px] font-semibold text-ink-muted uppercase tracking-wider">{{ ($step['kind'] ?? 'visit') === 'lunch' ? 'Pause déjeuner' : ($step['category'] ?? '') }}</p>
                                    <p class="font-semibold leading-snug">{{ $step['order'] ?? $i + 1 }}. {{ $step['title'] }}</p>
                                    <p class="text-xs text-ink-muted mt-0.5">{{ $step['arrive_at'] ?? '' }} → {{ $step['leave_at'] ?? '' }} · {{ $step['visit_minutes'] ?? '' }} min sur place</p>
                                </div>
                            </a>
                        </li>
                    @endforeach
                </ol>
                <div class="mt-6 flex flex-wrap gap-2">
                    <form method="POST" action="{{ route('itineraries.shared-open', $token) }}">@csrf<button class="btn btn-md btn-primary"><span class="material-symbols-outlined" style="font-size:18px">navigation</span>Ouvrir dans CAMINO</button></form>
                    <a href="{{ route('itineraries.shared-gpx', $token) }}" class="btn btn-md btn-soft"><span class="material-symbols-outlined" style="font-size:18px">download</span>GPX</a>
                    @guest<a href="{{ route('register') }}" class="btn btn-md btn-ghost">Créer mon compte</a>@endguest
                </div>
                <p class="mt-3 text-[11px] text-ink-muted">« Ouvrir dans CAMINO » copie ce parcours chez toi : tu peux le suivre avec le guidage vocal ou le modifier.</p>
            </div>
        </div>
    </section>
    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const el = document.getElementById('itinerary-map'); if (!el || !window.L) return;
            const C = window.Camino, r = @js(['start' => $result['start'] ?? null, 'end' => $result['end'] ?? null, 'steps' => collect($steps)->map(fn ($s) => ['lat' => $s['lat'], 'lng' => $s['lng'], 'order' => $s['order'] ?? 0])->all(), 'geometry' => $result['geometry'] ?? [], 'mode' => $result['mode'] ?? 'walk']);
            if (!r.start) return;
            const map = L.map(el, { zoomControl: false, scrollWheelZoom: false }); C.tileLayer().addTo(map);
            const pts = r.geometry.length > 1 ? r.geometry : [[r.start.lat, r.start.lng], ...r.steps.map(s => [s.lat, s.lng])];
            const line = L.polyline(pts, { color: r.mode === 'bike' ? '#0F8B8D' : '#FF5A3C', weight: 4 }).addTo(map);
            L.marker([r.start.lat, r.start.lng], { icon: C.stepIcon(0, true) }).addTo(map);
            r.steps.forEach(s => L.marker([s.lat, s.lng], { icon: C.stepIcon(s.order) }).addTo(map));
            if (r.end) L.marker([r.end.lat, r.end.lng], { icon: C.stepIcon('<span class="material-symbols-outlined" style="font-size:16px">sports_score</span>') }).addTo(map);
            map.fitBounds(line.getBounds(), { padding: [30, 30] });
        });
    </script>
    @endpush
</x-app-layout>
