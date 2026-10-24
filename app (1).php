<?php

return [
    'name' => env('APP_NAME', 'نظام توريد مواد البناء'),
    'env' => env('APP_ENV', 'production'),
    'debug' => (bool) env('APP_DEBUG', false),
    'url' => env('APP_URL', 'http://localhost'),
    'timezone' => env('APP_TIMEZONE', 'UTC'),
    'currency_symbol_url' => env('APP_CURRENCY_SYMBOL_URL', 'https://www.sama.gov.sa/ar-sa/Currency/Documents/Saudi_Riyal_Symbol-1.png'),
    'locale' => env('APP_LOCALE', 'ar'),
    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'ar'),
    'faker_locale' => env('APP_FAKER_LOCALE', 'ar_SA'),
    'cipher' => 'AES-256-CBC',
    'key' => env('APP_KEY'),
    'previous_keys' => array_filter(explode(',', env('APP_PREVIOUS_KEYS', ''))),
    'maintenance' => [
        'driver' => env('APP_MAINTENANCE_DRIVER', 'file'),
        'store' => env('APP_MAINTENANCE_STORE', 'database'),
    ],
];
