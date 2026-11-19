<?php

$methods = array_values(array_filter(array_map(
    'trim',
    explode(',', (string) env('DOKU_PAYMENT_METHODS', ''))
)));

return [
    'client_id' => env('DOKU_CLIENT_ID'),
    'secret_key' => env('DOKU_SECRET_KEY'),

    // Sandbox by default; production is https://api.doku.com.
    'base_url' => rtrim((string) env('DOKU_BASE_URL', 'https://api-sandbox.doku.com'), '/'),

    // How long a buyer has to pay, in minutes. 60 is DOKU's own default.
    'payment_due_minutes' => (int) env('DOKU_PAYMENT_DUE_MINUTES', 60),

    /*
     * Methods offered on the payment page, as DOKU names them
     * (QRIS, EMONEY_SHOPEEPAY, VIRTUAL_ACCOUNT_BCA, PEER_TO_PEER_KREDIVO).
     * Empty means every method active on the DOKU account.
     */
    'payment_methods' => $methods,

    // DOKU signs each notification against this path, so it must match the route.
    'notification_path' => '/api/payments/doku/notification',

    // Where "Kembali ke merchant" on DOKU's result page sends the buyer.
    'return_url' => env('DOKU_RETURN_URL'),
];
