@php
    /** Démo commerciale : un téléphone qui enchaîne les vrais écrans de l'app, avec titres et voix off (fr, en, zh). */
    $lang = in_array(request('lang'), ['fr', 'en', 'zh'], true) ? request('lang') : app()->getLocale();
    $speech = ['fr' => 'fr-FR', 'en' => 'en-US', 'zh' => 'zh-CN'][$lang];
    $demo = [
        'lang' => $lang, 'speech' => $speech, 'autoplay' => (bool) request('autoplay'), 'carnet' => request('carnet'), 'muted' => (bool) request('muted'),
        'csrf' => csrf_token(), 'generateUrl' => route('itineraries.store'), 'base' => url('/'),
        't' => [
            'fr' => [
                'intro' => ['CAMINO', 'Le GPS culturel intelligent', 'Île-de-France · carte vivante, parcours sur mesure, guidage à la voix.'],
                'map' => ['Explore', 'Une carte qui vit', 'Musées, monuments, street art, parcs. Ouvert maintenant, à dix minutes à pied, gratuit ce dimanche. Et même Paris d\'hier.'],
                'form' => ['Génère', 'Dis-lui où, quand, tes envies', 'Ta position ou une adresse, le temps que tu as, à pied, à vélo ou en transports.'],
                'result' => ['Ton parcours', 'Calculé pour de vrai', 'Des lieux ouverts à ton passage, l\'ordre optimisé, les vrais trajets, l\'heure d\'arrivée à chaque étape.'],
                'nav' => ['Guidage', 'Suis la flèche', 'La carte tourne avec toi, la voix te guide, le métro et le bus sont dans le trajet. À l\'arrivée, l\'audioguide raconte le lieu.'],
                'dark' => ['Confort', 'Mode sombre', 'Le soir, l\'app passe en sombre avec des contrastes soignés.'],
                'langs' => ['Langues', 'Français, English, 中文', 'Interface, voix et descriptions des lieux traduites automatiquement.'],
                'journal' => ['Souvenir', 'Ton carnet de voyage', 'À la fin, une page magazine avec tes photos, ton tracé et tes chiffres, à partager en un lien.'],
                'outro' => ['CAMINO', 'La ville a plus à raconter.', 'Gratuit, sans application à installer. camino-u0eo.onrender.com'],
                'replay' => 'Rejouer', 'play' => 'Lancer la démo', 'pause' => 'Pause',
            ],
            'en' => [
                'intro' => ['CAMINO', 'The smart cultural GPS', 'Paris region · a living map, tailor-made routes, voice guidance.'],
                'map' => ['Explore', 'A map that lives', 'Museums, monuments, street art, parks. Open now, ten minutes away, free this Sunday. Even Paris of yesterday.'],
                'form' => ['Generate', 'Tell it where, when, what you like', 'Your location or an address, the time you have, on foot, by bike or by public transport.'],
                'result' => ['Your route', 'Computed for real', 'Places open when you pass, optimised order, real routes, arrival time at every stop.'],
                'nav' => ['Guidance', 'Follow the arrow', 'The map turns with you, the voice guides you, metro and bus are part of the trip. On arrival, the audio guide tells the story.'],
                'dark' => ['Comfort', 'Dark mode', 'At night the app turns dark, with careful contrast.'],
                'langs' => ['Languages', 'Français, English, 中文', 'Interface, voice and place descriptions translated automatically.'],
                'journal' => ['Memory', 'Your travel journal', 'At the end, a magazine page with your photos, your track and your numbers, shared with one link.'],
                'outro' => ['CAMINO', 'The city has more to tell.', 'Free, nothing to install. camino-u0eo.onrender.com'],
                'replay' => 'Replay', 'play' => 'Play the demo', 'pause' => 'Pause',
            ],
            'zh' => [
                'intro' => ['CAMINO', '智能文化 GPS', '法兰西岛 · 活地图、量身定制的路线、语音导航。'],
                'map' => ['探索', '一张活地图', '博物馆、古迹、街头艺术、公园。现在开放、步行十分钟、本周日免费。还有昔日巴黎。'],
                'form' => ['生成', '告诉它地点、时间和兴趣', '你的位置或一个地址、可用时间，步行、骑行或公共交通。'],
                'result' => ['你的路线', '真实计算', '途经时开放的地点、优化的顺序、真实路线、每站到达时间。'],
                'nav' => ['导航', '跟着箭头走', '地图随你转动，语音为你指路，地铁和公交都在行程里。到达后，语音导览讲述这个地方。'],
                'dark' => ['舒适', '深色模式', '夜晚，应用切换为对比精细的深色。'],
                'langs' => ['语言', 'Français, English, 中文', '界面、语音和地点描述自动翻译。'],
                'journal' => ['回忆', '你的旅行手记', '结束时，一页杂志式页面：你的照片、轨迹和数据，一键分享。'],
                'outro' => ['CAMINO', '这座城市有更多故事。', '免费，无需安装。camino-u0eo.onrender.com'],
                'replay' => '重播', 'play' => '播放演示', 'pause' => '暂停',
            ],
        ][$lang],
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ $lang === 'zh' ? 'zh-CN' : $lang }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>CAMINO · {{ __('Démo') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700&family=Fraunces:ital,opsz,wght@0,9..144,500;0,9..144,600;1,9..144,500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,300..600,0..1,0">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        html, body { height: 100%; background: #0B0E12; color: #F3EFE7; overflow: hidden; }
        .stage { position: relative; width: 100vw; height: 100vh; display: grid; grid-template-columns: 1fr 460px; align-items: center; gap: 40px; padding: 0 6vw; background: radial-gradient(900px 600px at 20% 30%, rgba(255,90,60,.22), transparent 60%), radial-gradient(700px 500px at 85% 80%, rgba(15,139,141,.25), transparent 60%), #0B0E12; }
        .stage::before { content: ''; position: absolute; inset: 0; opacity: .12; background-image: radial-gradient(circle at 1px 1px, #fff 1px, transparent 0); background-size: 26px 26px; pointer-events: none; }
        .caption { position: relative; z-index: 2; max-width: 620px; }
        .caption .eyebrow { color: #FFC857; letter-spacing: .28em; }
        .caption h1 { font-family: Fraunces, Georgia, serif; font-size: clamp(40px, 5.2vw, 76px); line-height: 1.02; margin: 14px 0 18px; letter-spacing: -.02em; }
        .caption p { font-size: clamp(16px, 1.35vw, 22px); line-height: 1.5; color: rgba(243,239,231,.78); }
        .caption > * { animation: cap-in .7s cubic-bezier(.2,.8,.3,1) both; }
        .caption h1 { animation-delay: .08s; } .caption p { animation-delay: .18s; }
        @keyframes cap-in { from { opacity: 0; transform: translateY(18px); } to { opacity: 1; transform: none; } }
        .device { position: relative; z-index: 2; width: 400px; height: 820px; border-radius: 52px; background: #0d1015; padding: 12px; box-shadow: 0 40px 100px -30px rgba(0,0,0,.9), inset 0 0 0 2px #2a2f38; transform-origin: center; }
        .device::before { content: ''; position: absolute; top: 24px; left: 50%; transform: translateX(-50%); width: 110px; height: 30px; border-radius: 999px; background: #0d1015; z-index: 5; box-shadow: inset 0 0 0 1px #1d2128; }
        .device .screen { position: relative; width: 100%; height: 100%; border-radius: 40px; overflow: hidden; background: #F6F3EC; }
        .device iframe { position: absolute; inset: 0; width: 100%; height: 100%; border: 0; opacity: 0; transition: opacity .5s ease; }
        .device iframe.on { opacity: 1; }
        .device .veil { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; background: #0B0E12; color: #F3EFE7; transition: opacity .5s; }
        .device .veil.off { opacity: 0; pointer-events: none; }
        .progress { position: absolute; left: 6vw; right: 6vw; bottom: 34px; z-index: 3; display: flex; align-items: center; gap: 14px; }
        .progress .bar { flex: 1; height: 4px; border-radius: 999px; background: rgba(255,255,255,.12); overflow: hidden; }
        .progress .bar i { display: block; height: 100%; width: 0; background: #FF5A3C; transition: width .3s linear; }
        .dots { display: flex; gap: 6px; } .dots b { width: 8px; height: 8px; border-radius: 50%; background: rgba(255,255,255,.2); } .dots b.on { background: #FFC857; }
        .ctrl { position: absolute; top: 26px; right: 6vw; z-index: 3; display: flex; gap: 8px; }
        .logo { display: inline-flex; align-items: center; gap: 12px; }
        .logo .mark { width: 64px; height: 64px; border-radius: 20px; background: #FF5A3C; display: flex; align-items: center; justify-content: center; box-shadow: 0 20px 40px -12px rgba(255,90,60,.6); animation: pulse 2.4s ease-in-out infinite; }
        @keyframes pulse { 0%,100% { transform: scale(1);} 50% { transform: scale(1.06);} }
        .flags { display: flex; gap: 10px; margin-top: 18px; } .flags span { font-size: 15px; font-weight: 600; padding: 8px 14px; border-radius: 999px; background: rgba(255,255,255,.08); border: 1px solid rgba(255,255,255,.12); }
        @media (max-width: 1100px) { .stage { grid-template-columns: 1fr; padding: 24px; align-items: start; } .device { width: 300px; height: 615px; margin: 0 auto; border-radius: 40px; } .device .screen { border-radius: 30px; } .caption h1 { font-size: 34px; } }
    </style>
</head>
<body>
<div class="stage" x-data="caminoDemo(@js($demo))" x-init="init()">
    <div class="ctrl">
        <button type="button" @click="toggle()" class="btn btn-md bg-white/10 text-white border border-white/15 hover:bg-white/20"><span class="material-symbols-outlined" style="font-size:18px" x-text="playing ? 'pause' : 'play_arrow'"></span><span x-text="playing ? data.t.pause : (started ? data.t.replay : data.t.play)"></span></button>
        <button type="button" @click="muted = !muted" class="btn btn-md bg-white/10 text-white border border-white/15 hover:bg-white/20 !px-3"><span class="material-symbols-outlined" style="font-size:18px" x-text="muted ? 'volume_off' : 'volume_up'"></span></button>
    </div>

    <div class="caption" :key="scene">
        <template x-if="current.key === 'intro' || current.key === 'outro'">
            <div class="logo"><span class="mark"><span class="material-symbols-outlined filled text-white" style="font-size:36px">location_on</span></span><span class="font-display text-4xl font-semibold tracking-tight">CAMINO</span></div>
        </template>
        <p class="eyebrow mt-6" x-text="current.t[0]"></p>
        <h1 x-text="current.t[1]"></h1>
        <p x-text="current.t[2]"></p>
        <template x-if="current.key === 'langs'"><div class="flags"><span>🇫🇷 Français</span><span>🇬🇧 English</span><span>🇨🇳 中文</span></div></template>
    </div>

    <div class="device">
        <div class="screen">
            <iframe x-ref="a" title="CAMINO"></iframe>
            <iframe x-ref="b" title="CAMINO"></iframe>
            <div class="veil" :class="veil ? '' : 'off'"><div class="text-center"><span class="material-symbols-outlined filled text-coral" style="font-size:56px">location_on</span><p class="font-display text-2xl mt-2">CAMINO</p><p x-show="loadingText" class="text-xs text-white/60 mt-1" x-text="loadingText"></p></div></div>
        </div>
    </div>

    <div class="progress">
        <div class="dots"><template x-for="(s, i) in scenes" :key="s.key"><b :class="i === scene && 'on'"></b></template></div>
        <div class="bar"><i :style="'width:' + progress + '%'"></i></div>
        <span class="text-xs text-white/50 tabular-nums" x-text="Math.round(elapsed / 1000) + 's'"></span>
    </div>
</div>

<script>
function caminoDemo(data) {
    const L = data.lang;
    const url = (path, q = {}) => { const u = new URL(path, data.base); u.searchParams.set('lang', L); Object.entries(q).forEach(([k, v]) => u.searchParams.set(k, v)); return u.toString(); };
    return {
        data, scene: 0, playing: false, started: false, muted: data.muted, veil: true, loadingText: '', elapsed: 0, progress: 0, active: 'a', timer: null, tick: null, sceneStart: 0,
        scenes: [
            { key: 'intro', ms: 4200, run: null },
            { key: 'map', ms: 9000, run: (d) => d.show(url('/carte')) },
            { key: 'form', ms: 6500, run: (d) => d.show(url('/parcours', { fresh: 1 })) },
            { key: 'result', ms: 9000, run: async (d) => { await d.generate(); await d.show(url('/parcours')); } },
            { key: 'nav', ms: 16000, run: async (d) => { await d.show(url('/parcours/suivre', { simulate: 7 })); await d.sleep(1500); d.clickInFrame('button', /D[ée]marrer|Start|开始/); } },
            { key: 'dark', ms: 6000, run: async (d) => { d.setTheme('dark'); await d.show(url('/carte')); } },
            { key: 'langs', ms: 6000, run: async (d) => { d.setTheme('light'); await d.show(url('/parcours', { lang: L === 'en' ? 'zh' : 'en' })); } },
            { key: 'journal', ms: 8000, skip: !data.carnet, run: (d) => d.show(url('/p/' + data.carnet + '/carnet')) },
            { key: 'outro', ms: 6000, run: (d) => { d.setTheme('light'); return d.show(url('/')); } },
        ].filter(s => !s.skip),
        get current() { return this.scenes[this.scene]; },
        get total() { return this.scenes.reduce((a, s) => a + s.ms, 0); },
        init() { this.scenes.forEach(s => { s.t = data.t[s.key]; }); if (data.autoplay) setTimeout(() => this.start(), 600); },
        sleep(ms) { return new Promise(r => setTimeout(r, ms)); },
        toggle() { if (this.playing) { this.pause(); } else if (this.started && this.scene < this.scenes.length - 1 && this.elapsed > 0) { this.resume(); } else { this.start(); } },
        start() { this.started = true; this.scene = -1; this.elapsed = 0; this.playing = true; this.setTheme('light'); this.next(); },
        pause() { this.playing = false; clearTimeout(this.timer); clearInterval(this.tick); if ('speechSynthesis' in window) window.speechSynthesis.cancel(); },
        resume() { this.playing = true; this.runScene(); },
        async next() {
            if (this.scene + 1 >= this.scenes.length) { this.playing = false; window.demoDone = true; document.title = 'CAMINO · démo terminée'; return; }
            this.scene++;
            this.runScene();
        },
        async runScene() {
            const s = this.current;
            clearTimeout(this.timer); clearInterval(this.tick);
            this.sceneStart = Date.now();
            const before = this.scenes.slice(0, this.scene).reduce((a, x) => a + x.ms, 0);
            this.tick = setInterval(() => { const e = Math.min(s.ms, Date.now() - this.sceneStart); this.elapsed = before + e; this.progress = this.elapsed / this.total * 100; }, 100);
            this.speak(s.t[1] + '. ' + s.t[2]);
            if (s.run) { try { await s.run(this); } catch (e) { console.warn(e); } } else { this.veil = true; }
            this.timer = setTimeout(() => { if (this.playing) this.next(); }, Math.max(500, s.ms - (Date.now() - this.sceneStart)));
        },
        // Deux iframes en alternance : la nouvelle page se charge derrière, puis fondu.
        show(src) {
            return new Promise((resolve) => {
                const nextKey = this.active === 'a' ? 'b' : 'a';
                const next = this.$refs[nextKey], prev = this.$refs[this.active];
                let done = false;
                const finish = () => { if (done) return; done = true; next.classList.add('on'); prev.classList.remove('on'); this.veil = false; this.active = nextKey; setTimeout(() => { if (prev.src !== 'about:blank') prev.src = 'about:blank'; }, 600); resolve(); };
                // Révèle l'écran quand la page et ses polices (icônes) sont prêtes, pour éviter les icônes en texte.
                next.onload = () => { let p = Promise.resolve(); try { p = next.contentDocument.fonts.ready; } catch (e) {} Promise.race([p, this.sleep(2500)]).then(() => setTimeout(finish, 350)); };
                next.src = src;
                setTimeout(finish, 6000);
            });
        },
        frameDoc() { try { return this.$refs[this.active].contentDocument; } catch (e) { return null; } },
        clickInFrame(selector, re) {
            const doc = this.frameDoc(); if (!doc) return;
            const el = [...doc.querySelectorAll(selector)].find(b => re.test(b.innerText || ''));
            if (el) el.click();
        },
        setTheme(mode) { try { localStorage.setItem('camino-theme', mode); } catch (e) {} },
        async generate() {
            this.loadingText = '…';
            try {
                const body = new URLSearchParams({ _token: data.csrf, start_lat: '48.8443', start_lng: '2.3735', start_label: 'Gare de Lyon', end_mode: 'loop', date: new Date().toISOString().slice(0, 10), starts_at: '', budget_eur: '40', duration_minutes: '240', mode: 'walk', 'interests[]': 'musee', with_lunch: '0', use_weather: '1', accessible: '0' });
                body.append('interests[]', 'monument');
                await fetch(data.generateUrl, { method: 'POST', body, credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' }, redirect: 'follow' });
            } catch (e) { console.warn(e); }
            this.loadingText = '';
        },
        speak(text) {
            if (this.muted || !('speechSynthesis' in window)) return;
            const u = new SpeechSynthesisUtterance(text); u.lang = data.speech; u.rate = 1.0;
            const v = window.speechSynthesis.getVoices().find(x => x.lang && x.lang.toLowerCase().startsWith(data.speech.slice(0, 2)));
            if (v) u.voice = v;
            window.speechSynthesis.cancel(); window.speechSynthesis.speak(u);
        },
    };
}
</script>
</body>
</html>
