@props(['title' => '', 'steps' => [], 'lat' => null, 'lng' => null, 'speak' => false, 'floating' => true])
@php
    /** Compagnon de balade (IA) : feuille de discussion ouverte par $dispatch('open-companion'). Le contexte dynamique (étape, position) vient de window.caminoCompanionContext si défini. */
    $ai = app(\App\Services\AiService::class)->enabled();
    $quick = [__('Un café sympa près d\'ici ?'), __('Raconte-moi ce lieu'), __('On a combien de temps ?'), __('Un endroit gratuit à côté ?')];
    $cfg = [
        'url' => url('/api/v1/ai/compagnon?lang=' . app()->getLocale()), 'csrf' => csrf_token(), 'lang' => \App\Http\Middleware\SetLocale::speechLanguage(), 'speak' => (bool) $speak,
        'context' => ['title' => $title, 'steps' => collect($steps)->map(fn ($s) => ['title' => $s['title'] ?? '', 'arrive' => $s['arrive_at'] ?? ($s['arrive'] ?? null), 'category' => $s['category'] ?? null])->values()->all(), 'lat' => $lat, 'lng' => $lng],
        'quick' => $quick,
        't' => ['hello' => __('Je suis là pendant ta balade : un café, l\'histoire d\'un lieu, le temps qu\'il reste… demande-moi.'), 'fail' => __('Je n\'arrive pas à répondre pour le moment.'), 'thinking' => __('CAMINO réfléchit…')],
    ];
@endphp
@if($ai)
<div x-data="aiCompanion(@js($cfg))" @open-companion.window="open()" class="contents">
    @if($floating)
        <button type="button" @click="open()" class="fixed z-[1050] left-3 bottom-24 md:bottom-6 h-12 w-12 rounded-full bg-ink text-white shadow-float flex items-center justify-center" aria-label="{{ __('Demander à CAMINO') }}" x-show="!show" x-cloak><span class="material-symbols-outlined">auto_awesome</span></button>
    @endif
    <div x-show="show" x-cloak class="fixed inset-0 z-[1200] bg-ink/40 backdrop-blur-sm flex items-end sm:items-center justify-center p-0 sm:p-4" @click.self="show = false" @keydown.escape.window="show = false">
        <div class="card w-full sm:max-w-md max-h-[85vh] flex flex-col rounded-b-none sm:rounded-b-3xl" x-transition.opacity>
            <div class="flex items-center gap-3 px-4 py-3 border-b border-ink/5">
                <span class="h-10 w-10 rounded-2xl bg-coral-soft text-coral flex items-center justify-center"><span class="material-symbols-outlined">auto_awesome</span></span>
                <div class="min-w-0 flex-1"><p class="font-semibold leading-tight">{{ __('Compagnon CAMINO') }}</p><p class="text-[11px] text-ink-muted truncate" x-text="cfg.context.title || @js(__('Pose ta question'))"></p></div>
                <button type="button" @click="cfg.speak = !cfg.speak" class="h-9 w-9 rounded-full hover:bg-paper flex items-center justify-center text-ink-muted" :class="cfg.speak && 'text-coral'" :aria-label="cfg.speak ? @js(__('Couper la voix')) : @js(__('Activer la voix'))"><span class="material-symbols-outlined" x-text="cfg.speak ? 'volume_up' : 'volume_off'"></span></button>
                <button type="button" @click="show = false" class="h-9 w-9 rounded-full hover:bg-paper flex items-center justify-center text-ink-muted" aria-label="{{ __('Fermer') }}"><span class="material-symbols-outlined">close</span></button>
            </div>
            <div x-ref="log" class="flex-1 overflow-y-auto px-4 py-3 space-y-2 min-h-[10rem]">
                <p class="rounded-2xl bg-paper px-3 py-2 text-sm leading-snug max-w-[88%]" x-text="cfg.t.hello"></p>
                <template x-for="(m, i) in messages" :key="i">
                    <div class="flex" :class="m.role === 'user' ? 'justify-end' : ''"><p class="rounded-2xl px-3 py-2 text-sm leading-snug max-w-[88%] whitespace-pre-line" :class="m.role === 'user' ? 'bg-ink text-white' : 'bg-paper'" x-text="m.content"></p></div>
                </template>
                <p x-show="busy" class="text-xs text-ink-muted flex items-center gap-1.5"><span class="material-symbols-outlined animate-spin" style="font-size:16px">progress_activity</span><span x-text="cfg.t.thinking"></span></p>
            </div>
            <div class="flex gap-1.5 px-4 pb-2 overflow-x-auto hide-scrollbar">
                <template x-for="q in cfg.quick" :key="q"><button type="button" @click="ask(q)" class="shrink-0 rounded-full bg-paper hover:bg-paper-deep px-3 py-1.5 text-xs font-semibold" x-text="q"></button></template>
            </div>
            <form @submit.prevent="ask(draft)" class="flex items-center gap-1.5 px-4 pb-[max(1rem,env(safe-area-inset-bottom))] pt-1">
                <input x-model="draft" type="text" maxlength="400" placeholder="{{ __('Écris à CAMINO…') }}" class="field !py-2 flex-1 min-w-0" enterkeyhint="send" x-ref="input">
                <button class="h-10 w-10 rounded-full bg-coral text-white flex items-center justify-center shrink-0" :disabled="busy || !draft.trim()" aria-label="{{ __('Envoyer') }}"><span class="material-symbols-outlined" style="font-size:20px">send</span></button>
            </form>
        </div>
    </div>
</div>
@once
@push('scripts')
<script>
    function aiCompanion(cfg) {
        return {
            cfg, show: false, messages: [], draft: '', busy: false,
            open() { this.show = true; this.$nextTick(() => { if (window.innerWidth > 640 && this.$refs.input) this.$refs.input.focus(); }); },
            context() {
                const dyn = typeof window.caminoCompanionContext === 'function' ? (window.caminoCompanionContext() || {}) : {};
                const now = new Date();
                return { ...this.cfg.context, ...dyn, time: now.getHours() + ':' + String(now.getMinutes()).padStart(2, '0') };
            },
            async ask(text) {
                text = String(text || '').trim(); if (!text || this.busy) return;
                this.draft = ''; this.messages.push({ role: 'user', content: text }); this.busy = true; this.scroll();
                try {
                    const r = await fetch(this.cfg.url, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': this.cfg.csrf }, body: JSON.stringify({ messages: this.messages.slice(-8), context: this.context() }) });
                    const j = await r.json();
                    const answer = j.answer || this.cfg.t.fail;
                    this.messages.push({ role: 'assistant', content: answer });
                    if (this.cfg.speak && j.ok) this.say(answer);
                } catch (e) { this.messages.push({ role: 'assistant', content: this.cfg.t.fail }); }
                this.busy = false; this.scroll();
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
