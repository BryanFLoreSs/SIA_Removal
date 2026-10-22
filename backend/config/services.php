<?php

return [
    'google_weather' => ['key' => env('WEATHER_API_KEY')],
    'resend' => [
        'key' => env('RESEND_API_KEY'),
        'from' => env('RESEND_FROM', 'CityPulse <onboarding@resend.dev>'),
    ],
    'mfa_mailer' => [
        'host' => env('PHPMAILER_HOST', env('MAIL_HOST', '127.0.0.1')),
        'port' => env('PHPMAILER_PORT', env('MAIL_PORT', 587)),
        'username' => env('PHPMAILER_USERNAME', env('MAIL_USERNAME')),
        'password' => env('PHPMAILER_PASSWORD', env('MAIL_PASSWORD')),
        'encryption' => env('PHPMAILER_ENCRYPTION', env('MAIL_ENCRYPTION', 'tls')),
        'timeout' => env('PHPMAILER_TIMEOUT', 8),
        'from' => [
            'address' => env('PHPMAILER_FROM_ADDRESS', env('MAIL_FROM_ADDRESS', 'hello@example.com')),
            'name' => env('PHPMAILER_FROM_NAME', env('MAIL_FROM_NAME', env('APP_NAME', 'CityPulse'))),
        ],
    ],
];
