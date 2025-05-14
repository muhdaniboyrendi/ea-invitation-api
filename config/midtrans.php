<?php

return [
    'server_key' => env('MIDTRANS_SERVER_KEY', null),
    'client_key' => env('MIDTRANS_CLIENT_KEY', null),
    'is_production' => env('MIDTRANS_IS_PRODUCTION', false),
    'merchant_id' => env('MIDTRANS_MERCHANT_ID', null),
    'snap_url' => env('MIDTRANS_IS_PRODUCTION', false) ? 'https://app.midtrans.com/snap/snap.js' : 'https://app.sandbox.midtrans.com/snap/snap.js',
];