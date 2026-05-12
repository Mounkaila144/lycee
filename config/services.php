<?php

return [
    // Add your third-party services configuration here

    'cinetpay' => [
        /*
         * Identifiants CinetPay (Story Parent 06).
         *
         * Voir https://docs.cinetpay.com/api/1.0-fr/ pour l'obtention :
         *  - api_key  : clé d'API publique
         *  - site_id  : identifiant numérique du marchand
         *  - secret   : secret HMAC utilisé pour signer les webhooks
         *
         * `base_url` peut être surchargée en test pour pointer un mock.
         */
        'api_key' => env('CINETPAY_API_KEY'),
        'site_id' => env('CINETPAY_SITE_ID'),
        'secret' => env('CINETPAY_SECRET'),
        'base_url' => env('CINETPAY_BASE_URL', 'https://api-checkout.cinetpay.com'),
        'currency' => env('CINETPAY_CURRENCY', 'XOF'),
        'return_url' => env('CINETPAY_RETURN_URL', 'https://app.lycee.local/payment/return'),
        'notify_url' => env('CINETPAY_NOTIFY_URL', 'https://app.lycee.local/api/webhooks/cinetpay'),
    ],
];
