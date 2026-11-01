<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
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

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // SSO de la FEN. Authority = base_url + /realms/ + realms
    'keycloak' => [
        'base_url' => env('KEYCLOAK_BASE_URL'),
        'realms' => env('KEYCLOAK_REALM'),
        'client_id' => env('KEYCLOAK_CLIENT_ID'),
        'client_secret' => env('KEYCLOAK_CLIENT_SECRET'),
        'redirect' => env('KEYCLOAK_REDIRECT_URI', '/signin-oidc'),
        'post_logout_redirect' => env('KEYCLOAK_POST_LOGOUT_REDIRECT_URI'), // vacío = Keycloak no vuelve al sistema
    ],

    // API v2 de Webdox CLM (contratos, flujos y firma). Prueba: php artisan webdox:probar
    'webdox' => [
        'base_url' => env('WEBDOX_BASE_URL', 'https://api.webdoxclm.com/api/v2'),
        'username' => env('WEBDOX_USERNAME'),
        'password' => env('WEBDOX_PASSWORD'),
        'customer' => env('WEBDOX_CUSTOMER'), // solo si el usuario está en varios ambientes
        // Solicitudes que se traen al módulo «Pagos a proveedores»
        'pagos' => [
            'formulario' => env('WEBDOX_PAGOS_FORMULARIO', 'Pago a proveedores'),
            // Cobros de la UDT a las áreas (varios formularios separados por «|»)
            'formulario_traspasos' => env('WEBDOX_TRASPASOS_FORMULARIO', 'Traspaso (Cobros DEA)'),
            'unidad' => env('WEBDOX_PAGOS_UNIDAD', 'Unidad de Desarrollo Tecnológico'),
            // Carpeta compartida (Google Drive) con los Excel «ID####_Certificado de Pago.xlsx»
            'certificados' => env('WEBDOX_CERTIFICADOS_DIR'),
        ],
    ],

];
