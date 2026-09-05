<x-app-layout title="Mon profil">
    <section class="max-w-5xl mx-auto px-4 sm:px-6 pt-8 sm:pt-12" x-data="{ tab: 'profil' }">
        {{-- ================================================================ En-tête --}}
        <div class="rounded-4xl bg-ink text-white p-6 sm:p-8 relative overflow-hidden">
            <div class="absolute -right-16 -top-16 h-64 w-64 rounded-full bg-coral/40 blur-3xl"></div>
            <div class="absolute -left-10 -bottom-20 h-56 w-56 rounded-full bg-teal/40 blur-3xl"></div>
            <div class="relative flex flex-col sm:flex-row items-center sm:items-end gap-5">
                <div class="relative">
                    @if($user->avatar_url)
                        <img src="{{ $user->avatar_url }}" alt="{{ $user->name }}" class="h-28 w-28 sm:h-32 sm:w-32 rounded-3xl object-cover border-4 border-white/20 shadow-float">
                    @else
                        <div class="h-28 w-28 sm:h-32 sm:w-32 rounded-3xl bg-teal text-white flex items-center justify-center text-5xl font-display border-4 border-white/20 shadow-float">{{ $user->initial }}</div>
                    @endif
                    <span class="absolute -bottom-2 -right-2 h-10 w-10 rounded-2xl bg-sun text-ink flex items-center justify-center shadow-card" title="Niveau {{ $level['index'] }}"><span class="material-symbols-outlined filled" style="font-size:20px">{{ $level['icon'] }}</span></span>
                </div>
                <div class="text-center sm:text-left flex-1 min-w-0">
                    <p class="eyebrow">Niveau {{ $level['index'] }} · {{ $level['name'] }}</p>
                    <h1 class="display text-3xl sm:text-4xl mt-1 truncate">{{ $user->name }}</h1>
                    <p class="text-white/70 text-sm mt-1">{{ $user->bio ?: 'Explorateur CAMINO · membre depuis ' . $user->created_at->translatedFormat('F Y') }}{{ $user->city ? ' · ' . $user->city : '' }}</p>
                    <div class="mt-3 max-w-sm mx-auto sm:mx-0">
                        <div class="flex justify-between text-[11px] text-white/70 mb-1"><span>{{ $level['points'] }} pts</span><span>{{ $level['next'] ? 'Prochain niveau à ' . $level['next'] . ' pts' : 'Niveau max atteint' }}</span></div>
                        <div class="h-2 rounded-full bg-white/15 overflow-hidden"><div class="h-full rounded-full bg-gradient-to-r from-coral to-sun" style="width: {{ $level['progress'] }}%"></div></div>
                    </div>
                </div>
                <div class="flex gap-2">
                    <a href="{{ route('dashboard') }}" class="btn btn-sm bg-white/15 text-white hover:bg-white/25"><span class="material-symbols-outlined" style="font-size:16px">space_dashboard</span>Mon espace</a>
                    <form method="POST" action="{{ route('logout') }}">@csrf<button class="btn btn-sm bg-white/15 text-white hover:bg-white/25"><span class="material-symbols-outlined" style="font-size:16px">logout</span>Déconnexion</button></form>
                </div>
            </div>

            {{-- Stats --}}
            <div class="relative mt-6 grid grid-cols-3 sm:grid-cols-6 gap-2 text-center">
                @foreach([
                    ['itineraries', 'Parcours', 'route'], ['km', 'km parcourus', 'directions_walk'], ['favorites', 'Favoris', 'favorite'],
                    ['reviews', 'Avis', 'rate_review'], ['photos', 'Photos', 'photo_camera'], ['alerts', 'Alertes', 'campaign'],
                ] as [$k, $l, $i])
                    <div class="rounded-2xl bg-white/10 p-3">
                        <span class="material-symbols-outlined text-sun" style="font-size:18px">{{ $i }}</span>
                        <p class="text-xl font-semibold leading-tight">{{ is_float($stats[$k]) ? number_format($stats[$k], 1, ',', ' ') : $stats[$k] }}</p>
                        <p class="text-[10px] text-white/70 uppercase tracking-wider">{{ $l }}</p>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- ================================================================ Onglets --}}
        <div class="mt-6 flex gap-2 overflow-x-auto hide-scrollbar pb-1">
            @foreach([['profil', 'Mon profil', 'person'], ['gouts', 'Mes goûts', 'auto_awesome'], ['activite', 'Mon activité', 'history'], ['compte', 'Compte & sécurité', 'lock']] as [$k, $l, $i])
                <button @click="tab = '{{ $k }}'" class="chip shrink-0" :data-active="tab === '{{ $k }}'"><span class="material-symbols-outlined" style="font-size:16px">{{ $i }}</span>{{ $l }}</button>
            @endforeach
        </div>

        <form method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data" class="mt-4 space-y-6">
            @csrf @method('PATCH')

            {{-- Profil --}}
            <div x-show="tab === 'profil'" class="card p-6 sm:p-8 grid md:grid-cols-[220px_1fr] gap-6" x-data="{ preview: null, remove: false }">
                <div class="text-center">
                    <p class="label text-left">Photo de profil</p>
                    <div class="relative inline-block">
                        <template x-if="preview"><img :src="preview" class="h-40 w-40 rounded-3xl object-cover shadow-card" alt=""></template>
                        <template x-if="!preview">
                            <div>
                                @if($user->avatar_url)
                                    <img src="{{ $user->avatar_url }}" class="h-40 w-40 rounded-3xl object-cover shadow-card" :class="remove && 'opacity-30 grayscale'" alt="">
                                @else
                                    <div class="h-40 w-40 rounded-3xl bg-paper-deep text-ink-muted flex items-center justify-center"><span class="material-symbols-outlined" style="font-size:56px">add_a_photo</span></div>
                                @endif
                            </div>
                        </template>
                        <label class="absolute -bottom-2 -right-2 btn btn-icon btn-primary cursor-pointer" title="Changer la photo">
                            <span class="material-symbols-outlined" style="font-size:18px">edit</span>
                            <input type="file" name="avatar" accept="image/*" class="sr-only" @change="const f = $event.target.files[0]; if (f) { preview = URL.createObjectURL(f); remove = false; }">
                        </label>
                    </div>
                    <p class="mt-3 text-[11px] text-ink-muted">JPEG, PNG ou WebP · 6 Mo max · recadrée en carré</p>
                    @if($user->avatar_url)
                        <label class="mt-2 inline-flex items-center gap-2 text-xs text-ink-muted cursor-pointer"><input type="checkbox" name="remove_avatar" value="1" x-model="remove" class="rounded border-ink/20 text-coral focus:ring-coral">Retirer ma photo</label>
                    @endif
                </div>
                <div class="space-y-4">
                    <div class="grid sm:grid-cols-2 gap-4">
                        <div><label class="label" for="name">Prénom ou pseudo</label><input id="name" name="name" value="{{ old('name', $user->name) }}" required maxlength="60" class="field"><x-input-error :messages="$errors->get('name')" class="mt-1" /></div>
                        <div><label class="label" for="city">Ma ville</label><input id="city" name="city" value="{{ old('city', $user->city) }}" maxlength="80" class="field" placeholder="Paris, Montreuil, Versailles…"></div>
                    </div>
                    <div><label class="label" for="bio">Bio</label><textarea id="bio" name="bio" rows="3" maxlength="280" class="field" placeholder="Fan de street art, toujours partante pour un musée gratuit le dimanche…">{{ old('bio', $user->bio) }}</textarea><x-input-error :messages="$errors->get('bio')" class="mt-1" /></div>
                    <div>
                        <p class="label">Je me déplace surtout</p>
                        <div class="grid grid-cols-2 gap-2 max-w-sm">
                            @foreach(['walk' => ['directions_walk', 'À pied'], 'bike' => ['directions_bike', 'À vélo']] as $m => [$icon, $l])
                                <label class="cursor-pointer"><input type="radio" name="mobility" value="{{ $m }}" class="peer sr-only" @checked(old('mobility', $user->mobility ?? 'walk') === $m)><span class="flex items-center justify-center gap-2 rounded-2xl border border-ink/10 px-3 py-2.5 text-sm font-medium peer-checked:bg-ink peer-checked:text-white peer-checked:border-ink transition"><span class="material-symbols-outlined" style="font-size:18px">{{ $icon }}</span>{{ $l }}</span></label>
                            @endforeach
                        </div>
                    </div>
                    <button type="submit" class="btn btn-md btn-primary"><span class="material-symbols-outlined" style="font-size:18px">save</span>Enregistrer</button>
                </div>
            </div>

            {{-- Goûts --}}
            <div x-show="tab === 'gouts'" x-cloak class="card p-6 sm:p-8 space-y-6">
                <div>
                    <p class="eyebrow mb-1">Mes centres d'intérêt</p>
                    <h2 class="display text-2xl">Ce que j'aime</h2>
                    <p class="text-sm text-ink-muted mt-1">Sélectionne tes envies : le générateur de parcours et les recommandations en tiennent compte, même sans rien préciser.</p>
                    @php $chosen = old('interests', $user->interests ?? []); @endphp
                    <div class="mt-4 flex flex-wrap gap-2">
                        @foreach($categories as $category)
                            <label class="cursor-pointer"><input type="checkbox" name="interests[]" value="{{ $category->slug }}" class="peer sr-only" @checked(in_array($category->slug, $chosen))><x-category-pill :category="$category" class="!px-3.5 !py-2 !text-xs border border-transparent peer-checked:border-ink peer-checked:ring-2 peer-checked:ring-ink/10 transition" /></label>
                        @endforeach
                    </div>
                </div>
                <div class="rounded-2xl bg-paper p-5">
                    <p class="font-semibold flex items-center gap-2"><span class="material-symbols-outlined text-coral">auto_awesome</span>Ce que CAMINO a appris de toi</p>
                    @if(!empty($profile['top']))
                        <div class="mt-3 space-y-2">
                            @foreach($profile['top'] as $t)
                                <div class="flex items-center gap-3 text-sm"><span class="w-32 truncate">{{ $t['name'] }}</span><div class="flex-1 h-2 rounded-full bg-white overflow-hidden"><div class="h-full bg-teal rounded-full" style="width: {{ (int) round($t['weight'] * 100) }}%"></div></div><span class="text-xs text-ink-muted w-10 text-right">{{ (int) round($t['weight'] * 100) }} %</span></div>
                            @endforeach
                        </div>
                        <p class="mt-3 text-xs text-ink-muted">Calculé à partir de {{ $profile['signals']['favorites'] }} favoris, {{ $profile['signals']['reviews'] }} avis et {{ $profile['signals']['itineraries'] }} parcours.</p>
                    @else
                        <p class="mt-2 text-sm text-ink-muted">Pas encore assez de signaux. Ajoute des favoris, laisse des avis, génère des parcours : le profil s'affine tout seul.</p>
                    @endif
                </div>
                <button type="submit" class="btn btn-md btn-primary"><span class="material-symbols-outlined" style="font-size:18px">save</span>Enregistrer mes goûts</button>
            </div>
        </form>

        {{-- Activité --}}
        <div x-show="tab === 'activite'" x-cloak class="grid md:grid-cols-2 gap-4 mt-4">
            <div class="card p-6">
                <div class="flex items-center justify-between mb-3"><p class="eyebrow">Derniers parcours</p><a href="{{ route('itineraries.index') }}" class="text-xs font-semibold hover:text-coral">Tout voir</a></div>
                <div class="space-y-2">
                    @forelse($recentItineraries as $it)
                        <a href="{{ route('itineraries.show', $it) }}" class="flex items-center gap-3 rounded-2xl p-2 hover:bg-paper"><span class="h-9 w-9 rounded-2xl bg-coral-soft text-coral flex items-center justify-center"><span class="material-symbols-outlined" style="font-size:18px">route</span></span><div class="min-w-0"><p class="text-sm font-semibold truncate">{{ $it->name }}</p><p class="text-[11px] text-ink-muted">{{ $it->created_at->translatedFormat('j F') }} · {{ count($it->result_json['steps'] ?? []) }} étapes</p></div></a>
                    @empty
                        <p class="text-sm text-ink-muted">Aucun parcours pour l'instant. <a href="{{ route('itineraries.create') }}" class="underline font-semibold text-ink">Générer le premier</a>.</p>
                    @endforelse
                </div>
            </div>
            <div class="card p-6">
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
            <div class="card p-6 md:col-span-2">
                <p class="eyebrow mb-3">Badges</p>
                <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-6 gap-3 text-center text-xs">
                    @foreach([
                        ['Premier pas', 'flag', $stats['itineraries'] >= 1, '1 parcours généré'],
                        ['Marcheur', 'directions_walk', $stats['km'] >= 10, '10 km parcourus'],
                        ['Collectionneur', 'favorite', $stats['favorites'] >= 5, '5 favoris'],
                        ['Critique', 'rate_review', $stats['reviews'] >= 3, '3 avis publiés'],
                        ['Reporter', 'photo_camera', $stats['photos'] >= 1, '1 photo publiée'],
                        ['Vigie', 'campaign', $stats['alerts'] >= 1, '1 alerte signalée'],
                    ] as [$name, $icon, $earned, $hint])
                        <div class="rounded-2xl p-3 {{ $earned ? 'bg-sun-soft' : 'bg-paper opacity-60' }}" title="{{ $hint }}">
                            <span class="material-symbols-outlined {{ $earned ? 'filled text-amber-600' : 'text-ink-muted' }}" style="font-size:28px">{{ $icon }}</span>
                            <p class="font-semibold mt-1">{{ $name }}</p>
                            <p class="text-[10px] text-ink-muted">{{ $hint }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Compte --}}
        <div x-show="tab === 'compte'" x-cloak class="space-y-6 mt-4">
            <div class="card p-6 sm:p-8">
                <p class="eyebrow mb-1">Adresse e-mail</p>
                <form method="POST" action="{{ route('profile.update') }}" class="mt-2 grid sm:grid-cols-[1fr_auto] gap-3 items-end">
                    @csrf @method('PATCH')
                    <input type="hidden" name="name" value="{{ $user->name }}"><input type="hidden" name="bio" value="{{ $user->bio }}"><input type="hidden" name="city" value="{{ $user->city }}"><input type="hidden" name="mobility" value="{{ $user->mobility ?? 'walk' }}">
                    @foreach((array) ($user->interests ?? []) as $slug)<input type="hidden" name="interests[]" value="{{ $slug }}">@endforeach
                    <div><label class="label" for="email">E-mail</label><input id="email" type="email" name="email" value="{{ old('email', $user->email) }}" required class="field"><x-input-error :messages="$errors->get('email')" class="mt-1" /></div>
                    <button class="btn btn-md btn-ink">Mettre à jour</button>
                </form>
            </div>
            <div class="card p-6 sm:p-8">@include('profile.partials.update-password-form')</div>
            <div class="card p-6 sm:p-8 border-coral/20">@include('profile.partials.delete-user-form')</div>
        </div>
    </section>
</x-app-layout>
