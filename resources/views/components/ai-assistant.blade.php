@props(['floating' => true])
@php
    /**
     * Assistant CAMINO : une seule conversation partout dans l'app. Ouvert par $dispatch('open-assistant', { ask?: '…' }).
     * Chaque page décrit son contexte dans window.caminoAssistantContext() (page, position, étapes, sélection…) ;
     * les réponses sont courtes, avec des lieux en cartes et des actions (filtres de carte, ajout au parcours, retrait d'étape).
     */
    $ai = app(\App\Services\AiService::class)->enabled();
    $cfg = [
        'url' => url('/api/v1/ai/assistant?lang=' . app()->getLocale()), 'addUrl' => url('/parcours/ajouter-lieu'), 'stepRemoveUrl' => url('/parcours/etape/__i__/retirer'),
        'csrf' => csrf_token(), 'lang' => \App\Http\Middleware\SetLocale::speechLanguage(),
        'quick' => [
            'map' => [__('Un endroit calme pour lire'), __('Gratuit et ouvert maintenant'), __('Quoi faire avec des enfants ?')],
            'result' => [__('Ajoute une pause café'), __('C\'est trop long, retire une étape'), __('Raconte-moi la première étape')],
            'guidance' => [__('Un café sympa près d\'ici ?'), __('Raconte-moi ce lieu'), __('On a combien de temps ?')],
            'place' => [__('En trois points ?'), __('Qu\'est-ce qu\'il y a à côté ?'), __('C\'est bien pour des enfants ?')],
            'other' => [__('Que peut faire CAMINO ?'), __('Un musée gratuit ce week-end'), __('Une balade d\'une heure')],
        ],
        't' => [
            'hello' => ['map' => __('Dis-moi ce que tu cherches, je filtre la carte et je te propose des lieux.'), 'result' => __('Je peux ajouter un lieu, retirer une étape ou te raconter une étape.'), 'guidance' => __('Je marche avec toi : un café, l\'histoire d\'un lieu, le temps qu\'il reste…'), 'place' => __('Pose-moi une question sur ce lieu ou sur ce qu\'il y a autour.'), 'other' => __('Je suis CAMINO. Dis-moi ce dont tu as envie.')],
            'fail' => __('Je n\'arrive pas à répondre pour le moment.'), 'thinking' => __('CAMINO réfléchit…'), 'filters' => __('Filtres appliqués'), 'added' => __('Ajouté à ta sélection'), 'add' => __('Ajouter'), 'see' => __('Voir'), 'free' => __('gratuit'), 'open' => __('ouvert'),
            'listening' => __('Je t\'écoute…'), 'noMic' => __('La dictée n\'est pas disponible sur ce navigateur.'), 'removed' => __('Étape retirée'),
        ],
    ];
