@php
    /**
     * Carnet de voyage : objet souvenir autonome (sans en-tête ni pied de page de l'app).
     * Carte souvenir à partager, mode « récap » en diapositives plein écran, résumé du parcours, moments forts, lieux, chiffres, PDF.
     */
    $lang = app()->getLocale();
    $modeLabel = match ($stats['mode']) { 'bike' => __('à vélo'), 'transit' => __('à pied et en transports'), default => __('à pied') };
    $modeIcon = match ($stats['mode']) { 'bike' => 'directions_bike', 'transit' => 'directions_subway', default => 'directions_walk' };
    $hours = intdiv($stats['minutes'], 60);
    $mins = $stats['minutes'] % 60;
    $durationLabel = $hours > 0 ? $hours . ' h' . ($mins ? ' ' . str_pad((string) $mins, 2, '0', STR_PAD_LEFT) : '') : $mins . ' min';
    $startLabel = $result['start']['label'] ?? '';
    $startsAt = ! empty($result['starts_at']) ? \Illuminate\Support\Carbon::parse($result['starts_at']) : null;
    $endsAt = ! empty($result['ends_at']) ? \Illuminate\Support\Carbon::parse($result['ends_at']) : null;
    $shareUrl = $shared ? route('itineraries.shared-journal', $token) : ($itinerary->share_token ? route('itineraries.shared-journal', $itinerary->share_token) : null);
    $pdfUrl = $shared ? route('itineraries.shared-journal-pdf', $token) : route('itineraries.journal-pdf', $itinerary);
    $backUrl = $shared ? route('home') : route('itineraries.show', $itinerary);
    $dateLabel = ucfirst($date->translatedFormat('l j F Y'));
    $stops = collect($pages)->map(fn ($p) => ['lat' => $p['lat'], 'lng' => $p['lng'], 'n' => $p['index']])->all();
    $startStop = [['lat' => $result['start']['lat'] ?? null, 'lng' => $result['start']['lng'] ?? null, 'n' => 'start']];
    $sketch = \App\Services\RouteSketch::svg($result['geometry'] ?? [], array_merge($startStop, $stops), 200, 130, '#FFC857', '#12161C');
    $sketchBig = \App\Services\RouteSketch::svg($result['geometry'] ?? [], array_merge($startStop, $stops), 320, 260, '#FF5A3C', '#F6F3EC');
    $visits = array_values(array_filter($pages, fn ($p) => $p['kind'] === 'visit'));
    $fileName = 'camino-' . \Illuminate\Support\Str::slug(\Illuminate\Support\Str::limit($itinerary->name, 40, ''));
    $statChips = [
        ['museum', trans_choice(':n lieu|:n lieux', $stats['places'], ['n' => $stats['places']])],
        [$modeIcon, number_format($stats['km'], 1, ',', ' ') . ' km'],
        ['schedule', $durationLabel],
        $stats['cost'] > 0 ? ['payments', number_format($stats['cost'], 0, ',', ' ') . ' €'] : ['loyalty', __('Gratuit')],
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ $lang === 'zh' ? 'zh-CN' : $lang }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#12161C">
    <script>(function(){try{var t=localStorage.getItem('camino-theme');var d=t==='dark'||((!t||t==='system')&&window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)').matches);if(d)document.documentElement.classList.add('dark');}catch(e){}})();</script>
    <title>{{ __('Carnet de voyage') }} · {{ $itinerary->name }} · CAMINO</title>
    <meta name="description" content="{{ $itinerary->name }} · {{ $dateLabel }} · {{ trans_choice(':n lieu|:n lieux', $stats['places'], ['n' => $stats['places']]) }}">
    <meta property="og:title" content="{{ $itinerary->name }} · {{ __('Carnet de voyage') }}">
    @if($cover && $cover['photo'])<meta property="og:image" content="{{ $cover['photo'] }}">@endif
    <link rel="icon" type="image/svg+xml" href="{{ asset('logo.svg') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700&family=Fraunces:ital,opsz,wght@0,9..144,500;0,9..144,600;1,9..144,500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,300..600,0..1,0">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        html, body { overflow-x: hidden; }
        /* Carte souvenir : 540×675 (exportée en 1080×1350), mise à l'échelle sur petit écran via --s (calculé en JS). */
        .souvenir-wrap { --s: 1; width: calc(540px * var(--s)); height: calc(675px * var(--s)); }
        .souvenir { position: relative; width: 540px; height: 675px; border-radius: 28px; overflow: hidden; background: #12161C; color: #fff; font-family: Sora, sans-serif; box-shadow: 0 40px 80px -30px rgba(18,22,28,.55); transform: scale(var(--s)); transform-origin: top left; }
        .souvenir .photo-box { position: absolute; left: 0; top: 0; width: 100%; height: 62%; overflow: hidden; }
        .souvenir .photo-box img { width: 100%; height: 100%; object-fit: cover; object-position: center 40%; }
        .souvenir .photo-box::after { content: ''; position: absolute; inset: 0; background: linear-gradient(180deg, rgba(18,22,28,.45) 0%, rgba(18,22,28,0) 30%, rgba(18,22,28,0) 55%, #12161C 100%); }
        .souvenir .top { position: absolute; top: 24px; left: 26px; right: 26px; display: flex; align-items: center; justify-content: space-between; z-index: 2; }
        .souvenir .headline { position: absolute; left: 26px; right: 26px; top: 46%; z-index: 2; }
        .souvenir .title { font-family: Fraunces, Georgia, serif; font-weight: 500; font-size: 38px; line-height: 1.02; letter-spacing: -.02em; text-shadow: 0 2px 18px rgba(0,0,0,.35); }
        .souvenir .date { font-family: Fraunces, Georgia, serif; font-style: italic; font-size: 17px; opacity: .85; margin-top: 8px; }
        .souvenir .panel { position: absolute; left: 26px; right: 26px; bottom: 24px; z-index: 2; }
        .souvenir .stats { display: flex; gap: 8px; flex-wrap: wrap; }
        .souvenir .stats span { display: inline-flex; align-items: center; gap: 5px; background: rgba(255,255,255,.1); border: 1px solid rgba(255,255,255,.16); border-radius: 999px; padding: 6px 11px; font-size: 12px; font-weight: 600; }
        .souvenir .stops { margin-top: 16px; display: grid; grid-template-columns: 1fr 200px; gap: 12px; align-items: end; }
        .souvenir .stops ol { list-style: none; margin: 0; padding: 0; font-size: 12.5px; line-height: 1.35; }
        .souvenir .stops li { display: flex; gap: 8px; align-items: baseline; margin-top: 4px; }
        .souvenir .stops li b { display: inline-flex; width: 18px; height: 18px; border-radius: 50%; background: #FFC857; color: #12161C; font-size: 10px; align-items: center; justify-content: center; flex-shrink: 0; transform: translateY(2px); }
        .souvenir .stops li span { opacity: .92; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 230px; }
        .souvenir .stops li small { opacity: .6; font-size: 11px; }
        .souvenir .sketch { width: 200px; height: 130px; }
        .souvenir .brand { display: inline-flex; align-items: center; gap: 8px; font-family: Fraunces, Georgia, serif; font-weight: 600; font-size: 20px; letter-spacing: -.01em; }
        .souvenir .tag { font-size: 10px; font-weight: 700; letter-spacing: .24em; text-transform: uppercase; color: #FFC857; }
        /* Mode récap : diapositives 360×640 mises à l'échelle pour remplir l'écran */
        .story { --k: 1; }
        .story-slide { position: relative; width: 360px; height: 640px; border-radius: 26px; overflow: hidden; background: #12161C; color: #fff; font-family: Sora, sans-serif; transform: scale(var(--k)); transform-origin: center; box-shadow: 0 40px 100px -30px rgba(0,0,0,.8); }
        .story-slide img.bg { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }
        .story-slide .shade { position: absolute; inset: 0; background: linear-gradient(180deg, rgba(18,22,28,.55) 0%, rgba(18,22,28,.05) 35%, rgba(18,22,28,.15) 60%, rgba(18,22,28,.94) 100%); }
        .story-slide .inner { position: absolute; inset: 0; padding: 28px 26px; display: flex; flex-direction: column; justify-content: flex-end; }
        .story-slide .big { font-family: Fraunces, Georgia, serif; font-size: 64px; line-height: .95; letter-spacing: -.03em; }
        .story-slide .h { font-family: Fraunces, Georgia, serif; font-size: 34px; line-height: 1.04; letter-spacing: -.02em; }
        .story-slide .eyebrow { color: #FFC857; letter-spacing: .24em; }
        .story-slide .quote { font-family: Fraunces, Georgia, serif; font-style: italic; font-size: 17px; line-height: 1.45; opacity: .92; }
        .story-slide .num { position: absolute; top: 24px; left: 26px; width: 34px; height: 34px; border-radius: 50%; background: #FFC857; color: #12161C; font-weight: 700; display: flex; align-items: center; justify-content: center; }
        .story-progress { display: flex; gap: 4px; }
        .story-progress i { flex: 1; height: 3px; border-radius: 999px; background: rgba(255,255,255,.25); overflow: hidden; }
        .story-progress i b { display: block; height: 100%; width: 0; background: #fff; }
        .story-progress i.done b { width: 100%; }
        .story-progress i.on b { animation: story-fill var(--dur, 6s) linear forwards; }
        @keyframes story-fill { from { width: 0 } to { width: 100% } }
        .chapter img { transition: transform .8s ease; } .chapter:hover img { transform: scale(1.03); }
        @media print { .no-print { display: none !important; } }
    </style>
</head>
<body class="min-h-full bg-paper text-ink antialiased" x-data="journal(@js(['file' => $fileName, 'shareUrl' => $shareUrl, 'title' => $itinerary->name, 'text' => $dateLabel . ' · ' . trans_choice(':n lieu|:n lieux', $stats['places'], ['n' => $stats['places']]) . ' · ' . number_format($stats['km'], 1, ',', ' ') . ' km', 't' => ['copied' => __('Lien copié'), 'error' => __('L\'image n\'a pas pu être créée. Essaie le PDF.')]]))" x-init="init()">

    {{-- Barre discrète --}}
    <div class="no-print sticky top-0 z-40 bg-paper/85 backdrop-blur border-b border-ink/5">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 h-14 flex items-center gap-2 sm:gap-3">
            <a href="{{ $backUrl }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-ink-soft hover:text-ink shrink-0"><span class="material-symbols-outlined" style="font-size:18px">arrow_back</span><span class="hidden sm:inline">{{ $shared ? 'CAMINO' : __('Mon parcours') }}</span></a>
            <span class="text-ink/20 hidden sm:inline">|</span>
            <x-logo :size="26" animate="hover" class="camino-logo-hover shrink-0" /><span class="text-[10px] sm:text-xs font-bold uppercase tracking-[0.18em] text-ink-muted truncate">{{ __('Carnet de voyage') }}</span>
            <span class="flex-1"></span>
            <button type="button" @click="openStory()" class="btn btn-sm btn-primary shrink-0"><span class="material-symbols-outlined" style="font-size:16px">play_circle</span><span class="hidden sm:inline">{{ __('Récap') }}</span></button>
            <button type="button" @click="share()" class="btn btn-sm btn-ink shrink-0"><span class="material-symbols-outlined" style="font-size:16px" x-text="busy ? 'progress_activity' : 'ios_share'" :class="busy && 'animate-spin'"></span><span class="hidden sm:inline">{{ __('Partager') }}</span></button>
            <a href="{{ $pdfUrl }}" class="btn btn-sm btn-soft shrink-0"><span class="material-symbols-outlined" style="font-size:16px">picture_as_pdf</span><span class="hidden sm:inline">PDF</span></a>
        </div>
    </div>

    <main class="max-w-6xl mx-auto px-4 sm:px-6">
        {{-- ============================== Souvenir --}}
        <section class="py-8 sm:py-16 grid gap-8 lg:gap-12 lg:grid-cols-[560px_1fr] items-center">
            <div class="souvenir-wrap mx-auto lg:mx-0" x-ref="wrap">
                <div id="souvenir" class="souvenir">
                    <div class="photo-box">@if($cover && $cover['photo'])<img src="{{ $cover['photo'] }}" alt="" crossorigin="anonymous">@else<div class="placeholder-cover w-full h-full" style="--c1:#0F8B8D;--c2:#12161C"></div>@endif</div>
                    <div class="top">
                        <span class="brand"><x-logo :size="30" animate="none" />CAMINO</span>
                        <span class="tag">{{ __('Carnet de voyage') }}</span>
                    </div>
                    <div class="headline">
                        <p class="title">{{ $itinerary->name }}</p>
                        <p class="date">{{ $dateLabel }}</p>
                    </div>
                    <div class="panel">
                        <div class="stats">@foreach($statChips as [$icon, $value])<span><span class="material-symbols-outlined" style="font-size:14px">{{ $icon }}</span>{{ $value }}</span>@endforeach</div>
                        <div class="stops">
                            <ol>
                                @foreach(array_slice($pages, 0, 6) as $page)<li><b>{{ $page['index'] }}</b><span>{{ $page['title'] }}</span>@if($page['arrive_at'])<small>{{ $page['arrive_at'] }}</small>@endif</li>@endforeach
                                @if(count($pages) > 6)<li><small>+{{ count($pages) - 6 }}</small></li>@endif
                            </ol>
                            @if($sketch)<div class="sketch">{!! $sketch !!}</div>@endif
                        </div>
                    </div>
                </div>
            </div>
            <div class="min-w-0">
                <p class="eyebrow">{{ __('Ton souvenir') }}</p>
                <h1 class="display text-3xl sm:text-5xl mt-2 leading-[1.02]">{{ __('Une journée, une image.') }}</h1>
                <p class="mt-4 text-ink-soft max-w-md">{{ __('La carte de ta balade, prête à partager : tes étapes, ton tracé, tes chiffres. Lance le récap pour la revivre en diapositives, ou garde tout en PDF.') }}</p>
                <div class="mt-6 flex flex-wrap gap-2">
                    <button type="button" @click="openStory()" class="btn btn-md btn-primary"><span class="material-symbols-outlined" style="font-size:18px">play_circle</span>{{ __('Lancer le récap') }}</button>
                    <button type="button" @click="share()" class="btn btn-md btn-ink" :disabled="busy"><span class="material-symbols-outlined" style="font-size:18px" x-text="busy ? 'progress_activity' : 'ios_share'" :class="busy && 'animate-spin'"></span>{{ __('Partager l\'image') }}</button>
                    <button type="button" @click="download()" class="btn btn-md btn-soft" :disabled="busy"><span class="material-symbols-outlined" style="font-size:18px">download</span>{{ __('Télécharger l\'image') }}</button>
                    <a href="{{ $pdfUrl }}" class="btn btn-md btn-soft"><span class="material-symbols-outlined" style="font-size:18px">picture_as_pdf</span>{{ __('Télécharger le PDF') }}</a>
                    @if($shareUrl)<button type="button" @click="copy()" class="btn btn-md btn-ghost"><span class="material-symbols-outlined" style="font-size:18px" x-text="copied ? 'check' : 'link'"></span><span x-text="copied ? data.t.copied : @js(__('Copier le lien'))"></span></button>
                    @elseif(!$shared)<form method="POST" action="{{ route('itineraries.share', $itinerary) }}">@csrf<button class="btn btn-md btn-ghost"><span class="material-symbols-outlined" style="font-size:18px">link</span>{{ __('Créer le lien de partage') }}</button></form>@endif
                </div>
                <p class="mt-3 text-[11px] text-ink-muted">{{ __('Image 1080 × 1350, idéale pour Instagram, WhatsApp et les stories.') }}</p>
            </div>
        </section>

        {{-- ============================== Moments forts --}}
        @if(!empty($highlights))
            <section class="py-8 sm:py-12 border-t border-ink/5">
                <p class="eyebrow">{{ __('Les moments forts') }}</p>
                <div class="mt-4 grid grid-cols-2 lg:grid-cols-4 gap-3">
                    @foreach($highlights as $h)
                        <div class="card p-4 sm:p-5">
                            <span class="h-9 w-9 rounded-xl bg-coral-soft text-coral flex items-center justify-center"><span class="material-symbols-outlined" style="font-size:20px">{{ $h['icon'] }}</span></span>
                            <p class="mt-3 text-[10px] font-bold uppercase tracking-[0.16em] text-ink-muted">{{ $h['label'] }}</p>
                            <p class="mt-1 font-display text-xl sm:text-2xl leading-tight line-clamp-2">{{ $h['value'] }}</p>
                            <p class="text-xs text-ink-muted mt-1">{{ $h['detail'] }}</p>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        {{-- ============================== Résumé du parcours --}}
        <section class="py-8 sm:py-12 border-t border-ink/5">
            <div class="flex items-end justify-between gap-4 flex-wrap">
                <div><p class="eyebrow">{{ __('Le résumé') }}</p><h2 class="display text-2xl sm:text-4xl mt-1">{{ $dateLabel }}</h2></div>
                <p class="text-sm text-ink-muted">{{ __('Départ') }} {{ $startLabel }}@if($startsAt) · {{ $startsAt->format('H\hi') }}@endif @if($endsAt)· {{ __('retour vers') }} {{ $endsAt->format('H\hi') }}@endif</p>
            </div>
            <div class="mt-6 grid gap-6 lg:grid-cols-[1fr_340px] items-start">
                <ol class="relative">
                    <span class="absolute left-[15px] top-4 bottom-4 w-0.5 bg-ink/10"></span>
                    <li class="relative pl-12 pb-5"><span class="absolute left-0 top-0 h-8 w-8 rounded-full bg-coral text-white flex items-center justify-center"><span class="material-symbols-outlined" style="font-size:16px">flag</span></span><p class="font-semibold">{{ $startLabel ?: __('Départ') }}</p><p class="text-xs text-ink-muted">{{ $startsAt ? $startsAt->format('H\hi') : '' }}</p></li>
                    @foreach($pages as $page)
                        @if($page['travel_minutes'])<li class="relative pl-12 pb-3 text-xs text-ink-muted"><span class="absolute left-[9px] top-0 h-3.5 w-3.5 rounded-full bg-paper border-2 border-ink/15"></span><span class="material-symbols-outlined align-middle" style="font-size:14px">{{ $page['travel_mode'] === 'transit' ? 'directions_subway' : ($page['travel_mode'] === 'bike' ? 'directions_bike' : 'directions_walk') }}</span> {{ $page['travel_minutes'] }} {{ __('min de trajet') }}</li>@endif
                        <li class="relative pl-12 pb-5">
                            <span class="absolute left-0 top-0 h-8 w-8 rounded-full flex items-center justify-center text-xs font-bold {{ $page['kind'] === 'lunch' ? 'bg-sun text-ink-fixed' : 'bg-ink text-white' }}">@if($page['kind'] === 'lunch')<span class="material-symbols-outlined" style="font-size:16px">restaurant</span>@else{{ $page['index'] }}@endif</span>
                            <div class="flex flex-wrap items-baseline gap-x-3 gap-y-0.5">
                                <p class="font-semibold">{{ $page['title'] }}</p>
                                <p class="text-xs text-ink-muted">{{ $page['kind'] === 'lunch' ? __('Pause déjeuner') : $page['category'] }}@if($page['arrive_at']) · {{ $page['arrive_at'] }}@if($page['leave_at']) → {{ $page['leave_at'] }}@endif @endif @if($page['visit_minutes'])· {{ $page['visit_minutes'] }} {{ __('min sur place') }}@endif @if($page['is_free'])· {{ __('gratuit') }}@endif</p>
                            </div>
                        </li>
                    @endforeach
                    <li class="relative pl-12"><span class="absolute left-0 top-0 h-8 w-8 rounded-full bg-teal text-white flex items-center justify-center"><span class="material-symbols-outlined" style="font-size:16px">sports_score</span></span><p class="font-semibold">{{ $result['end']['label'] ?? __('Fin du parcours') }}</p><p class="text-xs text-ink-muted">{{ $endsAt ? $endsAt->format('H\hi') : '' }}</p></li>
                </ol>
                @if($sketchBig)<div class="card p-4 hidden lg:block"><p class="text-[10px] font-bold uppercase tracking-[0.16em] text-ink-muted mb-2">{{ __('Le tracé') }}</p><div class="w-full [&>svg]:w-full [&>svg]:h-auto">{!! $sketchBig !!}</div></div>@endif
            </div>
        </section>

        {{-- ============================== Les lieux --}}
        <section class="py-8 sm:py-12 border-t border-ink/5">
            <p class="eyebrow">{{ __('Les lieux') }}</p>
            <h2 class="display text-2xl sm:text-4xl mt-1">{{ trans_choice(':n halte|:n haltes', count($visits), ['n' => count($visits)]) }}</h2>
            <div class="mt-6 grid gap-5 md:grid-cols-2">
                @foreach($pages as $page)
                    <article class="chapter card overflow-hidden">
                        <div class="relative aspect-[16/10] overflow-hidden placeholder-cover" style="--c1:{{ \App\Services\ColorHelper::forSlug($page['slug'] ?? null) }};--c2:#12161C">
                            @if($page['photo_medium'])<img src="{{ $page['photo_medium'] }}" alt="{{ $page['title'] }}" loading="lazy" class="absolute inset-0 w-full h-full object-cover">@else<span class="absolute inset-0 flex items-center justify-center"><span class="material-symbols-outlined text-white/80" style="font-size:56px">{{ $page['kind'] === 'lunch' ? 'restaurant' : 'place' }}</span></span>@endif
                            <span class="absolute top-3 left-3 h-9 w-9 rounded-full bg-white/95 text-ink-fixed font-bold text-sm flex items-center justify-center shadow-card">{{ $page['index'] }}</span>
                            @if($page['arrive_at'])<span class="absolute bottom-3 left-3 rounded-full bg-ink-fixed/70 text-white text-[11px] font-semibold px-2.5 py-1 backdrop-blur">{{ $page['arrive_at'] }}@if($page['visit_minutes']) · {{ $page['visit_minutes'] }} min @endif</span>@endif
                        </div>
                        <div class="p-4 sm:p-5">
                            <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-coral">{{ $page['kind'] === 'lunch' ? __('Pause déjeuner') : $page['category'] }}</p>
                            <h3 class="display text-2xl mt-1 leading-tight"><a href="{{ $page['place'] ? route('places.show', $page['place']) : '#' }}" class="hover:text-coral transition">{{ $page['title'] }}</a></h3>
                            @if($page['address'])<p class="text-xs text-ink-muted mt-1">{{ $page['address'] }}</p>@endif
                            @if($page['excerpt'])<p class="mt-3 font-display italic text-[16px] leading-relaxed text-ink-soft border-l-2 border-coral pl-4">{{ $page['excerpt'] }}</p>@endif
                            @if($page['photos']->isNotEmpty())
                                <div class="mt-4 grid grid-cols-3 gap-2">@foreach($page['photos'] as $photo)<img src="{{ $photo->url }}" alt="{{ $photo->caption }}" loading="lazy" class="aspect-square w-full object-cover rounded-xl">@endforeach</div>
                                <p class="mt-1.5 text-[11px] text-ink-muted">{{ __('Tes photos') }}</p>
                            @endif
                            @if($page['reason'])<p class="mt-3"><span class="badge bg-teal-soft text-teal-dark"><span class="material-symbols-outlined" style="font-size:12px">auto_awesome</span>{{ __($page['reason']) }}</span></p>@endif
                        </div>
                    </article>
                @endforeach
            </div>
        </section>

        {{-- ============================== Chiffres et signature --}}
        <section class="py-8 sm:py-12 border-t border-ink/5">
            <div class="rounded-4xl bg-ink text-white p-6 sm:p-10 relative overflow-hidden">
                <div class="absolute -right-20 -top-24 h-72 w-72 rounded-full bg-coral/30 blur-3xl"></div>
                <div class="relative grid gap-8 lg:grid-cols-[1fr_auto] items-end">
                    <div>
                        <p class="text-[11px] font-bold uppercase tracking-[0.28em] text-sun">{{ __('En chiffres') }}</p>
                        <div class="mt-4 grid grid-cols-2 sm:grid-cols-4 gap-3">
                            @foreach([[$stats['places'], __('lieux')], [number_format($stats['km'], 1, ',', ' ') . ' km', $modeLabel], [$durationLabel, __('de balade')], [$stats['cost'] > 0 ? number_format($stats['cost'], 0, ',', ' ') . ' €' : __('0 €'), __('dépensés')]] as [$value, $label])
                                <div class="rounded-2xl bg-white/10 p-4"><p class="font-display text-3xl sm:text-4xl">{{ $value }}</p><p class="text-xs text-white/60 mt-1">{{ $label }}</p></div>
                            @endforeach
                        </div>
                    </div>
                    <div class="flex flex-col items-start gap-3">
                        <p class="font-display text-2xl leading-tight">{{ __('Une journée signée CAMINO.') }}</p>
                        <div class="flex flex-wrap gap-2 no-print">
                            @if($shared)<form method="POST" action="{{ route('itineraries.shared-open', $token) }}">@csrf<button class="btn btn-md btn-primary"><span class="material-symbols-outlined" style="font-size:18px">navigation</span>{{ __('Refaire ce parcours') }}</button></form>@endif
                            <a href="{{ route('itineraries.create') }}" class="btn btn-md bg-white/10 text-white border border-white/15 hover:bg-white/20"><span class="material-symbols-outlined" style="font-size:18px">auto_awesome</span>{{ __('Créer le mien') }}</a>
                        </div>
                    </div>
                </div>
            </div>
            <p class="mt-6 text-center text-[11px] text-ink-muted">{{ __('Fait avec CAMINO, le GPS culturel intelligent.') }} · camino-u0eo.onrender.com</p>
        </section>
    </main>

    {{-- ============================== Mode récap : diapositives plein écran --}}
    <div x-show="story" x-cloak class="story fixed inset-0 z-[100] bg-ink-fixed/95 backdrop-blur-sm flex items-center justify-center" @keydown.escape.window="closeStory()" @keydown.arrow-right.window="next()" @keydown.arrow-left.window="prev()" x-ref="story">
        <div class="absolute top-4 inset-x-4 sm:inset-x-auto sm:w-[360px] z-10 pointer-events-none" :style="'transform: scale(var(--k)); transform-origin: top center;'">
            <div class="story-progress"><template x-for="(s, i) in slides" :key="'p' + i"><i :class="i < slide ? 'done' : (i === slide ? 'on' : '')" :style="'--dur:' + (s.ms / 1000) + 's'"><b></b></i></template></div>
        </div>
        <button type="button" @click="closeStory()" class="absolute top-4 right-4 z-20 h-10 w-10 rounded-full bg-white/15 text-white flex items-center justify-center" aria-label="{{ __('Fermer') }}"><span class="material-symbols-outlined">close</span></button>
        <div class="absolute bottom-5 z-20 flex gap-2">
            <button type="button" @click="prev()" class="h-10 w-10 rounded-full bg-white/15 text-white flex items-center justify-center"><span class="material-symbols-outlined">chevron_left</span></button>
            <button type="button" @click="paused ? resume() : pause()" class="h-10 w-10 rounded-full bg-white/15 text-white flex items-center justify-center"><span class="material-symbols-outlined" x-text="paused ? 'play_arrow' : 'pause'"></span></button>
            <button type="button" @click="shareSlide()" class="h-10 px-4 rounded-full bg-white text-ink-fixed font-semibold text-sm inline-flex items-center gap-1.5" :disabled="busy"><span class="material-symbols-outlined" style="font-size:18px" x-text="busy ? 'progress_activity' : 'ios_share'" :class="busy && 'animate-spin'"></span>{{ __('Partager cette carte') }}</button>
            <button type="button" @click="next()" class="h-10 w-10 rounded-full bg-white/15 text-white flex items-center justify-center"><span class="material-symbols-outlined">chevron_right</span></button>
        </div>
        <div class="absolute inset-0" @click="tap($event)"></div>
        <div class="relative pointer-events-none" x-ref="stage">
            @php $slideIndex = 0; @endphp
            {{-- 1. Couverture --}}
            <div class="story-slide" x-show="slide === {{ $slideIndex++ }}" x-transition.opacity.duration.300ms data-ms="5000">
                @if($cover && $cover['photo'])<img class="bg" src="{{ $cover['photo'] }}" alt="" crossorigin="anonymous">@endif
                <div class="shade"></div>
                <div class="inner">
                    <span class="brand absolute top-6 left-6 inline-flex items-center gap-2 font-display font-semibold text-lg"><x-logo :size="26" animate="none" />CAMINO</span>
                    <p class="eyebrow text-[10px] font-bold uppercase">{{ __('Carnet de voyage') }}</p>
                    <p class="h mt-2">{{ $itinerary->name }}</p>
                    <p class="quote mt-2">{{ $dateLabel }}</p>
                </div>
            </div>
            {{-- 2. Les chiffres --}}
            <div class="story-slide" x-show="slide === {{ $slideIndex++ }}" x-transition.opacity.duration.300ms data-ms="6000" style="background: linear-gradient(160deg, #FF5A3C, #B4301A 70%)">
                <div class="inner">
                    <p class="eyebrow text-[10px] font-bold uppercase" style="color:#FFE9E3">{{ __('Ta journée') }}</p>
                    <p class="big mt-3">{{ $stats['places'] }}</p><p class="text-lg -mt-1">{{ __('lieux') }}</p>
                    <p class="big mt-5">{{ number_format($stats['km'], 1, ',', ' ') }}<span class="text-3xl"> km</span></p><p class="text-lg -mt-1">{{ $modeLabel }}</p>
                    <p class="big mt-5">{{ $durationLabel }}</p><p class="text-lg -mt-1">{{ __('de balade') }}</p>
                </div>
            </div>
            {{-- 3. Le tracé --}}
            <div class="story-slide" x-show="slide === {{ $slideIndex++ }}" x-transition.opacity.duration.300ms data-ms="6000" style="background:#F6F3EC;color:#12161C">
                <div class="inner">
                    <div class="absolute top-16 inset-x-6 [&>svg]:w-full [&>svg]:h-auto">{!! \App\Services\RouteSketch::svg($result['geometry'] ?? [], array_merge($startStop, $stops), 308, 300, '#FF5A3C', '#12161C') !!}</div>
                    <p class="eyebrow text-[10px] font-bold uppercase" style="color:#FF5A3C">{{ __('Le tracé') }}</p>
                    <p class="h mt-2">{{ $startLabel ?: __('Départ') }}@if($startsAt) · {{ $startsAt->format('H\hi') }}@endif</p>
                    <ol class="mt-3 text-sm space-y-1">@foreach(array_slice($pages, 0, 5) as $page)<li class="flex gap-2"><b class="inline-flex h-5 w-5 rounded-full bg-ink-fixed text-white text-[10px] items-center justify-center shrink-0">{{ $page['index'] }}</b><span class="truncate">{{ $page['title'] }}</span><small class="opacity-60 ml-auto shrink-0">{{ $page['arrive_at'] }}</small></li>@endforeach</ol>
                </div>
            </div>
            {{-- 4… Un lieu par diapositive --}}
            @foreach($pages as $page)
                <div class="story-slide" x-show="slide === {{ $slideIndex++ }}" x-transition.opacity.duration.300ms data-ms="6000">
                    @if($page['photo_medium'])<img class="bg" src="{{ $page['photo_medium'] }}" alt="" crossorigin="anonymous">@else<div class="absolute inset-0 placeholder-cover" style="--c1:{{ \App\Services\ColorHelper::forSlug($page['slug'] ?? null) }};--c2:#12161C"></div>@endif
                    <div class="shade"></div>
                    <span class="num">{{ $page['index'] }}</span>
                    <div class="inner">
                        <p class="eyebrow text-[10px] font-bold uppercase">{{ $page['kind'] === 'lunch' ? __('Pause déjeuner') : $page['category'] }}@if($page['arrive_at']) · {{ $page['arrive_at'] }}@endif</p>
                        <p class="h mt-2">{{ $page['title'] }}</p>
                        @if($page['excerpt'])<p class="quote mt-3 line-clamp-4">{{ \Illuminate\Support\Str::limit($page['excerpt'], 170) }}</p>@endif
                    </div>
                </div>
            @endforeach
            {{-- Moments forts --}}
            @if(!empty($highlights))
                <div class="story-slide" x-show="slide === {{ $slideIndex++ }}" x-transition.opacity.duration.300ms data-ms="6000" style="background: linear-gradient(160deg, #0F8B8D, #0B4F51 75%)">
                    <div class="inner">
                        <p class="eyebrow text-[10px] font-bold uppercase" style="color:#DDF3F2">{{ __('Les moments forts') }}</p>
                        @foreach(array_slice($highlights, 0, 3) as $h)
                            <div class="mt-4 flex items-start gap-3"><span class="material-symbols-outlined mt-1" style="font-size:22px">{{ $h['icon'] }}</span><div><p class="text-[11px] uppercase tracking-widest opacity-75">{{ $h['label'] }}</p><p class="font-display text-2xl leading-tight">{{ $h['value'] }}</p><p class="text-sm opacity-80">{{ $h['detail'] }}</p></div></div>
                        @endforeach
                    </div>
                </div>
            @endif
            {{-- Fin --}}
            <div class="story-slide" x-show="slide === {{ $slideIndex++ }}" x-transition.opacity.duration.300ms data-ms="6000">
                @if($cover && $cover['photo'])<img class="bg" src="{{ $cover['photo'] }}" alt="" crossorigin="anonymous" style="filter: saturate(.6) brightness(.6)">@endif
                <div class="shade"></div>
                <div class="inner items-center text-center">
                    <x-logo :size="64" animate="loop" />
                    <p class="h mt-4">{{ __('Une journée signée CAMINO.') }}</p>
                    <p class="text-sm opacity-80 mt-3">{{ __('Génère ton propre parcours selon ton temps, ton budget et la météo.') }}</p>
                    <p class="text-xs mt-6" style="color:#FFC857">camino-u0eo.onrender.com</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        function journal(data) {
            return {
                data, busy: false, copied: false, story: false, slide: 0, paused: false, timer: null, slides: [],
                init() {
                    const fit = () => { const w = Math.min(540, window.innerWidth - 32); this.$refs.wrap.style.setProperty('--s', (w / 540).toFixed(4)); };
                    fit(); window.addEventListener('resize', fit);
                    this.slides = [...this.$refs.stage.querySelectorAll('.story-slide')].map(el => ({ ms: parseInt(el.dataset.ms || '6000', 10) }));
                    const fitStory = () => { const k = Math.min((window.innerWidth - 24) / 360, (window.innerHeight - 120) / 640, 1.35); this.$refs.story.style.setProperty('--k', k.toFixed(4)); };
                    fitStory(); window.addEventListener('resize', fitStory);
                },
                // -------- export de la carte souvenir
                async render(node, w, h) {
                    const mod = await window.Camino.loadHtmlToImage();
                    await document.fonts.ready;
                    return mod.toPng(node, { pixelRatio: 2, cacheBust: true, width: w, height: h, style: { transform: 'none' } });
                },
                async save(url, name) { const a = document.createElement('a'); a.href = url; a.download = name; document.body.appendChild(a); a.click(); a.remove(); },
                async shareUrlOrSave(url, name) {
                    const blob = await (await fetch(url)).blob();
                    const file = new File([blob], name, { type: 'image/png' });
                    if (navigator.canShare && navigator.canShare({ files: [file] })) { await navigator.share({ files: [file], title: data.title, text: data.text + (data.shareUrl ? ' · ' + data.shareUrl : '') }); }
                    else this.save(url, name);
                },
                async download() { this.busy = true; try { this.save(await this.render(document.getElementById('souvenir'), 540, 675), data.file + '.png'); } catch (e) { console.warn(e); window.dispatchEvent(new CustomEvent('toast', { detail: data.t.error })); } this.busy = false; },
                async share() { this.busy = true; try { await this.shareUrlOrSave(await this.render(document.getElementById('souvenir'), 540, 675), data.file + '.png'); } catch (e) { if (e && e.name !== 'AbortError') { console.warn(e); window.dispatchEvent(new CustomEvent('toast', { detail: data.t.error })); } } this.busy = false; },
                async shareSlide() {
                    this.pause(); this.busy = true;
                    try { const el = this.$refs.stage.querySelectorAll('.story-slide')[this.slide]; await this.shareUrlOrSave(await this.render(el, 360, 640), data.file + '-' + (this.slide + 1) + '.png'); }
                    catch (e) { if (e && e.name !== 'AbortError') { console.warn(e); window.dispatchEvent(new CustomEvent('toast', { detail: data.t.error })); } }
                    this.busy = false;
                },
                copy() { if (!data.shareUrl) return; navigator.clipboard.writeText(data.shareUrl); this.copied = true; setTimeout(() => this.copied = false, 2500); },
                // -------- récap en diapositives
                openStory() { this.story = true; this.slide = 0; this.paused = false; document.body.style.overflow = 'hidden'; this.arm(); },
                closeStory() { this.story = false; clearTimeout(this.timer); document.body.style.overflow = ''; },
                arm() { clearTimeout(this.timer); if (this.paused) return; this.timer = setTimeout(() => this.next(), this.slides[this.slide]?.ms || 6000); },
                next() { if (this.slide >= this.slides.length - 1) { this.pause(); return; } this.slide++; this.arm(); },
                prev() { if (this.slide > 0) this.slide--; this.arm(); },
                pause() { this.paused = true; clearTimeout(this.timer); },
                resume() { this.paused = false; this.arm(); },
                tap(e) { const x = e.clientX / window.innerWidth; if (x < 0.3) this.prev(); else if (x > 0.7) this.next(); else (this.paused ? this.resume() : this.pause()); },
            };
        }
    </script>
    <div x-data="{ msg: null, t: null }" x-on:toast.window="msg = $event.detail; clearTimeout(t); t = setTimeout(() => msg = null, 3500)" x-cloak x-show="msg" x-transition class="fixed bottom-6 left-1/2 -translate-x-1/2 z-[110] rounded-full bg-ink text-white px-4 py-2 text-sm shadow-float" x-text="msg"></div>
</body>
</html>
