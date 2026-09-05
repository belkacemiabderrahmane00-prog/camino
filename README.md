# CAMINO — GPS culturel intelligent

Application web de découverte culturelle en Île-de-France : carte interactive de plus de
6 000 lieux (musées, monuments, parcs, scènes, restaurants, événements), fiches détaillées,
favoris, avis, et **générateur de parcours à pied** selon le temps et le budget disponibles.

Site : https://camino-u0eo.onrender.com

## Stack

- Laravel 11 · PHP 8.4 · Breeze (auth)
- Blade · Tailwind CSS · Alpine.js · Leaflet (OpenStreetMap)
- Postgres (Neon) en production, SQLite/MySQL possibles en local
- Docker (nginx + php-fpm) déployé sur Render

## Données

Les lieux proviennent du flux ouvert [DATAtourisme](https://www.datatourisme.fr/) (Île-de-France,
JSON-LD). L'import classe chaque objet dans une catégorie CAMINO selon son type le plus précis,
masque les objets hors sujet (hôtels, équipements sportifs, boutiques…), lit les tarifs et les
images fournies avec leur licence.

```bash
# Import / mise à jour (upsert par external_id, ~1 min)
php artisan camino:ingest-datatourisme --file=storage/app/datatourisme/idf-poi.json.zip

# Images libres (Wikidata / Wikimedia Commons / Wikipedia) pour les lieux sans image
php artisan camino:enrich-poi-media --limit=200
```

## Installation locale

```bash
composer install
npm install && npm run build
cp .env.example .env && php artisan key:generate
php artisan migrate --seed
php artisan serve
```

Tests : `php vendor/bin/phpunit` (SQLite en mémoire : `DB_CONNECTION=sqlite DB_DATABASE=:memory:`).

## API interne

| Route | Description |
|---|---|
| `GET /api/v1/pois` | Lieux visibles. Filtres : `bbox`, `category_slugs`, `q`, `free=1`, `price_max=1..3`, `tags`, `limit` |
| `GET /api/v1/poi/{id}` | Détail d'un lieu |
| `POST /api/v1/itineraries/generate` | Parcours à pied : `time_budget_min`, `bbox`, `category_slugs`, `free`, `start_lat/lng` |

## Déploiement

`render.yaml` décrit le service (Docker, plan gratuit). Variables secrètes à renseigner dans Render :
`APP_KEY`, `APP_URL`, `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`.
Au démarrage, `docker/start.sh` exécute les migrations et le seed des catégories.
