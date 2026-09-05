<x-app-layout title="Proposer un lieu">
    <section class="max-w-4xl mx-auto px-4 sm:px-6 pt-8 sm:pt-12" x-data="{ lat: {{ old('lat', 'null') }}, lng: {{ old('lng', 'null') }}, map: null, marker: null, free: {{ old('is_free') ? 'true' : 'false' }},
        init() {
            const C = window.Camino; this.map = L.map(this.$refs.map, { zoomControl: false }).setView([this.lat || 48.8566, this.lng || 2.3522], this.lat ? 15 : 12);
            C.tileLayer().addTo(this.map); L.control.zoom({ position: 'bottomright' }).addTo(this.map);
            if (this.lat) this.place(this.lat, this.lng);
            this.map.on('click', e => this.place(e.latlng.lat, e.latlng.lng));
        },
        place(lat, lng) { this.lat = +lat.toFixed(6); this.lng = +lng.toFixed(6); if (this.marker) this.marker.setLatLng([lat, lng]); else this.marker = L.marker([lat, lng], { draggable: true, icon: window.Camino.placeIcon(null, { size: 40 }) }).addTo(this.map).on('dragend', e => this.place(e.target.getLatLng().lat, e.target.getLatLng().lng)); },
        async locate() { try { const p = await window.Camino.locate(); this.map.setView([p.lat, p.lng], 16); this.place(p.lat, p.lng); } catch (e) { alert('Position indisponible.'); } }
    }">
        <div class="mb-6">
            <p class="eyebrow mb-1.5">Communauté</p>
            <h1 class="display text-4xl">Proposer un lieu</h1>
            <p class="mt-2 text-sm text-ink-muted max-w-2xl">Un atelier d'artisan, une galerie associative, une fresque, une librairie culturelle… Ajoute ce que la carte ne connaît pas encore. L'équipe valide avant publication.</p>
        </div>

        <form method="POST" action="{{ route('community.propose.store') }}" class="grid grid-cols-1 lg:grid-cols-[1fr_1fr] gap-6">
            @csrf
            <div class="card p-5 sm:p-6 space-y-4">
                <div><label class="label" for="title">Nom du lieu</label><input id="title" name="title" value="{{ old('title') }}" required maxlength="120" class="field" placeholder="Ex. Atelier des Lumières du Marais"></div>
                <div><label class="label" for="category_id">Catégorie</label>
                    <select id="category_id" name="category_id" class="field" required>
                        @foreach($categories as $c)<option value="{{ $c->id }}" @selected(old('category_id') == $c->id)>{{ $c->name }}</option>@endforeach
                    </select>
                </div>
                <div><label class="label" for="description">Description</label><textarea id="description" name="description" rows="5" required minlength="20" maxlength="2000" class="field" placeholder="Ce qu'on y trouve, pourquoi ça vaut le détour, comment y accéder…">{{ old('description') }}</textarea></div>
                <div><label class="label" for="address">Adresse</label><input id="address" name="address" value="{{ old('address') }}" maxlength="255" class="field" placeholder="12 rue Exemple, 75011 Paris"></div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <p class="label">Tarif</p>
                        <label class="inline-flex items-center gap-2 text-sm cursor-pointer"><input type="checkbox" name="is_free" value="1" x-model="free" class="rounded border-ink/20 text-coral focus:ring-coral">Gratuit</label>
                        <select name="price_level" class="field mt-2" x-show="!free">
                            <option value="">Niveau de prix</option><option value="1" @selected(old('price_level') == 1)>€ (moins de 6 €)</option><option value="2" @selected(old('price_level') == 2)>€€ (6 à 15 €)</option><option value="3" @selected(old('price_level') == 3)>€€€ (plus de 15 €)</option>
                        </select>
                    </div>
                    <div><label class="label" for="visit_duration_min">Durée de visite (min)</label><input id="visit_duration_min" type="number" name="visit_duration_min" min="10" max="480" value="{{ old('visit_duration_min', 60) }}" class="field"></div>
                </div>
                <div><label class="label" for="tags">Mots-clés</label><input id="tags" name="tags" value="{{ old('tags') }}" class="field" placeholder="street art, atelier, gratuit le dimanche (séparés par des virgules)"></div>
            </div>
            <div class="space-y-4">
                <div class="card overflow-hidden">
                    <div class="flex items-center justify-between px-4 py-3 border-b border-ink/5">
                        <p class="text-sm font-semibold">Position sur la carte <span class="text-ink-muted font-normal">· clique pour placer</span></p>
                        <button type="button" @click="locate()" class="btn btn-sm btn-soft"><span class="material-symbols-outlined" style="font-size:16px">my_location</span>Ma position</button>
                    </div>
                    <div x-ref="map" class="h-72"></div>
                    <div class="px-4 py-2 text-xs text-ink-muted" x-text="lat ? `Latitude ${lat} · Longitude ${lng}` : 'Aucune position choisie'"></div>
                    <input type="hidden" name="lat" :value="lat"><input type="hidden" name="lng" :value="lng">
                </div>
                <button type="submit" class="btn btn-lg btn-primary w-full" :disabled="!lat"><span class="material-symbols-outlined">send</span>Envoyer ma proposition</button>
                <p class="text-[11px] text-ink-muted text-center">En proposant un lieu, tu confirmes que les informations sont exactes et publiables.</p>
            </div>
        </form>
    </section>
</x-app-layout>