@endphp
@if($ai)
<div x-data="aiAssistant(@js($cfg))" @open-assistant.window="open($event.detail)" class="contents">
    @if($floating)
        <button type="button" @click="open()" x-show="!show" x-cloak class="fixed z-[1050] right-3 bottom-24 md:right-6 md:bottom-6 h-13 w-13 rounded-full bg-coral text-white shadow-float flex items-center justify-center ai-fab" aria-label="{{ __('Demander à CAMINO') }}"><span class="material-symbols-outlined" style="font-size:26px">auto_awesome</span></button>
    @endif
    <div x-show="show" x-cloak class="fixed inset-0 z-[1200] bg-ink/40 backdrop-blur-sm flex items-end sm:items-center justify-center sm:p-4" @click.self="show = false" @keydown.escape.window="show = false">
        <div class="card w-full sm:max-w-lg h-[82vh] sm:h-[78vh] flex flex-col rounded-b-none sm:rounded-b-3xl overflow-hidden" x-transition.opacity>
            <div class="flex items-center gap-3 px-4 py-3 border-b border-ink/5">
                <span class="h-10 w-10 rounded-2xl bg-coral text-white flex items-center justify-center shrink-0"><span class="material-symbols-outlined">auto_awesome</span></span>
                <div class="min-w-0 flex-1"><p class="font-display text-lg leading-tight">CAMINO</p><p class="text-[11px] text-ink-muted truncate" x-text="pageLabel"></p></div>
                <button type="button" @click="speak = !speak" class="h-9 w-9 rounded-full hover:bg-paper flex items-center justify-center" :class="speak ? 'text-coral' : 'text-ink-muted'" :aria-label="speak ? @js(__('Couper la voix')) : @js(__('Activer la voix'))"><span class="material-symbols-outlined" x-text="speak ? 'volume_up' : 'volume_off'"></span></button>
                <button type="button" @click="show = false" class="h-9 w-9 rounded-full hover:bg-paper flex items-center justify-center text-ink-muted" aria-label="{{ __('Fermer') }}"><span class="material-symbols-outlined">close</span></button>
            </div>

            <div x-ref="log" class="flex-1 overflow-y-auto px-4 py-3 space-y-3">
                <div class="flex items-end gap-2">
                    <span class="h-7 w-7 rounded-full bg-coral-soft text-coral flex items-center justify-center shrink-0"><span class="material-symbols-outlined" style="font-size:16px">auto_awesome</span></span>
                    <p class="rounded-2xl rounded-bl-md bg-paper px-3 py-2 text-sm leading-snug max-w-[88%]" x-text="hello"></p>
                </div>
                <template x-for="(m, i) in messages" :key="i">
                    <div>
                        <template x-if="m.role === 'user'"><div class="flex justify-end"><p class="rounded-2xl rounded-br-md bg-ink text-white px-3 py-2 text-sm leading-snug max-w-[85%]" x-text="m.content"></p></div></template>
                        <template x-if="m.role === 'assistant'">
                            <div class="space-y-2">
                                <div class="flex items-end gap-2">
                                    <span class="h-7 w-7 rounded-full bg-coral-soft text-coral flex items-center justify-center shrink-0"><span class="material-symbols-outlined" style="font-size:16px">auto_awesome</span></span>
                                    <p class="rounded-2xl rounded-bl-md bg-paper px-3 py-2 text-sm leading-snug max-w-[88%]" x-text="m.typed"></p>
                                </div>
                                <div x-show="m.done && m.filter" class="ml-9 inline-flex items-center gap-1.5 rounded-full bg-coral-soft text-coral-dark px-2.5 py-1 text-[11px] font-semibold"><span class="material-symbols-outlined" style="font-size:14px">tune</span><span x-text="cfg.t.filters + ' · ' + filterLabel(m.filter)"></span></div>
                                <template x-for="a in (m.done ? m.actions : [])" :key="a.type + (a.id || a.index)">
                                    <div class="ml-9 inline-flex items-center gap-1.5 rounded-full bg-teal-soft text-teal px-2.5 py-1 text-[11px] font-semibold"><span class="material-symbols-outlined" style="font-size:14px" x-text="a.type === 'add_place' ? 'add_circle' : 'remove_circle'"></span><span x-text="a.type === 'add_place' ? cfg.t.added + ' : ' + a.title : cfg.t.removed + ' ' + (a.index + 1)"></span></div>
                                </template>
                                <div x-show="m.done && m.places.length" class="ml-9 grid gap-2">
                                    <template x-for="p in m.places" :key="p.id">
                                        <div class="flex items-center gap-2.5 rounded-2xl border border-ink/5 bg-surface p-2">
                                            <button type="button" @click="see(p)" class="h-14 w-14 rounded-xl overflow-hidden shrink-0 placeholder-cover flex items-center justify-center"><template x-if="p.cover"><img :src="p.cover" alt="" class="h-full w-full object-cover" loading="lazy"></template><template x-if="!p.cover"><span class="material-symbols-outlined text-white/80">place</span></template></button>
                                            <button type="button" @click="see(p)" class="min-w-0 flex-1 text-left">
                                                <p class="text-sm font-semibold leading-tight line-clamp-1" x-text="p.title"></p>
                                                <p class="text-[11px] text-ink-muted line-clamp-1"><span x-text="p.category || ''"></span><span x-show="p.distance_m !== null" x-text="' · ' + fmt(p.distance_m)"></span><span x-show="p.free" class="text-teal" x-text="' · ' + cfg.t.free"></span><span x-show="p.open" class="text-emerald-600" x-text="' · ' + cfg.t.open"></span></p>
                                                <p class="text-xs text-ink-soft leading-snug line-clamp-2 mt-0.5" x-text="p.reason"></p>
                                            </button>
                                            <button type="button" @click="add(p)" class="h-9 w-9 rounded-full flex items-center justify-center shrink-0" :class="added.includes(p.id) ? 'bg-teal text-white' : 'bg-paper hover:bg-paper-deep text-ink'" :aria-label="cfg.t.add"><span class="material-symbols-outlined" style="font-size:20px" x-text="added.includes(p.id) ? 'check' : 'add'"></span></button>
                                        </div>
                                    </template>
                                </div>
                                <div x-show="m.done && m.suggestions.length && i === messages.length - 1" class="ml-9 flex flex-wrap gap-1.5">
                                    <template x-for="s in m.suggestions" :key="s"><button type="button" @click="ask(s)" class="rounded-full border border-ink/10 hover:bg-paper px-3 py-1.5 text-xs font-semibold" x-text="s"></button></template>
                                </div>
                            </div>
                        </template>
                    </div>
                </template>
                <p x-show="busy" class="text-xs text-ink-muted flex items-center gap-1.5 ml-9"><span class="material-symbols-outlined animate-spin" style="font-size:16px">progress_activity</span><span x-text="cfg.t.thinking"></span></p>
                <div x-show="!messages.length" class="flex flex-wrap gap-1.5 ml-9">
                    <template x-for="q in quick" :key="q"><button type="button" @click="ask(q)" class="rounded-full border border-ink/10 hover:bg-paper px-3 py-1.5 text-xs font-semibold" x-text="q"></button></template>
                </div>
            </div>

            <form @submit.prevent="ask(draft)" class="flex items-center gap-1.5 px-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] pt-2 border-t border-ink/5">
                <button type="button" @click="listen()" class="h-10 w-10 rounded-full flex items-center justify-center shrink-0" :class="listening ? 'bg-coral text-white animate-pulse' : 'bg-paper hover:bg-paper-deep text-ink'" aria-label="{{ __('Dicter') }}"><span class="material-symbols-outlined" x-text="listening ? 'graphic_eq' : 'mic'"></span></button>
                <input x-model="draft" type="text" maxlength="400" :placeholder="listening ? cfg.t.listening : @js(__('Écris à CAMINO…'))" class="field !py-2 flex-1 min-w-0" enterkeyhint="send" x-ref="input">
                <button class="h-10 w-10 rounded-full bg-coral text-white flex items-center justify-center shrink-0 disabled:opacity-40" :disabled="busy || !draft.trim()" aria-label="{{ __('Envoyer') }}"><span class="material-symbols-outlined" style="font-size:20px">send</span></button>
            </form>
        </div>
    </div>
