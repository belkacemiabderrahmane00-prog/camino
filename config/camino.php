<?php

return [
    /*
     * Routage réel (Valhalla, instance publique FOSSGIS). Profils : pedestrian, bicycle.
     * Aucune clé requise. Usage raisonnable : les résultats sont mis en cache.
     */
    'routing' => [
        'base_url' => env('CAMINO_ROUTING_URL', 'https://valhalla1.openstreetmap.de'),
        'timeout' => (int) env('CAMINO_ROUTING_TIMEOUT', 20),
        'cache_minutes' => (int) env('CAMINO_ROUTING_CACHE', 60 * 24 * 7),
    ],

    /*
     * Météo (Open-Meteo, sans clé).
     */
    'weather' => [
        'base_url' => env('CAMINO_WEATHER_URL', 'https://api.open-meteo.com/v1/forecast'),
        'timeout' => (int) env('CAMINO_WEATHER_TIMEOUT', 10),
        'cache_minutes' => (int) env('CAMINO_WEATHER_CACHE', 30),
    ],

    /*
     * Point de départ par défaut (centre de Paris, parvis de Notre-Dame).
     */
    'default_start' => [
        'lat' => 48.8530,
        'lng' => 2.3499,
        'label' => 'Centre de Paris',
    ],

    /*
     * Vitesses de repli (km/h) si le routage est indisponible.
     */
    'fallback_speed_kmh' => [
        'walk' => 4.0,
        'bike' => 13.0,
    ],

    /*
     * Transports en commun : API PRIM d'Île-de-France Mobilités (calculateur Navitia). Clé gratuite sur prim.iledefrance-mobilites.fr.
     */
    // Traduction automatique des descriptions de lieux (anglais, chinois) : DeepL si clé, sinon MyMemory (gratuit, sans clé).
    'translation' => [
        'enabled' => (bool) env('CAMINO_TRANSLATE_PLACES', true),
        'deepl_key' => env('CAMINO_DEEPL_KEY', ''),
        'email' => env('CAMINO_TRANSLATION_EMAIL', env('MAIL_FROM_ADDRESS', '')),
        'timeout' => (int) env('CAMINO_TRANSLATION_TIMEOUT', 8),
        'max_chars' => 4000,
    ],

    'history' => [
        'timeout' => (int) env('CAMINO_HISTORY_TIMEOUT', 8),
    ],

    'transit' => [
        'api_key' => env('CAMINO_PRIM_API_KEY', ''),
        'base_url' => env('CAMINO_PRIM_URL', 'https://prim.iledefrance-mobilites.fr/marketplace/v2/navitia'),
        'timeout' => (int) env('CAMINO_PRIM_TIMEOUT', 10),
        'cache_minutes' => (int) env('CAMINO_PRIM_CACHE', 60),
    ],

    'user_agent' => 'CAMINO/2.0 (+https://camino-u0eo.onrender.com)',
];
