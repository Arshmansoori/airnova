<?php
declare(strict_types=1);

/**
 * AirNova AQI-72 Prediction Engine Configuration Template
 * Copy this file to config.php and insert your valid API keys.
 */
return [
    'data_gov_api_key' => getenv('DATA_GOV_API_KEY') ?: 'YOUR_DATA_GOV_API_KEY',
    'firms_map_key' => getenv('FIRMS_MAP_KEY') ?: 'YOUR_NASA_FIRMS_MAP_KEY',
    'timezone' => 'Asia/Kolkata',
    'cache_ttl' => [
        'open_meteo' => 900,
        'cpcb' => 900,
        'firms' => 1800,
    ],
    'http_timeout' => 22,
];
