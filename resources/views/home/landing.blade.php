@extends('layouts.landing')

@section('title', "CAMINO — Explorez l'Île-de-France comme jamais")

@section('content')

{{-- HERO (photo en background) --}}
<section id="discover" class="scroll-mt-28 relative overflow-hidden bg-slate-950">
    {{-- Background image --}}
    <div class="absolute inset-0">
        <img
            src="{{ asset('images/photo_paris.avif') }}"
            alt="Paris en arrière-plan"
            class="w-full h-full object-cover object-center"
            loading="eager"
        />
        {{-- Overlays pour lisibilité --}}
        <div class="absolute inset-0 bg-slate-950/70"></div>
        <div class="absolute inset-0 bg-[radial-gradient(circle_at_top,_rgba(19,236,236,0.18),transparent_55%)]"></div>
        <div class="absolute inset-0 bg-gradient-to-b from-slate-950/30 via-slate-950/75 to-slate-950"></div>
    </div>

    {{-- Content --}}
    <div class="relative max-w-6xl mx-auto px-4 sm:px-6 pt-16 sm:pt-20 md:pt-24 pb-16 sm:pb-20">
        <div class="max-w-3xl space-y-7">
            <p class="inline-flex items-center gap-2 rounded-full bg-slate-900/60 border border-slate-700/60 px-3 py-1 text-[11px] font-semibold tracking-[0.18em] uppercase text-slate-200">
                <span class="inline-flex h-1.5 w-1.5 rounded-full bg-cyan-300"></span>
                GPS culturel intelligent
            </p>

            <div class="space-y-4">
                <h1 class="text-[34px] sm:text-[42px] md:text-[50px] font-extrabold tracking-tight text-white leading-[1.06]">
                    Explorez l’Île-de-France
                    <span class="block text-cyan-300">en parcours, pas en liste.</span>
                </h1>

                <p class="text-sm sm:text-base md:text-lg text-slate-100/95 max-w-2xl leading-relaxed">
                    CAMINO transforme votre temps libre en itinéraires culturels cohérents :
                    musées, jardins, street-art et lieux insolites, optimisés selon votre point de départ
                    et la durée dont vous disposez.
                </p>
            </div>

            {{-- CTAs --}}
            <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 pt-1">
                <a
                    href="{{ route('map.index') }}"
                    class="sm:w-auto w-full inline-flex items-center justify-center gap-2 rounded-full bg-cyan-300 text-slate-950 px-7 py-3.5 text-sm sm:text-base font-semibold shadow-lg shadow-cyan-300/25 hover:bg-cyan-200 transition focus:outline-none focus-visible:ring-2 focus-visible:ring-cyan-300/80 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-950"
                >
                    <span class="material-symbols-outlined text-[20px]">explore</span>
                    <span>Ouvrir la carte</span>
                </a>

                <a
                    href="{{ route('itineraries.create') }}"
                    class="sm:w-auto w-full inline-flex items-center justify-center gap-2 rounded-full border border-white/25 bg-slate-950/35 backdrop-blur px-7 py-3.5 text-sm sm:text-base font-semibold text-white hover:border-cyan-300 hover:text-cyan-300 transition focus:outline-none focus-visible:ring-2 focus-visible:ring-cyan-300/70 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-950"
                >
                    <span class="material-symbols-outlined text-[20px]">route</span>
                    <span>Générer un parcours</span>
                </a>
            </div>

            {{-- Trust line --}}
            <div class="flex flex-wrap items-center gap-x-5 gap-y-2 text-[11px] sm:text-[12px] text-slate-200/80 pt-1">
                <span class="inline-flex items-center gap-1.5">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                    Lieux issus de DATAtourisme &amp; de la communauté
                </span>
                <span class="inline-flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-[16px] text-cyan-300">schedule</span>
                    Itinéraires optimisés selon votre temps
                </span>
                <span class="inline-flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-[16px] text-cyan-300">near_me</span>
                    Point de départ pris en compte
                </span>
            </div>

            {{-- Mini proof --}}
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 pt-3 max-w-2xl">
                <div class="rounded-2xl bg-slate-950/40 border border-white/15 backdrop-blur px-4 py-3">
                    <p class="text-[11px] text-slate-200/70">En 2 clics</p>
                    <p class="text-sm font-semibold text-white">un parcours prêt</p>
                </div>
                <div class="rounded-2xl bg-slate-950/40 border border-white/15 backdrop-blur px-4 py-3">
                    <p class="text-[11px] text-slate-200/70">Selon votre durée</p>
                    <p class="text-sm font-semibold text-white">1h, 3h, journée</p>
                </div>
                <div class="rounded-2xl bg-slate-950/40 border border-white/15 backdrop-blur px-4 py-3">
                    <p class="text-[11px] text-slate-200/70">Cohérence</p>
                    <p class="text-sm font-semibold text-white">thèmes &amp; proximité</p>
                </div>
            </div>

            {{-- Hint --}}
            <div class="pt-4 hidden md:flex items-center gap-2 text-xs text-slate-200/70">
                <span class="material-symbols-outlined text-[18px] text-cyan-300">south</span>
                Faites défiler pour découvrir CAMINO
            </div>
        </div>
    </div>

    {{-- Divider --}}
    <div class="relative">
        <div class="absolute inset-x-0 bottom-0 h-px bg-gradient-to-r from-transparent via-white/15 to-transparent"></div>
    </div>
