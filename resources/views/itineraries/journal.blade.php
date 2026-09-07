@php
    /**
     * Carnet de voyage : objet souvenir autonome (sans en-tête ni pied de page de l'app).
     * Carte souvenir à partager en image, résumé du parcours, chapitres par lieu, chiffres, PDF.
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
    $sketch = \App\Services\RouteSketch::svg($result['geometry'] ?? [], array_merge([['lat' => $result['start']['lat'] ?? null, 'lng' => $result['start']['lng'] ?? null, 'n' => 'start']], $stops), 220, 140, '#FFC857', '#12161C');
    $visits = array_values(array_filter($pages, fn ($p) => $p['kind'] === 'visit'));
    $fileName = 'camino-' . \Illuminate\Support\Str::slug(\Illuminate\Support\Str::limit($itinerary->name, 40, ''));
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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700&family=Fraunces:ital,opsz,wght@0,9..144,500;0,9..144,600;1,9..144,500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,300..600,0..1,0">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        /* Carte souvenir : format 4:5 (1080×1350 à l'export), rendue à 540×675 puis exportée en double résolution. */
        .souvenir { position: relative; width: 540px; height: 675px; border-radius: 28px; overflow: hidden; background: #12161C; color: #fff; font-family: Sora, sans-serif; box-shadow: 0 40px 80px -30px rgba(18,22,28,.55); }
        .souvenir .photo { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }
        .souvenir .shade { position: absolute; inset: 0; background: linear-gradient(180deg, rgba(18,22,28,.55) 0%, rgba(18,22,28,0) 30%, rgba(18,22,28,.1) 50%, rgba(18,22,28,.92) 100%); }
        .souvenir .top { position: absolute; top: 26px; left: 28px; right: 28px; display: flex; align-items: center; justify-content: space-between; }
        .souvenir .bottom { position: absolute; left: 28px; right: 28px; bottom: 26px; }
        .souvenir .title { font-family: Fraunces, Georgia, serif; font-weight: 500; font-size: 40px; line-height: 1.02; letter-spacing: -.02em; }
        .souvenir .date { font-family: Fraunces, Georgia, serif; font-style: italic; font-size: 17px; opacity: .85; margin-top: 8px; }
        .souvenir .stats { display: flex; gap: 8px; margin-top: 14px; flex-wrap: wrap; }
        .souvenir .stats span { display: inline-flex; align-items: center; gap: 5px; background: rgba(255,255,255,.14); border: 1px solid rgba(255,255,255,.18); border-radius: 999px; padding: 6px 11px; font-size: 12px; font-weight: 600; backdrop-filter: blur(6px); }
        .souvenir .stops { margin-top: 14px; display: grid; grid-template-columns: 1fr auto; gap: 10px; align-items: end; }
        .souvenir .stops ol { list-style: none; margin: 0; padding: 0; font-size: 12.5px; line-height: 1.35; }
        .souvenir .stops li { display: flex; gap: 8px; align-items: baseline; margin-top: 3px; }
        .souvenir .stops li b { display: inline-flex; width: 18px; height: 18px; border-radius: 50%; background: #FFC857; color: #12161C; font-size: 10px; align-items: center; justify-content: center; flex-shrink: 0; transform: translateY(2px); }
        .souvenir .stops li span { opacity: .92; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 250px; }
        .souvenir .stops li small { opacity: .6; font-size: 11px; }
        .souvenir .sketch { width: 220px; height: 140px; }
        .souvenir .brand { display: inline-flex; align-items: center; gap: 8px; font-family: Fraunces, Georgia, serif; font-weight: 600; font-size: 20px; letter-spacing: -.01em; }
        .souvenir .tag { font-size: 10px; font-weight: 700; letter-spacing: .24em; text-transform: uppercase; color: #FFC857; }
        .souvenir-wrap { width: 540px; height: 675px; transform-origin: top left; }
        @media (max-width: 640px) { .souvenir-wrap { transform: scale(calc((100vw - 40px) / 540)); height: calc((100vw - 40px) * 1.25); } }
        .chapter img { transition: transform .8s ease; } .chapter:hover img { transform: scale(1.03); }
        @media print { .no-print { display: none !important; } .print-break { break-before: page; } }
    </style>
</head>
<body class="min-h-full bg-paper text-ink antialiased" x-data="journal(@js(['file' => $fileName, 'shareUrl' => $shareUrl, 'title' => $itinerary->name, 'text' => $dateLabel . ' · ' . trans_choice(':n lieu|:n lieux', $stats['places'], ['n' => $stats['places']]) . ' · ' . number_format($stats['km'], 1, ',', ' ') . ' km', 't' => ['copied' => __('Lien copié'), 'preparing' => __('Préparation de l\'image…'), 'error' => __('L\'image n\'a pas pu être créée. Essaie le PDF.')]]))">

    {{-- Barre discrète --}}
    <div class="no-print sticky top-0 z-40 bg-paper/85 backdrop-blur border-b border-ink/5">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 h-14 flex items-center gap-3">
            <a href="{{ $backUrl }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-ink-soft hover:text-ink"><span class="material-symbols-outlined" style="font-size:18px">arrow_back</span><span class="hidden sm:inline">{{ $shared ? 'CAMINO' : __('Mon parcours') }}</span></a>
            <span class="text-ink/20">|</span>
            <x-logo :size="26" animate="hover" class="camino-logo-hover" /><span class="text-xs font-bold uppercase tracking-[0.2em] text-ink-muted">{{ __('Carnet de voyage') }}</span>
            <span class="flex-1"></span>
            <button type="button" @click="share()" class="btn btn-sm btn-ink"><span class="material-symbols-outlined" style="font-size:16px" x-text="busy ? 'progress_activity' : 'ios_share'" :class="busy && 'animate-spin'"></span><span class="hidden sm:inline">{{ __('Partager') }}</span></button>
            <a href="{{ $pdfUrl }}" class="btn btn-sm btn-soft"><span class="material-symbols-outlined" style="font-size:16px">picture_as_pdf</span><span class="hidden sm:inline">PDF</span></a>
        </div>
    </div>

    <main class="max-w-6xl mx-auto px-4 sm:px-6">
        {{-- ============================== Souvenir --}}
        <section class="py-10 sm:py-16 grid gap-10 lg:grid-cols-[560px_1fr] items-center">
            <div class="souvenir-wrap mx-auto lg:mx-0">
                <div id="souvenir" class="souvenir">
                    @if($cover && $cover['photo'])<img class="photo" src="{{ $cover['photo'] }}" alt="" crossorigin="anonymous">@else<div class="photo placeholder-cover" style="--c1:#0F8B8D;--c2:#12161C"></div>@endif
                    <div class="shade"></div>
                    <div class="top">
                        <span class="brand"><x-logo :size="30" animate="none" />CAMINO</span>
                        <span class="tag">{{ __('Carnet de voyage') }}</span>
                    </div>
                    <div class="bottom">
                        <p class="title">{{ $itinerary->name }}</p>
                        <p class="date">{{ $dateLabel }}</p>
                        <div class="stats">
                            <span><span class="material-symbols-outlined" style="font-size:14px">museum</span>{{ trans_choice(':n lieu|:n lieux', $stats['places'], ['n' => $stats['places']]) }}</span>
                            <span><span class="material-symbols-outlined" style="font-size:14px">{{ $modeIcon }}</span>{{ number_format($stats['km'], 1, ',', ' ') }} km</span>
                            <span><span class="material-symbols-outlined" style="font-size:14px">schedule</span>{{ $durationLabel }}</span>
                            @if($stats['cost'] > 0)<span><span class="material-symbols-outlined" style="font-size:14px">payments</span>{{ number_format($stats['cost'], 0, ',', ' ') }} €</span>@else<span><span class="material-symbols-outlined" style="font-size:14px">loyalty</span>{{ __('Gratuit') }}</span>@endif
                        </div>
                        <div class="stops">
                            <ol>
                                @foreach(array_slice($pages, 0, 6) as $page)
                                    <li><b>{{ $page['index'] }}</b><span>{{ $page['title'] }}</span>@if($page['arrive_at'])<small>{{ $page['arrive_at'] }}</small>@endif</li>
                                @endforeach
                                @if(count($pages) > 6)<li><small>+{{ count($pages) - 6 }}</small></li>@endif
                            </ol>
                            @if($sketch)<div class="sketch">{!! $sketch !!}</div>@endif
                        </div>
                    </div>
                </div>
            </div>
            <div>
                <p class="eyebrow">{{ __('Ton souvenir') }}</p>
                <h1 class="display text-3xl sm:text-5xl mt-2 leading-[1.02]">{{ __('Une journée, une image.') }}</h1>
                <p class="mt-4 text-ink-soft max-w-md">{{ __('La carte de ta balade, prête à partager : tes étapes, ton tracé, tes chiffres. Le carnet complet est en dessous, et en PDF.') }}</p>
                <div class="mt-6 flex flex-wrap gap-2">
                    <button type="button" @click="share()" class="btn btn-md btn-primary" :disabled="busy"><span class="material-symbols-outlined" style="font-size:18px" x-text="busy ? 'progress_activity' : 'ios_share'" :class="busy && 'animate-spin'"></span>{{ __('Partager l\'image') }}</button>
                    <button type="button" @click="download()" class="btn btn-md btn-soft" :disabled="busy"><span class="material-symbols-outlined" style="font-size:18px">download</span>{{ __('Télécharger l\'image') }}</button>
                    <a href="{{ $pdfUrl }}" class="btn btn-md btn-soft"><span class="material-symbols-outlined" style="font-size:18px">picture_as_pdf</span>{{ __('Télécharger le PDF') }}</a>
                    @if($shareUrl)<button type="button" @click="copy()" class="btn btn-md btn-ghost"><span class="material-symbols-outlined" style="font-size:18px" x-text="copied ? 'check' : 'link'"></span><span x-text="copied ? data.t.copied : @js(__('Copier le lien'))"></span></button>
                    @elseif(!$shared)<form method="POST" action="{{ route('itineraries.share', $itinerary) }}">@csrf<button class="btn btn-md btn-ghost"><span class="material-symbols-outlined" style="font-size:18px">link</span>{{ __('Créer le lien de partage') }}</button></form>@endif
                </div>
                <p class="mt-3 text-[11px] text-ink-muted">{{ __('Image 1080 × 1350, idéale pour Instagram, WhatsApp et les stories.') }}</p>
            </div>
        </section>

        {{-- ============================== Résumé du parcours --}}
        <section class="py-10 sm:py-14 border-t border-ink/5">
            <div class="flex items-end justify-between gap-4 flex-wrap">
                <div><p class="eyebrow">{{ __('Le résumé') }}</p><h2 class="display text-2xl sm:text-4xl mt-1">{{ $dateLabel }}</h2></div>
                <p class="text-sm text-ink-muted">{{ __('Départ') }} {{ $startLabel }}@if($startsAt) · {{ $startsAt->format('H\hi') }}@endif @if($endsAt)· {{ __('retour vers') }} {{ $endsAt->format('H\hi') }}@endif</p>
            </div>
            <ol class="mt-6 relative">
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
        </section>

        {{-- ============================== Chapitres --}}
        <section class="py-10 sm:py-14 border-t border-ink/5">
            <p class="eyebrow">{{ __('Les lieux') }}</p>
            <h2 class="display text-2xl sm:text-4xl mt-1">{{ trans_choice(':n halte|:n haltes', count($visits), ['n' => count($visits)]) }}</h2>
            <div class="mt-6 grid gap-6 md:grid-cols-2">
                @foreach($pages as $page)
                    <article class="chapter card overflow-hidden print-break">
                        <div class="relative aspect-[4/3] overflow-hidden placeholder-cover" style="--c1:{{ \App\Services\ColorHelper::forSlug($page['slug'] ?? null) }};--c2:#12161C">
                            @if($page['photo_medium'])<img src="{{ $page['photo_medium'] }}" alt="{{ $page['title'] }}" loading="lazy" class="absolute inset-0 w-full h-full object-cover">@else<span class="absolute inset-0 flex items-center justify-center"><span class="material-symbols-outlined text-white/80" style="font-size:56px">{{ $page['kind'] === 'lunch' ? 'restaurant' : 'place' }}</span></span>@endif
                            <span class="absolute top-3 left-3 h-9 w-9 rounded-full bg-white/95 text-ink-fixed font-bold text-sm flex items-center justify-center shadow-card">{{ $page['index'] }}</span>
                            @if($page['arrive_at'])<span class="absolute bottom-3 left-3 rounded-full bg-ink-fixed/70 text-white text-[11px] font-semibold px-2.5 py-1 backdrop-blur">{{ $page['arrive_at'] }}@if($page['visit_minutes']) · {{ $page['visit_minutes'] }} min @endif</span>@endif
                        </div>
                        <div class="p-5">
                            <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-coral">{{ $page['kind'] === 'lunch' ? __('Pause déjeuner') : $page['category'] }}</p>
                            <h3 class="display text-2xl mt-1 leading-tight"><a href="{{ $page['place'] ? route('places.show', $page['place']) : '#' }}" class="hover:text-coral transition">{{ $page['title'] }}</a></h3>
                            @if($page['address'])<p class="text-xs text-ink-muted mt-1">{{ $page['address'] }}</p>@endif
                            @if($page['excerpt'])<p class="mt-3 font-display italic text-[17px] leading-relaxed text-ink-soft border-l-2 border-coral pl-4">{{ $page['excerpt'] }}</p>@endif
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
        <section class="py-10 sm:py-14 border-t border-ink/5">
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

    <script>
        function journal(data) {
            return {
                data, busy: false, copied: false,
                async render() {
                    const mod = await window.Camino.loadHtmlToImage();
                    const node = document.getElementById('souvenir');
                    await document.fonts.ready;
                    const dataUrl = await mod.toPng(node, { pixelRatio: 2, cacheBust: true, width: 540, height: 675, style: { transform: 'none' } });
                    return dataUrl;
                },
                async download() {
                    this.busy = true;
                    try { const url = await this.render(); const a = document.createElement('a'); a.href = url; a.download = data.file + '.png'; document.body.appendChild(a); a.click(); a.remove(); }
                    catch (e) { console.warn(e); window.dispatchEvent(new CustomEvent('toast', { detail: data.t.error })); }
                    this.busy = false;
                },
                async share() {
                    this.busy = true;
                    try {
                        const url = await this.render();
                        const blob = await (await fetch(url)).blob();
                        const file = new File([blob], data.file + '.png', { type: 'image/png' });
                        if (navigator.canShare && navigator.canShare({ files: [file] })) { await navigator.share({ files: [file], title: data.title, text: data.text + (data.shareUrl ? ' · ' + data.shareUrl : '') }); }
                        else { const a = document.createElement('a'); a.href = url; a.download = file.name; document.body.appendChild(a); a.click(); a.remove(); }
                    } catch (e) { if (e && e.name !== 'AbortError') { console.warn(e); window.dispatchEvent(new CustomEvent('toast', { detail: data.t.error })); } }
                    this.busy = false;
                },
                copy() { if (!data.shareUrl) return; navigator.clipboard.writeText(data.shareUrl); this.copied = true; setTimeout(() => this.copied = false, 2500); },
            };
        }
    </script>
    <div x-data="{ msg: null, t: null }" x-on:toast.window="msg = $event.detail; clearTimeout(t); t = setTimeout(() => msg = null, 3500)" x-cloak x-show="msg" x-transition class="fixed bottom-6 left-1/2 -translate-x-1/2 z-50 rounded-full bg-ink text-white px-4 py-2 text-sm shadow-float" x-text="msg"></div>
</body>
</html>
