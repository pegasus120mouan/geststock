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

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'external_auth' => [
        'login_url' => env('EXTERNAL_AUTH_LOGIN_URL', 'https://api.objetombrepegasus.online/api/camions/login.php'),
        'chef_login_url' => env('EXTERNAL_AUTH_CHEF_LOGIN_URL', 'https://api.objetombrepegasus.online/api/camions/login_chef_equipe.php'),
        'mes_camions_url' => env('EXTERNAL_AUTH_MES_CAMIONS_URL', 'https://api.objetombrepegasus.online/api/camions/mes_camions.php'),
        'mes_tickets_url' => env('EXTERNAL_AUTH_MES_TICKETS_URL', 'https://api.objetombrepegasus.online/api/camions/mes_tickets.php'),
        'mes_depenses_url' => env('EXTERNAL_AUTH_MES_DEPENSES_URL', 'https://api.objetombrepegasus.online/api/camions/mes_depenses.php'),
        'mes_ponts_url' => env('EXTERNAL_AUTH_MES_PONTS_URL', 'https://api.objetombrepegasus.online/api/camions/mes_ponts.php'),
        'mes_agents_url' => env('EXTERNAL_AUTH_MES_AGENTS_URL', 'https://api.objetombrepegasus.online/api/camions/mes_agents.php'),
        'mes_financements_url' => env('EXTERNAL_AUTH_MES_FINANCEMENTS_URL', 'https://api.objetombrepegasus.online/api/camions/mes_financements.php'),
        'mes_usines_url' => env('EXTERNAL_AUTH_MES_USINES_URL', 'https://api.objetombrepegasus.online/api/camions/mes_usines.php'),
        'mes_vehicules_url' => env('EXTERNAL_AUTH_MES_VEHICULES_URL', 'https://api.objetombrepegasus.online/api/camions/mes_vehicules.php'),
        'solde_chef_equipe_url' => env('EXTERNAL_AUTH_SOLDE_CHEF_EQUIPE_URL', 'https://api.objetombrepegasus.online/api/camions/solde_chef_equipe.php'),
        'default_chef_equipe_token' => env('CHEF_EQUIPE_TOKEN', ''),
        'timeout' => (int) env('EXTERNAL_AUTH_TIMEOUT', 10),
        // auto = DB si chef_equipe/agents détectés, sinon API | api = toujours HTTP | database = toujours DB
        'camions_data_source' => env('CAMIONS_DATA_SOURCE', 'auto'),
    ],

];
