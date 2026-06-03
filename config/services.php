<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Stripe, Mailgun, SparkPost and others. This file provides a sane
    | default location for this type of information, allowing packages
    | to have a conventional place to find your various credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
    ],

    'ses' => [
        'key' => env('SES_KEY'),
        'secret' => env('SES_SECRET'),
        'region' => 'us-east-1',
    ],

    'sparkpost' => [
        'secret' => env('SPARKPOST_SECRET'),
    ],

    'stripe' => [
        'model' => App\User::class,
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
    ],
    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],
    'pagadetodo' => [
        'mock' => env('PAGADETODO_MOCK', false),
        'urls' => [
            'generar_liga' => env('PAGADETODO_URL_GENERAR_LIGA', 'https://pagadetodo.mx/Pagadetodo/Service/GenerarLigaIndi'),
            'generar_domiciliacion' => env('PAGADETODO_URL_GENERAR_DOMICILIACION', 'https://pagadetodo.mx/Pagadetodo/Service/GenerarLigaDomiciliacionIndi'),
            'cancelar_domiciliacion' => env('PAGADETODO_URL_CANCELAR_DOMICILIACION', 'https://pagadetodo.mx/Pagadetodo/Service/CancelarDomiciliacionIndi'),
            'generar_spei' => env('PAGADETODO_URL_GENERAR_SPEI', 'https://pagadetodo.mx/Pagadetodo/Service/GenerarClabeIndi'),
            'generar_lector' => env('PAGADETODO_URL_GENERAR_LECTOR', 'https://pagadetodo.mx/Pagadetodo/Service/GenerarPagoLectorIndi'),
            'cancelar_lector' => env('PAGADETODO_URL_CANCELAR_LECTOR', 'https://pagadetodo.mx/Pagadetodo/Service/CancelarReferenciaLectorIndi'),
            'cargo_domiciliacion' => env('PAGADETODO_URL_CARGO_DOMICILIACION', 'https://pagadetodo.mx/Pagadetodo/Service/PagarDomiciliacionIndi'),
        ],
        'integration' => [
            'id' => env('PAGADETODO_INTEGRATION_ID', ''),
            'business_id' => env('PAGADETODO_BUSINESS_ID', ''),
            'dom_id' => env('PAGADETODO_DOM_INTEGRATION_ID', ''),
            'dom_business_id' => env('PAGADETODO_DOM_BUSINESS_ID', ''),
            'dom_ba_id' => env('PAGADETODO_DOM_BA_INTEGRATION_ID', ''),
            'dom_ba_business_id' => env('PAGADETODO_DOM_BA_BUSINESS_ID', ''),
        ],
        'credentials' => [
            'user' => env('PAGADETODO_USER', ''),
            'password' => env('PAGADETODO_PASSWORD', ''),
            'dom_user' => env('PAGADETODO_DOM_USER', ''),
            'dom_password' => env('PAGADETODO_DOM_PASSWORD', ''),
            'dom_ba_user' => env('PAGADETODO_DOM_BA_USER', ''),
            'dom_ba_password' => env('PAGADETODO_DOM_BA_PASSWORD', ''),
            'sandbox_user' => env('PAGADETODO_SANDBOX_USER', ''),
            'sandbox_password' => env('PAGADETODO_SANDBOX_PASSWORD', ''),
        ],
    ],
];
