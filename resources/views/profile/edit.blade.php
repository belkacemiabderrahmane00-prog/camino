@php
    $catStyle = [
        'musee' => ['palette', '#7C3AED'], 'monument' => ['account_balance', '#B45309'], 'parc-jardin' => ['park', '#15803D'],
        'lieu-culturel' => ['theater_comedy', '#0369A1'], 'restauration' => ['restaurant', '#DB2777'], 'evenement-culturel' => ['celebration', '#F59E0B'],
        'street-art' => ['brush', '#E11D48'], 'itineraire' => ['route', '#0F766E'],
    ];
    $personas = [
        'musee' => ['Âme de muséophile', 'Les salles feutrées, les grandes collections et les expos du moment : ton terrain de jeu.', 'palette'],
        'monument' => ['Gardien·ne du patrimoine', 'Façades, châteaux et vieilles pierres : tu lis la ville comme un livre d\'histoire.', 'account_balance'],
        'parc-jardin' => ['Flâneur·se des jardins', 'Une allée ombragée, un banc, une vue : tu explores au rythme de tes pas.', 'park'],
        'lieu-culturel' => ['Noctambule culturel·le', 'Scènes, galeries, cinémas d\'art et d\'essai : tu es là où ça vibre.', 'theater_comedy'],
        'street-art' => ['Chasseur·se de street art', 'Un mur peint, une ruelle cachée : tu vois la ville comme une galerie à ciel ouvert.', 'brush'],
        'evenement-culturel' => ['Toujours à l\'affût', 'Festivals, concerts, journées spéciales : tu ne rates jamais ce qui se passe.', 'celebration'],
    ];
    $topSlug = $profile['top'][0]['slug'] ?? null;
    $persona = $personas[$topSlug] ?? ['Curieux·se de tout', 'Ton profil se dessine à chaque favori, avis et parcours. Continue d\'explorer.', 'explore'];
    $earnedBadges = collect($badges)->where('earned', true)->count();
    $chosen = old('interests', $user->interests ?? []);
    $memberSince = $user->created_at->translatedFormat('F Y');
