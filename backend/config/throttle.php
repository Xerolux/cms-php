<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting Configuration
    |--------------------------------------------------------------------------
    |
    | Konfiguration für API Rate Limiting
    |
    */

    // Standard API Rate Limit (100 Requests pro Minute)
    'api' => 100,

    // Auth Endpoints (striktere Limits)
    'login' => '5,1', // 5 Versuche pro Minute
    'register' => '3,1', // 3 Registrierungen pro Minute

    // Upload Endpoints
    'upload' => '20,1', // 20 Uploads pro Minute
    'download' => '100,1', // 100 Downloads pro Minute

    // Public Endpoints
    'public' => '60,1', // 60 Requests pro Minute

    // Burst Limit (kurzzeitige Spitzen)
    'burst' => '10,1', // 10 Requests pro Sekunde

];
