{{--
    Modale "Signaler" façon Waze. Utilisation : <x-alert-modal :place="$place" /> (lieu) ou <x-alert-modal /> (carte, position libre).
    Ouvre avec $dispatch('open-alert', { lat, lng }) ou via un bouton @click="$dispatch('open-alert')".
--}}
@props(['place' => null, 'types'])
<div
    x-data="{ open: false, lat: {{ $place?->lat ?? 'null' }}, lng: {{ $place?->lng ?? 'null' }}, type: 'free_event' }"
    x-on:open-alert.window="open = true; if ($event.detail) { lat = $event.detail.lat ?? lat; lng = $event.detail.lng ?? lng; }"
    x-on:keydown.escape.window="open = false"
>
    <div x-cloak x-show="open" class="fixed inset-0 z-[1200] flex items-end sm:items-center justify-center p-0 sm:p-6">
        <div class="absolute inset-0 bg-ink/50 backdrop-blur-sm" @click="open = false"></div>
        <div x-show="open" x-transition class="relative w-full sm:max-w-lg card rounded-b-none sm:rounded-3xl p-6 max-h-[90vh] overflow-y-auto">
            <div class="flex items-start justify-between gap-3 mb-4">
                <div>
                    <p class="eyebrow">Communauté</p>
                    <h3 class="display text-2xl mt-1">Signaler {{ $place ? 'sur ce lieu' : 'ici' }}</h3>
                    <p class="text-sm text-ink-muted mt-1">Comme sur Waze : ton alerte apparaît sur la carte pour tout le monde, pendant quelques heures.</p>
                </div>
                <button @click="open = false" class="btn btn-icon btn-ghost"><span class="material-symbols-outlined">close</span></button>
            </div>

            @auth
                <form method="POST" action="{{ $place ? route('places.alerts.store', $place) : route('alerts.store') }}" class="space-y-4">
                    @csrf
                    <input type="hidden" name="lat" :value="lat">
                    <input type="hidden" name="lng" :value="lng">
                    <div class="grid grid-cols-2 gap-2">
                        @foreach($types as $key => $t)
                            <label class="cursor-pointer">
                                <input type="radio" name="type" value="{{ $key }}" class="peer sr-only" x-model="type">
                                <span class="flex items-center gap-2.5 rounded-2xl border border-ink/10 px-3 py-2.5 text-sm peer-checked:border-ink peer-checked:bg-ink peer-checked:text-white transition">
                                    <span class="h-8 w-8 rounded-full flex items-center justify-center shrink-0" style="background: {{ $t['color'] }}22; color: {{ $t['color'] }}">
                                        <span class="material-symbols-outlined" style="font-size:18px">{{ $t['icon'] }}</span>
                                    </span>
                                    <span class="leading-tight">{{ $t['label'] }}<br><span class="text-[10px] opacity-70">visible {{ $t['hours'] }} h</span></span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                    <div>
                        <label class="label" for="alert-title">Titre</label>
                        <input id="alert-title" name="title" required maxlength="120" class="field" placeholder="Ex. Concert gratuit ce soir à 19h">
                    </div>
                    <div>
                        <label class="label" for="alert-message">Détails (optionnel)</label>
                        <textarea id="alert-message" name="message" rows="2" maxlength="500" class="field" placeholder="Horaires, accès, conseils…"></textarea>
                    </div>
                    <div class="flex justify-end gap-2 pt-1">
                        <button type="button" @click="open = false" class="btn btn-md btn-ghost">Annuler</button>
                        <button type="submit" class="btn btn-md btn-primary"><span class="material-symbols-outlined" style="font-size:18px">campaign</span>Publier l'alerte</button>
                    </div>
                </form>
            @else
                <div class="rounded-2xl bg-paper p-5 text-center">
                    <p class="text-sm">Connecte-toi pour signaler un événement gratuit, une affluence ou une fermeture.</p>
                    <div class="mt-3 flex justify-center gap-2">
                        <a href="{{ route('login') }}" class="btn btn-sm btn-ink">Connexion</a>
                        <a href="{{ route('register') }}" class="btn btn-sm btn-soft">Créer un compte</a>
                    </div>
                </div>
            @endauth
        </div>
    </div>
</div>
