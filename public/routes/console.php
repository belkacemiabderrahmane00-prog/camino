<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

// Ingestion DATAtourisme : à exécuter en cron (ex. toutes les nuits)
Schedule::command('camino:ingest-datatourisme')->daily()->at('02:00');
