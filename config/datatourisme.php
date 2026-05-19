<?php

return [
    'api_url' => rtrim(env('DATATOURISME_API_URL', 'https://api.datatourisme.fr'), '/'),
    'api_key' => env('DATATOURISME_API_KEY'),
    'zip_path' => env('DATATOURISME_ZIP_PATH'),
];
