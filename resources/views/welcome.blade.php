@extends('layouts.landing')

@section('title', "CAMINO — Explorez l'Île-de-France comme jamais")

@section('content')
    <!-- Hero minimal & premium avec photo de Paris -->
    <section class="relative overflow-hidden bg-slate-950">
        <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_top,_rgba(19,236,236,0.22),transparent_60%),radial-gradient(circle_at_bottom,_rgba(15,23,42,0.98),#020617)]"></div>

        <div class="relative max-w-6xl mx-auto px-4 sm:px-6 py-16 sm:py-20 md:py-24 grid gap-10 md:grid-cols-[minmax(0,1.1fr)_minmax(0,0.9fr)] items-center">
            <!-- Texte -->
            <div class="space-y-6">
                <p class="inline-flex items-center gap-2 rounded-full bg-slate-900/70 border border-slate-700/80 px-3 py-1 text-[11px] font-semibold tracking-[0.18em] uppercase text-slate-300">
                    <span class="inline-flex h-1.5 w-1.5 rounded-full bg-primary"></span>
                    GPS culturel intelligent
                </p>
                <div class="space-y-4">
                    <h1 class="text-[30px] sm:text-[34px] md:text-[40px] font-extrabold tracking-tight text-white leading-[1.12]">
                        Explorez l’Île-de-France
                        <span class="block text-primary">en parcours, pas en liste.</span>
                    </h1>
                    <p class="text-sm sm:text-base md:text-lg text-slate-200 max-w-xl">
                        CAMINO transforme votre temps libre en itinéraires culturels cohérents&nbsp;:
                        musées, jardins, street‑art et lieux insolites, optimisés selon votre point
                        de départ et la durée dont vous disposez.
                    </p>
                </div>
                <div class="flex flex-col sm:flex-row items-center gap-3 pt-1">
                    <a
                        href="{{ route('map.index') }}"
                        class="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-full bg-primary text-slate-950 px-7 py-3.5 text-sm sm:text-base font-semibold shadow-lg shadow-primary/40 hover:bg-cyan-300 transition"
                    >
                        <span class="material-symbols-outlined text-[20px]">explore</span>
                        <span>Ouvrir la carte</span>
                    </a>
                    <a
                        href="{{ route('itineraries.create') }}"
                        class="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-full border border-slate-600 px-7 py-3.5 text-sm sm:text-base font-semibold text-slate-100 hover:border-primary hover:text-primary transition"
                    >
                        <span class="material-symbols-outlined text-[20px]">route</span>
                        <span>Générer un parcours</span>
                    </a>
                </div>
                <div class="flex flex-wrap items-center gap-3 text-[11px] text-slate-400 pt-1">
                    <span class="inline-flex items-center gap-1.5">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                        Lieux issus de DATAtourisme &amp; de la communauté
                    </span>
                    <span class="inline-flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-[14px] text-primary">schedule</span>
                        Itinéraires optimisés selon votre temps
                    </span>
                </div>
            </div>

            <!-- Carte photo Paris -->
            <div class="relative">
                <div class="pointer-events-none absolute -inset-8 bg-[radial-gradient(circle_at_top,_rgba(19,236,236,0.25),transparent_65%)] blur-3xl opacity-80"></div>
                <div class="relative rounded-[32px] overflow-hidden border border-slate-700/80 bg-slate-900/80 shadow-[0_32px_80px_rgba(0,0,0,0.85)]">
                    <div class="relative aspect-[4/5] md:h-[460px]">
                        <img
                            src="{{ asset('images/photo_paris.avif') }}"
                            alt="Vue panoramique de Paris"
                            class="w-full h-full object-cover object-center"
                        >
                        <div class="absolute inset-0 bg-gradient-to-t from-slate-950/85 via-slate-900/10 to-transparent"></div>

                        <!-- Badge en haut -->
                        <div class="absolute top-4 left-4 inline-flex items-center gap-2 rounded-full bg-slate-900/80 border border-white/10 px-3 py-1 text-[11px] text-slate-100">
                            <span class="material-symbols-outlined text-[16px] text-primary">location_on</span>
                            <span>Île-de-France · Aperçu de parcours</span>
                        </div>

                        <!-- Cartouche en bas -->
                        <div class="absolute inset-x-4 bottom-4 space-y-2 text-[11px] text-slate-100">
                            <div class="flex items-center justify-between">
                                <span class="inline-flex items-center gap-1.5">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                                    3&nbsp;heures disponibles · 5 étapes
                                </span>
                                <span class="inline-flex items-center gap-1.5 text-slate-300">
                                    <span class="material-symbols-outlined text-[16px] text-primary">directions_walk</span>
                                    ~ 6&nbsp;km · tout à pied
                                </span>
                            </div>
                            <div class="grid grid-cols-3 gap-2">
                                <div class="rounded-2xl bg-slate-950/80 border border-slate-700/70 px-3 py-2">
                                    <p class="text-[11px] font-semibold">Musée d’Orsay</p>
                                    <p class="text-[10px] text-slate-300">50&nbsp;min de visite</p>
                                </div>
                                <div class="rounded-2xl bg-slate-950/65 border border-slate-700/60 px-3 py-2">
                                    <p class="text-[11px] font-semibold">Jardin du Luxembourg</p>
                                    <p class="text-[10px] text-slate-300">Balade &amp; pause</p>
                                </div>
                                <div class="rounded-2xl bg-slate-950/65 border border-slate-700/60 px-3 py-2">
                                    <p class="text-[11px] font-semibold">Galerie de photos</p>
                                    <p class="text-[10px] text-slate-300">Quartier Montparnasse</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection

@extends('layouts.landing')

@section('title', "CAMINO — Explorez l'Île-de-France comme jamais")

@section('content')
    <!-- Hero premium avec photo de Paris -->
    <section class="relative overflow-hidden bg-slate-950">
        <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_top,_rgba(19,236,236,0.22),transparent_60%),radial-gradient(circle_at_bottom,_rgba(15,23,42,0.95),#020617)]"></div>

        <div class="relative max-w-6xl mx-auto px-4 sm:px-6 py-16 sm:py-20 md:py-24">
            <div class="grid gap-12 md:grid-cols-[minmax(0,1.05fr)_minmax(0,0.95fr)] items-center">
                <!-- Texte -->
                <div class="space-y-6">
                    <p class="inline-flex items-center gap-2 rounded-full bg-slate-900/70 border border-slate-700/80 px-3 py-1 text-[11px] font-semibold tracking-[0.18em] uppercase text-slate-300">
                        <span class="inline-flex h-1.5 w-1.5 rounded-full bg-primary"></span>
                        GPS culturel intelligent
                    </p>
                    <div class="space-y-4">
                        <h1 class="text-[30px] sm:text-[34px] md:text-[40px] font-extrabold tracking-tight text-white leading-[1.12]">
                            Explorez l’Île-de-France
                            <span class="block text-primary">en parcours, pas en liste.</span>
                        </h1>
                        <p class="text-sm sm:text-base md:text-lg text-slate-200 max-w-xl">
                            CAMINO transforme votre temps libre en itinéraires culturels cohérents&nbsp;:
                            musées, jardins, street‑art et lieux insolites, optimisés selon votre point
                            de départ et la durée dont vous disposez.
                        </p>
                    </div>
                    <div class="flex flex-col sm:flex-row items-center gap-3 pt-1">
                        <a
                            href="{{ route('map.index') }}"
                            class="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-full bg-primary text-slate-950 px-7 py-3.5 text-sm sm:text-base font-semibold shadow-lg shadow-primary/40 hover:bg-cyan-300 transition"
                        >
                            <span class="material-symbols-outlined text-[20px]">explore</span>
                            <span>Lancer la carte</span>
                        </a>
                        <a
                            href="{{ route('itineraries.create') }}"
                            class="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-full border border-slate-600 px-7 py-3.5 text-sm sm:text-base font-semibold text-slate-100 hover:border-primary hover:text-primary transition"
                        >
                            <span class="material-symbols-outlined text-[20px]">route</span>
                            <span>Générer un parcours</span>
                        </a>
                    </div>
                    <div class="flex flex-wrap items-center gap-3 text-[11px] text-slate-400 pt-1">
                        <span class="inline-flex items-center gap-1.5">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                            Lieux issus de DATAtourisme &amp; de la communauté
                        </span>
                        <span class="inline-flex items-center gap-1.5">
                            <span class="material-symbols-outlined text-[14px] text-primary">schedule</span>
                            Itinéraires optimisés selon votre temps
                        </span>
                    </div>
                </div>

                <!-- Carte photo Paris -->
                <div class="relative">
                    <div class="pointer-events-none absolute -inset-8 bg-[radial-gradient(circle_at_top,_rgba(19,236,236,0.25),transparent_65%)] blur-3xl opacity-80"></div>
                    <div class="relative rounded-[32px] overflow-hidden border border-slate-700/80 bg-slate-900/80 shadow-[0_32px_80px_rgba(0,0,0,0.85)]">
                        <div class="relative aspect-[4/5] md:h-[460px]">
                            <img
                                src="{{ asset('images/photo_paris.avif') }}"
                                alt="Vue panoramique de Paris"
                                class="w-full h-full object-cover object-center"
                            >
                            <div class="absolute inset-0 bg-gradient-to-t from-slate-950/80 via-slate-900/10 to-transparent"></div>

                            <!-- Badge en haut -->
                            <div class="absolute top-4 left-4 inline-flex items-center gap-2 rounded-full bg-slate-900/80 border border-white/10 px-3 py-1 text-[11px] text-slate-100">
                                <span class="material-symbols-outlined text-[16px] text-primary">location_on</span>
                                <span>Île-de-France · Aperçu de parcours</span>
                            </div>

                            <!-- Cartouche en bas -->
                            <div class="absolute inset-x-4 bottom-4 space-y-2 text-[11px] text-slate-100">
                                <div class="flex items-center justify-between">
                                    <span class="inline-flex items-center gap-1.5">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                                        3&nbsp;heures disponibles · 5 étapes
                                    </span>
                                    <span class="inline-flex items-center gap-1.5 text-slate-300">
                                        <span class="material-symbols-outlined text-[16px] text-primary">directions_walk</span>
                                        ~ 6&nbsp;km · tout à pied
                                    </span>
                                </div>
                                <div class="grid grid-cols-3 gap-2">
                                    <div class="rounded-2xl bg-slate-950/80 border border-slate-700/70 px-3 py-2">
                                        <p class="text-[11px] font-semibold">Musée d’Orsay</p>
                                        <p class="text-[10px] text-slate-300">50&nbsp;min de visite</p>
                                    </div>
                                    <div class="rounded-2xl bg-slate-950/65 border border-slate-700/60 px-3 py-2">
                                        <p class="text-[11px] font-semibold">Jardin du Luxembourg</p>
                                        <p class="text-[10px] text-slate-300">Balade &amp; pause</p>
                                    </div>
                                    <div class="rounded-2xl bg-slate-950/65 border border-slate-700/60 px-3 py-2">
                                        <p class="text-[11px] font-semibold">Galerie de photos</p>
                                        <p class="text-[10px] text-slate-300">Quartier Montparnasse</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Fonctionnalités principales -->
    <section id="features" class="py-20 px-4 sm:px-6 bg-background-light dark:bg-slate-950">
        <div class="max-w-6xl mx-auto">
            <div class="mb-10 sm:mb-12 text-center">
                <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-slate-500 dark:text-slate-400 mb-2">
                    Fonctionnalités clés
                </p>
                <h2 class="text-3xl md:text-4xl font-extrabold mb-4 text-slate-900 dark:text-white">
                    Pensé pour vos sorties culturelles.
                </h2>
                <p class="text-sm sm:text-base text-slate-600 dark:text-slate-300 max-w-2xl mx-auto">
                    CAMINO se concentre sur les lieux qui comptent vraiment, et construit pour vous des parcours cohérents plutôt qu’une simple liste de points sur une carte.
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="group p-6 rounded-2xl bg-white/95 dark:bg-slate-900/85 border border-slate-200/80 dark:border-slate-800 hover:border-primary/60 transition-all shadow-sm hover:shadow-lg hover:scale-[1.02]">
                    <div class="w-10 h-10 bg-primary/15 rounded-xl flex items-center justify-center mb-4 group-hover:scale-105 transition-transform">
                        <span class="material-symbols-outlined text-primary text-[22px]">map</span>
                    </div>
                    <h3 class="text-base font-semibold mb-2 text-slate-900 dark:text-white">
                        Visualisez les lieux culturels autour de vous
                    </h3>
                    <p class="text-sm text-slate-600 dark:text-slate-300 leading-relaxed">
                        Musées, galeries, street‑art, monuments&nbsp;: une carte filtrée pour la culture, sans le bruit des commerces et services.
                    </p>
                </div>

                <div class="group p-6 rounded-2xl bg-white/95 dark:bg-slate-900/85 border border-slate-200/80 dark:border-slate-800 hover:border-primary/60 transition-all shadow-sm hover:shadow-lg hover:scale-[1.02]">
                    <div class="w-10 h-10 bg-emerald-400/15 rounded-xl flex items-center justify-center mb-4 group-hover:scale-105 transition-transform">
                        <span class="material-symbols-outlined text-emerald-400 text-[22px]">route</span>
                    </div>
                    <h3 class="text-base font-semibold mb-2 text-slate-900 dark:text-white">
                        Obtenez un parcours optimisé selon votre temps
                    </h3>
                    <p class="text-sm text-slate-600 dark:text-slate-300 leading-relaxed">
                        Indiquez votre point de départ et votre durée disponible&nbsp;: CAMINO ordonne les étapes et les temps de marche pour tenir dans le créneau.
                    </p>
                </div>

                <div class="group p-6 rounded-2xl bg-white/95 dark:bg-slate-900/85 border border-slate-200/80 dark:border-slate-800 hover:border-primary/60 transition-all shadow-sm hover:shadow-lg hover:scale-[1.02]">
                    <div class="w-10 h-10 bg-amber-400/15 rounded-xl flex items-center justify-center mb-4 group-hover:scale-105 transition-transform">
                        <span class="material-symbols-outlined text-amber-400 text-[22px]">favorite</span>
                    </div>
                    <h3 class="text-base font-semibold mb-2 text-slate-900 dark:text-white">
                        Sauvegardez et partagez vos découvertes
                    </h3>
                    <p class="text-sm text-slate-600 dark:text-slate-300 leading-relaxed">
                        Gardez une trace de vos lieux préférés et partagez facilement vos parcours avec vos amis ou votre communauté.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Section bêta / prochaine étape -->
    <section id="contact" class="py-20 px-4 sm:px-6 bg-slate-950">
        <div class="max-w-6xl mx-auto space-y-8 md:space-y-0 md:grid md:grid-cols-[minmax(0,1.2fr)_minmax(0,0.8fr)] md:items-center md:gap-10">
            <div class="space-y-4">
                <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-slate-400">
                    Prochaine étape
                </p>
                <h2 class="text-3xl md:text-4xl font-extrabold text-white">
                    Rejoignez les premiers explorateurs CAMINO.
                </h2>
                <p class="text-sm sm:text-base text-slate-300 max-w-xl">
                    Accédez en avant‑première aux nouveaux parcours, aidez‑nous à prioriser les quartiers et les types de lieux, et influencez directement l’évolution de l’outil.
                </p>
                <ul class="mt-3 space-y-2 text-sm text-slate-300">
                    <li class="flex items-start gap-2">
                        <span class="material-symbols-outlined text-[18px] text-primary mt-0.5">check_circle</span>
                        <span>Accès anticipé aux nouveaux parcours.</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <span class="material-symbols-outlined text-[18px] text-primary mt-0.5">check_circle</span>
                        <span>Possibilité de suggérer des lieux et idées d’itinéraires.</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <span class="material-symbols-outlined text-[18px] text-primary mt-0.5">check_circle</span>
                        <span>Priorité sur les futures fonctionnalités (notifications, hors‑ligne…).</span>
                    </li>
                </ul>
            </div>

            <div class="rounded-3xl border border-slate-800 bg-slate-950/85 px-8 py-8 sm:px-10 sm:py-10 text-center shadow-2xl shadow-black/60">
                <p class="text-[12px] font-semibold uppercase tracking-[0.3em] text-primary mb-3">
                    Bêta CAMINO
                </p>
                <p class="text-sm text-slate-300 mb-6">
                    Créez gratuitement votre compte pour sauvegarder vos parcours et tester les nouveautés en avant‑première.
                </p>
                <div class="flex flex-col gap-3">
                    <a
                        href="{{ route('register') }}"
                        class="inline-flex items-center justify-center gap-2 rounded-full bg-primary text-slate-900 px-8 py-3.5 text-sm sm:text-base font-semibold shadow-lg shadow-primary/40 hover:bg-cyan-300 transition"
                    >
                        <span class="material-symbols-outlined text-[20px]">rocket_launch</span>
                        <span>Créer mon compte gratuit</span>
                    </a>
                    <p class="text-[11px] text-slate-400">
                        Gratuit. Pas de spam. Désinscription en un clic.
                    </p>
                </div>
            </div>
        </div>
    </section>
@endsection

@extends('layouts.landing')

@section('title', "CAMINO — Explorez l'Île-de-France comme jamais")

@section('content')
    <!-- Hero premium avec photo de Paris -->
    <section class="relative overflow-hidden bg-slate-950">
        <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_top,_rgba(19,236,236,0.22),transparent_60%),radial-gradient(circle_at_bottom,_rgba(15,23,42,0.95),#020617)]"></div>

        <div class="relative max-w-6xl mx-auto px-4 sm:px-6 py-16 sm:py-20 md:py-24">
            <div class="grid gap-12 md:grid-cols-[minmax(0,1.05fr)_minmax(0,0.95fr)] items-center">
                <!-- Texte -->
                <div class="space-y-6">
                    <p class="inline-flex items-center gap-2 rounded-full bg-slate-900/70 border border-slate-700/80 px-3 py-1 text-[11px] font-semibold tracking-[0.18em] uppercase text-slate-300">
                        <span class="inline-flex h-1.5 w-1.5 rounded-full bg-primary"></span>
                        GPS culturel intelligent
                    </p>
                    <div class="space-y-4">
                        <h1 class="text-[30px] sm:text-[34px] md:text-[40px] font-extrabold tracking-tight text-white leading-[1.12]">
                            Explorez l’Île-de-France
                            <span class="block text-primary">en parcours, pas en liste.</span>
                        </h1>
                        <p class="text-sm sm:text-base md:text-lg text-slate-200 max-w-xl">
                            CAMINO transforme votre temps libre en itinéraires culturels cohérents&nbsp;:
                            musées, jardins, street‑art et lieux insolites, optimisés selon votre point
                            de départ et la durée dont vous disposez.
                        </p>
                    </div>
                    <div class="flex flex-col sm:flex-row items-center gap-3 pt-1">
                        <a
                            href="{{ route('map.index') }}"
                            class="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-full bg-primary text-slate-950 px-7 py-3.5 text-sm sm:text-base font-semibold shadow-lg shadow-primary/40 hover:bg-cyan-300 transition"
                        >
                            <span class="material-symbols-outlined text-[20px]">explore</span>
                            <span>Lancer la carte</span>
                        </a>
                        <a
                            href="{{ route('itineraries.create') }}"
                            class="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-full border border-slate-600 px-7 py-3.5 text-sm sm:text-base font-semibold text-slate-100 hover:border-primary hover:text-primary transition"
                        >
                            <span class="material-symbols-outlined text-[20px]">route</span>
                            <span>Générer un parcours</span>
                        </a>
                    </div>
                    <div class="flex flex-wrap items-center gap-3 text-[11px] text-slate-400 pt-1">
                        <span class="inline-flex items-center gap-1.5">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                            Lieux issus de DATAtourisme &amp; de la communauté
                        </span>
                        <span class="inline-flex items-center gap-1.5">
                            <span class="material-symbols-outlined text-[14px] text-primary">schedule</span>
                            Itinéraires optimisés selon votre temps
                        </span>
                    </div>
                </div>

                <!-- Carte photo Paris -->
                <div class="relative">
                    <div class="pointer-events-none absolute -inset-8 bg-[radial-gradient(circle_at_top,_rgba(19,236,236,0.25),transparent_65%)] blur-3xl opacity-80"></div>
                    <div class="relative rounded-[32px] overflow-hidden border border-slate-700/80 bg-slate-900/80 shadow-[0_32px_80px_rgba(0,0,0,0.85)]">
                        <div class="relative aspect-[4/5] md:h-[460px]">
                            <img
                                src="{{ asset('images/photo_paris.avif') }}"
                                alt="Vue panoramique de Paris"
                                class="w-full h-full object-cover object-center"
                            >
                            <div class="absolute inset-0 bg-gradient-to-t from-slate-950/80 via-slate-900/10 to-transparent"></div>

                            <!-- Badge en haut -->
                            <div class="absolute top-4 left-4 inline-flex items-center gap-2 rounded-full bg-slate-900/80 border border-white/10 px-3 py-1 text-[11px] text-slate-100">
                                <span class="material-symbols-outlined text-[16px] text-primary">location_on</span>
                                <span>Île-de-France · Aperçu de parcours</span>
                            </div>

                            <!-- Cartouche en bas -->
                            <div class="absolute inset-x-4 bottom-4 space-y-2 text-[11px] text-slate-100">
                                <div class="flex items-center justify-between">
                                    <span class="inline-flex items-center gap-1.5">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                                        3&nbsp;heures disponibles · 5 étapes
                                    </span>
                                    <span class="inline-flex items-center gap-1.5 text-slate-300">
                                        <span class="material-symbols-outlined text-[16px] text-primary">directions_walk</span>
                                        ~ 6&nbsp;km · tout à pied
                                    </span>
                                </div>
                                <div class="grid grid-cols-3 gap-2">
                                    <div class="rounded-2xl bg-slate-950/80 border border-slate-700/70 px-3 py-2">
                                        <p class="text-[11px] font-semibold">Musée d’Orsay</p>
                                        <p class="text-[10px] text-slate-300">50&nbsp;min de visite</p>
                                    </div>
                                    <div class="rounded-2xl bg-slate-950/65 border border-slate-700/60 px-3 py-2">
                                        <p class="text-[11px] font-semibold">Jardin du Luxembourg</p>
                                        <p class="text-[10px] text-slate-300">Balade &amp; pause</p>
                                    </div>
                                    <div class="rounded-2xl bg-slate-950/65 border border-slate-700/60 px-3 py-2">
                                        <p class="text-[11px] font-semibold">Galerie de photos</p>
                                        <p class="text-[10px] text-slate-300">Quartier Montparnasse</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Fonctionnalités principales -->
    <section id="features" class="py-20 px-4 sm:px-6 bg-background-light dark:bg-slate-950">
        <div class="max-w-6xl mx-auto">
            <div class="mb-10 sm:mb-12 text-center">
                <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-slate-500 dark:text-slate-400 mb-2">
                    Fonctionnalités clés
                </p>
                <h2 class="text-3xl md:text-4xl font-extrabold mb-4 text-slate-900 dark:text-white">
                    Pensé pour vos sorties culturelles.
                </h2>
                <p class="text-sm sm:text-base text-slate-600 dark:text-slate-300 max-w-2xl mx-auto">
                    CAMINO se concentre sur les lieux qui comptent vraiment, et construit pour vous des parcours cohérents plutôt qu’une simple liste de points sur une carte.
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="group p-6 rounded-2xl bg-white/95 dark:bg-slate-900/85 border border-slate-200/80 dark:border-slate-800 hover:border-primary/60 transition-all shadow-sm hover:shadow-lg hover:scale-[1.02]">
                    <div class="w-10 h-10 bg-primary/15 rounded-xl flex items-center justify-center mb-4 group-hover:scale-105 transition-transform">
                        <span class="material-symbols-outlined text-primary text-[22px]">map</span>
                    </div>
                    <h3 class="text-base font-semibold mb-2 text-slate-900 dark:text-white">
                        Visualisez les lieux culturels autour de vous
                    </h3>
                    <p class="text-sm text-slate-600 dark:text-slate-300 leading-relaxed">
                        Musées, galeries, street‑art, monuments&nbsp;: une carte filtrée pour la culture, sans le bruit des commerces et services.
                    </p>
                </div>

                <div class="group p-6 rounded-2xl bg-white/95 dark:bg-slate-900/85 border border-slate-200/80 dark:border-slate-800 hover:border-primary/60 transition-all shadow-sm hover:shadow-lg hover:scale-[1.02]">
                    <div class="w-10 h-10 bg-emerald-400/15 rounded-xl flex items-center justify-center mb-4 group-hover:scale-105 transition-transform">
                        <span class="material-symbols-outlined text-emerald-400 text-[22px]">route</span>
                    </div>
                    <h3 class="text-base font-semibold mb-2 text-slate-900 dark:text-white">
                        Obtenez un parcours optimisé selon votre temps
                    </h3>
                    <p class="text-sm text-slate-600 dark:text-slate-300 leading-relaxed">
                        Indiquez votre point de départ et votre durée disponible&nbsp;: CAMINO ordonne les étapes et les temps de marche pour tenir dans le créneau.
                    </p>
                </div>

                <div class="group p-6 rounded-2xl bg-white/95 dark:bg-slate-900/85 border border-slate-200/80 dark:border-slate-800 hover:border-primary/60 transition-all shadow-sm hover:shadow-lg hover:scale-[1.02]">
                    <div class="w-10 h-10 bg-amber-400/15 rounded-xl flex items-center justify-center mb-4 group-hover:scale-105 transition-transform">
                        <span class="material-symbols-outlined text-amber-400 text-[22px]">favorite</span>
                    </div>
                    <h3 class="text-base font-semibold mb-2 text-slate-900 dark:text-white">
                        Sauvegardez et partagez vos découvertes
                    </h3>
                    <p class="text-sm text-slate-600 dark:text-slate-300 leading-relaxed">
                        Gardez une trace de vos lieux préférés et partagez facilement vos parcours avec vos amis ou votre communauté.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Section bêta / prochaine étape -->
    <section id="contact" class="py-20 px-4 sm:px-6 bg-slate-950">
        <div class="max-w-6xl mx-auto space-y-8 md:space-y-0 md:grid md:grid-cols-[minmax(0,1.2fr)_minmax(0,0.8fr)] md:items-center md:gap-10">
            <div class="space-y-4">
                <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-slate-400">
                    Prochaine étape
                </p>
                <h2 class="text-3xl md:text-4xl font-extrabold text-white">
                    Rejoignez les premiers explorateurs CAMINO.
                </h2>
                <p class="text-sm sm:text-base text-slate-300 max-w-xl">
                    Accédez en avant‑première aux nouveaux parcours, aidez‑nous à prioriser les quartiers et les types de lieux, et influencez directement l’évolution de l’outil.
                </p>
                <ul class="mt-3 space-y-2 text-sm text-slate-300">
                    <li class="flex items-start gap-2">
                        <span class="material-symbols-outlined text-[18px] text-primary mt-0.5">check_circle</span>
                        <span>Accès anticipé aux nouveaux parcours.</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <span class="material-symbols-outlined text-[18px] text-primary mt-0.5">check_circle</span>
                        <span>Possibilité de suggérer des lieux et idées d’itinéraires.</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <span class="material-symbols-outlined text-[18px] text-primary mt-0.5">check_circle</span>
                        <span>Priorité sur les futures fonctionnalités (notifications, hors‑ligne…).</span>
                    </li>
                </ul>
            </div>

            <div class="rounded-3xl border border-slate-800 bg-slate-950/85 px-8 py-8 sm:px-10 sm:py-10 text-center shadow-2xl shadow-black/60">
                <p class="text-[12px] font-semibold uppercase tracking-[0.3em] text-primary mb-3">
                    Bêta CAMINO
                </p>
                <p class="text-sm text-slate-300 mb-6">
                    Créez gratuitement votre compte pour sauvegarder vos parcours et tester les nouveautés en avant‑première.
                </p>
                <div class="flex flex-col gap-3">
                    <a
                        href="{{ route('register') }}"
                        class="inline-flex items-center justify-center gap-2 rounded-full bg-primary text-slate-900 px-8 py-3.5 text-sm sm:text-base font-semibold shadow-lg shadow-primary/40 hover:bg-cyan-300 transition"
                    >
                        <span class="material-symbols-outlined text-[20px]">rocket_launch</span>
                        <span>Créer mon compte gratuit</span>
                    </a>
                    <p class="text-[11px] text-slate-400">
                        Gratuit. Pas de spam. Désinscription en un clic.
                    </p>
                </div>
            </div>
        </div>
    </section>
@endsection
            <!-- Copy -->
            <div class="space-y-6">
                <p class="inline-flex items-center gap-2 rounded-full bg-slate-900/70 border border-slate-700/80 px-3 py-1 text-[11px] font-semibold tracking-[0.18em] uppercase text-slate-300">
                    <span class="inline-flex h-1.5 w-1.5 rounded-full bg-primary"></span>
                    AI-powered cultural route planner
                </p>
                <div class="space-y-3">
                    <h1 class="text-[28px] sm:text-[32px] md:text-[40px] font-extrabold tracking-tight text-white leading-[1.15]">
                        AI‑powered cultural routes
                        <span class="block text-primary">in seconds.</span>
                    </h1>
                    <p class="text-sm sm:text-base text-slate-200 max-w-xl">
                        Tell CAMINO where you start and how much time you have. We generate a clean, optimized cultural walk instead of yet another list of pins.
                    </p>
                </div>
                <div class="flex flex-col sm:flex-row items-center gap-3 pt-1">
                    <a
                        href="{{ route('itineraries.create') }}"
                        class="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-full bg-primary text-slate-950 px-7 py-3.5 text-sm sm:text-base font-semibold shadow-lg shadow-primary/40 hover:bg-cyan-300 transition"
                    >
                        <span class="material-symbols-outlined text-[20px]">auto_awesome_motion</span>
                        <span>Create my route</span>
                    </a>
                    <a
                        href="{{ route('map.index') }}"
                        class="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-full border border-slate-600 px-7 py-3.5 text-sm sm:text-base font-semibold text-slate-100 hover:border-primary hover:text-primary transition"
                    >
                        <span class="material-symbols-outlined text-[20px]">travel_explore</span>
                        <span>Browse the map</span>
                    </a>
                </div>
                <div class="flex flex-wrap items-center gap-3 text-[11px] text-slate-400">
                    <span class="inline-flex items-center gap-1.5">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                        Powered by open cultural data & community insights
                    </span>
                    <span class="inline-flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-[14px] text-primary">schedule</span>
                        Optimized by available time, not only distance
                    </span>
                </div>
            </div>

            <!-- Product mock -->
            <div class="hidden md:flex justify-end">
                <div class="relative">
                    <div class="pointer-events-none absolute -inset-10 bg-[radial-gradient(circle_at_top,_rgba(19,236,236,0.22),transparent_65%)] blur-3xl opacity-70"></div>
                    <div class="relative w-[260px] h-[520px] rounded-[2.6rem] border border-slate-700/80 bg-slate-900/90 shadow-[0_40px_80px_rgba(0,0,0,0.9)] overflow-hidden flex flex-col">
                        <div class="h-10 flex items-center justify-between px-5 text-[11px] text-slate-300 bg-slate-900/80 border-b border-slate-700/70">
                            <span class="inline-flex items-center gap-1">
                                <span class="w-2 h-2 rounded-full bg-primary"></span>
                                CAMINO
                            </span>
                            <span class="text-slate-500">Route preview</span>
                        </div>
                        <div class="flex-1 relative">
                            <div class="absolute inset-3 rounded-3xl bg-gradient-to-b from-slate-800 via-slate-900 to-slate-950 overflow-hidden">
                                <div class="absolute inset-0 opacity-40 bg-[radial-gradient(circle_at_top,_rgba(19,236,236,0.25),transparent_60%)]"></div>
                                <div class="absolute inset-x-4 inset-y-5 border border-slate-700/60 rounded-2xl"></div>
                                <div class="absolute inset-x-5 top-9 space-y-3">
                                    <div class="flex items-center justify-between text-[11px] text-slate-200">
                                        <span class="inline-flex items-center gap-1">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                                            Today · 3h available
                                        </span>
                                        <span class="text-slate-400">6 stops</span>
                                    </div>
                                    <div class="space-y-2">
                                        <div class="flex items-center justify-between rounded-xl bg-slate-900/80 px-3 py-2 text-[11px] text-slate-200">
                                            <div>
                                                <p class="font-semibold">Start · Current location</p>
                                                <p class="text-slate-400">ETA 14:05</p>
                                            </div>
                                            <span class="material-symbols-outlined text-[16px] text-primary">my_location</span>
                                        </div>
                                        <div class="rounded-xl bg-slate-900/70 px-3 py-2 text-[11px] text-slate-200 flex items-center justify-between">
                                            <div>
                                                <p class="font-semibold">Galerie d’art urbain</p>
                                                <p class="text-slate-400">18 min walk · 60 min visit</p>
                                            </div>
                                            <span class="material-symbols-outlined text-[16px] text-amber-300">palette</span>
                                        </div>
                                        <div class="rounded-xl bg-slate-900/60 px-3 py-2 text-[11px] text-slate-200 flex items-center justify-between">
                                            <div>
                                                <p class="font-semibold">Musée d’histoire locale</p>
                                                <p class="text-slate-400">12 min walk · 45 min visit</p>
                                            </div>
                                            <span class="material-symbols-outlined text-[16px] text-sky-300">museum</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="absolute inset-x-6 bottom-6 flex items-center justify-between text-[10px] text-slate-300">
                                    <span class="inline-flex items-center gap-1">
                                        <span class="material-symbols-outlined text-[14px] text-primary">distance</span>
                                        6.2 km total
                                    </span>
                                    <span class="inline-flex items-center gap-1">
                                        <span class="material-symbols-outlined text-[14px] text-primary">schedule</span>
                                        2h58 · on time
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- HOW IT WORKS -->
    <section id="how-it-works" class="py-20 px-4 sm:px-6 bg-slate-950 border-t border-slate-800/60">
        <div class="max-w-6xl mx-auto space-y-10">
            <div class="text-center space-y-3">
                <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-slate-400">
                    How it works
                </p>
                <h2 class="text-[28px] md:text-[36px] font-extrabold text-white">
                    From idea to cultural route in three steps.
                </h2>
            </div>
            <div class="grid gap-6 md:grid-cols-3">
                <div class="flex flex-col gap-3 rounded-2xl bg-slate-900/70 border border-slate-800 px-5 py-6">
                    <div class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-primary/15 text-primary text-[18px] font-semibold">
                        1
                    </div>
                    <h3 class="text-sm font-semibold text-white">
                        Choose your starting point
                    </h3>
                    <p class="text-sm text-slate-300">
                        Home, hotel, metro station or any place on the map.
                    </p>
                </div>
                <div class="flex flex-col gap-3 rounded-2xl bg-slate-900/70 border border-slate-800 px-5 py-6">
                    <div class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-primary/15 text-primary text-[18px] font-semibold">
                        2
                    </div>
                    <h3 class="text-sm font-semibold text-white">
                        Select your available time
                    </h3>
                    <p class="text-sm text-slate-300">
                        1 hour between meetings or a full afternoon to wander.
                    </p>
                </div>
                <div class="flex flex-col gap-3 rounded-2xl bg-slate-900/70 border border-slate-800 px-5 py-6">
                    <div class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-primary/15 text-primary text-[18px] font-semibold">
                        3
                    </div>
                    <h3 class="text-sm font-semibold text-white">
                        CAMINO generates your route
                    </h3>
                    <p class="text-sm text-slate-300">
                        A balanced cultural walk with optimized order and timings.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- FEATURES -->
    <section id="features" class="py-20 px-4 sm:px-6 bg-background-light dark:bg-slate-950">
        <div class="max-w-6xl mx-auto">
            <div class="mb-10 sm:mb-12 text-center space-y-3">
                <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-slate-500 dark:text-slate-400">
                    Features
                </p>
                <h2 class="text-[28px] md:text-[36px] font-extrabold text-slate-900 dark:text-white">
                    Built for cultural exploration, not generic tourism.
                </h2>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-7">
                <div class="group p-6 rounded-2xl bg-white/95 dark:bg-slate-900/80 border border-slate-200/80 dark:border-slate-800 hover:border-primary/60 transition-all shadow-sm hover:shadow-xl hover:scale-[1.03]">
                    <div class="w-10 h-10 bg-primary/15 rounded-xl flex items-center justify-center mb-4 group-hover:scale-105 transition-transform">
                        <span class="material-symbols-outlined text-primary text-[22px]">travel_explore</span>
                    </div>
                    <h3 class="text-sm font-semibold mb-2 text-slate-900 dark:text-white">
                        Visualize cultural places around you instantly
                    </h3>
                    <p class="text-sm text-slate-600 dark:text-slate-300 leading-relaxed">
                        See relevant museums, galleries and cultural spots without the noise of every shop and restaurant.
                    </p>
                </div>
                <div class="group p-6 rounded-2xl bg-white/95 dark:bg-slate-900/80 border border-slate-200/80 dark:border-slate-800 hover:border-primary/60 transition-all shadow-sm hover:shadow-xl hover:scale-[1.03]">
                    <div class="w-10 h-10 bg-emerald-400/15 rounded-xl flex items-center justify-center mb-4 group-hover:scale-105 transition-transform">
                        <span class="material-symbols-outlined text-emerald-400 text-[22px]">route</span>
                    </div>
                    <h3 class="text-sm font-semibold mb-2 text-slate-900 dark:text-white">
                        Get an optimized route based on your time
                    </h3>
                    <p class="text-sm text-slate-600 dark:text-slate-300 leading-relaxed">
                        CAMINO orders stops, walking time and visits to fit exactly into your time window.
                    </p>
                </div>
                <div class="group p-6 rounded-2xl bg-white/95 dark:bg-slate-900/80 border border-slate-200/80 dark:border-slate-800 hover:border-primary/60 transition-all shadow-sm hover:shadow-xl hover:scale-[1.03]">
                    <div class="w-10 h-10 bg-amber-400/15 rounded-xl flex items-center justify-center mb-4 group-hover:scale-105 transition-transform">
                        <span class="material-symbols-outlined text-amber-400 text-[22px]">favorite</span>
                    </div>
                    <h3 class="text-sm font-semibold mb-2 text-slate-900 dark:text-white">
                        Save and share your discoveries
                    </h3>
                    <p class="text-sm text-slate-600 dark:text-slate-300 leading-relaxed">
                        Keep your favourite places in one place and share full routes with friends in one tap.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- WHY NOT GOOGLE MAPS -->
    <section class="py-20 px-4 sm:px-6 bg-slate-950 border-y border-slate-800/70">
        <div class="max-w-6xl mx-auto space-y-8">
            <div class="text-center space-y-3">
                <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-slate-400">
                    Why not just Google Maps?
                </p>
                <h2 class="text-[28px] md:text-[36px] font-extrabold text-white">
                    Because planning a cultural route is not the same as finding an address.
                </h2>
            </div>
            <div class="grid gap-6 md:grid-cols-2 text-sm">
                <div class="rounded-2xl bg-slate-900/80 border border-slate-800 p-6 space-y-3">
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400 mb-1">
                        Google Maps
                    </p>
                    <ul class="space-y-2 text-slate-300">
                        <li>Shows everything around you, not what matters.</li>
                        <li>Requires manual trial‑and‑error to plan a walk.</li>
                        <li>Not curated for cultural relevance or balance.</li>
                    </ul>
                </div>
                <div class="rounded-2xl bg-slate-900/90 border border-primary/40 p-6 space-y-3">
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-primary mb-1">
                        CAMINO
                    </p>
                    <ul class="space-y-2 text-slate-100">
                        <li>Selects culturally relevant places intelligently.</li>
                        <li>Optimizes order and timing based on your availability.</li>
                        <li>Designed for cultural‑first exploration, not pure navigation.</li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <!-- BETA / CONVERSION -->
    <section id="contact" class="py-20 px-4 sm:px-6 bg-slate-950">
        <div class="max-w-6xl mx-auto space-y-8 md:space-y-0 md:grid md:grid-cols-[minmax(0,1.2fr)_minmax(0,0.8fr)] md:items-center md:gap-10">
            <div class="space-y-4">
                <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-slate-400">
                    Beta program
                </p>
                <h2 class="text-[28px] md:text-[36px] font-extrabold text-white">
                    Join the first CAMINO explorers.
                </h2>
                <p class="text-sm sm:text-base text-slate-300 max-w-xl">
                    Get early access and help us shape the future of cultural exploration in Île‑de‑France.
                </p>
                <ul class="mt-3 space-y-2 text-sm text-slate-300">
                    <li class="flex items-start gap-2">
                        <span class="material-symbols-outlined text-[18px] text-primary mt-0.5">check_circle</span>
                        <span>Early access to new routes.</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <span class="material-symbols-outlined text-[18px] text-primary mt-0.5">check_circle</span>
                        <span>Suggest new places to integrate.</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <span class="material-symbols-outlined text-[18px] text-primary mt-0.5">check_circle</span>
                        <span>Priority access to upcoming features.</span>
                    </li>
                </ul>
            </div>
            <div class="rounded-3xl border border-slate-800 bg-slate-950/85 px-8 py-8 sm:px-10 sm:py-10 text-center shadow-2xl shadow-black/60">
                <p class="text-[12px] font-semibold uppercase tracking-[0.3em] text-primary mb-3">
                    Get early access
                </p>
                <p class="text-sm text-slate-300 mb-6">
                    Create your free account to save routes and receive new cultural walks as we release them.
                </p>
                <div class="flex flex-col gap-3">
                    <a
                        href="{{ route('register') }}"
                        class="inline-flex items-center justify-center gap-2 rounded-full bg-primary text-slate-900 px-8 py-3.5 text-sm sm:text-base font-semibold shadow-lg shadow-primary/40 hover:bg-cyan-300 transition"
                    >
                        <span class="material-symbols-outlined text-[20px]">rocket_launch</span>
                        <span>Create my free account</span>
                    </a>
                    <p class="text-[11px] text-slate-400">
                        Free. No spam. Unsubscribe anytime.
                    </p>
                </div>
            </div>
        </div>
    </section>
@endsection

