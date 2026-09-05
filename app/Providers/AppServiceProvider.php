<?php

namespace App\Providers;

use App\Database\PgsqlConnection;
use App\Models\User;
use Illuminate\Database\Connection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
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
    }
}
