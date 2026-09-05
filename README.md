# CAMINO — GPS culturel intelligent

Application web de découverte culturelle en Île-de-France, pensée comme un « Waze de la culture » :
une carte vivante de près de 6 000 lieux (musées, monuments, parcs, scènes, galeries, restaurants,
événements), des alertes communautaires en temps réel, et un **générateur de parcours** qui calcule
de vrais trajets à pied ou à vélo selon le temps, le budget, les envies et la météo.

Site : https://camino-u0eo.onrender.com

## Fonctionnalités

- **Carte interactive** : filtres par catégorie, budget et événements, recherche, géolocalisation,
  fiches avec photos, liste de la zone visible, alertes façon Waze (événement gratuit, affluence,
  fermeture, bon plan) avec expiration automatique.
- **Générateur de parcours** : sélection des lieux par centres d'intérêt, profil culturel et météo
  (lieux couverts s'il pleut), ordre optimisé et trajets réels sur le réseau de rues (Valhalla /
  OpenStreetMap), horaires d'arrivée à chaque étape, tracé sur carte, export Google Maps.
- **Communauté** : avis, favoris, photos partagées (modérées), proposition de nouveaux lieux,
  signalements d'erreurs.
- **Profil culturel** : recommandations déduites des favoris, avis et parcours réalisés.
- **Espace modération** pour les administrateurs (`users.is_admin`).

## Stack

- Laravel 11 · PHP 8.4 · Breeze · Blade · Tailwind CSS · Alpine.js · Leaflet (embarqué via Vite)
- Postgres (Neon) en production, SQLite en test
- Services externes gratuits : Valhalla (routage), Open-Meteo (météo), Wikidata / Wikimedia Commons /
  Wikipedia (photos libres), DATAtourisme (données touristiques), OpenStreetMap France (fond de carte)
- Docker (nginx + php-fpm) déployé sur Render (`render.yaml`)

## Données

```bash
# Import / mise à jour DATAtourisme (upsert par external_id, ~1 min) : classification par type,
# lieux hors sujet masqués, tarifs, dates d'événements, images du flux
php artisan camino:ingest-datatourisme --file=storage/app/datatourisme/idf-poi.json.zip

# Photos libres pour les lieux sans image (Wikidata → Commons, photos géolocalisées Commons, Wikipedia)
php artisan camino:enrich-poi-media --limit=500 --skip-overpass --categories=musee,monument
```

Ces commandes s'exécutent depuis un poste de développement contre la base de production
(Render gratuit n'a pas de shell) : exporter les variables `DB_*` avant de les lancer.

## Installation locale

```bash
composer install
npm install && npm run build
cp .env.example .env && php artisan key:generate
php artisan migrate --seed
php artisan serve
```

Tests : `DB_CONNECTION=sqlite DB_DATABASE=:memory: php vendor/bin/phpunit` (35 tests ; les appels
externes sont simulés indisponibles pour vérifier les replis).

Pour promouvoir un administrateur : `php artisan tinker` puis
`User::where('email', 'x@y.z')->first()->forceFill(['is_admin' => true])->save();`

## API interne

| Route | Description |
|---|---|
| `GET /api/v1/pois` | Lieux visibles. Filtres : `bbox`, `category_slugs`, `q`, `free=1`, `price_max=1..3`, `events=1`, `tags`, `limit` |
| `GET /api/v1/poi/{id}` | Détail d'un lieu |
| `GET /api/v1/alerts?bbox=` | Alertes communautaires actives |
| `GET /api/v1/weather?lat&lng` | Météo actuelle, horaire et sur 3 jours |
| `POST /api/v1/itineraries/generate` | Parcours : `time_budget_min`, `budget_eur`, `mode` (walk/bike), `start_lat/lng`, `radius_km`, `interests`, `free`, `starts_at` |

## Déploiement

`render.yaml` décrit le service (Docker, plan gratuit). Variables secrètes à renseigner dans Render :
`APP_KEY`, `APP_URL`, `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`.
Au démarrage, `docker/start.sh` exécute les migrations et le seed des catégories.
