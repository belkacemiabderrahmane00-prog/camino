<?php

namespace App\Providers;

use App\Database\PgsqlConnection;
use App\Models\User;
use App\Services\WeatherService;
use Illuminate\Database\Connection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Postgres derrière le pooler Neon : requêtes préparées émulées + booléens liés correctement.
        Connection::resolverFor('pgsql', function ($connection, $database, $prefix, $config) {
            return new PgsqlConnection($connection, $database, $prefix, $config);
        });
    }

    public function boot(): void
    {
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        Carbon::setLocale(config('app.locale', 'fr'));

        Gate::define('admin', fn (User $user) => (bool) $user->is_admin);

        // Météo disponible dans toutes les pages (pastille d'en-tête + feuille météo), mise en cache 30 min.
        View::composer(['components.app-layout', 'components.weather-sheet'], function ($view) {
            $start = config('camino.default_start');
            $weather = app(WeatherService::class);
            $forecast = $weather->forecast((float) $start['lat'], (float) $start['lng']);
            $view->with('globalForecast', $forecast)->with('globalAdvice', $weather->advice($forecast));
        });
    }
}
