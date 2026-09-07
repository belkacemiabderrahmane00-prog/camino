@php
    /** Balade à plusieurs : carte en direct, rendez-vous, groupe, messages et photos. Invité sans compte accepté. */
    $active = $walk->isActive();
    $route = $walk->route_json;
    $live = [
        'code' => $walk->code, 'title' => $walk->title, 'me' => $member?->id, 'host' => $walk->host_member_id, 'active' => $active,
        'stateUrl' => route('walks.state', $walk->code), 'positionUrl' => route('walks.position', $walk->code), 'messageUrl' => route('walks.message', $walk->code),
        'photoUrl' => route('walks.photo.store', $walk->code), 'meetingUrl' => route('walks.meeting', $walk->code), 'shareUrl' => route('walks.show', $walk->code),
        'csrf' => csrf_token(), 'route' => $route, 'quick' => $quick, 'lang' => \App\Http\Middleware\SetLocale::speechLanguage(),
        't' => [
            'isAt' => __(':name est à :d'), 'arrived' => __(':name est arrivé au rendez-vous'), 'meetingSet' => __('Nouveau rendez-vous : :label'), 'meetingCleared' => __('Rendez-vous annulé'),
            'joined' => __(':name a rejoint la balade'), 'left' => __(':name a quitté la balade'), 'everyone' => __('Tout le monde est là !'), 'ping' => __(':name vous fait signe'),
            'message' => __('Message de :name : :body'), 'photo' => __(':name a partagé une photo'), 'you' => __('toi'), 'noPos' => __('Position inconnue'), 'here' => __('ici'),
            'meetingHere' => __('Rendez-vous'), 'min' => __('min'), 'walk' => __('à pied'), 'copied' => __('Lien copié'), 'gpsDenied' => __('Autorise la localisation pour apparaître sur la carte.'),
            'photoFail' => __('La photo n\'a pas pu être envoyée.'), 'closeTo' => __('tout près'),
        ],
    ];
    $titleLabel = __('Balade à plusieurs');
