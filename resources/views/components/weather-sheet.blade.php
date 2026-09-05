{{-- Feuille météo globale : s'ouvre avec $dispatch('open-weather'). Données injectées par le View composer (globalForecast, globalAdvice). --}}
@php
    $wf = $globalForecast ?? ['current' => null, 'hours' => [], 'days' => [], 'available' => false];
    $wa = $globalAdvice ?? null;
    $now = \Illuminate\Support\Carbon::now(config('app.timezone'));
    $nextHours = collect($wf['hours'] ?? [])->filter(fn ($h) => \Illuminate\Support\Carbon::parse($h['time'], config('app.timezone'))->gte($now->copy()->startOfHour()))->take(8)->values();
    $tones = ['sun' => 'from-amber-300 to-orange-400', 'rain' => 'from-sky-400 to-indigo-500', 'hot' => 'from-orange-400 to-rose-500', 'cold' => 'from-sky-200 to-blue-400', 'mild' => 'from-teal to-teal-dark', 'neutral' => 'from-slate-400 to-slate-600'];
@endphp
<div x-data="{ open: false }" x-on:open-weather.window="open = true" x-on:keydown.escape.window="open = false">
    <div x-cloak x-show="open" class="fixed inset-0 z-[1200] flex items-end sm:items-center justify-center">
        <div class="absolute inset-0 bg-ink/50 backdrop-blur-sm" x-transition.opacity @click="open = false"></div>
        <div x-show="open" x-transition:enter="animate-sheet-up" class="relative w-full sm:max-w-md card rounded-b-none sm:rounded-3xl overflow-hidden max-h-[88vh] overflow-y-auto">
            <div class="bg-gradient-to-br {{ $tones[$wa['tone'] ?? 'neutral'] }} text-white p-5 sm:p-6">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-[11px] font-bold uppercase tracking-[0.16em] opacity-90">Paris · maintenant</p>
                        <div class="flex items-end gap-3 mt-1">
                            <p class="font-display text-6xl leading-none">{{ $wf['current'] ? round($wf['current']['temp']) . '°' : '—' }}</p>
                            <span class="material-symbols-outlined filled mb-1" style="font-size:44px">{{ $wf['current']['icon'] ?? 'cloud' }}</span>
                        </div>
                        <p class="mt-1 text-sm opacity-90">{{ $wf['current']['label'] ?? 'Météo indisponible' }}@if($wf['current'] && $wf['current']['wind']) · vent {{ round($wf['current']['wind']) }} km/h @endif</p>
                    </div>
                    <button @click="open = false" class="h-9 w-9 rounded-full bg-white/20 flex items-center justify-center hover:bg-white/30" aria-label="Fermer"><span class="material-symbols-outlined" style="font-size:18px">close</span></button>
                </div>
                @if($wa)
                    <div class="mt-4 rounded-2xl bg-white/15 backdrop-blur p-3.5">
                        <p class="font-semibold flex items-center gap-2"><span class="material-symbols-outlined" style="font-size:18px">auto_awesome</span>{{ $wa['title'] }}</p>
                        <p class="text-sm opacity-90 mt-1">{{ $wa['text'] }}</p>
                    </div>
                @endif
            </div>
            <div class="p-5 sm:p-6 space-y-5">
                @if($nextHours->isNotEmpty())
                    <div>
                        <p class="label">Prochaines heures</p>
                        <div class="flex gap-2 overflow-x-auto hide-scrollbar -mx-1 px-1 pb-1">
                            @foreach($nextHours as $h)
                                <div class="shrink-0 w-16 rounded-2xl bg-paper p-2 text-center">
                                    <p class="text-[10px] text-ink-muted">{{ \Illuminate\Support\Carbon::parse($h['time'])->format('H\h') }}</p>
                                    <span class="material-symbols-outlined text-amber-600 {{ $h['indoor'] ? 'text-sky-600' : '' }}" style="font-size:20px">{{ $h['icon'] }}</span>
                                    <p class="text-sm font-semibold">{{ round($h['temp']) }}°</p>
                                    <p class="text-[10px] text-ink-muted">{{ $h['rain_probability'] }} %</p>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
                @if(!empty($wf['days']))
                    <div>
                        <p class="label">Trois jours</p>
                        <div class="space-y-1.5">
                            @foreach(array_slice($wf['days'], 0, 3) as $i => $day)
                                <div class="flex items-center gap-3 rounded-2xl bg-paper px-3 py-2 text-sm">
                                    <span class="w-20 font-semibold">{{ $i === 0 ? "Aujourd'hui" : ucfirst(\Illuminate\Support\Carbon::parse($day['date'])->translatedFormat('l')) }}</span>
                                    <span class="material-symbols-outlined text-amber-600" style="font-size:20px">{{ $day['icon'] }}</span>
                                    <span class="text-ink-muted flex-1 truncate">{{ $day['label'] }}</span>
                                    <span class="text-ink-muted text-xs">{{ $day['rain_probability'] }} %</span>
                                    <span class="font-semibold">{{ $day['tmax'] }}° <span class="text-ink-muted font-normal">{{ $day['tmin'] }}°</span></span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
                <a href="{{ route('itineraries.create') }}" class="btn btn-md btn-primary w-full"><span class="material-symbols-outlined" style="font-size:18px">auto_awesome</span>Générer un parcours adapté à la météo</a>
                <p class="text-[10px] text-ink-muted text-center">Données Open-Meteo et MET Norway, mises à jour toutes les 30 minutes.</p>
            </div>
        </div>
    </div>
</div>