@endphp
<x-app-layout title="Mon profil">
    <section class="max-w-5xl mx-auto px-4 sm:px-6 pt-6 sm:pt-10 pb-8" x-data="{ tab: @js(session('profile_tab', old('interests') !== null ? 'gouts' : 'profil')), preview: null, remove: false }">

        {{-- ================================================================ En-tête --}}
        <div class="rounded-4xl bg-ink text-white relative overflow-hidden">
            <div class="absolute -right-16 -top-16 h-72 w-72 rounded-full bg-coral/40 blur-3xl"></div>
            <div class="absolute -left-10 -bottom-24 h-64 w-64 rounded-full bg-teal/40 blur-3xl"></div>
            <div class="absolute inset-0 opacity-[0.1]" style="background-image: radial-gradient(circle at 1px 1px, #fff 1px, transparent 0); background-size: 24px 24px;"></div>

            <div class="relative p-5 sm:p-8">
                <div class="flex flex-col sm:flex-row items-center sm:items-start gap-5">
                    {{-- Avatar + anneau de progression --}}
                    <div class="relative shrink-0">
                        <svg class="progress-ring absolute -inset-2 h-[calc(100%+1rem)] w-[calc(100%+1rem)]" viewBox="0 0 100 100" aria-hidden="true">
                            <circle cx="50" cy="50" r="46" stroke="rgba(255,255,255,0.12)"></circle>
                            <circle cx="50" cy="50" r="46" stroke="#FFC53D" pathLength="100" stroke-dasharray="{{ max(2, $level['progress']) }} 100"></circle>
                        </svg>
                        <button type="button" @click="tab = 'profil'; $nextTick(() => $refs.avatarInput.click())" class="relative block rounded-full overflow-hidden h-28 w-28 sm:h-32 sm:w-32 group outline-none focus-visible:ring-4 focus-visible:ring-sun/60" title="Changer ma photo">
                            <template x-if="preview"><img :src="preview" class="h-full w-full object-cover" alt=""></template>
                            <template x-if="!preview">
                                <div class="h-full w-full">
                                    @if($user->avatar_url)
                                        <img src="{{ $user->avatar_url }}" alt="{{ $user->name }}" class="h-full w-full object-cover" :class="remove && 'opacity-30 grayscale'">
                                    @else
                                        <div class="h-full w-full bg-teal flex items-center justify-center text-5xl font-display">{{ $user->initial }}</div>
                                    @endif
                                </div>
                            </template>
                            <span class="absolute inset-0 bg-ink/50 opacity-0 group-hover:opacity-100 transition flex items-center justify-center"><span class="material-symbols-outlined">photo_camera</span></span>
                        </button>
                        <span class="absolute -bottom-1 -right-1 h-10 w-10 rounded-2xl bg-sun text-ink flex items-center justify-center shadow-card" title="Niveau {{ $level['index'] }} · {{ $level['name'] }}"><span class="material-symbols-outlined filled" style="font-size:20px">{{ $level['icon'] }}</span></span>
                    </div>

                    <div class="text-center sm:text-left flex-1 min-w-0">
                        <p class="eyebrow">Niveau {{ $level['index'] }} · {{ $level['name'] }}</p>
                        <h1 class="display text-3xl sm:text-4xl mt-1 truncate">{{ $user->name }}</h1>
                        <p class="text-white/70 text-sm mt-1.5 line-clamp-2">{{ $user->bio ?: 'Membre CAMINO depuis ' . $memberSince }}</p>
                        <div class="mt-3 flex flex-wrap justify-center sm:justify-start gap-1.5 text-[11px]">
                            @if($user->city)<span class="inline-flex items-center gap-1 rounded-full bg-white/10 px-2.5 py-1"><span class="material-symbols-outlined" style="font-size:13px">location_on</span>{{ $user->city }}</span>@endif
                            <span class="inline-flex items-center gap-1 rounded-full bg-white/10 px-2.5 py-1"><span class="material-symbols-outlined" style="font-size:13px">{{ ($user->mobility ?? 'walk') === 'bike' ? 'directions_bike' : 'directions_walk' }}</span>{{ ($user->mobility ?? 'walk') === 'bike' ? 'À vélo' : 'À pied' }}</span>
                            <span class="inline-flex items-center gap-1 rounded-full bg-white/10 px-2.5 py-1"><span class="material-symbols-outlined" style="font-size:13px">calendar_month</span>Depuis {{ $memberSince }}</span>
                            <span class="inline-flex items-center gap-1 rounded-full bg-white/10 px-2.5 py-1"><span class="material-symbols-outlined filled text-sun" style="font-size:13px">military_tech</span>{{ $earnedBadges }}/{{ count($badges) }} badges</span>
                        </div>
                        <div class="mt-3 max-w-sm mx-auto sm:mx-0">
                            <div class="flex justify-between text-[11px] text-white/70 mb-1"><span>{{ $level['points'] }} pts</span><span>{{ $level['next'] ? 'Niveau ' . ($level['index'] + 1) . ' à ' . $level['next'] . ' pts' : 'Niveau max atteint' }}</span></div>
                            <div class="h-1.5 rounded-full bg-white/15 overflow-hidden"><div class="h-full rounded-full bg-gradient-to-r from-coral to-sun" style="width: {{ $level['progress'] }}%"></div></div>
                        </div>
                    </div>

                    <div class="hidden sm:flex flex-col gap-2 shrink-0">
                        <a href="{{ route('dashboard') }}" class="btn btn-sm bg-white/15 text-white hover:bg-white/25"><span class="material-symbols-outlined" style="font-size:16px">space_dashboard</span>Mon espace</a>
                        <form method="POST" action="{{ route('logout') }}">@csrf<button class="btn btn-sm bg-white/15 text-white hover:bg-white/25 w-full"><span class="material-symbols-outlined" style="font-size:16px">logout</span>Déconnexion</button></form>
                    </div>
                </div>

                {{-- Stats : défilement horizontal sur mobile --}}
                <div class="mt-5 flex sm:grid sm:grid-cols-6 gap-2 overflow-x-auto hide-scrollbar -mx-5 px-5 sm:mx-0 sm:px-0 text-center">
                    @foreach([
                        ['itineraries', 'Parcours', 'route'], ['km', 'km', 'directions_walk'], ['favorites', 'Favoris', 'favorite'],
                        ['reviews', 'Avis', 'rate_review'], ['photos', 'Photos', 'photo_camera'], ['alerts', 'Alertes', 'campaign'],
                    ] as [$k, $l, $i])
                        <div class="shrink-0 w-24 sm:w-auto rounded-2xl bg-white/10 p-3">
                            <span class="material-symbols-outlined text-sun" style="font-size:18px">{{ $i }}</span>
                            <p class="text-xl font-semibold leading-tight">{{ is_float($stats[$k]) ? number_format($stats[$k], 1, ',', ' ') : $stats[$k] }}</p>
                            <p class="text-[10px] text-white/70 uppercase tracking-wider">{{ $l }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- ================================================================ Onglets --}}
        <div class="sticky top-[4.4rem] z-30 mt-4 -mx-4 px-4 sm:mx-0 sm:px-0">
            <div class="glass rounded-full p-1 grid grid-cols-4 text-[11px] sm:text-sm font-semibold">
                @foreach([['profil', 'Profil', 'person'], ['gouts', 'Goûts', 'auto_awesome'], ['activite', 'Activité', 'history'], ['compte', 'Compte', 'lock']] as [$k, $l, $i])
                    <button type="button" @click="tab = '{{ $k }}'" class="flex items-center justify-center gap-1.5 rounded-full py-2 transition" :class="tab === '{{ $k }}' ? 'bg-ink text-white shadow-card' : 'text-ink-muted hover:text-ink'">
                        <span class="material-symbols-outlined" style="font-size:16px">{{ $i }}</span>{{ $l }}
                    </button>
                @endforeach
            </div>
        </div>

        @if($errors->any())
            <div class="mt-4 rounded-2xl bg-coral-soft text-coral-dark px-4 py-3 text-sm flex items-start gap-2"><span class="material-symbols-outlined" style="font-size:18px">error</span><div>@foreach($errors->all() as $e)<p>{{ $e }}</p>@endforeach</div></div>
        @endif

        <form method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data" class="mt-4 space-y-4">
            @csrf @method('PATCH')
            <input type="hidden" name="email" value="{{ $user->email }}">

            {{-- ============================================================ Profil --}}
            <div x-show="tab === 'profil'" class="space-y-4">
                <div class="card p-5 sm:p-8 grid md:grid-cols-[200px_1fr] gap-6">
                    <div class="text-center">
                        <p class="label text-left">Photo de profil</p>
                        <div class="relative inline-block">
                            <template x-if="preview"><img :src="preview" class="h-36 w-36 rounded-3xl object-cover shadow-card" alt=""></template>
                            <template x-if="!preview">
                                <div>
                                    @if($user->avatar_url)
                                        <img src="{{ $user->avatar_url }}" class="h-36 w-36 rounded-3xl object-cover shadow-card" :class="remove && 'opacity-30 grayscale'" alt="">
                                    @else
                                        <div class="h-36 w-36 rounded-3xl bg-paper-deep text-ink-muted flex items-center justify-center"><span class="material-symbols-outlined" style="font-size:52px">add_a_photo</span></div>
                                    @endif
                                </div>
                            </template>
                            <label class="absolute -bottom-2 -right-2 btn btn-icon btn-primary cursor-pointer" title="Changer la photo">
                                <span class="material-symbols-outlined" style="font-size:18px">photo_camera</span>
                                <input x-ref="avatarInput" type="file" name="avatar" accept="image/jpeg,image/png,image/webp" class="sr-only" @change="const f = $event.target.files[0]; if (f) { preview = URL.createObjectURL(f); remove = false; }">
                            </label>
                        </div>
                        <p class="mt-3 text-[11px] text-ink-muted">JPEG, PNG ou WebP · 6 Mo max · recadrée en carré</p>
                        <x-input-error :messages="$errors->get('avatar')" class="mt-1" />
                        @if($user->avatar_url)
                            <label class="mt-2 inline-flex items-center gap-2 text-xs text-ink-muted cursor-pointer"><input type="checkbox" name="remove_avatar" value="1" x-model="remove" class="rounded border-ink/20 text-coral focus:ring-coral">Retirer ma photo</label>
                        @endif
                    </div>
                    <div class="space-y-4">
                        <div class="grid sm:grid-cols-2 gap-4">
                            <div><label class="label" for="name">Prénom ou pseudo</label><input id="name" name="name" value="{{ old('name', $user->name) }}" required maxlength="60" class="field"><x-input-error :messages="$errors->get('name')" class="mt-1" /></div>
                            <div><label class="label" for="city">Ma ville</label><input id="city" name="city" value="{{ old('city', $user->city) }}" maxlength="80" class="field" placeholder="Paris, Montreuil, Versailles…"></div>
                        </div>
                        <div x-data="{ n: {{ mb_strlen((string) old('bio', $user->bio)) }} }">
                            <div class="flex items-center justify-between"><label class="label" for="bio">Bio</label><span class="text-[11px] text-ink-muted" x-text="n + ' / 280'"></span></div>
                            <textarea id="bio" name="bio" rows="3" maxlength="280" class="field" @input="n = $event.target.value.length" placeholder="Fan de street art, toujours partante pour un musée gratuit le dimanche…">{{ old('bio', $user->bio) }}</textarea>
                            <x-input-error :messages="$errors->get('bio')" class="mt-1" />
                        </div>
                        <div>
                            <p class="label">Je me déplace surtout</p>
                            <div class="grid grid-cols-2 gap-2 max-w-sm">
                                @foreach(['walk' => ['directions_walk', 'À pied', 'Parcours de 3 à 6 km'], 'bike' => ['directions_bike', 'À vélo', 'Parcours jusqu\'à 15 km']] as $m => [$icon, $l, $d])
                                    <label class="cursor-pointer"><input type="radio" name="mobility" value="{{ $m }}" class="peer sr-only" @checked(old('mobility', $user->mobility ?? 'walk') === $m)><span class="flex flex-col items-center gap-0.5 rounded-2xl border border-ink/10 px-3 py-3 text-sm font-medium peer-checked:bg-ink peer-checked:text-white peer-checked:border-ink transition text-center"><span class="material-symbols-outlined" style="font-size:22px">{{ $icon }}</span>{{ $l }}<span class="text-[10px] font-normal opacity-70">{{ $d }}</span></span></label>
                                @endforeach
                            </div>
                        </div>
                        <button type="submit" class="btn btn-md btn-primary w-full sm:w-auto"><span class="material-symbols-outlined" style="font-size:18px">save</span>Enregistrer</button>
                    </div>
                </div>

                <div class="grid sm:grid-cols-3 gap-3">
                    @foreach([
                        [route('map.index'), 'map', 'Explorer la carte', 'Les lieux autour de toi'],
                        [route('itineraries.create'), 'auto_awesome', 'Générer un parcours', 'Selon ta mobilité'],
                        [route('community.propose'), 'add_location_alt', 'Proposer un lieu', 'Enrichis la carte'],
                    ] as [$href, $icon, $t, $d])
                        <a href="{{ $href }}" class="card card-hover p-4 flex items-center gap-3">
                            <span class="h-10 w-10 rounded-2xl bg-coral-soft text-coral flex items-center justify-center shrink-0"><span class="material-symbols-outlined">{{ $icon }}</span></span>
                            <div class="min-w-0"><p class="font-semibold text-sm">{{ $t }}</p><p class="text-xs text-ink-muted">{{ $d }}</p></div>
                        </a>
                    @endforeach
                </div>
            </div>

            {{-- ============================================================ Goûts --}}
            <div x-show="tab === 'gouts'" x-cloak class="space-y-4">
                <div class="card p-5 sm:p-8 grid md:grid-cols-[1fr_260px] gap-6">
                    <div>
                        <p class="eyebrow mb-1">Mon profil culturel</p>
                        <h2 class="display text-2xl">{{ $persona[0] }}</h2>
                        <p class="text-sm text-ink-muted mt-1">{{ $persona[1] }}</p>
                        @if(!empty($profile['top']))
                            <div class="mt-5 space-y-2.5">
                                @foreach(array_slice($profile['top'], 0, 5) as $t)
                                    @php [$ic, $col] = $catStyle[$t['slug']] ?? ['place', '#0F8B8D']; $pct = (int) round($t['weight'] * 100); @endphp
                                    <div class="flex items-center gap-3 text-sm">
                                        <span class="h-8 w-8 rounded-xl flex items-center justify-center shrink-0" style="background: {{ $col }}1A; color: {{ $col }}"><span class="material-symbols-outlined" style="font-size:18px">{{ $ic }}</span></span>
                                        <span class="w-28 sm:w-36 truncate font-medium">{{ $t['name'] }}</span>
                                        <div class="flex-1 h-2 rounded-full bg-paper overflow-hidden"><div class="h-full rounded-full" style="width: {{ $pct }}%; background: {{ $col }}"></div></div>
                                        <span class="text-xs text-ink-muted w-11 text-right tabular-nums whitespace-nowrap">{{ $pct }} %</span>
                                    </div>
                                @endforeach
                            </div>
                            <p class="mt-3 text-xs text-ink-muted">Calculé à partir de {{ $profile['signals']['favorites'] }} favoris, {{ $profile['signals']['reviews'] }} avis et {{ $profile['signals']['itineraries'] }} parcours, plus tes centres d'intérêt.</p>
                        @else
                            <div class="mt-4 rounded-2xl bg-paper p-4 text-sm text-ink-muted">Pas encore assez de signaux. Ajoute des favoris, laisse des avis, génère des parcours : le profil s'affine tout seul.</div>
                        @endif
                    </div>
                    <div class="rounded-3xl bg-ink text-white p-5 flex flex-col justify-between">
                        <span class="h-12 w-12 rounded-2xl bg-white/10 flex items-center justify-center"><span class="material-symbols-outlined text-sun" style="font-size:26px">{{ $persona[2] }}</span></span>
                        <div class="mt-6">
                            <p class="text-[11px] uppercase tracking-widest text-white/60">Ce que ça change</p>
                            <p class="text-sm mt-1 text-white/85">Le générateur privilégie tes catégories préférées et « Mon espace » te propose des lieux qui te ressemblent.</p>
                        </div>
                    </div>
                </div>

                <div class="card p-5 sm:p-8">
                    <p class="eyebrow mb-1">Mes envies</p>
                    <h2 class="display text-2xl">Ce que j'aime</h2>
                    <p class="text-sm text-ink-muted mt-1">Coche tes envies : elles comptent dans le générateur et les recommandations, même sans autre signal.</p>
                    <div class="mt-4 grid grid-cols-2 sm:grid-cols-4 gap-2">
                        @foreach($categories as $category)
                            @php [$ic, $col] = $catStyle[$category->slug] ?? ['place', '#0F8B8D']; @endphp
                            <label class="cursor-pointer">
                                <input type="checkbox" name="interests[]" value="{{ $category->slug }}" class="peer sr-only" @checked(in_array($category->slug, $chosen))>
                                <span class="flex items-center gap-2.5 rounded-2xl border-2 border-transparent bg-paper px-3 py-2.5 text-sm font-medium transition peer-checked:border-ink peer-checked:bg-white peer-checked:shadow-card">
                                    <span class="h-8 w-8 rounded-xl flex items-center justify-center shrink-0" style="background: {{ $col }}1A; color: {{ $col }}"><span class="material-symbols-outlined" style="font-size:18px">{{ $ic }}</span></span>
                                    <span class="truncate">{{ $category->name }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                    <button type="submit" class="mt-5 btn btn-md btn-primary w-full sm:w-auto"><span class="material-symbols-outlined" style="font-size:18px">save</span>Enregistrer mes goûts</button>
                </div>
            </div>
        </form>

        {{-- ============================================================ Activité --}}
        <div x-show="tab === 'activite'" x-cloak class="space-y-4 mt-4">
            <div class="card p-5 sm:p-8">
                <div class="flex items-end justify-between gap-3 mb-4">
                    <div><p class="eyebrow mb-1">Badges</p><h2 class="display text-2xl">{{ $earnedBadges }} sur {{ count($badges) }} débloqués</h2></div>
                </div>
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                    @foreach($badges as $b)
                        <div class="rounded-2xl p-3.5 {{ $b['earned'] ? 'bg-sun-soft' : 'bg-paper' }}">
                            <div class="flex items-center gap-2.5">
                                <span class="h-10 w-10 rounded-2xl flex items-center justify-center shrink-0 {{ $b['earned'] ? 'bg-sun text-ink' : 'bg-white text-ink-muted' }}"><span class="material-symbols-outlined {{ $b['earned'] ? 'filled' : '' }}" style="font-size:22px">{{ $b['icon'] }}</span></span>
                                <div class="min-w-0"><p class="font-semibold text-sm truncate">{{ $b['name'] }}</p><p class="text-[11px] text-ink-muted">{{ $b['earned'] ? $b['hint'] : $b['progress'] . ' %' }}</p></div>
                            </div>
                            @if(!$b['earned'])
                                <div class="mt-2.5 h-1.5 rounded-full bg-white overflow-hidden"><div class="h-full rounded-full bg-teal" style="width: {{ $b['progress'] }}%"></div></div>
                                <p class="mt-1 text-[10px] text-ink-muted">Encore {{ $b['missing'] }} {{ $b['label'] }}</p>
                            @else
                                <p class="mt-2.5 text-[10px] font-semibold text-amber-700 flex items-center gap-1"><span class="material-symbols-outlined" style="font-size:12px">check_circle</span>Obtenu</p>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="grid md:grid-cols-2 gap-4">
                <div class="card p-5 sm:p-6">
                    <div class="flex items-center justify-between mb-3"><p class="eyebrow">Derniers parcours</p><a href="{{ route('itineraries.index') }}" class="text-xs font-semibold hover:text-coral">Tout voir</a></div>
                    <div class="space-y-2">
                        @forelse($recentItineraries as $it)
                            @php $r = $it->result_json ?? []; @endphp
                            <a href="{{ route('itineraries.show', $it) }}" class="flex items-center gap-3 rounded-2xl p-2 hover:bg-paper"><span class="h-10 w-10 rounded-2xl bg-coral-soft text-coral flex items-center justify-center shrink-0"><span class="material-symbols-outlined" style="font-size:18px">route</span></span><div class="min-w-0"><p class="text-sm font-semibold truncate">{{ $it->name }}</p><p class="text-[11px] text-ink-muted">{{ $it->created_at->translatedFormat('j F') }} · {{ count($r['steps'] ?? []) }} étapes · {{ number_format($r['total_distance_km'] ?? 0, 1, ',', ' ') }} km</p></div></a>
                        @empty
                            <p class="text-sm text-ink-muted">Aucun parcours pour l'instant. <a href="{{ route('itineraries.create') }}" class="underline font-semibold text-ink">Générer le premier</a>.</p>
                        @endforelse
                    </div>
                </div>
                <div class="card p-5 sm:p-6">
                    <p class="eyebrow mb-3">Mes photos</p>
                    @if($recentPhotos->isNotEmpty())
                        <div class="grid grid-cols-3 gap-2">
                            @foreach($recentPhotos as $photo)
                                <a href="{{ route('places.show', $photo->place_id) }}" class="relative aspect-square rounded-2xl overflow-hidden bg-paper" title="{{ $photo->place->title ?? '' }}">
                                    <img src="{{ $photo->url }}" alt="" class="w-full h-full object-cover">
                                    @if($photo->status !== 'approved')<span class="absolute bottom-1 left-1 badge badge-alert !text-[9px]">{{ $photo->status === 'pending' ? 'En attente' : 'Refusée' }}</span>@endif
                                </a>
                            @endforeach
                        </div>
                    @else
                        <p class="text-sm text-ink-muted">Partage une photo depuis la fiche d'un lieu : elle apparaîtra ici et sur la carte après validation.</p>
                    @endif
                </div>
            </div>
        </div>

        {{-- ============================================================ Compte --}}
        <div x-show="tab === 'compte'" x-cloak class="space-y-4 mt-4">
            <div class="card p-5 sm:p-8">
                <p class="eyebrow mb-1">Adresse e-mail</p>
                <form method="POST" action="{{ route('profile.update') }}" class="mt-2 grid sm:grid-cols-[1fr_auto] gap-3 items-end">
                    @csrf @method('PATCH')
                    <input type="hidden" name="name" value="{{ $user->name }}"><input type="hidden" name="bio" value="{{ $user->bio }}"><input type="hidden" name="city" value="{{ $user->city }}"><input type="hidden" name="mobility" value="{{ $user->mobility ?? 'walk' }}">
                    @foreach((array) ($user->interests ?? []) as $slug)<input type="hidden" name="interests[]" value="{{ $slug }}">@endforeach
                    <div><label class="label" for="email">E-mail</label><input id="email" type="email" name="email" value="{{ old('email', $user->email) }}" required class="field"><x-input-error :messages="$errors->get('email')" class="mt-1" /></div>
                    <button class="btn btn-md btn-ink">Mettre à jour</button>
                </form>
            </div>
            <div class="card p-5 sm:p-8">@include('profile.partials.update-password-form')</div>
            @can('admin')
                <a href="{{ route('moderation.index') }}" class="card card-hover p-5 flex items-center gap-3"><span class="h-10 w-10 rounded-2xl bg-sun-soft text-amber-700 flex items-center justify-center"><span class="material-symbols-outlined">shield</span></span><div><p class="font-semibold text-sm">Modération</p><p class="text-xs text-ink-muted">Photos, alertes et lieux proposés par la communauté</p></div></a>
            @endcan
            <div class="sm:hidden card p-5"><form method="POST" action="{{ route('logout') }}">@csrf<button class="btn btn-md btn-soft w-full"><span class="material-symbols-outlined" style="font-size:18px">logout</span>Déconnexion</button></form></div>
            <div class="card p-5 sm:p-8 border-coral/20">@include('profile.partials.delete-user-form')</div>
        </div>
    </section>
</x-app-layout>
