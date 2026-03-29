<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    // ElevenLabs TTS provider
    'elevenlabs' => [
        'api_key' => env('ELEVENLABS_API_KEY', ''),
        'api_url' => env('ELEVENLABS_API_URL', 'https://api.elevenlabs.io/v1'),
        'timeout' => env('ELEVENLABS_TIMEOUT', 30),
    ],

    // Shared secret for inbound sync requests from Kashtre
    'calling_service' => [
        'sync_secret' => env('CALLING_SERVICE_SYNC_SECRET', ''),
    ],

];