@endphp
<x-app-layout :title="$titleLabel . ' · ' . $walk->title" :fullscreen="$member !== null && $active" :bottom-nav="$member === null || ! $active">
    @if(!$member || !$active)
        {{-- ============================== Entrée ou balade terminée --}}
        <section class="max-w-md mx-auto px-4 pt-8 sm:pt-16 pb-16">
            <div class="card overflow-hidden">
                <div class="relative h-40 bg-ink text-white p-5 flex flex-col justify-end overflow-hidden">
                    <div class="absolute -right-16 -top-16 h-56 w-56 rounded-full bg-coral/40 blur-3xl"></div>
                    <div class="absolute inset-0 opacity-[0.12]" style="background-image: radial-gradient(circle at 1px 1px, #fff 1px, transparent 0); background-size: 22px 22px;"></div>
                    <p class="relative eyebrow">{{ $titleLabel }}</p>
                    <p class="relative font-display text-2xl leading-tight line-clamp-2">{{ $walk->title }}</p>
                    <p class="relative text-xs text-white/70 mt-1">{{ trans_choice(':n personne|:n personnes', $membersOnline, ['n' => $membersOnline]) }} · {{ $active ? __('en cours') : __('terminée') }}</p>
                </div>
                <div class="p-5">
                    @if(!$active)
                        <p class="text-sm text-ink-soft">{{ __('Cette balade est terminée. Merci d\'avoir marché ensemble.') }}</p>
                        @php $photos = $walk->messages()->where('type', 'photo')->latest()->limit(12)->get(); @endphp
                        @if($photos->isNotEmpty())
                            <p class="eyebrow mt-5 mb-2">{{ __('Les photos du groupe') }}</p>
                            <div class="grid grid-cols-3 gap-2">@foreach($photos as $p)<a href="{{ route('walks.photo', [$walk->code, $p->id]) }}" target="_blank"><img src="{{ route('walks.photo', [$walk->code, $p->id]) }}" alt="" loading="lazy" class="aspect-square w-full object-cover rounded-xl"></a>@endforeach</div>
                        @endif
                        <a href="{{ route('itineraries.create') }}" class="btn btn-md btn-primary mt-5"><span class="material-symbols-outlined" style="font-size:18px">auto_awesome</span>{{ __('Générer un parcours') }}</a>
                    @else
                        <form method="POST" action="{{ route('walks.join', $walk->code) }}" class="space-y-4" x-data="{ color: @js($palette[$membersOnline % count($palette)]) }">
                            @csrf
                            <div>
                                <label class="label" for="name">{{ __('Ton prénom ou pseudo') }}</label>
                                <input id="name" name="name" type="text" required maxlength="40" value="{{ old('name', $joinName) }}" class="field" placeholder="{{ __('Léa, Sam…') }}" autofocus>
                                @error('name')<p class="text-xs text-coral-dark mt-1">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <p class="label">{{ __('Ta couleur sur la carte') }}</p>
                                <div class="flex flex-wrap gap-2">
                                    @foreach($palette as $c)<button type="button" @click="color = '{{ $c }}'" class="h-8 w-8 rounded-full border-2 transition" :class="color === '{{ $c }}' ? 'border-ink scale-110' : 'border-transparent'" style="background: {{ $c }}" aria-label="{{ $c }}"></button>@endforeach
                                </div>
                                <input type="hidden" name="color" :value="color">
                            </div>
                            <button class="btn btn-lg btn-primary w-full"><span class="material-symbols-outlined">group</span>{{ __('Rejoindre la balade') }}</button>
                            <p class="text-[11px] text-ink-muted">{{ __('Ta position n\'est partagée qu\'avec ce groupe, pendant la balade, et s\'efface ensuite.') }}</p>
                        </form>
                    @endif
                </div>
            </div>
        </section>
    @else
        {{-- ============================== En direct --}}
        <div class="absolute inset-0" x-data="walkLive(@js($live))">
            <div id="walk-map" class="absolute inset-0 z-0"></div>

            {{-- Bandeau haut : groupe + partage --}}
            <div class="absolute top-[4.6rem] inset-x-3 z-[600] flex items-start gap-2 pointer-events-none">
                <div class="card px-3 py-2 flex items-center gap-2 pointer-events-auto min-w-0 flex-1">
                    <div class="flex -space-x-2 shrink-0">
                        <template x-for="m in members.slice(0, 5)" :key="'a' + m.id">
                            <span class="h-8 w-8 rounded-full border-2 border-surface flex items-center justify-center text-[11px] font-bold text-white relative" :style="'background:' + m.color" :title="m.name">
                                <template x-if="m.avatar"><img :src="m.avatar" alt="" class="h-full w-full rounded-full object-cover"></template>
                                <template x-if="!m.avatar"><span x-text="m.initials"></span></template>
                                <span class="absolute -bottom-0.5 -right-0.5 h-2.5 w-2.5 rounded-full border-2 border-surface" :class="m.online ? 'bg-emerald-500' : 'bg-ink/30'"></span>
                            </span>
                        </template>
                        <span x-show="members.length > 5" class="h-8 w-8 rounded-full border-2 border-surface bg-paper text-[11px] font-bold flex items-center justify-center" x-text="'+' + (members.length - 5)"></span>
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-semibold truncate" x-text="data.title"></p>
                        <p class="text-[11px] text-ink-muted truncate" x-text="statusLine"></p>
                    </div>
                    <button type="button" @click="invite = true" class="btn btn-sm btn-ink shrink-0"><span class="material-symbols-outlined" style="font-size:16px">person_add</span><span class="hidden sm:inline">{{ __('Inviter') }}</span></button>
                </div>
            </div>

            {{-- Boussole vers un ami --}}
            <div x-show="target" x-cloak class="absolute top-[8.6rem] left-3 z-[600] card px-3 py-2 flex items-center gap-3 pointer-events-auto" @click="target = null">
                <span class="relative h-10 w-10 rounded-full flex items-center justify-center text-white" :style="'background:' + (targetMember ? targetMember.color : '#0F8B8D')"><span class="material-symbols-outlined transition-transform duration-300" :style="'transform: rotate(' + compassAngle + 'deg)'">navigation</span></span>
                <div class="min-w-0"><p class="text-sm font-semibold truncate" x-text="targetMember ? targetMember.name : (meeting ? data.t.meetingHere : '')"></p><p class="text-[11px] text-ink-muted" x-text="targetDistance"></p></div>
                <span class="material-symbols-outlined text-ink-muted" style="font-size:16px">close</span>
            </div>

            {{-- Boutons carte --}}
            <div class="absolute right-3 z-[600] flex flex-col gap-2" :style="'bottom:' + (sheetHeight + 16) + 'px'">
                <button type="button" @click="recenter()" class="h-11 w-11 rounded-full bg-white shadow-card flex items-center justify-center text-ink" aria-label="{{ __('Recentrer') }}"><span class="material-symbols-outlined">my_location</span></button>
                <button type="button" @click="fitAll()" class="h-11 w-11 rounded-full bg-white shadow-card flex items-center justify-center text-ink" aria-label="{{ __('Voir tout le groupe') }}"><span class="material-symbols-outlined">groups</span></button>
                <button type="button" @click="toggleMute()" class="h-11 w-11 rounded-full bg-white shadow-card flex items-center justify-center text-ink" :aria-label="muted ? @js(__('Activer la voix')) : @js(__('Couper la voix'))"><span class="material-symbols-outlined" x-text="muted ? 'volume_off' : 'volume_up'"></span></button>
            </div>

            {{-- Mode « choisir le rendez-vous sur la carte » --}}
            <div x-show="picking" x-cloak class="absolute inset-0 z-[650] pointer-events-none flex items-center justify-center">
                <span class="material-symbols-outlined filled text-coral drop-shadow-lg -mt-8" style="font-size:44px">flag</span>
                <div class="absolute bottom-[calc(env(safe-area-inset-bottom)+1rem)] inset-x-3 flex gap-2 pointer-events-auto">
                    <button type="button" @click="picking = false" class="btn btn-md btn-soft flex-1">{{ __('Annuler') }}</button>
                    <button type="button" @click="confirmPick()" class="btn btn-md btn-primary flex-1"><span class="material-symbols-outlined" style="font-size:18px">flag</span>{{ __('Rendez-vous ici') }}</button>
                </div>
            </div>

            {{-- Feuille basse : actions, groupe, messages --}}
            <div class="absolute inset-x-0 bottom-0 z-[600] px-3 pb-[max(0.75rem,env(safe-area-inset-bottom))]" x-ref="sheet" x-show="!picking">
                <div class="nav-card card overflow-hidden">
                    <div class="flex items-center gap-1 px-2 pt-2">
                        <button type="button" @click="tab = 'group'; open = true" class="btn btn-sm flex-1" :class="tab === 'group' && open ? 'btn-ink' : 'btn-ghost'"><span class="material-symbols-outlined" style="font-size:16px">groups</span>{{ __('Groupe') }} <span class="ml-1 text-[10px] opacity-70" x-text="members.length"></span></button>
                        <button type="button" @click="tab = 'chat'; open = true; unread = 0; $nextTick(() => scrollChat())" class="btn btn-sm flex-1 relative" :class="tab === 'chat' && open ? 'btn-ink' : 'btn-ghost'"><span class="material-symbols-outlined" style="font-size:16px">chat</span>{{ __('Messages') }}<span x-show="unread" class="absolute -top-1 right-2 h-5 min-w-5 px-1 rounded-full bg-coral text-white text-[10px] font-bold flex items-center justify-center" x-text="unread"></span></button>
                        <button type="button" @click="meetingMenu = !meetingMenu" class="btn btn-sm btn-ghost relative"><span class="material-symbols-outlined text-coral" style="font-size:18px">flag</span><span class="hidden sm:inline">{{ __('Rendez-vous') }}</span></button>
                        <button type="button" @click="open = !open; measure()" class="h-8 w-8 rounded-full hover:bg-paper flex items-center justify-center text-ink-muted"><span class="material-symbols-outlined transition-transform" :class="open && 'rotate-180'">expand_less</span></button>
                    </div>

                    {{-- Menu rendez-vous --}}
                    <div x-show="meetingMenu" x-cloak class="mx-2 mt-2 rounded-2xl bg-paper p-2 space-y-1 text-sm">
                        <button type="button" @click="setMeetingHere(); meetingMenu = false" class="w-full text-left px-3 py-2 rounded-xl hover:bg-surface flex items-center gap-2"><span class="material-symbols-outlined text-coral" style="font-size:18px">my_location</span>{{ __('Rendez-vous là où je suis') }}</button>
                        <button type="button" @click="picking = true; meetingMenu = false; open = false" class="w-full text-left px-3 py-2 rounded-xl hover:bg-surface flex items-center gap-2"><span class="material-symbols-outlined text-coral" style="font-size:18px">pin_drop</span>{{ __('Choisir un point sur la carte') }}</button>
                        <button type="button" x-show="members.filter(m => m.lat).length > 1" @click="setMeetingMiddle(); meetingMenu = false" class="w-full text-left px-3 py-2 rounded-xl hover:bg-surface flex items-center gap-2"><span class="material-symbols-outlined text-coral" style="font-size:18px">hub</span>{{ __('Au milieu du groupe') }}</button>
                        <template x-for="s in nextSteps" :key="'s' + s.order"><button type="button" @click="setMeeting(s.lat, s.lng, s.title); meetingMenu = false" class="w-full text-left px-3 py-2 rounded-xl hover:bg-surface flex items-center gap-2"><span class="material-symbols-outlined text-coral" style="font-size:18px">place</span><span class="truncate" x-text="s.title"></span></button></template>
                        <button type="button" x-show="meeting" @click="clearMeeting(); meetingMenu = false" class="w-full text-left px-3 py-2 rounded-xl hover:bg-surface flex items-center gap-2 text-ink-muted"><span class="material-symbols-outlined" style="font-size:18px">flag_circle</span>{{ __('Annuler le rendez-vous') }}</button>
                    </div>

                    {{-- Rendez-vous en cours --}}
                    <div x-show="meeting" x-cloak class="mx-2 mt-2 rounded-2xl bg-coral-soft px-3 py-2 flex items-center gap-2 text-sm">
                        <span class="material-symbols-outlined filled text-coral" style="font-size:20px">flag</span>
                        <div class="min-w-0 flex-1"><p class="font-semibold text-coral-dark truncate" x-text="meeting ? (meeting.label || data.t.meetingHere) : ''"></p><p class="text-[11px] text-coral-dark/80" x-text="meetingLine"></p></div>
                        <button type="button" @click="target = 'meeting'" class="btn btn-sm btn-primary !px-2.5"><span class="material-symbols-outlined" style="font-size:16px">explore</span></button>
                    </div>

                    <div x-show="open" x-cloak class="max-h-[42vh] overflow-y-auto">
                        {{-- Groupe --}}
                        <div x-show="tab === 'group'" class="p-2 space-y-1">
                            <template x-for="m in members" :key="'m' + m.id">
                                <button type="button" @click="m.id === data.me ? recenter() : (target = m.id, focus(m))" class="w-full flex items-center gap-3 rounded-2xl px-2 py-2 hover:bg-paper text-left">
                                    <span class="h-10 w-10 rounded-full flex items-center justify-center text-white font-bold text-sm relative shrink-0" :style="'background:' + m.color">
                                        <template x-if="m.avatar"><img :src="m.avatar" alt="" class="h-full w-full rounded-full object-cover"></template>
                                        <template x-if="!m.avatar"><span x-text="m.initials"></span></template>
                                        <span class="absolute -bottom-0.5 -right-0.5 h-3 w-3 rounded-full border-2 border-surface" :class="m.online ? 'bg-emerald-500' : 'bg-ink/30'"></span>
                                    </span>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm font-semibold truncate"><span x-text="m.name"></span><span x-show="m.id === data.me" class="text-ink-muted font-normal"> · <span x-text="data.t.you"></span></span><span x-show="m.id === data.host" class="ml-1 text-[10px] uppercase tracking-wider text-coral">{{ __('hôte') }}</span></p>
                                        <p class="text-[11px] text-ink-muted truncate" x-text="memberLine(m)"></p>
                                    </div>
                                    <span x-show="m.arrived" class="badge badge-free !py-0.5"><span class="material-symbols-outlined" style="font-size:12px">flag</span>{{ __('arrivé') }}</span>
                                    <span x-show="m.id !== data.me && !m.arrived" class="material-symbols-outlined text-ink-muted" style="font-size:18px">explore</span>
                                </button>
                            </template>
                            <div class="flex flex-wrap gap-2 px-2 pt-2 pb-1">
                                <button type="button" @click="invite = true" class="btn btn-sm btn-soft flex-1 min-w-[40%]"><span class="material-symbols-outlined" style="font-size:16px">qr_code_2</span>{{ __('Inviter') }}</button>
                                @if(!empty($guideUrl))<a href="{{ $guideUrl }}" class="btn btn-sm btn-ink flex-1 min-w-[40%]"><span class="material-symbols-outlined" style="font-size:16px">navigation</span>{{ __('Guidage') }}</a>@endif
                                <form method="POST" action="{{ route('walks.leave', $walk->code) }}" class="flex-1 min-w-[40%]">@csrf<button class="btn btn-sm btn-ghost w-full text-ink-muted"><span class="material-symbols-outlined" style="font-size:16px">logout</span>{{ __('Quitter') }}</button></form>
                                @if($member->id === $walk->host_member_id)<form method="POST" action="{{ route('walks.end', $walk->code) }}" class="flex-1 min-w-[40%]" onsubmit="return confirm(@js(__('Terminer la balade pour tout le monde ?')))">@csrf<button class="btn btn-sm btn-ghost w-full text-ink-muted"><span class="material-symbols-outlined" style="font-size:16px">stop_circle</span>{{ __('Terminer') }}</button></form>@endif
                            </div>
                        </div>

                        {{-- Messages --}}
                        <div x-show="tab === 'chat'" class="flex flex-col">
                            <div x-ref="chat" class="px-3 pt-2 pb-1 space-y-1.5 max-h-[26vh] overflow-y-auto">
                                <template x-for="msg in messages" :key="'g' + msg.id">
                                    <div>
                                        <template x-if="['join','leave','arrive','meet','ping'].includes(msg.type)"><p class="text-center text-[11px] text-ink-muted py-0.5" x-text="systemText(msg)"></p></template>
                                        <template x-if="msg.type === 'text' || msg.type === 'emoji' || msg.type === 'photo'">
                                            <div class="flex items-end gap-2" :class="msg.member === data.me ? 'flex-row-reverse' : ''">
                                                <span class="h-7 w-7 rounded-full flex items-center justify-center text-white text-[10px] font-bold shrink-0" :style="'background:' + (msg.color || '#6B7684')" x-text="initials(msg.name)"></span>
                                                <div class="max-w-[78%]">
                                                    <p class="text-[10px] text-ink-muted mb-0.5" :class="msg.member === data.me ? 'text-right' : ''"><span x-text="msg.name"></span> · <span x-text="timeOf(msg.at)"></span></p>
                                                    <template x-if="msg.type === 'photo'"><a :href="msg.photo" target="_blank" class="block rounded-2xl overflow-hidden"><img :src="msg.photo" alt="" class="max-h-48 w-auto object-cover"><span x-show="msg.body" class="block bg-paper px-3 py-1.5 text-xs" x-text="msg.body"></span></a></template>
                                                    <template x-if="msg.type === 'emoji'"><p class="text-3xl leading-none px-1" x-text="msg.body"></p></template>
                                                    <template x-if="msg.type === 'text'"><p class="rounded-2xl px-3 py-2 text-sm leading-snug break-words" :class="msg.member === data.me ? 'bg-ink text-white' : 'bg-paper'" x-text="msg.body"></p></template>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </template>
                                <p x-show="!messages.length" class="text-center text-xs text-ink-muted py-4">{{ __('Un mot, un emoji, une photo : tout le groupe le voit.') }}</p>
                            </div>
                            <div class="flex gap-1 px-3 py-1.5 overflow-x-auto hide-scrollbar">
                                <template x-for="e in data.quick" :key="e"><button type="button" @click="send('emoji', e)" class="h-9 w-9 rounded-full bg-paper hover:bg-paper-deep text-xl shrink-0" x-text="e"></button></template>
                                <button type="button" @click="send('ping', '')" class="h-9 px-3 rounded-full bg-sun-soft text-amber-800 text-xs font-semibold shrink-0 inline-flex items-center gap-1"><span class="material-symbols-outlined" style="font-size:16px">waving_hand</span>{{ __('Faire signe') }}</button>
                            </div>
                            <form @submit.prevent="sendText()" class="flex items-center gap-1.5 px-3 pb-3">
                                <label class="h-10 w-10 rounded-full bg-paper hover:bg-paper-deep flex items-center justify-center text-ink cursor-pointer shrink-0" title="{{ __('Prendre une photo') }}"><span class="material-symbols-outlined" x-text="uploading ? 'progress_activity' : 'photo_camera'" :class="uploading && 'animate-spin'"></span><input type="file" accept="image/*" capture="environment" class="sr-only" @change="uploadPhoto($event)"></label>
                                <input x-model="draft" type="text" maxlength="500" placeholder="{{ __('Écris au groupe…') }}" class="field !py-2 flex-1 min-w-0" enterkeyhint="send">
                                <button class="h-10 w-10 rounded-full bg-coral text-white flex items-center justify-center shrink-0" :disabled="!draft.trim()"><span class="material-symbols-outlined" style="font-size:20px">send</span></button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Inviter : lien + QR --}}
            <div x-show="invite" x-cloak class="absolute inset-0 z-[700] bg-ink/40 backdrop-blur-sm flex items-end sm:items-center justify-center p-3" @click.self="invite = false">
                <div class="card w-full max-w-sm p-5 text-center">
                    <p class="eyebrow">{{ __('Inviter') }}</p>
                    <p class="font-display text-2xl mt-1">{{ __('Scanne ou partage le lien') }}</p>
                    <canvas x-ref="qr" class="mx-auto mt-4 rounded-2xl"></canvas>
                    <p class="mt-3 text-xs text-ink-muted break-all" x-text="data.shareUrl"></p>
                    <div class="mt-4 flex gap-2">
                        <button type="button" @click="copyLink()" class="btn btn-md btn-soft flex-1"><span class="material-symbols-outlined" style="font-size:18px" x-text="copied ? 'check' : 'link'"></span><span x-text="copied ? data.t.copied : @js(__('Copier'))"></span></button>
                        <button type="button" @click="shareLink()" class="btn btn-md btn-primary flex-1"><span class="material-symbols-outlined" style="font-size:18px">ios_share</span>{{ __('Partager') }}</button>
                    </div>
                    <button type="button" @click="invite = false" class="mt-3 text-xs font-semibold text-ink-muted">{{ __('Fermer') }}</button>
                </div>
            </div>
        </div>

        @push('scripts')
        <script>
            function walkLive(data) {
                const C = window.Camino, R = 6371000, toRad = d => d * Math.PI / 180;
                const dist = (a, b) => { const x = toRad(b[1] - a[1]) * Math.cos(toRad((a[0] + b[0]) / 2)); const y = toRad(b[0] - a[0]); return Math.sqrt(x * x + y * y) * R; };
                const bearing = (a, b) => { const y = Math.sin(toRad(b[1] - a[1])) * Math.cos(toRad(b[0])); const x = Math.cos(toRad(a[0])) * Math.sin(toRad(b[0])) - Math.sin(toRad(a[0])) * Math.cos(toRad(b[0])) * Math.cos(toRad(b[1] - a[1])); return (Math.atan2(y, x) * 180 / Math.PI + 360) % 360; };
                let map = null, markers = {}, meetingMarker = null, meetingCircle = null, routeLine = null, watchId = null, poll = null, lastSent = 0, lastSentPos = null, deviceHeading = null;
                const announced = {};
                const fmt = (m) => m >= 1000 ? (m / 1000).toFixed(1).replace('.', ',') + ' km' : Math.max(0, Math.round(m / 10) * 10) + ' m';
                return {
                    data, members: [], messages: [], meeting: null, lastId: 0, pos: null, heading: 0, muted: false, open: false, tab: 'group', unread: 0, draft: '', uploading: false,
                    invite: false, copied: false, picking: false, meetingMenu: false, target: null, sheetHeight: 120, everyoneAnnounced: false,
                    get targetMember() { return typeof this.target === 'number' ? this.members.find(m => m.id === this.target) : null; },
                    get targetPoint() { if (this.target === 'meeting' && this.meeting) return [this.meeting.lat, this.meeting.lng]; const m = this.targetMember; return m && m.lat ? [m.lat, m.lng] : null; },
                    get compassAngle() { const p = this.targetPoint; if (!p || !this.pos) return 0; const b = bearing(this.pos, p); const h = deviceHeading ?? this.heading ?? 0; return b - h; },
                    get targetDistance() { const p = this.targetPoint; if (!p || !this.pos) return data.t.noPos; const d = dist(this.pos, p); return fmt(d) + ' · ' + Math.max(1, Math.round(d * 1.25 / 78)) + ' ' + data.t.min + ' ' + data.t.walk; },
                    get statusLine() { const on = this.members.filter(m => m.online).length; const arrived = this.members.filter(m => m.arrived).length; return on + '/' + this.members.length + ' {{ __('en ligne') }}' + (this.meeting ? ' · ' + arrived + ' {{ __('au rendez-vous') }}' : ''); },
                    get meetingLine() { if (!this.meeting) return ''; if (!this.pos) return data.t.noPos; const d = dist(this.pos, [this.meeting.lat, this.meeting.lng]); return d < 40 ? '{{ __('Tu y es') }}' : fmt(d) + ' · ' + Math.max(1, Math.round(d * 1.25 / 78)) + ' ' + data.t.min + ' ' + data.t.walk; },
                    get nextSteps() { return data.route && data.route.steps ? data.route.steps.slice(0, 4) : []; },

                    init() {
                        map = L.map(document.getElementById('walk-map'), { zoomControl: false }).setView(data.route && data.route.start ? [data.route.start.lat, data.route.start.lng] : [48.8566, 2.3522], 14);
                        C.tileLayer().addTo(map);
                        if (data.route && data.route.steps && data.route.steps.length) {
                            const pts = data.route.geometry && data.route.geometry.length > 1 ? data.route.geometry : [data.route.start ? [data.route.start.lat, data.route.start.lng] : null, ...data.route.steps.map(s => [s.lat, s.lng])].filter(Boolean);
                            routeLine = L.polyline(pts, { color: '#FF5A3C', weight: 4, opacity: 0.55, dashArray: data.route.geometry && data.route.geometry.length > 1 ? null : '6 8' }).addTo(map);
                            if (data.route.start) L.marker([data.route.start.lat, data.route.start.lng], { icon: C.stepIcon(0, true) }).addTo(map);
                            data.route.steps.forEach((s, i) => L.marker([s.lat, s.lng], { icon: C.stepIcon(s.order || i + 1) }).addTo(map));
                        }
                        map.on('moveend', () => { if (this.picking) this.pickCenter = map.getCenter(); });
                        this.measure(); window.addEventListener('resize', () => this.measure());
                        if (window.DeviceOrientationEvent) window.addEventListener('deviceorientationabsolute', (e) => { if (e.alpha !== null) deviceHeading = (360 - e.alpha) % 360; }, true);
                        this.refresh(true); poll = setInterval(() => this.refresh(false), 4000);
                        this.track();
                        this.$watch('invite', v => { if (v) this.$nextTick(() => this.drawQr()); });
                        this.$watch('open', () => this.measure());
                        document.addEventListener('visibilitychange', () => { if (document.visibilityState === 'visible') this.refresh(false); });
                    },
                    measure() { this.$nextTick(() => { this.sheetHeight = this.$refs.sheet ? this.$refs.sheet.offsetHeight : 120; }); },
                    // ---------------------------------------------------------------- position
                    track() {
                        if (!navigator.geolocation) return;
                        watchId = navigator.geolocation.watchPosition(p => {
                            const latlng = [p.coords.latitude, p.coords.longitude];
                            if (this.pos && dist(this.pos, latlng) > 3) this.heading = bearing(this.pos, latlng);
                            if (p.coords.heading !== null && !isNaN(p.coords.heading)) this.heading = p.coords.heading;
                            this.pos = latlng;
                            this.drawMe();
                            const now = Date.now();
                            if (now - lastSent > 6000 || (lastSentPos && dist(lastSentPos, latlng) > 25)) { lastSent = now; lastSentPos = latlng; this.sendPosition(p.coords.accuracy); }
                        }, e => { if (e.code === 1) this.$dispatch('toast', data.t.gpsDenied); }, { enableHighAccuracy: true, maximumAge: 3000, timeout: 20000 });
                    },
                    async sendPosition(accuracy) {
                        if (!this.pos) return;
                        try {
                            const r = await fetch(data.positionUrl, { method: 'POST', headers: this.headers(), body: JSON.stringify({ lat: this.pos[0], lng: this.pos[1], heading: this.heading, accuracy }) });
                            const j = await r.json();
                            if (j.arrived && !this.iArrived) { this.iArrived = true; }
                        } catch (e) {}
                    },
                    drawMe() {
                        const me = this.members.find(m => m.id === data.me);
                        const color = me ? me.color : '#0F8B8D';
                        if (!markers.me) markers.me = L.marker(this.pos, { icon: L.divIcon({ className: 'camino-marker', html: `<div class="walk-me" style="--c:${color}"><span></span></div>`, iconSize: [26, 26], iconAnchor: [13, 13] }), zIndexOffset: 1000 }).addTo(map);
                        markers.me.setLatLng(this.pos);
                        if (!this.centered) { this.centered = true; map.setView(this.pos, 16); }
                    },
                    recenter() { if (this.pos) map.flyTo(this.pos, Math.max(map.getZoom(), 16), { duration: 0.6 }); },
                    fitAll() { const pts = this.members.filter(m => m.lat).map(m => [m.lat, m.lng]); if (this.pos) pts.push(this.pos); if (this.meeting) pts.push([this.meeting.lat, this.meeting.lng]); if (pts.length) map.fitBounds(L.latLngBounds(pts), { padding: [60, 60], maxZoom: 17 }); },
                    focus(m) { if (m.lat) map.flyTo([m.lat, m.lng], Math.max(map.getZoom(), 16), { duration: 0.6 }); },
                    // ---------------------------------------------------------------- état
                    headers() { return { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': data.csrf }; },
                    async refresh(first) {
                        try {
                            const r = await fetch(data.stateUrl + '?since=' + this.lastId, { headers: { Accept: 'application/json' } });
                            if (r.status === 403) { window.location.reload(); return; }
                            const j = await r.json();
                            if (j.walk.status !== 'active') { window.location.reload(); return; }
                            const prevMembers = this.members;
                            this.members = j.members;
                            this.applyMeeting(j.meeting, first);
                            this.drawMembers();
                            const fresh = j.messages.filter(m => m.id > this.lastId);
                            if (fresh.length) {
                                this.lastId = fresh[fresh.length - 1].id;
                                this.messages = [...this.messages, ...fresh].slice(-200);
                                if (!first) this.onNewMessages(fresh);
                                if (this.open && this.tab === 'chat') this.$nextTick(() => this.scrollChat());
                            }
                            if (!first) this.announceProximity(prevMembers);
                        } catch (e) { console.warn(e); }
                    },
                    applyMeeting(m, first) {
                        const changed = JSON.stringify(m) !== JSON.stringify(this.meeting);
                        this.meeting = m;
                        if (meetingMarker) { meetingMarker.remove(); meetingMarker = null; }
                        if (meetingCircle) { meetingCircle.remove(); meetingCircle = null; }
                        if (m) {
                            meetingMarker = L.marker([m.lat, m.lng], { icon: L.divIcon({ className: 'camino-marker', html: '<div class="camino-pin camino-pin-start" style="width:34px;height:34px"><span class="material-symbols-outlined filled" style="font-size:18px">flag</span></div>', iconSize: [34, 34], iconAnchor: [17, 17] }), zIndexOffset: 900 }).addTo(map);
                            meetingCircle = L.circle([m.lat, m.lng], { radius: 40, color: '#FF5A3C', weight: 1, fillOpacity: 0.08 }).addTo(map);
                            if (changed && !first) this.speak(data.t.meetingSet.replace(':label', m.label || data.t.here));
                        } else if (changed && !first) this.speak(data.t.meetingCleared);
                    },
                    drawMembers() {
                        const ids = new Set();
                        this.members.forEach(m => {
                            if (m.id === data.me || !m.lat) return;
                            ids.add(m.id);
                            const html = `<div class="walk-member ${m.online ? '' : 'walk-member-off'}" style="--c:${m.color}">${m.avatar ? `<img src="${C.escapeHtml(m.avatar)}" alt="">` : `<b>${C.escapeHtml(m.initials)}</b>`}<i style="transform: rotate(${m.heading || 0}deg)"></i><span>${C.escapeHtml(m.name)}</span></div>`;
                            const icon = L.divIcon({ className: 'camino-marker', html, iconSize: [40, 40], iconAnchor: [20, 20] });
                            if (markers[m.id]) { markers[m.id].setLatLng([m.lat, m.lng]).setIcon(icon); } else { markers[m.id] = L.marker([m.lat, m.lng], { icon, zIndexOffset: 500 }).addTo(map); markers[m.id].on('click', () => { this.target = m.id; }); }
                        });
                        Object.keys(markers).forEach(k => { if (k !== 'me' && !ids.has(Number(k))) { markers[k].remove(); delete markers[k]; } });
                        if (this.pos) this.drawMe();
                    },
                    memberLine(m) {
                        if (m.id === data.me) return this.pos ? (this.meeting ? this.meetingLine : '{{ __('Tu es sur la carte') }}') : data.t.noPos;
                        if (!m.lat) return data.t.noPos;
                        const parts = [];
                        if (this.pos) parts.push(fmt(dist(this.pos, [m.lat, m.lng])));
                        if (this.meeting && !m.arrived) parts.push(Math.max(1, Math.round(dist([m.lat, m.lng], [this.meeting.lat, this.meeting.lng]) * 1.25 / 78)) + ' ' + data.t.min + ' → ' + data.t.meetingHere);
                        if (!m.online) parts.push('{{ __('hors ligne') }}');
                        return parts.join(' · ');
                    },
                    // ---------------------------------------------------------------- voix : « Léa est à 200 m »
                    announceProximity(prev) {
                        if (!this.pos) return;
                        const steps = [500, 200, 50];
                        this.members.forEach(m => {
                            if (m.id === data.me || !m.lat || !m.online) return;
                            const d = dist(this.pos, [m.lat, m.lng]);
                            const key = m.id;
                            const before = announced[key] ?? Infinity;
                            const crossed = steps.find(s => d <= s && before > s);
                            if (crossed) { announced[key] = crossed; this.speak(data.t.isAt.replace(':name', m.name).replace(':d', crossed === 50 ? data.t.closeTo : fmt(crossed))); }
                            else if (d > 600) announced[key] = Infinity;
                        });
                        const online = this.members.filter(m => m.online);
                        if (this.meeting && online.length > 1 && online.every(m => m.arrived) && !this.everyoneAnnounced) { this.everyoneAnnounced = true; this.speak(data.t.everyone); }
                        if (!this.meeting) this.everyoneAnnounced = false;
                    },
                    onNewMessages(list) {
                        list.forEach(msg => {
                            if (msg.member === data.me) return;
                            if (msg.type === 'text') { this.unread++; this.speak(data.t.message.replace(':name', msg.name || '').replace(':body', msg.body)); }
                            else if (msg.type === 'emoji') { this.unread++; }
                            else if (msg.type === 'photo') { this.unread++; this.speak(data.t.photo.replace(':name', msg.name || '')); }
                            else if (msg.type === 'arrive') this.speak(data.t.arrived.replace(':name', msg.name || ''));
                            else if (msg.type === 'join') this.speak(data.t.joined.replace(':name', msg.name || ''));
                            else if (msg.type === 'leave') this.speak(data.t.left.replace(':name', msg.name || ''));
                            else if (msg.type === 'ping') { this.speak(data.t.ping.replace(':name', msg.name || '')); if (navigator.vibrate) navigator.vibrate([80, 40, 80]); }
                        });
                        if (this.open && this.tab === 'chat') this.unread = 0;
                    },
                    systemText(msg) {
                        const n = msg.name || '';
                        return { join: data.t.joined, leave: data.t.left, arrive: data.t.arrived, ping: data.t.ping }[msg.type]?.replace(':name', n) || (msg.type === 'meet' ? (msg.lat ? data.t.meetingSet.replace(':label', msg.body || data.t.here) : data.t.meetingCleared) : '');
                    },
                    speak(text) {
                        if (this.muted || !('speechSynthesis' in window) || !text) return;
                        const u = new SpeechSynthesisUtterance(text); u.lang = data.lang; u.rate = 1.0;
                        const v = window.speechSynthesis.getVoices().find(x => x.lang && x.lang.toLowerCase().startsWith(data.lang.slice(0, 2).toLowerCase()));
                        if (v) u.voice = v;
                        window.speechSynthesis.speak(u);
                    },
                    toggleMute() { this.muted = !this.muted; if (this.muted && 'speechSynthesis' in window) window.speechSynthesis.cancel(); },
                    // ---------------------------------------------------------------- messages
                    async send(type, body) { try { await fetch(data.messageUrl, { method: 'POST', headers: this.headers(), body: JSON.stringify({ type, body }) }); this.refresh(false); } catch (e) {} },
                    async sendText() { const t = this.draft.trim(); if (!t) return; this.draft = ''; await this.send('text', t); },
                    async uploadPhoto(e) {
                        const file = e.target.files && e.target.files[0]; if (!file) return;
                        this.uploading = true;
                        try { const fd = new FormData(); fd.append('photo', file); const r = await fetch(data.photoUrl, { method: 'POST', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': data.csrf }, body: fd }); if (!r.ok) throw new Error(); await this.refresh(false); this.tab = 'chat'; this.open = true; this.$nextTick(() => this.scrollChat()); }
                        catch (err) { this.$dispatch('toast', data.t.photoFail); }
                        this.uploading = false; e.target.value = '';
                    },
                    scrollChat() { const el = this.$refs.chat; if (el) el.scrollTop = el.scrollHeight; },
                    initials(n) { return String(n || '?').split(/\s+/).slice(0, 2).map(p => p.charAt(0).toUpperCase()).join('') || '?'; },
                    timeOf(iso) { const d = new Date(iso); return d.getHours() + ':' + String(d.getMinutes()).padStart(2, '0'); },
                    // ---------------------------------------------------------------- rendez-vous
                    async setMeeting(lat, lng, label) { try { await fetch(data.meetingUrl, { method: 'POST', headers: this.headers(), body: JSON.stringify({ lat, lng, label: label || '' }) }); this.refresh(false); } catch (e) {} },
                    setMeetingHere() { if (this.pos) this.setMeeting(this.pos[0], this.pos[1], ''); else this.$dispatch('toast', data.t.noPos); },
                    setMeetingMiddle() { const pts = this.members.filter(m => m.lat).map(m => [m.lat, m.lng]); if (this.pos) pts.push(this.pos); const lat = pts.reduce((a, p) => a + p[0], 0) / pts.length, lng = pts.reduce((a, p) => a + p[1], 0) / pts.length; this.setMeeting(lat, lng, '{{ __('Au milieu du groupe') }}'); },
                    confirmPick() { const c = map.getCenter(); this.picking = false; this.setMeeting(c.lat, c.lng, ''); },
                    async clearMeeting() { try { await fetch(data.meetingUrl, { method: 'DELETE', headers: this.headers() }); this.refresh(false); } catch (e) {} },
                    // ---------------------------------------------------------------- invitation
                    async drawQr() { try { const QR = await C.loadQr(); await QR.toCanvas(this.$refs.qr, data.shareUrl, { width: 220, margin: 1, color: { dark: '#12161C', light: '#FFFFFF' } }); } catch (e) { console.warn(e); } },
                    copyLink() { navigator.clipboard.writeText(data.shareUrl); this.copied = true; setTimeout(() => this.copied = false, 2000); },
                    async shareLink() { try { if (navigator.share) await navigator.share({ title: data.title, text: '{{ __('Rejoins ma balade CAMINO') }}', url: data.shareUrl }); else this.copyLink(); } catch (e) {} },
                };
            }
        </script>
        @endpush
    @endif
</x-app-layout>