</div>
@once
@push('scripts')
<script>
    function aiAssistant(cfg) {
        const pageLabels = { map: @js(__('Sur la carte')), result: @js(__('Ton parcours')), guidance: @js(__('En balade')), place: @js(__('Ce lieu')), other: @js(__('Partout dans CAMINO')) };
        return {
            cfg, show: false, messages: [], draft: '', busy: false, listening: false, speak: false, added: [], page: 'other',
            get hello() { return this.cfg.t.hello[this.page] || this.cfg.t.hello.other; },
            get quick() { return this.cfg.quick[this.page] || this.cfg.quick.other; },
            get pageLabel() { return pageLabels[this.page] || pageLabels.other; },
            context() {
                const dyn = typeof window.caminoAssistantContext === 'function' ? (window.caminoAssistantContext() || {}) : {};
                const now = new Date();
                return { page: 'other', ...dyn, time: now.getHours() + ':' + String(now.getMinutes()).padStart(2, '0') };
            },
            open(detail) {
                const ctx = this.context();
                this.page = ctx.page || 'other';
                if (ctx.speak !== undefined && !this.messages.length) this.speak = !!ctx.speak;
                this.show = true;
                if (detail && detail.ask) { this.ask(detail.ask); } else this.$nextTick(() => { if (window.innerWidth > 640 && this.$refs.input) this.$refs.input.focus(); });
            },
            async ask(text) {
                text = String(text || '').trim(); if (!text || this.busy) return;
                this.draft = ''; this.messages.push({ role: 'user', content: text }); this.busy = true; this.scroll();
                const history = this.messages.filter(m => m.role === 'user' || m.done).slice(-8).map(m => ({ role: m.role, content: m.role === 'user' ? m.content : m.say }));
                let reply;
                try {
                    const ctx = this.context(); this.page = ctx.page || this.page;
                    const r = await fetch(this.cfg.url, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': this.cfg.csrf }, body: JSON.stringify({ messages: history, context: ctx }) });
                    reply = await r.json();
                } catch (e) { reply = { ok: false }; }
                this.busy = false;
                const m = { role: 'assistant', say: reply.say || this.cfg.t.fail, typed: '', done: false, places: reply.places || [], filter: reply.filter || null, actions: reply.actions || [], suggestions: reply.suggestions || [] };
                this.messages.push(m); this.scroll();
                const rm = this.messages[this.messages.length - 1];
                if (reply.ok) {
                    if (m.filter) window.dispatchEvent(new CustomEvent('camino-assistant', { detail: { type: 'filter', filter: m.filter } }));
                    if (m.places.length) window.dispatchEvent(new CustomEvent('camino-assistant', { detail: { type: 'places', places: m.places } }));
                    m.actions.forEach(a => this.run(a));
                    if (this.speak) this.say(m.say);
                }
                this.type(rm);
            },
            type(m) {
                const full = m.say; let i = 0;
                const step = () => { i = Math.min(full.length, i + 3); m.typed = full.slice(0, i); if (i < full.length) setTimeout(step, 16); else { m.done = true; this.scroll(); } };
                step();
            },
            async run(a) {
                if (a.type === 'add_place') { await this.addId(a.id); }
                else if (a.type === 'remove_step') {
                    const f = document.createElement('form'); f.method = 'POST'; f.action = this.cfg.stepRemoveUrl.replace('__i__', a.index);
                    f.innerHTML = `<input type="hidden" name="_token" value="${this.cfg.csrf}">`; document.body.appendChild(f); setTimeout(() => f.submit(), 900);
                }
            },
            async add(p) { await this.addId(p.id); },
            async addId(id) {
                if (this.added.includes(id)) return;
                try { const r = await fetch(this.cfg.addUrl + '/' + id, { method: 'POST', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': this.cfg.csrf } }); if (r.ok) { this.added.push(id); window.dispatchEvent(new CustomEvent('camino-assistant', { detail: { type: 'cart', id } })); this.$dispatch('toast', this.cfg.t.added); } } catch (e) {}
            },
            see(p) {
                if (this.page === 'map') { window.dispatchEvent(new CustomEvent('camino-assistant', { detail: { type: 'focus', place: p } })); this.show = false; }
                else window.location.href = p.url;
            },
            filterLabel(f) {
                if (!f) return '';
                const parts = [...(f.labels || f.category_slugs || [])];
                if (f.free) parts.push(this.cfg.t.free); if (f.open_now) parts.push(this.cfg.t.open); if (f.terms) parts.push(f.terms);
                return parts.join(' · ') || '…';
            },
            fmt(m) { return m >= 1000 ? (m / 1000).toFixed(1).replace('.', ',') + ' km' : Math.max(0, Math.round(m / 10) * 10) + ' m'; },
            listen() {
                const SR = window.SpeechRecognition || window.webkitSpeechRecognition;
                if (!SR) { this.$dispatch('toast', this.cfg.t.noMic); return; }
                if (this.listening) { this.listening = false; return; }
                const rec = new SR(); rec.lang = this.cfg.lang; rec.interimResults = false; rec.maxAlternatives = 1;
                rec.onresult = (e) => { const t = e.results[0][0].transcript; this.listening = false; this.ask(t); };
                rec.onerror = () => { this.listening = false; }; rec.onend = () => { this.listening = false; };
                this.listening = true; rec.start();
            },
            say(text) {
                if (!('speechSynthesis' in window)) return;
                const u = new SpeechSynthesisUtterance(text); u.lang = this.cfg.lang; u.rate = 1.0;
                const v = window.speechSynthesis.getVoices().find(x => x.lang && x.lang.toLowerCase().startsWith(this.cfg.lang.slice(0, 2).toLowerCase()));
                if (v) u.voice = v;
                window.speechSynthesis.cancel(); window.speechSynthesis.speak(u);
            },
            scroll() { this.$nextTick(() => { const el = this.$refs.log; if (el) el.scrollTop = el.scrollHeight; }); },
        };
    }
</script>
@endpush
@endonce
@endif
