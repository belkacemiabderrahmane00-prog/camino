@php
    /** Bulle « balade en cours » : visible partout dans l'app pour les membres d'une balade active et quand un compagnon de route en lance une. */
    $hasGuestWalk = collect(session()->all())->keys()->contains(fn ($k) => str_starts_with((string) $k, 'walk_member_'));
    $watch = (auth()->check() || $hasGuestWalk) && ! request()->routeIs('walks.*');
@endphp
@if($watch)
<div x-data="walkBubble({ url: @js(route('walks.live')), t: { online: @js(__('en ligne')), inProgress: @js(__('Balade en cours')), launched: @js(__(':name a lancé une balade')), join: @js(__('Rejoindre')), open: @js(__('Ouvrir')) } })"
     x-show="item" x-cloak x-transition
     class="fixed z-[1050] left-3 right-3 sm:right-auto sm:max-w-sm bottom-24 md:bottom-6">
    <div class="card flex items-center gap-3 pl-3 pr-2 py-2 shadow-float border-l-4 border-l-coral">
        <span class="relative h-10 w-10 rounded-2xl bg-coral-soft text-coral flex items-center justify-center shrink-0"><span class="material-symbols-outlined">groups</span><span class="absolute -top-0.5 -right-0.5 h-3 w-3 rounded-full bg-emerald-500 animate-pulse"></span></span>
        <a :href="item ? item.url : '#'" class="min-w-0 flex-1">
            <p class="text-sm font-semibold truncate" x-text="item ? item.headline : ''"></p>
            <p class="text-[11px] text-ink-muted truncate" x-text="item ? item.sub : ''"></p>
        </a>
        <a :href="item ? item.url : '#'" class="btn btn-sm btn-primary shrink-0" x-text="item ? item.cta : ''"></a>
        <button type="button" @click="dismiss()" class="h-8 w-8 rounded-full hover:bg-paper flex items-center justify-center text-ink-muted shrink-0" aria-label="{{ __('Fermer') }}"><span class="material-symbols-outlined" style="font-size:18px">close</span></button>
    </div>
</div>
@once
@push('scripts')
<script>
    function walkBubble(cfg) {
        const KEY = 'camino-walk-dismissed';
        return {
            item: null, timer: null,
            init() { this.refresh(); this.timer = setInterval(() => this.refresh(), 30000); document.addEventListener('visibilitychange', () => { if (document.visibilityState === 'visible') this.refresh(); }); },
            dismissed() { try { return JSON.parse(localStorage.getItem(KEY) || '[]'); } catch (e) { return []; } },
            dismiss() { if (!this.item) return; try { localStorage.setItem(KEY, JSON.stringify([...this.dismissed(), this.item.code].slice(-20))); } catch (e) {} this.item = null; },
            async refresh() {
                try {
                    const r = await fetch(cfg.url, { headers: { Accept: 'application/json' } });
                    if (!r.ok) return;
                    const j = await r.json(); const skip = this.dismissed();
                    const mine = (j.mine || []).find(w => !skip.includes(w.code));
                    const comp = (j.companions || []).find(w => !skip.includes(w.code));
                    if (mine) this.item = { code: mine.code, url: mine.url, headline: cfg.t.inProgress + ' · ' + mine.online + ' ' + cfg.t.online, sub: mine.title, cta: cfg.t.open };
                    else if (comp) this.item = { code: comp.code, url: comp.url, headline: cfg.t.launched.replace(':name', comp.by), sub: comp.title, cta: cfg.t.join };
                    else this.item = null;
                } catch (e) {}
            },
        };
    }
</script>
@endpush
@endonce
@endif
