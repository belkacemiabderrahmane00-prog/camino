@php
    /** Carnet de voyage en PDF (dompdf) : couverture, résumé, une page par lieu, chiffres. Mise en page sans flex/grid. */
    $lang = app()->getLocale();
    $modeLabel = match ($stats['mode']) { 'bike' => __('à vélo'), 'transit' => __('à pied et en transports'), default => __('à pied') };
    $hours = intdiv($stats['minutes'], 60);
    $mins = $stats['minutes'] % 60;
    $durationLabel = $hours > 0 ? $hours . ' h' . ($mins ? ' ' . str_pad((string) $mins, 2, '0', STR_PAD_LEFT) : '') : $mins . ' min';
    $startLabel = $result['start']['label'] ?? '';
    $startsAt = ! empty($result['starts_at']) ? \Illuminate\Support\Carbon::parse($result['starts_at']) : null;
    $endsAt = ! empty($result['ends_at']) ? \Illuminate\Support\Carbon::parse($result['ends_at']) : null;
    $dateLabel = ucfirst($date->translatedFormat('l j F Y'));
    $stops = collect($pages)->map(fn ($p) => ['lat' => $p['lat'], 'lng' => $p['lng'], 'n' => $p['index']])->all();
    $sketch = \App\Services\RouteSketch::svg($result['geometry'] ?? [], array_merge([['lat' => $result['start']['lat'] ?? null, 'lng' => $result['start']['lng'] ?? null, 'n' => 'start']], $stops), 520, 300, '#FF5A3C', '#12161C');
    $fonts = public_path('fonts');
    $shareUrl = $itinerary->share_token ? route('itineraries.shared-journal', $itinerary->share_token) : null;