</section>


{{-- FEATURES --}}
<section id="features" class="scroll-mt-28 py-20 sm:py-24 px-4 sm:px-6 bg-slate-950">
    <div class="max-w-6xl mx-auto">
        <div class="mb-12 sm:mb-14 text-center space-y-3">
            <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-slate-400">
                Fonctionnalités clés
            </p>
            <h2 class="text-3xl md:text-4xl font-extrabold text-white leading-tight">
                Pensé pour vos sorties culturelles.
            </h2>
            <p class="text-sm sm:text-base text-slate-300 max-w-2xl mx-auto leading-relaxed">
                CAMINO se concentre sur les lieux qui comptent vraiment et construit des parcours cohérents,
                plutôt qu’une simple liste de points sur une carte.
            </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="group p-6 rounded-2xl bg-slate-900/60 border border-slate-800 hover:border-cyan-300/50 transition-all shadow-sm hover:shadow-lg hover:scale-[1.02]">
                <div class="w-10 h-10 bg-cyan-300/15 rounded-xl flex items-center justify-center mb-4 group-hover:scale-105 transition-transform">
                    <span class="material-symbols-outlined text-cyan-300 text-[22px]">map</span>
                </div>
                <h3 class="text-base font-semibold mb-2 text-white">Carte culturelle filtrée</h3>
                <p class="text-sm text-slate-300 leading-relaxed">
                    Musées, galeries, jardins, street-art et monuments : une carte dédiée à la culture,
                    sans le bruit des commerces et services génériques.
                </p>
            </div>

            <div class="group p-6 rounded-2xl bg-slate-900/60 border border-slate-800 hover:border-cyan-300/50 transition-all shadow-sm hover:shadow-lg hover:scale-[1.02]">
                <div class="w-10 h-10 bg-emerald-400/15 rounded-xl flex items-center justify-center mb-4 group-hover:scale-105 transition-transform">
                    <span class="material-symbols-outlined text-emerald-400 text-[22px]">route</span>
                </div>
                <h3 class="text-base font-semibold mb-2 text-white">Parcours optimisés par le temps</h3>
                <p class="text-sm text-slate-300 leading-relaxed">
                    Indiquez votre point de départ et votre durée : CAMINO ordonne les étapes
                    et les temps de marche pour tenir dans le créneau.
                </p>
            </div>

            <div class="group p-6 rounded-2xl bg-slate-900/60 border border-slate-800 hover:border-cyan-300/50 transition-all shadow-sm hover:shadow-lg hover:scale-[1.02]">
                <div class="w-10 h-10 bg-amber-400/15 rounded-xl flex items-center justify-center mb-4 group-hover:scale-105 transition-transform">
                    <span class="material-symbols-outlined text-amber-400 text-[22px]">favorite</span>
                </div>
                <h3 class="text-base font-semibold mb-2 text-white">Favoris &amp; historique</h3>
                <p class="text-sm text-slate-300 leading-relaxed">
                    Conservez vos coups de cœur, retrouvez vos itinéraires passés et partagez facilement
                    vos découvertes avec vos proches.
                </p>
            </div>
        </div>

        <div class="mt-12 sm:mt-14 flex flex-col sm:flex-row items-stretch sm:items-center justify-center gap-3">
            <a
                href="{{ route('itineraries.create') }}"
                class="inline-flex items-center justify-center gap-2 rounded-full bg-slate-900 text-white border border-slate-700 px-7 py-3.5 text-sm sm:text-base font-semibold hover:border-cyan-300 hover:text-cyan-300 transition"
            >
                <span class="material-symbols-outlined text-[20px]">auto_awesome</span>
                <span>Créer mon premier parcours</span>
            </a>

            <a
                href="{{ route('map.index') }}"
                class="inline-flex items-center justify-center gap-2 rounded-full border border-slate-700 px-7 py-3.5 text-sm sm:text-base font-semibold text-white hover:border-cyan-300 hover:text-cyan-300 transition"
            >
                <span class="material-symbols-outlined text-[20px]">pin_drop</span>
                <span>Voir les lieux sur la carte</span>
            </a>
        </div>
    </div>
