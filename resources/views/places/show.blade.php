@php
    /** @var \App\Models\Place $place */
    $shareUrl = url()->current();
    $shareTitle = $place->title . ' – CAMINO';
    $shareDescription = \Illuminate\Support\Str::limit($place->description ?? $place->address ?? 'Lieu culturel sur CAMINO', 120);
@endphp

@push('meta')
    <meta property="og:type" content="place">
    <meta property="og:url" content="{{ $shareUrl }}">
    <meta property="og:title" content="{{ $shareTitle }}">
    <meta property="og:description" content="{{ $shareDescription }}">
    <meta property="og:site_name" content="CAMINO">
    <meta name="twitter:card" content="summary">
    <meta name="twitter:title" content="{{ $shareTitle }}">
    <meta name="twitter:description" content="{{ $shareDescription }}">
@endpush

<x-app-layout>
    <div class="relative min-h-[calc(100vh-4rem)] w-full overflow-hidden bg-gradient-to-b from-slate-900 via-slate-950 to-black text-slate-100">
        <!-- Map background -->
        <div id="place-map" class="absolute inset-0 z-0"></div>
        <div class="pointer-events-none absolute inset-0 z-0 bg-gradient-to-b from-slate-950/80 via-slate-950/60 to-black/80 backdrop-blur-md"></div>

        <!-- Top bar -->
        <div class="relative z-40 px-4 pt-4 flex items-center justify-start gap-3 max-w-4xl mx-auto">
            <button
                type="button"
                onclick="window.location.href='{{ route('map.index') }}'"
                class="inline-flex items-center gap-1.5 rounded-full bg-slate-950/90 border border-slate-700 px-3 py-1.5 text-xs text-slate-200 hover:border-primary hover:text-primary transition"
            >
                <span class="material-symbols-outlined text-[16px]">arrow_back</span>
                    Carte
            </button>
        </div>

        <!-- Drawer -->
        <div class="absolute inset-0 z-30 flex items-end justify-center pb-6 px-4 sm:px-6">
            <div class="w-full max-w-md sm:max-w-lg md:max-w-2xl">
                <div class="relative rounded-3xl bg-slate-950/98 border border-slate-800 drawer-shadow overflow-hidden camino-animate-drawer">
                    <div class="flex justify-center pt-3">
                        <div class="w-12 h-1.5 rounded-full bg-slate-600"></div>
                    </div>

                    <div class="px-5 pb-5 space-y-5 max-h-[70vh] overflow-y-auto hide-scrollbar">
                        @php
                            $coverMedia = $place->media->first();
                        @endphp

                        <!-- Header -->
                        <div class="flex flex-col gap-3">
                            @if($place->cover_image_url)
                                <div class="mb-1 rounded-2xl overflow-hidden bg-slate-900 border border-slate-800 relative">
                                    <img
                                        src="{{ $place->cover_image_url }}"
                                        alt="{{ $place->title }}"
                                        class="w-full h-44 sm:h-56 object-cover"
                                        loading="lazy"
                                    >
                                    <div class="absolute inset-0 bg-gradient-to-t from-slate-950/80 via-slate-900/10 to-transparent"></div>
                                    @php
                                        $creditAuthor = $place->cover_image_author ?: ($coverMedia->author ?? null);
                                        $creditLicense = $place->cover_image_license ?: ($coverMedia->license ?? null);
                                        $creditUrl = $place->cover_image_page_url ?: ($coverMedia->attribution_url ?? null);
                                    @endphp
                                    @if($creditAuthor || $creditLicense)
                                        <div class="absolute bottom-2 left-3 right-3 flex items-center justify-between gap-2 text-[10px] text-slate-300">
                                            <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-900/80 px-2 py-0.5 border border-slate-700/80 min-w-0">
                                                <span class="material-symbols-outlined text-[14px] text-cyan-300">photo_camera</span>
                                                <span class="truncate">{{ $creditAuthor ?: 'Crédit photo' }}</span>
                                            </span>
                                            @if($creditLicense)
                                                @if($creditUrl)
                                                    <a href="{{ $creditUrl }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 rounded-full bg-slate-900/80 px-2 py-0.5 border border-slate-700/80 hover:border-cyan-300 hover:text-cyan-300 transition-colors shrink-0">
                                                        <span>{{ $creditLicense }}</span>
                                                        <span class="material-symbols-outlined text-[14px]">open_in_new</span>
                                                    </a>
                                                @else
                                                    <span class="inline-flex items-center rounded-full bg-slate-900/80 px-2 py-0.5 border border-slate-700/80 shrink-0">{{ $creditLicense }}</span>
                                                @endif
                                            @endif
                                        </div>
                                    @endif
            </div>
                            @endif
                            <div class="flex items-start justify-between gap-3">
                    <div>
                                    <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-slate-900 border border-slate-700 text-[10px] text-slate-300 mb-2">
                                        @php
                                            $categoryIcon = match($place->category->slug ?? '') {
                                                'musee' => 'museum', 'monument' => 'castle', 'parc-jardin' => 'park', 'restauration' => 'restaurant',
                                                'street-art' => 'brush', 'itineraire' => 'route', 'evenement-culturel' => 'event', default => 'apartment',
                                            };
                                        @endphp
                                        <span class="material-symbols-outlined text-[14px] text-primary">{{ $categoryIcon }}</span>
                                        <span>{{ $place->category->name ?? 'Lieu culturel' }}</span>
                                    </div>
                                    <h1 class="text-lg sm:text-xl font-semibold text-slate-50">
                            {{ $place->title }}
                        </h1>
                                    <p class="text-[11px] text-slate-400 mt-1">
                            {{ $place->address ?? 'Adresse à venir' }}
                        </p>
                    </div>
                    <div class="flex flex-col items-end gap-1 text-[11px]">
                        <x-ui.badge tone="{{ $place->is_free ? 'success' : 'neutral' }}">
                            {{ $place->is_free ? 'Gratuit' : str_repeat('€', (int) ($place->price_level ?? 2)) }}
                        </x-ui.badge>
                        <x-ui.badge tone="neutral">
                                        <span class="material-symbols-outlined text-[12px] mr-0.5">schedule</span>
                                        ≈ {{ $place->visit_duration_min ?? 60 }} min
                                    </x-ui.badge>
                    </div>
                </div>

                            @if($reviewCount > 0 && $averageRating)
                                <div class="inline-flex items-center gap-3 rounded-2xl bg-slate-900/80 border border-slate-800 px-3 py-2 text-[11px]">
                                    <div class="inline-flex items-center gap-1.5">
                                        <span class="material-symbols-outlined text-[16px] text-amber-300">star</span>
                                        <span class="font-semibold text-slate-50">
                                            {{ $averageRating }}/5
                                        </span>
                                    </div>
                                    <span class="h-4 w-px bg-slate-700/70"></span>
                                    <span class="text-slate-400">
                                        {{ $reviewCount }} avis
                                    </span>
                                    <span class="hidden sm:inline-flex items-center gap-1 text-slate-500">
                                        <span class="material-symbols-outlined text-[14px]">insights</span>
                                        Note communautaire CAMINO
                                    </span>
                                </div>
                            @endif
                        </div>

                        <!-- Actions principales (style Stitch) -->
                        @php
                            $gmUrl = $place->getGoogleMapsUrl();
                            $wazeUrl = $place->getWazeUrl();
                            $primaryNavUrl = $gmUrl ?? $wazeUrl;
                        @endphp
                        <div class="flex justify-center gap-6 sm:gap-8 pb-1 text-[11px]">
                            {{-- Sauver / Favori --}}
                            <div class="flex flex-col items-center min-w-[4.5rem]">
                                @auth
                                    <form method="POST" action="{{ route('places.toggle-favorite', $place) }}">
                                        @csrf
                                        <button
                                            type="submit"
                                            class="w-16 h-18 rounded-full flex items-center justify-center shadow-[0_10px_20px_rgba(34,211,238,0.45)]
                                                {{ $isFavorite
                                                    ? 'bg-primary/30 text-primary'
                                                    : 'bg-primary/15 text-primary'
                                                }}"
                                        >
                                            <span
                                                class="material-symbols-outlined text-[22px]"
                                                style="font-variation-settings: 'FILL' {{ $isFavorite ? 1 : 0 }}"
                                            >
                                                favorite
                                            </span>
                                        </button>
                                    </form>
                                @else
                                    <div class="w-16 h-18 rounded-full bg-slate-900/70 text-slate-500 flex items-center justify-center">
                                        <span class="material-symbols-outlined text-[22px]">lock</span>
                                    </div>
                                @endauth
                                <span class="mt-1 text-[11px] text-slate-200">
                                    {{ $isFavorite ? 'Enregistré' : 'Sauver' }}
                                </span>
                            </div>

                            {{-- Parcours --}}
                            <div class="flex flex-col items-center min-w-[4.5rem]">
                                @if($isInItinerary)
                                    <a
                                        href="{{ route('itineraries.create') }}"
                                        class="w-16 h-18 rounded-full bg-primary/15 text-primary flex items-center justify-center shadow-[0_10px_20px_rgba(34,211,238,0.45)]"
                                    >
                                        <span class="material-symbols-outlined text-[22px]">check_circle</span>
                                    </a>
                                @else
                                    <form method="POST" action="{{ route('itineraries.add-place', $place) }}">
                                        @csrf
                                        <button
                                            type="submit"
                                            class="w-16 h-18 rounded-full bg-primary/15 text-primary flex items-center justify-center shadow-[0_10px_20px_rgba(34,211,238,0.45)]"
                                        >
                                            <span class="material-symbols-outlined text-[22px]">add_road</span>
                                        </button>
                                    </form>
                                @endif
                                <span class="mt-1 text-[11px] text-slate-200">Parcours</span>
                            </div>

                            {{-- Partager --}}
                            <div class="flex flex-col items-center min-w-[4.5rem]">
                                <button
                                    type="button"
                                    x-data="{
                                        url: @js($shareUrl),
                                        title: @js($shareTitle),
                                        text: @js($shareDescription),
                                        copied: false,
                                        async share() {
                                            if (navigator.share) {
                                                try {
                                                    await navigator.share({ title: this.title, text: this.text, url: this.url });
                                                } catch (e) { if (e.name !== 'AbortError') this.copy(); }
                                            } else {
                                                this.copy();
                                            }
                                        },
                                        copy() {
                                            navigator.clipboard.writeText(this.url);
                                            this.copied = true;
                                            setTimeout(() => this.copied = false, 2000);
                                        }
                                    }"
                                    @click="share()"
                                    class="w-16 h-18 rounded-full bg-primary/15 text-primary flex items-center justify-center shadow-[0_10px_20px_rgba(34,211,238,0.45)]"
                                >
                                    <span class="material-symbols-outlined text-[22px]" x-show="!copied">share</span>
                                    <span class="material-symbols-outlined text-[22px] text-primary" x-show="copied" x-cloak x-transition>check</span>
                                </button>
                                <span class="mt-1 text-[11px] text-slate-200">Partager</span>
                            </div>

                            {{-- Y aller --}}
                            <div class="flex flex-col items-center min-w-[4.5rem]">
                                @if($primaryNavUrl)
                                    <a
                                        href="{{ $primaryNavUrl }}"
                                        target="_blank"
                                        rel="noopener"
                                        class="w-16 h-18 rounded-full bg-primary/15 text-primary flex items-center justify-center shadow-[0_10px_20px_rgba(34,211,238,0.45)]"
                                    >
                                        <span class="material-symbols-outlined text-[22px]">navigation</span>
                                    </a>
                                    <span class="mt-1 text-[11px] text-slate-200">Y aller</span>
                                @else
                                    <div class="w-14 h-14 sm:w-16 sm:h-16 rounded-full bg-slate-900/60 border border-slate-700 text-slate-500 flex items-center justify-center">
                                        <span class="material-symbols-outlined text-[22px]">navigation</span>
                                    </div>
                                    <span class="mt-1 text-[11px] text-slate-500">Adresse manquante</span>
                                @endif
                            </div>
                        </div>

                        <!-- Raccourcis secondaires -->
                        <div class="mt-3 flex flex-wrap gap-2 text-[11px]">
                            @if($gmUrl)
                                <a
                                    href="{{ $gmUrl }}"
                                    target="_blank"
                                    rel="noopener"
                                    class="inline-flex items-center gap-1.5 rounded-full bg-slate-900 border border-slate-700 px-3 py-1 text-slate-200 hover:border-primary hover:text-primary transition-colors"
                                >
                                    <span class="material-symbols-outlined text-[16px]">map</span>
                                    <span>Google Maps</span>
                                </a>
                            @endif
                            @if($wazeUrl)
                                <a
                                    href="{{ $wazeUrl }}"
                                    target="_blank"
                                    rel="noopener"
                                    class="inline-flex items-center gap-1.5 rounded-full bg-slate-900 border border-slate-700 px-3 py-1 text-slate-200 hover:border-primary hover:text-primary transition-colors"
                                >
                                    <span class="material-symbols-outlined text-[16px]">directions_car</span>
                                    <span>Waze</span>
                                </a>
                            @endif
                            <button
                                type="button"
                                x-data
                                @click="$dispatch('open-modal', 'signaler-lieu')"
                                class="inline-flex items-center gap-1.5 rounded-full bg-slate-900 border border-slate-700 px-3 py-1 text-slate-200 hover:border-rose-500/50 hover:text-rose-400 transition-colors"
                            >
                                <span class="material-symbols-outlined text-[16px]">campaign</span>
                                <span>Signaler un problème</span>
                            </button>
                        </div>

                        <!-- Info grid (Horaires / Tarifs / Durée / Adresse) -->
                        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 text-[11px]">
                        @php
                            $opening = $place->opening_hours ?? null;
                            $sources = is_array($place->sources ?? null) ? $place->sources : [];
                            $fromDatatourisme = in_array('datatourisme', $sources, true);
                        @endphp

                            <div class="flex items-start gap-2 p-3 rounded-2xl bg-slate-900 border border-slate-800">
                                <span class="material-symbols-outlined text-primary text-[18px]">schedule</span>
                                <div>
                                    <p class="uppercase tracking-[0.18em] text-slate-500 font-bold mb-0.5">Horaires</p>
                                    @if(is_array($opening) && !empty($opening))
                                        <ul class="text-[11px] text-slate-100 space-y-0.5">
                                            @foreach($opening as $line)
                                                <li>{{ $line }}</li>
                                            @endforeach
                                        </ul>
                                        <p class="text-slate-400 text-[10px] mt-1">Horaires indicatifs issus de la fiche officielle.</p>
                                    @else
                                        <p class="text-slate-100 font-semibold">À compléter</p>
                                        <p class="text-slate-400 text-[10px]">Ajoute les horaires connus via la contribution.</p>
                                    @endif
            </div>
        </div>

                            <div class="flex items-start gap-2 p-3 rounded-2xl bg-slate-900 border border-slate-800">
                                <span class="material-symbols-outlined text-primary text-[18px]">payments</span>
                                <div>
                                    <p class="uppercase tracking-[0.18em] text-slate-500 font-bold mb-0.5">Tarifs</p>
                                    <p class="text-slate-100 font-semibold">
                                        {{ $place->is_free ? 'Gratuit' : 'À partir de ' . ($place->price_level ? str_repeat('€', $place->price_level) : '€€') }}
                                    </p>
                                    <p class="text-slate-400 text-[10px]">Estimation communautaire.</p>
                                </div>
                            </div>

                            <div class="flex items-start gap-2 p-3 rounded-2xl bg-slate-900 border border-slate-800">
                                <span class="material-symbols-outlined text-primary text-[18px]">hourglass_empty</span>
                                <div>
                                    <p class="uppercase tracking-[0.18em] text-slate-500 font-bold mb-0.5">Durée de visite</p>
                                    @php
                                        $minutes = (int) ($place->visit_duration_min ?? 0);
                                    @endphp
                                    @if($minutes > 0)
                                        @php
                                            $hours = max(1, round($minutes / 60));
                                        @endphp
                                        <p class="text-slate-100 font-semibold">
                                            Environ {{ $hours }} h
                                        </p>
                                    @else
                                        <p class="text-slate-100 font-semibold">À estimer</p>
                                    @endif
                                    <p class="text-slate-400 text-[10px]">Temps indicatif pour profiter du lieu.</p>
                                </div>
                            </div>

                            <div class="flex items-start gap-2 p-3 rounded-2xl bg-slate-900 border border-slate-800">
                                <span class="material-symbols-outlined text-primary text-[18px]">location_on</span>
                                <div>
                                    <p class="uppercase tracking-[0.18em] text-slate-500 font-bold mb-0.5">Adresse</p>
                                    <p class="text-slate-100 font-semibold">
                                        {{ $place->address ?? 'Adresse à venir' }}
                                    </p>
                                    <p class="text-slate-400 text-[10px]">Point de départ pour calculer ton trajet.</p>
                                </div>
                            </div>
                        </div>

                        <!-- Live Affluence (placeholder) -->
                        <div class="space-y-2">
                            <div class="flex items-center justify-between gap-2">
                                <div class="flex items-center gap-2">
                                    <span class="material-symbols-outlined text-primary text-[18px]">monitoring</span>
                                    <h2 class="text-sm font-semibold text-slate-100">
                                        Affluence en direct
                                    </h2>
                                </div>
                                <span class="inline-flex items-center gap-1 rounded-full bg-emerald-500/10 border border-emerald-500/40 px-2 py-0.5 text-[10px] text-emerald-300">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                                    Bientôt
                                            </span>
                            </div>
                            <div class="rounded-2xl bg-slate-900 border border-slate-800 p-3 flex flex-col gap-3">
                                <div class="flex items-center gap-3">
                                    <div class="flex-1 h-2 rounded-full bg-slate-800 overflow-hidden">
                                        <div class="h-full w-1/3 rounded-full bg-emerald-500/80"></div>
                                    </div>
                                    <span class="text-xs text-slate-300">
                                        Calme pour le moment
                                    </span>
                                </div>
                                <p class="text-[11px] text-slate-400">
                                    Les données d’affluence temps réel seront bientôt disponibles sur CAMINO. En attendant, base-toi sur les avis de la communauté.
                                </p>
                            </div>
                        </div>

                        <!-- Description -->
                        <div class="space-y-2">
                            <div class="flex items-center gap-2">
                                <span class="material-symbols-outlined text-primary text-[18px]">description</span>
                                <h2 class="text-sm font-semibold text-slate-100">
                                    Description
                                </h2>
                                @if($fromDatatourisme)
                                    <span class="ml-2 inline-flex items-center gap-1 rounded-full bg-slate-900 border border-slate-700 px-2 py-0.5 text-[10px] text-slate-300">
                                        <span class="material-symbols-outlined text-[14px] text-cyan-300">public</span>
                                        <span>Source DATAtourisme</span>
                                    </span>
                                @endif
                            </div>
                            <p class="text-xs sm:text-sm text-slate-300 leading-relaxed">
                                {{ $place->description ?? 'Lieu culturel proposé par la communauté CAMINO. Ajoute une description plus précise en contribuant.' }}
                            </p>
                        </div>

                        <!-- Localisation -->
                        <div class="space-y-2">
                            <div class="flex items-center gap-2">
                                <span class="material-symbols-outlined text-primary text-[18px]">location_on</span>
                                <h2 class="text-sm font-semibold text-slate-100">
                                    Localisation
                                </h2>
                            </div>
                            <div class="rounded-2xl bg-slate-900 border border-slate-800 p-3 flex flex-col gap-3">
                                <p class="text-[11px] text-slate-300 flex items-center gap-1.5">
                                    <span class="material-symbols-outlined text-[16px] text-primary">place</span>
                                    <span>{{ $place->address ?? 'Adresse à venir' }}</span>
                                </p>
                                <div class="relative overflow-hidden shadow-lg shadow-slate-900/40 bg-slate-800" style="width: 100%; height: 190px; border-radius: 32px;">
                                    <div id="place-mini-map" class="absolute inset-0" style="z-index:0;width:100%;height:100%;"></div>
                                    <div class="pointer-events-none absolute inset-0 bg-gradient-to-b from-slate-950/5 via-transparent to-slate-950/10"></div>
                                    @if($gmUrl || $wazeUrl)
                                        <div class="absolute top-3 left-3 right-3 flex items-center justify-end gap-2 text-[10px]">
                                            @if($gmUrl)
                                                <a
                                                    href="{{ $gmUrl }}"
                                                    target="_blank"
                                                    rel="noopener"
                                                    class="inline-flex items-center gap-1.5 rounded-full bg-slate-950/80 px-3 py-1 border border-slate-700/80 text-slate-100 hover:border-primary hover:text-primary transition-colors text-[10px]"
                                                >
                                                    <span class="material-symbols-outlined text-[14px]">map</span>
                                                    <span>Google Maps</span>
                                                </a>
                                            @endif
                                            @if($wazeUrl)
                                                <a
                                                    href="{{ $wazeUrl }}"
                                                    target="_blank"
                                                    rel="noopener"
                                                    class="inline-flex items-center gap-1.5 rounded-full bg-slate-950/80 px-3 py-1 border border-slate-700/80 text-slate-100 hover:border-primary hover:text-primary transition-colors text-[10px]"
                                                >
                                                    <span class="material-symbols-outlined text-[14px]">directions_car</span>
                                                    <span>Waze</span>
                                                </a>
                                            @endif
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <!-- Reviews (section repliable dans la fiche) -->
                        <div id="avis" x-data="{ open: false }" class="space-y-2">
                            <button
                                type="button"
                                @click="open = !open"
                                class="w-full flex items-center justify-between rounded-2xl bg-slate-900 border border-slate-800 px-3 py-2"
                            >
                                <div class="flex items-center gap-2">
                                    <span class="material-symbols-outlined text-primary text-[18px]">reviews</span>
                                    <div class="flex flex-col items-start">
                                        <span class="text-sm font-semibold text-slate-100">Avis de la communauté</span>
                                        <span class="text-[11px] text-slate-400">
                                            {{ $reviewCount > 0 ? $reviewCount . ' avis' : 'Pas encore d’avis' }}
                                        </span>
                                    </div>
                                </div>
                                <span class="material-symbols-outlined text-[18px] text-slate-400" x-show="!open">expand_more</span>
                                <span class="material-symbols-outlined text-[18px] text-slate-400" x-show="open">expand_less</span>
                            </button>

                            <div x-show="open" x-transition class="space-y-3 rounded-2xl bg-slate-950/80 border border-slate-800 px-3 py-3">
                                <div class="flex items-center justify-between">
                                    <p class="text-[11px] text-slate-400">
                                        Partage ton expérience pour aider les prochains explorateurs.
                                    </p>
                                    @if($reviewCount > 0 && $averageRating)
                                        <div class="inline-flex items-center gap-2 rounded-full bg-slate-800 px-3 py-1 text-[11px] text-amber-300">
                                            <span class="material-symbols-outlined text-[16px] text-amber-300">star</span>
                                            <span class="font-semibold">{{ $averageRating }}/5</span>
                                            <span class="text-slate-400">({{ $reviewCount }})</span>
                                        </div>
                                    @endif
                                </div>

                                <!-- Idées pour ton avis -->
                                <div class="flex flex-wrap gap-1.5 text-[10px] text-slate-300">
                                    <span class="inline-flex items-center gap-1 rounded-full bg-slate-900/80 border border-slate-700 px-2 py-0.5">
                                        <span class="material-symbols-outlined text-[14px] text-primary">emoji_people</span>
                                        <span>Ambiance</span>
                                    </span>
                                    <span class="inline-flex items-center gap-1 rounded-full bg-slate-900/80 border border-slate-700 px-2 py-0.5">
                                        <span class="material-symbols-outlined text-[14px] text-primary">schedule</span>
                                        <span>Temps d’attente</span>
                                    </span>
                                    <span class="inline-flex items-center gap-1 rounded-full bg-slate-900/80 border border-slate-700 px-2 py-0.5">
                                        <span class="material-symbols-outlined text-[14px] text-primary">payments</span>
                                        <span>Budget</span>
                                    </span>
                                    <span class="inline-flex items-center gap-1 rounded-full bg-slate-900/80 border border-slate-700 px-2 py-0.5">
                                        <span class="material-symbols-outlined text-[14px] text-primary">diversity_3</span>
                                        <span>En famille / en couple</span>
                                    </span>
                                </div>

                                <div class="space-y-2 max-h-56 overflow-y-auto hide-scrollbar pr-1">
                                    @forelse($reviews as $review)
                                        <div class="rounded-2xl bg-slate-900/90 border border-slate-800 px-3 py-2 flex flex-col gap-1.5">
                                            <div class="flex items-center justify-between gap-2">
                                                <div class="flex items-center gap-2">
                                                    <span class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-slate-800 text-[11px] font-semibold text-slate-100">
                                                        {{ strtoupper(mb_substr($review->user->name ?? 'C', 0, 1)) }}
                                                    </span>
                                                    <div>
                                                        <p class="font-semibold text-slate-100 text-xs sm:text-sm">
                                                            {{ $review->user->name ?? 'Explorateur CAMINO' }}
                                                        </p>
                                                        <p class="text-[10px] text-slate-500">
                                                            {{ optional($review->visited_at)->format('d/m/Y') ?? 'Visite récente' }}
                                                        </p>
                                                    </div>
                                                </div>
                                                <div class="inline-flex items-center gap-1 rounded-full bg-slate-800 px-2 py-0.5 text-[10px] text-amber-300">
                                                    <span class="material-symbols-outlined text-[14px] text-amber-300">star</span>
                                                    <span>{{ $review->rating }}/5</span>
                                                </div>
                                            </div>
                                            <p class="text-xs sm:text-sm text-slate-200 leading-snug">
                                                {{ $review->comment }}
                                            </p>
                                        </div>
                                    @empty
                                        <p class="text-xs sm:text-sm text-slate-400">
                                            Aucun avis pour le moment. Sois le premier à partager ton expérience sur ce lieu.
                            </p>
                        @endforelse
                    </div>

                                @if($reviews->hasPages())
                                    <div class="flex items-center justify-center gap-2 pt-2 text-[11px] text-slate-300">
                                        @if($reviews->onFirstPage())
                                            <span class="px-3 py-1.5 rounded-full bg-slate-800/50 text-slate-500 cursor-not-allowed">Précédent</span>
                                        @else
                                            <a href="{{ $reviews->previousPageUrl() }}#avis" class="px-3 py-1.5 rounded-full bg-slate-800 text-slate-200 hover:text-primary transition">Précédent</a>
                                        @endif
                                        <span class="text-slate-400">{{ $reviews->currentPage() }} / {{ $reviews->lastPage() }}</span>
                                        @if($reviews->hasMorePages())
                                            <a href="{{ $reviews->nextPageUrl() }}#avis" class="px-3 py-1.5 rounded-full bg-slate-800 text-slate-200 hover:text-primary transition">Suivant</a>
                                        @else
                                            <span class="px-3 py-1.5 rounded-full bg-slate-800/50 text-slate-500 cursor-not-allowed">Suivant</span>
                                        @endif
                                    </div>
                                @endif

                    @auth
                                    <form
                                        method="POST"
                                        action="{{ route('places.reviews.store', $place) }}"
                                        class="space-y-2 border-t border-slate-800 pt-3 text-[11px]"
                                    >
                                @csrf
                                        <div class="flex flex-col sm:flex-row sm:items-center gap-2">
                                            <label for="rating" class="flex items-center gap-2 text-slate-300">
                                                <span class="material-symbols-outlined text-[16px] text-amber-300">star</span>
                                                <span>Note</span>
                                                <select
                                                    id="rating"
                                                    name="rating"
                                                    class="rounded-full border-slate-700 bg-slate-900/80 text-xs text-slate-100 focus:ring-primary focus:border-primary"
                                                    required
                                                >
                                                    @for($i = 5; $i >= 1; $i--)
                                                        <option value="{{ $i }}">{{ $i }}/5</option>
                                                    @endfor
                                                </select>
                                            </label>
                                            <div class="flex-1 flex items-center gap-2">
                                                <label for="visited_at" class="text-slate-400">
                                                    Date de visite
                                                </label>
                                                <input
                                                    id="visited_at"
                                                    name="visited_at"
                                                    type="date"
                                                    class="rounded-full border-slate-700 bg-slate-900/80 text-xs text-slate-100 focus:ring-primary focus:border-primary"
                                                >
                                            </div>
                                        </div>
                                        <div class="flex flex-col sm:flex-row gap-2 items-end">
                                            <textarea
                                                name="comment"
                                                rows="2"
                                                class="flex-1 rounded-2xl border-slate-700 bg-slate-900/80 text-xs sm:text-sm text-slate-100 placeholder:text-slate-500 focus:ring-primary focus:border-primary"
                                                placeholder="Raconte en quelques mots ton expérience dans ce lieu..."
                                                required
                                            ></textarea>
                                            <x-ui.button variant="accent" size="md" class="rounded-full text-xs sm:text-sm">
                                                <span class="material-symbols-outlined text-[16px]">send</span>
                                                Publier
                                </x-ui.button>
                                        </div>
                            </form>
                                @else
                                    <p class="pt-2 text-[11px] text-slate-400">
                                        Connecte-toi pour laisser un avis sur ce lieu.
                                    </p>
                                @endauth
                            </div>
                        </div>

                        <x-modal name="signaler-lieu" maxWidth="md" focusable>
                            <div class="p-6 bg-slate-900 border border-slate-800 rounded-t-lg">
                                <h3 class="text-lg font-semibold text-slate-100 flex items-center gap-2">
                                    <span class="material-symbols-outlined text-rose-400">campaign</span>
                                    Signaler ce lieu
                                </h3>
                                <p class="mt-1 text-xs text-slate-400">Indique la raison de ton signalement. L’équipe CAMINO le traitera.</p>
                                <form method="POST" action="{{ route('places.report', $place) }}" class="mt-4 space-y-4">
                                    @csrf
                                    <div>
                                        <label for="report_reason" class="block text-xs font-medium text-slate-300 mb-1">Motif</label>
                                        <select id="report_reason" name="reason" required class="w-full rounded-xl border border-slate-700 bg-slate-800 text-slate-100 text-sm focus:ring-primary focus:border-primary">
                                            <option value="">Choisir un motif</option>
                                            <option value="Information incorrecte">Information incorrecte</option>
                                            <option value="Lieu fermé ou supprimé">Lieu fermé ou supprimé</option>
                                            <option value="Contenu inapproprié">Contenu inapproprié</option>
                                            <option value="Doublon">Doublon</option>
                                            <option value="Autre">Autre</option>
                                        </select>
            </div>
                                    <div>
                                        <label for="report_message" class="block text-xs font-medium text-slate-300 mb-1">Précisions (optionnel)</label>
                                        <textarea id="report_message" name="message" rows="3" maxlength="1000" class="w-full rounded-xl border border-slate-700 bg-slate-800 text-slate-100 text-sm placeholder:text-slate-500 focus:ring-primary focus:border-primary" placeholder="Détails utiles pour l’équipe..."></textarea>
                        </div>
                                    <div class="flex gap-2 justify-end">
                                        <button type="button" @click="$dispatch('close-modal', 'signaler-lieu')" class="px-4 py-2 rounded-xl border border-slate-600 text-slate-300 text-sm hover:bg-slate-800">Annuler</button>
                                        <button type="submit" class="px-4 py-2 rounded-xl bg-rose-500/20 border border-rose-500/50 text-rose-400 text-sm font-medium hover:bg-rose-500/30">Envoyer le signalement</button>
                        </div>
                                </form>
                        </div>
                        </x-modal>

                        <!-- Contribute -->
                        <div class="space-y-2 border-t border-slate-800 pt-3 text-[11px]">
                    <h2 class="text-sm font-semibold text-slate-100">
                        Contribuer
                    </h2>
                            <p class="text-slate-400">
                        Tu remarques une information manquante, une fermeture exceptionnelle ou une affluence inhabituelle ?
                                La contribution communautaire sera activée dans une prochaine version.
                            </p>
                        </div>
                    </div>

                    <!-- Sticky footer CTA -->
                    <div class="px-5 pb-4 pt-2 border-t border-slate-800 bg-slate-950/98 flex items-center gap-3">
                        <button class="w-11 h-11 rounded-full bg-slate-900 border border-slate-700 flex items-center justify-center text-slate-200" aria-label="Appeler">
                            <span class="material-symbols-outlined">call</span>
                        </button>
                        @if($isInItinerary)
                            <a href="{{ route('itineraries.create') }}" class="flex-1 bg-primary/20 border border-primary text-primary font-semibold py-2.5 rounded-full inline-flex items-center justify-center gap-2 text-sm">
                                <span class="material-symbols-outlined text-[18px]">check_circle</span>
                                Voir mon parcours
                            </a>
                        @else
                            <form method="POST" action="{{ route('itineraries.add-place', $place) }}" class="flex-1">
                                @csrf
                                <button type="submit" class="w-full bg-primary text-slate-900 font-semibold py-2.5 rounded-full shadow-lg shadow-primary/20 inline-flex items-center justify-center gap-2 text-sm">
                                    <span class="material-symbols-outlined text-[18px]">add_location_alt</span>
                                    Ajouter à mon parcours
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        (function () {
            var lat = {{ json_encode($place->lat) }};
            var lng = {{ json_encode($place->lng) }};
            if (lat == null || lng == null || typeof lat !== 'number' || typeof lng !== 'number') return;

            function initMaps() {
                if (!window.L) return false;

                var mapElement = document.getElementById('place-map');
                if (!mapElement) return false;

                // Grande carte de fond
                var map = L.map(mapElement, { zoomControl: false }).setView([lat, lng], 14);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    attribution: '&copy; OpenStreetMap contributors',
                }).addTo(map);
                L.marker([lat, lng]).addTo(map);
                L.control.zoom({ position: 'bottomright' }).addTo(map);

                // Mini-carte dans "Localisation"
                var miniEl = document.getElementById('place-mini-map');
                if (miniEl) {
                    var miniMap = L.map(miniEl, {
                        zoomControl: false,
                        dragging: false,
                        scrollWheelZoom: false,
                        doubleClickZoom: false,
                        boxZoom: false,
                        keyboard: false,
                        tap: false,
                        attributionControl: false,
                    }).setView([lat, lng], 15);

                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        maxZoom: 19,
                    }).addTo(miniMap);
                    L.marker([lat, lng]).addTo(miniMap);
                    setTimeout(function () { miniMap.invalidateSize(); }, 100);
                }

                return true;
            }

            function ready(fn) {
                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', fn);
                } else {
                    fn();
                }
            }

            ready(function () {
                var done = initMaps();
                if (!done) {
                    var attempts = 0;
                    var t = setInterval(function () {
                        if (initMaps() || ++attempts > 20) clearInterval(t);
                    }, 150);
                }
            });
        })();
    </script>
</x-app-layout>

