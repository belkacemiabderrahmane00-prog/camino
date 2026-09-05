@php $steps = $result['steps'] ?? []; $v2 = ($result['version'] ?? 1) === 2; @endphp
<x-app-layout :title="$itinerary->name">
    <section class="max-w-4xl mx-auto px-4 sm:px-6 pt-8 sm:pt-12">
        <a href="{{ route('itineraries.index') }}" class="btn btn-sm btn-ghost mb-4"><span class="material-symbols-outlined" style="font-size:16px">arrow_back</span>Mes parcours</a>
        <div class="card overflow-hidden">
            @if($v2)<div id="itinerary-map" class="h-[300px] sm:h-[400px]"></div>@endif
            <div class="p-5 sm:p-8">
                <p class="eyebrow">{{ $itinerary->created_at->translatedFormat('j F Y') }}</p>
                <h1 class="display text-3xl sm:text-4xl mt-1">{{ $itinerary->name }}</h1>
                @if($v2)
                    <p class="text-sm text-ink-muted mt-1">{{ $result['start']['label'] ?? '' }} · {{ ($result['mode'] ?? 'walk') === 'bike' ? 'à vélo' : 'à pied' }} · {{ floor($result['total_minutes'] / 60) }} h {{ str_pad($result['total_minutes'] % 60, 2, '0', STR_PAD_LEFT) }} · {{ number_format($result['total_distance_km'], 1, ',', ' ') }} km · {{ number_format($result['total_cost_eur'], 0) }} €</p>
                @endif
                <ol class="mt-6 space-y-3">
                    @foreach($steps as $i => $step)
                        <li>
                            <a href="{{ route('places.show', $step['place_id']) }}" class="card card-hover flex gap-3 p-2.5">
                                <span class="h-8 w-8 rounded-full bg-ink text-white flex items-center justify-center text-sm font-bold shrink-0 self-center">{{ $step['order'] ?? $i + 1 }}</span>
                                <div class="min-w-0 flex-1">
                                    <p class="font-semibold leading-snug">{{ $step['title'] }}</p>
                                    <p class="text-xs text-ink-muted">{{ $step['address'] ?? '' }}</p>
                                    @if($v2)<p class="text-xs text-ink-muted mt-0.5">{{ $step['arrive_at'] }} → {{ $step['leave_at'] }} · {{ $step['travel_minutes'] }} min de trajet · {{ $step['visit_minutes'] }} min sur place</p>@endif
                                </div>
                            </a>
                        </li>
                    @endforeach
                </ol>
                <div class="mt-6 flex flex-wrap gap-2">
                    <form method="POST" action="{{ route('itineraries.replay', $itinerary) }}">@csrf<button class="btn btn-md btn-primary"><span class="material-symbols-outlined" style="font-size:18px">replay</span>Rouvrir dans le générateur</button></form>
                    <form method="POST" action="{{ route('itineraries.destroy', $itinerary) }}" onsubmit="return confirm('Supprimer ce parcours ?');">@csrf @method('DELETE')<button class="btn btn-md btn-ghost text-ink-muted">Supprimer</button></form>
                </div>
            </div>
        </div>
    </section>
    @if($v2)
        @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const el = document.getElementById('itinerary-map'); if (!el || !window.L) return;
                const C = window.Camino, r = @js(['start' => $result['start'], 'steps' => collect($steps)->map(fn ($s) => ['lat' => $s['lat'], 'lng' => $s['lng'], 'order' => $s['order']])->all(), 'geometry' => $result['geometry'] ?? [], 'mode' => $result['mode'] ?? 'walk']);
                const map = L.map(el, { zoomControl: false, scrollWheelZoom: false }); C.tileLayer().addTo(map);
                const pts = r.geometry.length > 1 ? r.geometry : [[r.start.lat, r.start.lng], ...r.steps.map(s => [s.lat, s.lng])];
                const line = L.polyline(pts, { color: r.mode === 'bike' ? '#0F8B8D' : '#FF5A3C', weight: 4 }).addTo(map);
                L.marker([r.start.lat, r.start.lng], { icon: C.stepIcon(0, true) }).addTo(map);
                r.steps.forEach(s => L.marker([s.lat, s.lng], { icon: C.stepIcon(s.order) }).addTo(map));
                map.fitBounds(line.getBounds(), { padding: [30, 30] });
            });
        </script>
        @endpush
    @endif
</x-app-layout>