</section>


{{-- HOW --}}
<section id="how" class="scroll-mt-28 py-20 sm:py-24 px-4 sm:px-6 bg-slate-950">
    <div class="max-w-6xl mx-auto">
        <div class="mb-12 sm:mb-14 text-center space-y-3">
            <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-slate-400">Comment ça marche</p>
            <h2 class="text-3xl md:text-4xl font-extrabold text-white leading-tight">Simple, rapide, cohérent.</h2>
            <p class="text-sm sm:text-base text-slate-300 max-w-2xl mx-auto leading-relaxed">
                Vous partez d’un point A, vous avez un temps donné : CAMINO vous propose un parcours logique,
                avec marche + temps de visite.
            </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="rounded-2xl border border-slate-800 bg-slate-900/55 p-6">
                <div class="flex items-center gap-3 mb-3">
                    <span class="inline-flex items-center justify-center w-9 h-9 rounded-xl bg-cyan-300/15 text-cyan-300 font-extrabold">1</span>
                    <h3 class="text-base font-semibold text-white">Choisissez votre point de départ</h3>
                </div>
                <p class="text-sm text-slate-300 leading-relaxed">
                    Adresse, station ou position actuelle : on part de là où vous êtes.
                </p>
            </div>

            <div class="rounded-2xl border border-slate-800 bg-slate-900/55 p-6">
                <div class="flex items-center gap-3 mb-3">
                    <span class="inline-flex items-center justify-center w-9 h-9 rounded-xl bg-emerald-400/15 text-emerald-400 font-extrabold">2</span>
                    <h3 class="text-base font-semibold text-white">Indiquez votre durée</h3>
                </div>
                <p class="text-sm text-slate-300 leading-relaxed">
                    1h, 2h, 3h, demi-journée… CAMINO ajuste les étapes pour rentrer dans votre créneau.
                </p>
            </div>

            <div class="rounded-2xl border border-slate-800 bg-slate-900/55 p-6">
                <div class="flex items-center gap-3 mb-3">
                    <span class="inline-flex items-center justify-center w-9 h-9 rounded-xl bg-amber-400/15 text-amber-400 font-extrabold">3</span>
                    <h3 class="text-base font-semibold text-white">Partez explorer</h3>
                </div>
                <p class="text-sm text-slate-300 leading-relaxed">
                    Un parcours ordonné, lisible et partageable : moins de friction, plus de découverte.
                </p>
            </div>
        </div>
    </div>
