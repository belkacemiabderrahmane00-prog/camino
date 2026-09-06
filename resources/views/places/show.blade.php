@php
    $shareTitle = $place->title . ' · CAMINO';
    $shareDescription = \Illuminate\Support\Str::limit($place->description ?? $place->address ?? __('Lieu culturel sur CAMINO'), 150);
    $gmUrl = $place->getGoogleMapsUrl();
@endphp
@push('meta')
    <meta property="og:type" content="place">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:title" content="{{ $shareTitle }}">
    <meta property="og:description" content="{{ $shareDescription }}">
    @if($place->cover_image_url)<meta property="og:image" content="{{ $place->coverThumb(1200) }}">@endif
    <meta name="twitter:card" content="{{ $place->cover_image_url ? 'summary_large_image' : 'summary' }}">
@endpush

<x-app-layout :title="$place->title" :description="$shareDescription">
    {{-- ============================================================ Hero --}}
    <section class="relative">
        <div class="relative h-[300px] sm:h-[420px] overflow-hidden">
            <x-cover :place="$place" class="h-full" />
            <div class="absolute inset-0 bg-gradient-to-t from-ink/85 via-ink/20 to-transparent"></div>
            @php
                $creditAuthor = $place->cover_image_author ?: ($place->media->first()->author ?? null);
                $creditLicense = $place->cover_image_license ?: ($place->media->first()->license ?? null);
                $creditUrl = $place->cover_image_page_url ?: ($place->media->first()->attribution_url ?? null);
            @endphp
            @if($place->cover_image_url && ($creditAuthor || $creditLicense))
                <a href="{{ $creditUrl ?? '#' }}" target="_blank" rel="noopener" class="absolute top-20 right-4 text-[10px] text-white/80 bg-ink/40 backdrop-blur px-2 py-1 rounded-full hover:text-white">
                    <span class="material-symbols-outlined align-middle" style="font-size:12px">photo_camera</span> {{ $creditAuthor ?: 'Photo' }}{{ $creditLicense ? ' · ' . $creditLicense : '' }}
                </a>
            @endif
        </div>
        <div class="max-w-5xl mx-auto px-4 sm:px-6 -mt-24 sm:-mt-28 relative">
            <div class="flex flex-wrap items-center gap-2 mb-3">
                <a href="{{ url()->previous() !== url()->current() ? url()->previous() : route('map.index') }}" class="btn btn-sm bg-white/90 text-ink backdrop-blur"><span class="material-symbols-outlined" style="font-size:16px">arrow_back</span>{{ __('Retour') }}</a>
                <a href="{{ route('map.index', ['lat' => $place->lat, 'lng' => $place->lng, 'z' => 16]) }}" class="btn btn-sm bg-white/90 text-ink backdrop-blur"><span class="material-symbols-outlined" style="font-size:16px">map</span>{{ __('Voir sur la carte') }}</a>
            </div>
            <div class="card p-6 sm:p-8">
                <div class="flex flex-wrap items-center gap-2">
                    <x-category-pill :category="$place->category" />
                    @if($place->is_free)<span class="badge badge-free">{{ __('Gratuit') }}</span>@elseif($place->price_level)<span class="badge badge-paid">{{ str_repeat('€', (int) $place->price_level) }} · dès {{ [1 => 5, 2 => 15, 3 => 30][$place->price_level] }} €</span>@endif
                    <span class="badge badge-paid"><span class="material-symbols-outlined" style="font-size:14px">schedule</span>≈ {{ $place->visit_duration_min ?? 60 }} min</span>
                    @if($place->accessible === true)<span class="badge badge-free" title="{{ $place->accessibility_note }}"><span class="material-symbols-outlined" style="font-size:14px">accessible</span>{{ __('Accessible PMR') }}</span>@elseif($place->accessible === false)<span class="badge badge-alert" title="{{ $place->accessibility_note }}"><span class="material-symbols-outlined" style="font-size:14px">accessible</span>{{ __('Accès difficile') }}</span>@endif
                    @if($place->event_end_at)
                        <span class="badge badge-event"><span class="material-symbols-outlined" style="font-size:14px">event</span>
                            @if($place->event_start_at && !$place->event_start_at->isSameDay($place->event_end_at)) Du {{ $place->event_start_at->translatedFormat('j M') }} au {{ $place->event_end_at->translatedFormat('j M Y') }} @else Le {{ ($place->event_start_at ?? $place->event_end_at)->translatedFormat('j F Y') }} @endif
                        </span>
                    @endif
                    @if($reviewCount > 0)
                        <a href="#avis" class="badge bg-amber-50 text-amber-700"><span class="material-symbols-outlined filled" style="font-size:14px">star</span>{{ $averageRating }}/5 · {{ $reviewCount }} avis</a>
                    @endif
                    @if($place->status === 'pending')<span class="badge badge-alert">{{ __('En attente de validation') }}</span>@endif
                </div>
                <h1 class="display text-3xl sm:text-5xl mt-3">{{ $place->title }}</h1>
                <p class="mt-2 text-ink-muted flex items-center gap-1.5"><span class="material-symbols-outlined" style="font-size:18px">location_on</span>{{ $place->address ?? 'Adresse non renseignée' }}</p>

                {{-- Alertes actives --}}
                @if($place->alerts->isNotEmpty())
                    <div class="mt-4 space-y-2">
                        @foreach($place->alerts as $alert)
                            <div class="flex items-start gap-3 rounded-2xl p-3" style="background: {{ $alert->type_color }}14">
                                <span class="h-8 w-8 rounded-full flex items-center justify-center shrink-0 text-white" style="background: {{ $alert->type_color }}"><span class="material-symbols-outlined" style="font-size:16px">{{ $alert->type_icon }}</span></span>
                                <div class="text-sm min-w-0 flex-1">
                                    <p class="font-semibold">{{ $alert->type_label }} · {{ $alert->title }}</p>
                                    @if($alert->message)<p class="text-ink-soft">{{ $alert->message }}</p>@endif
                                    <p class="text-[11px] text-ink-muted mt-0.5">Signalé {{ $alert->created_at->diffForHumans() }}{{ $alert->user ? ' par ' . $alert->user->name : '' }} · expire {{ $alert->expires_at->diffForHumans() }}</p>
                                </div>
                                @if(auth()->check() && (auth()->id() === $alert->user_id || auth()->user()->is_admin))
                                    <form method="POST" action="{{ route('alerts.destroy', $alert) }}">@csrf @method('DELETE')<button class="text-ink-muted hover:text-ink" title="{{ __('Retirer') }}"><span class="material-symbols-outlined" style="font-size:18px">close</span></button></form>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif

                {{-- Actions --}}
                <div class="mt-6 flex flex-wrap gap-2">
                    @auth
                        <form method="POST" action="{{ route('places.toggle-favorite', $place) }}">@csrf
                            <button class="btn btn-md {{ $isFavorite ? 'btn-primary' : 'btn-soft' }}"><span class="material-symbols-outlined {{ $isFavorite ? 'filled' : '' }}" style="font-size:18px">favorite</span>{{ $isFavorite ? 'Dans tes favoris' : 'Favori' }}</button>
                        </form>
                    @else
                        <a href="{{ route('login') }}" class="btn btn-md btn-soft"><span class="material-symbols-outlined" style="font-size:18px">favorite</span>{{ __('Favori') }}</a>
                    @endauth
                    @if($isInItinerary)
                        <form method="POST" action="{{ route('itineraries.remove-place', $place) }}">@csrf @method('DELETE')
                            <button class="btn btn-md btn-teal"><span class="material-symbols-outlined" style="font-size:18px">check</span>{{ __('Dans ton parcours · retirer') }}</button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('itineraries.add-place', $place) }}">@csrf
                            <button class="btn btn-md btn-ink"><span class="material-symbols-outlined" style="font-size:18px">add_location_alt</span>{{ __('Ajouter au parcours') }}</button>
                        </form>
                    @endif
                    @auth
                        <form method="POST" action="{{ route('places.visit', $place) }}">@csrf<input type="hidden" name="source" value="manuel">
                            <button class="btn btn-md btn-soft" title="{{ __('Ajouter à mon journal de visites') }}"><span class="material-symbols-outlined" style="font-size:18px">footprint</span>{{ __('J\'y suis allé') }}</button>
                        </form>
                    @endauth
                    @if($gmUrl)
                        <a href="{{ $gmUrl }}" target="_blank" rel="noopener" class="btn btn-md btn-soft"><span class="material-symbols-outlined" style="font-size:18px">navigation</span>{{ __('Y aller') }}</a>
                    @endif
                    <button @click="$dispatch('open-alert')" class="btn btn-md btn-soft"><span class="material-symbols-outlined" style="font-size:18px">campaign</span>{{ __('Signaler') }}</button>
                    <button x-data @click="navigator.share ? navigator.share({ title: @js($place->title), url: window.location.href }) : (navigator.clipboard.writeText(window.location.href), alert('Lien copié !'))" class="btn btn-md btn-ghost"><span class="material-symbols-outlined" style="font-size:18px">share</span>{{ __('Partager') }}</button>
                </div>
            </div>
        </div>
    </section>

    {{-- ============================================================ Corps --}}
    <section class="max-w-5xl mx-auto px-4 sm:px-6 mt-8 grid grid-cols-1 lg:grid-cols-[1fr_340px] gap-6">
        <div class="space-y-6 min-w-0">
            {{-- Description --}}
            <div class="card p-6 sm:p-8">
                <p class="eyebrow mb-2">{{ __('À propos') }}</p>
                @if($place->description)
                    <p class="text-[15px] leading-relaxed text-ink-soft whitespace-pre-line">{{ $place->description }}</p>
                @else
                    <p class="text-sm text-ink-muted">{{ __('Pas encore de description. Tu connais ce lieu ? Laisse un avis ou une photo ci-dessous.') }}</p>
                @endif
                @if(!empty($place->tags))
                    <div class="mt-4 flex flex-wrap gap-1.5">
                        @foreach($place->tags as $tag)<span class="badge badge-paid">#{{ $tag }}</span>@endforeach
                    </div>
                @endif
            </div>

            {{-- Photos communauté --}}
            <div class="card p-6 sm:p-8" id="photos">
                <div class="flex items-center justify-between gap-3 mb-4">
                    <div><p class="eyebrow mb-1">{{ __('Communauté') }}</p><h2 class="display text-2xl">{{ __('Photos') }}</h2></div>
                    <span class="text-xs text-ink-muted">{{ $place->photos->count() }} photo{{ $place->photos->count() > 1 ? 's' : '' }}</span>
                </div>
                @if($place->photos->isNotEmpty())
                    <div class="grid grid-cols-3 sm:grid-cols-4 gap-2 mb-4">
                        @foreach($place->photos as $photo)
                            <a href="{{ $photo->url }}" target="_blank" class="block aspect-square rounded-2xl overflow-hidden bg-paper">
                                <img src="{{ $photo->url }}" alt="{{ $photo->caption }}" loading="lazy" class="w-full h-full object-cover hover:scale-105 transition-transform duration-300">
                            </a>
                        @endforeach
                    </div>
                @endif
                @auth
                    <form method="POST" action="{{ route('places.photos.store', $place) }}" enctype="multipart/form-data" class="space-y-2" x-data="{ name: '', preview: null, pick(camera) { const i = this.$refs.photo; if (camera) { i.setAttribute('capture', 'environment'); } else { i.removeAttribute('capture'); } i.click(); }, onFile(e) { const f = e.target.files[0]; this.name = f ? f.name : ''; this.preview = f ? URL.createObjectURL(f) : null; } }">
                        @csrf
                        <input type="file" name="photo" accept="image/*" class="sr-only" required x-ref="photo" @change="onFile($event)">
                        <div class="grid grid-cols-2 gap-2">
                            <button type="button" @click="pick(true)" class="flex items-center justify-center gap-2 rounded-2xl border border-dashed border-ink/20 px-3 py-3 text-sm font-medium hover:border-ink/50 hover:bg-paper"><span class="material-symbols-outlined text-coral">photo_camera</span>{{ __('Prendre une photo') }}</button>
                            <button type="button" @click="pick(false)" class="flex items-center justify-center gap-2 rounded-2xl border border-dashed border-ink/20 px-3 py-3 text-sm font-medium hover:border-ink/50 hover:bg-paper"><span class="material-symbols-outlined text-ink-muted">photo_library</span>{{ __('Galerie') }}</button>
                        </div>
                        <div x-show="preview" x-cloak class="flex items-center gap-3">
                            <img :src="preview" alt="" class="h-16 w-16 rounded-xl object-cover shrink-0">
                            <div class="flex-1 min-w-0 flex flex-col sm:flex-row gap-2">
                                <input type="text" name="caption" maxlength="160" placeholder="{{ __('Légende (optionnel)') }}" class="field flex-1 min-w-0">
                                <button class="btn btn-md btn-ink shrink-0"><span class="material-symbols-outlined" style="font-size:18px">upload</span>{{ __('Envoyer') }}</button>
                            </div>
                        </div>
                        <p class="text-[11px] text-ink-muted">{{ __('JPEG, PNG ou WebP · 8 Mo max · publiée après validation.') }}</p>
                    </form>
                    <p class="mt-2 text-[11px] text-ink-muted">{{ __('Ta photo sera visible après validation. Merci de ne partager que tes propres photos.') }}</p>
                @else
                    <p class="text-sm text-ink-muted"><a href="{{ route('login') }}" class="font-semibold text-ink underline">{{ __('Connecte-toi') }}</a> {{ __('pour partager une photo de ce lieu.') }}</p>
                @endauth
            </div>

            {{-- Avis --}}
            <div class="card p-6 sm:p-8" id="avis">
                <div class="flex items-center justify-between gap-3 mb-4">
                    <div><p class="eyebrow mb-1">{{ __('Avis') }}</p><h2 class="display text-2xl">{{ $reviewCount > 0 ? $averageRating . '/5 · ' . $reviewCount . ' avis' : 'Aucun avis pour l\'instant' }}</h2></div>
                </div>
                @auth
                    <form method="POST" action="{{ route('places.reviews.store', $place) }}" class="rounded-2xl bg-paper p-4 mb-5 space-y-3" x-data="{ rating: 5 }">
                        @csrf
                        <div class="flex items-center gap-1">
                            <template x-for="i in 5" :key="i">
                                <button type="button" @click="rating = i" class="text-amber-500"><span class="material-symbols-outlined" :class="i <= rating ? 'filled' : ''" style="font-size:26px">star</span></button>
                            </template>
                            <input type="hidden" name="rating" :value="rating">
                            <span class="ml-2 text-sm text-ink-muted" x-text="['', 'Décevant', 'Moyen', 'Bien', 'Très bien', 'Exceptionnel'][rating]"></span>
                        </div>
                        <textarea name="comment" rows="3" required maxlength="1000" class="field" placeholder="{{ __('Ton expérience, un conseil, le meilleur moment pour y aller…') }}"></textarea>
                        <div class="flex flex-wrap items-center gap-3">
                            <label class="text-xs text-ink-muted flex items-center gap-2">{{ __('Visité le') }} <input type="date" name="visited_at" class="field !py-1.5 !w-auto text-xs"></label>
                            <button class="btn btn-md btn-ink ml-auto">{{ __('Publier mon avis') }}</button>
                        </div>
                    </form>
                @else
                    <p class="text-sm text-ink-muted mb-5"><a href="{{ route('login') }}" class="font-semibold text-ink underline">{{ __('Connecte-toi') }}</a> {{ __('pour laisser un avis.') }}</p>
                @endauth
                <div class="space-y-4">
                    @forelse($reviews as $review)
                        <div class="flex gap-3">
                            <span class="h-9 w-9 rounded-full bg-teal text-white flex items-center justify-center text-sm font-bold shrink-0">{{ mb_strtoupper(mb_substr($review->user->name ?? '?', 0, 1)) }}</span>
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2 text-sm">
                                    <span class="font-semibold">{{ $review->user->name ?? 'Anonyme' }}</span>
                                    <span class="text-amber-500 inline-flex">@for($i = 1; $i <= 5; $i++)<span class="material-symbols-outlined {{ $i <= $review->rating ? 'filled' : '' }}" style="font-size:14px">star</span>@endfor</span>
                                    <span class="text-[11px] text-ink-muted">{{ $review->created_at->diffForHumans() }}</span>
                                </div>
                                <p class="mt-1 text-sm text-ink-soft">{{ $review->comment }}</p>
                            </div>
                        </div>
                    @empty
                    @endforelse
                    @if($reviews->hasPages())<div class="pt-2">{{ $reviews->links() }}</div>@endif
                </div>
            </div>
        </div>

        {{-- Colonne latérale --}}
        <aside class="space-y-6">
            <div class="card overflow-hidden">
                <div id="place-map" class="h-56"></div>
                <div class="p-4 text-sm space-y-2">
                    <p class="flex items-start gap-2 text-ink-soft"><span class="material-symbols-outlined text-ink-muted" style="font-size:18px">location_on</span>{{ $place->address ?? 'Adresse non renseignée' }}</p>
                    @auth
                        <form method="POST" action="{{ route('places.visit', $place) }}">@csrf<input type="hidden" name="source" value="manuel">
                            <button class="btn btn-md btn-soft" title="{{ __('Ajouter à mon journal de visites') }}"><span class="material-symbols-outlined" style="font-size:18px">footprint</span>{{ __('J\'y suis allé') }}</button>
                        </form>
                    @endauth
                    @if($gmUrl)
                        <div class="flex gap-2 pt-1">
                            <a href="{{ $gmUrl }}" target="_blank" rel="noopener" class="btn btn-sm btn-soft flex-1">Google Maps</a>
                            <a href="{{ $place->getWazeUrl() }}" target="_blank" rel="noopener" class="btn btn-sm btn-soft flex-1">{{ __('Waze') }}</a>
                        </div>
                    @endif
                </div>
            </div>

            <div class="card p-5">
                <p class="eyebrow mb-3">{{ __('Infos pratiques') }}</p>
                <dl class="space-y-2.5 text-sm">
                    <div class="flex justify-between gap-3"><dt class="text-ink-muted">{{ __('Tarif') }}</dt><dd class="font-semibold">{{ $place->is_free ? 'Gratuit' : ($place->price_level ? str_repeat('€', (int) $place->price_level) . ' · dès ' . [1 => 5, 2 => 15, 3 => 30][$place->price_level] . ' €' : 'Non renseigné') }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-ink-muted">{{ __('Durée conseillée') }}</dt><dd class="font-semibold">{{ $place->visit_duration_min ?? 60 }} min</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-ink-muted">{{ __('Catégorie') }}</dt><dd class="font-semibold">{{ $place->category->name ?? '—' }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-ink-muted">{{ __('Source') }}</dt><dd class="font-semibold">{{ in_array('community', (array) $place->sources) ? 'Communauté CAMINO' : 'DATAtourisme' }}</dd></div>
                </dl>
                <details class="mt-4 text-sm">
                    <summary class="cursor-pointer text-ink-muted hover:text-ink">{{ __('Une erreur sur cette fiche ? Signaler') }}</summary>
                    <form method="POST" action="{{ route('places.report', $place) }}" class="mt-3 space-y-2">
                        @csrf
                        <select name="reason" class="field !py-2 text-xs">
                            @foreach(['Lieu fermé définitivement', 'Adresse ou position incorrecte', 'Informations erronées', 'Contenu inapproprié', 'Doublon'] as $r)<option value="{{ $r }}">{{ __($r) }}</option>@endforeach
                        </select>
                        <textarea name="message" rows="2" class="field !py-2 text-xs" placeholder="{{ __('Précisions (optionnel)') }}"></textarea>
                        <button class="btn btn-sm btn-soft w-full">{{ __('Envoyer') }}</button>
                    </form>
                </details>
            </div>

            @if($nearby->isNotEmpty())
                <div>
                    <p class="eyebrow mb-3">{{ __('À proximité') }}</p>
                    <div class="space-y-2">
                        @foreach($nearby as $n)
                            <x-place-card :place="$n" :compact="true" />
                        @endforeach
                    </div>
                </div>
            @endif
        </aside>
    </section>

    <x-alert-modal :place="$place" :types="$alertTypes" />

    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const el = document.getElementById('place-map');
            if (!el || !window.L || !{{ $place->lat && $place->lng ? 'true' : 'false' }}) return;
            const map = L.map(el, { zoomControl: false, scrollWheelZoom: false, dragging: false }).setView([{{ $place->lat }}, {{ $place->lng }}], 15);
            window.Camino.tileLayer().addTo(map);
            L.marker([{{ $place->lat }}, {{ $place->lng }}], { icon: window.Camino.placeIcon(@js($place->category->slug ?? null), { size: 40 }) }).addTo(map);
        });
    </script>
    @endpush
</x-app-layout>