@endphp
<!DOCTYPE html>
<html lang="{{ $lang }}">
<head>
<meta charset="utf-8">
<title>{{ $itinerary->name }}</title>
<style>
    @font-face { font-family: 'Sora'; font-style: normal; font-weight: normal; src: url('{{ $fonts }}/Sora-Regular.ttf') format('truetype'); }
    @font-face { font-family: 'Sora'; font-style: normal; font-weight: bold; src: url('{{ $fonts }}/Sora-Bold.ttf') format('truetype'); }
    @font-face { font-family: 'Fraunces'; font-style: normal; font-weight: normal; src: url('{{ $fonts }}/Fraunces-Regular.ttf') format('truetype'); }
    @font-face { font-family: 'Fraunces'; font-style: normal; font-weight: bold; src: url('{{ $fonts }}/Fraunces-Regular.ttf') format('truetype'); }
    @font-face { font-family: 'Fraunces'; font-style: italic; font-weight: normal; src: url('{{ $fonts }}/Fraunces-Italic.ttf') format('truetype'); }
    @font-face { font-family: 'Fraunces'; font-style: italic; font-weight: bold; src: url('{{ $fonts }}/Fraunces-Italic.ttf') format('truetype'); }
    @page { margin: 0; }
    * { box-sizing: border-box; }
    body { margin: 0; font-family: 'Sora', 'DejaVu Sans', sans-serif; color: #12161C; font-size: 11pt; line-height: 1.45; }
    .page { page-break-after: always; position: relative; width: 210mm; height: 297mm; overflow: hidden; }
    .page.last { page-break-after: auto; }
    .serif { font-family: 'Fraunces', 'DejaVu Serif', Georgia, serif; }
    .eyebrow { font-size: 8pt; font-weight: 700; letter-spacing: 3px; text-transform: uppercase; color: #FF5A3C; }
    .muted { color: #6B7684; }
    /* Couverture */
    .cover { background: #12161C; color: #fff; }
    .cover img.bg { position: absolute; left: 0; top: 0; width: 210mm; height: 297mm; object-fit: cover; }
    .cover .shade { position: absolute; left: 0; top: 0; width: 210mm; height: 297mm; background: linear-gradient(to bottom, rgba(18,22,28,.35) 0%, rgba(18,22,28,.05) 35%, rgba(18,22,28,.9) 100%); }
    .cover .top { position: absolute; left: 16mm; top: 14mm; color: #fff; font-family: 'Fraunces', serif; font-size: 16pt; }
    .cover .top .tag { float: right; font-family: 'Sora', sans-serif; font-size: 8pt; letter-spacing: 3px; text-transform: uppercase; color: #FFC857; margin-top: 4pt; }
    .cover .bottom { position: absolute; left: 16mm; right: 16mm; bottom: 18mm; color: #fff; }
    .cover h1 { font-family: 'Fraunces', serif; font-weight: 400; font-size: 34pt; line-height: 1.02; margin: 0 0 8pt; }
    .cover .date { font-family: 'Fraunces', serif; font-style: italic; font-size: 13pt; opacity: .9; margin: 0 0 12pt; }
    .chip { display: inline-block; border: 1px solid rgba(255,255,255,.35); border-radius: 999px; padding: 3pt 9pt; font-size: 9pt; margin-right: 5pt; color: #fff; }
    /* Pages intérieures */
    .inner { padding: 18mm 16mm 16mm; }
    h2 { font-family: 'Fraunces', serif; font-weight: 400; font-size: 24pt; margin: 4pt 0 10pt; line-height: 1.05; }
    table.timeline { width: 100%; border-collapse: collapse; margin-top: 8pt; }
    table.timeline td { padding: 5pt 4pt; border-bottom: 1px solid #EEE9DF; vertical-align: top; font-size: 10pt; }
    table.timeline td.n { width: 22pt; }
    .num { display: inline-block; width: 16pt; height: 16pt; border-radius: 8pt; background: #12161C; color: #fff; text-align: center; font-size: 8pt; font-weight: 700; line-height: 16pt; }
    .num.start { background: #FF5A3C; } .num.end { background: #0F8B8D; } .num.lunch { background: #FFC857; color: #12161C; }
    .stats td { width: 25%; padding: 8pt; text-align: center; }
    .stats .v { font-family: 'Fraunces', serif; font-size: 22pt; }
    .stats .l { font-size: 8pt; color: #6B7684; }
    .photo { width: 178mm; height: 118mm; object-fit: cover; border-radius: 10pt; }
    .quote { font-family: 'Fraunces', serif; font-style: italic; font-size: 13pt; line-height: 1.5; color: #3B4552; border-left: 2pt solid #FF5A3C; padding-left: 10pt; margin: 12pt 0; }
    .badge { display: inline-block; background: #DDF3F2; color: #0B6F71; border-radius: 999px; padding: 2pt 8pt; font-size: 8pt; font-weight: 700; margin-right: 4pt; }
    .badge.paid { background: #EEE9DF; color: #3B4552; }
    .userphotos img { width: 56mm; height: 42mm; object-fit: cover; border-radius: 6pt; margin-right: 3mm; }
    .footer { position: absolute; left: 16mm; right: 16mm; bottom: 10mm; font-size: 8pt; color: #6B7684; }
    .footer .r { float: right; }
    .dark { background: #12161C; color: #fff; }
    .dark .muted { color: rgba(255,255,255,.6); }
</style>
</head>
<body>

{{-- Couverture --}}
<div class="page cover">
    @if($cover && $cover['photo'])<img class="bg" src="{{ $cover['photo'] }}" alt="">@endif
    <div class="shade"></div>
    <div class="top">CAMINO <span class="tag">{{ __('Carnet de voyage') }}</span></div>
    <div class="bottom">
        <h1>{{ $itinerary->name }}</h1>
        <p class="date">{{ $dateLabel }}</p>
        <span class="chip">{{ trans_choice(':n lieu|:n lieux', $stats['places'], ['n' => $stats['places']]) }}</span>
        <span class="chip">{{ number_format($stats['km'], 1, ',', ' ') }} km {{ $modeLabel }}</span>
        <span class="chip">{{ $durationLabel }}</span>
        @if($cover && $cover['title'])<p class="muted" style="font-size:7pt;letter-spacing:2px;text-transform:uppercase;margin-top:14pt;color:rgba(255,255,255,.55)">{{ __('En couverture') }} · {{ $cover['title'] }}</p>@endif
    </div>
</div>

{{-- Résumé --}}
<div class="page">
    <div class="inner">
        <p class="eyebrow">{{ __('Le résumé') }}</p>
        <h2>{{ $dateLabel }}</h2>
        <p class="muted" style="margin:0 0 10pt">{{ __('Départ') }} {{ $startLabel }}@if($startsAt) · {{ $startsAt->format('H\hi') }}@endif @if($endsAt)· {{ __('retour vers') }} {{ $endsAt->format('H\hi') }}@endif · {{ number_format($stats['km'], 1, ',', ' ') }} km {{ $modeLabel }}</p>
        @if($sketch)<div style="text-align:center;margin:6pt 0 10pt">{!! $sketch !!}</div>@endif
        <table class="timeline">
            <tr><td class="n"><span class="num start">&#9873;</span></td><td><b>{{ $startLabel ?: __('Départ') }}</b></td><td class="muted" style="text-align:right">{{ $startsAt ? $startsAt->format('H\hi') : '' }}</td></tr>
            @foreach($pages as $page)
                @if($page['travel_minutes'])<tr><td></td><td class="muted" colspan="2" style="font-size:9pt">↓ {{ $page['travel_minutes'] }} {{ __('min de trajet') }}</td></tr>@endif
                <tr>
                    <td class="n"><span class="num {{ $page['kind'] === 'lunch' ? 'lunch' : '' }}">{{ $page['kind'] === 'lunch' ? '·' : $page['index'] }}</span></td>
                    <td><b>{{ $page['title'] }}</b><br><span class="muted" style="font-size:9pt">{{ $page['kind'] === 'lunch' ? __('Pause déjeuner') : $page['category'] }}@if($page['visit_minutes']) · {{ $page['visit_minutes'] }} {{ __('min sur place') }}@endif @if($page['is_free'])· {{ __('gratuit') }}@endif</span></td>
                    <td class="muted" style="text-align:right;white-space:nowrap">{{ $page['arrive_at'] }}@if($page['leave_at']) → {{ $page['leave_at'] }}@endif</td>
                </tr>
            @endforeach
            <tr><td class="n"><span class="num end">&#9873;</span></td><td><b>{{ $result['end']['label'] ?? __('Fin du parcours') }}</b></td><td class="muted" style="text-align:right">{{ $endsAt ? $endsAt->format('H\hi') : '' }}</td></tr>
        </table>
    </div>
    <div class="footer">CAMINO · {{ __('Carnet de voyage') }}<span class="r">{{ $itinerary->name }}</span></div>
</div>

{{-- Une page par lieu --}}
@foreach($pages as $page)
<div class="page">
    <div class="inner">
        <p class="eyebrow">{{ str_pad((string) $page['index'], 2, '0', STR_PAD_LEFT) }} · {{ $page['kind'] === 'lunch' ? __('Pause déjeuner') : $page['category'] }}</p>
        <h2>{{ $page['title'] }}</h2>
        @if($page['address'])<p class="muted" style="margin:0 0 8pt;font-size:9.5pt">{{ $page['address'] }}</p>@endif
        @if($page['photo_medium'])<img class="photo" src="{{ $page['photo_medium'] }}" alt="">@endif
        <p style="margin:8pt 0 0;font-size:9.5pt" class="muted">@if($page['arrive_at']){{ __('Arrivée') }} {{ $page['arrive_at'] }}@endif @if($page['visit_minutes'])· {{ $page['visit_minutes'] }} {{ __('min sur place') }}@endif @if($page['is_free'])· <span class="badge">{{ __('Gratuit') }}</span>@endif @if($page['reason'])<span class="badge">{{ __($page['reason']) }}</span>@endif</p>
        @if($page['excerpt'])<p class="quote">{{ $page['excerpt'] }}</p>@endif
        @if($page['photos']->isNotEmpty())
            <p class="eyebrow" style="margin-top:10pt">{{ __('Tes photos') }}</p>
            <div class="userphotos">@foreach($page['photos']->take(3) as $photo)<img src="{{ $photo->url }}" alt="">@endforeach</div>
        @endif
    </div>
    <div class="footer">CAMINO · {{ __('Carnet de voyage') }}<span class="r">{{ $page['index'] }} / {{ count($pages) }}</span></div>
</div>
@endforeach

{{-- Chiffres --}}
<div class="page dark last">
    <div class="inner">
        <p class="eyebrow" style="color:#FFC857">{{ __('En chiffres') }}</p>
        <h2 style="color:#fff">{{ __('Une journée signée CAMINO.') }}</h2>
        <table class="stats" style="width:100%;margin-top:14pt">
            <tr>
                <td><div class="v">{{ $stats['places'] }}</div><div class="l">{{ __('lieux') }}</div></td>
                <td><div class="v">{{ number_format($stats['km'], 1, ',', ' ') }} km</div><div class="l">{{ $modeLabel }}</div></td>
                <td><div class="v">{{ $durationLabel }}</div><div class="l">{{ __('de balade') }}</div></td>
                <td><div class="v">{{ $stats['cost'] > 0 ? number_format($stats['cost'], 0, ',', ' ') . ' €' : __('0 €') }}</div><div class="l">{{ __('dépensés') }}</div></td>
            </tr>
        </table>
        <p class="muted" style="margin-top:24pt">{{ __('Génère ton propre parcours selon ton temps, ton budget et la météo, puis laisse-toi guider à la voix.') }}</p>
        @if($shareUrl)<p style="margin-top:14pt;font-size:9pt;color:#FFC857">{{ $shareUrl }}</p>@endif
        <p class="muted" style="margin-top:40pt;font-size:8pt">{{ __('Fait avec CAMINO, le GPS culturel intelligent.') }} · camino-u0eo.onrender.com</p>
    </div>
</div>
</body>
</html>