</section>


{{-- BETA --}}
<section id="contact" class="scroll-mt-28 py-20 sm:py-24 px-4 sm:px-6 bg-slate-950">
    <div class="max-w-6xl mx-auto space-y-10 md:space-y-0 md:grid md:grid-cols-[minmax(0,1.2fr)_minmax(0,0.8fr)] md:items-center md:gap-12">
        <div class="space-y-5">
            <div class="space-y-3">
                <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-slate-400">Prochaine étape</p>
                <h2 class="text-3xl md:text-4xl font-extrabold text-white leading-tight">
                    Rejoignez les premiers explorateurs CAMINO.
                </h2>
                <p class="text-sm sm:text-base text-slate-300 max-w-xl leading-relaxed">
                    Accédez en avant-première aux nouveaux parcours, aidez-nous à prioriser les quartiers
                    et les types de lieux, et façonnez avec nous le GPS culturel que vous voudriez utiliser au quotidien.
                </p>
            </div>

            <ul class="mt-2 space-y-2.5 text-sm text-slate-300">
                <li class="flex items-start gap-2">
                    <span class="material-symbols-outlined text-[18px] text-cyan-300 mt-0.5">check_circle</span>
                    <span>Accès anticipé aux nouveaux parcours et filtres.</span>
                </li>
                <li class="flex items-start gap-2">
                    <span class="material-symbols-outlined text-[18px] text-cyan-300 mt-0.5">check_circle</span>
                    <span>Suggestions de lieux, quartiers et idées d’itinéraires.</span>
                </li>
                <li class="flex items-start gap-2">
                    <span class="material-symbols-outlined text-[18px] text-cyan-300 mt-0.5">check_circle</span>
                    <span>Priorité sur les futures fonctionnalités (notifications, hors-ligne, partage, etc.).</span>
                </li>
            </ul>
        </div>

        <div class="rounded-3xl border border-slate-800 bg-slate-900/55 px-8 py-8 sm:px-10 sm:py-10 text-center shadow-2xl shadow-black/60">
            <p class="text-[12px] font-semibold uppercase tracking-[0.3em] text-cyan-300 mb-3">Bêta CAMINO</p>
            <p class="text-sm text-slate-300 mb-6 leading-relaxed">
                Créez gratuitement votre compte pour sauvegarder vos parcours et tester les nouveautés en avant-première.
            </p>

            <div class="flex flex-col gap-3">
                <a
                    href="{{ route('register') }}"
                    class="inline-flex items-center justify-center gap-2 rounded-full bg-cyan-300 text-slate-950 px-8 py-3.5 text-sm sm:text-base font-semibold shadow-lg shadow-cyan-300/25 hover:bg-cyan-200 transition"
                >
                    <span class="material-symbols-outlined text-[20px]">rocket_launch</span>
                    <span>Créer mon compte gratuit</span>
                </a>

                <a
                    href="{{ route('map.index') }}"
                    class="inline-flex items-center justify-center gap-2 rounded-full border border-slate-700 px-8 py-3.5 text-sm sm:text-base font-semibold text-white hover:border-cyan-300 hover:text-cyan-300 transition"
                >
                    <span class="material-symbols-outlined text-[20px]">travel_explore</span>
                    <span>Explorer sans compte</span>
                </a>

                <p class="text-[11px] text-slate-400">
                    Gratuit. Pas de spam. Désinscription en un clic.
                </p>
            </div>
        </div>
    </div>

    <div class="mt-16 max-w-6xl mx-auto px-4 sm:px-6">
        <div class="h-px bg-gradient-to-r from-transparent via-slate-700/50 to-transparent"></div>
        <p class="mt-6 text-center text-[11px] text-slate-500">
            © {{ date('Y') }} CAMINO · Explorez mieux, simplement.
        </p>
    </div>
</section>

@endsection