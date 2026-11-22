<?php

return [
    // Explicitly bypasses the installer/license gate; normal admin authentication still applies.
    'allow_unverified_admin' => env('DTLIVE_ALLOW_UNVERIFIED_ADMIN', false),
    // The API uses the same self-hosted opt-out, but keeps its own switch so
    // cached configuration remains reliable in production.
    'allow_unverified_api' => env('DTLIVE_ALLOW_UNVERIFIED_API', false),
    'api_token' => env('API_TOKEN', ''),
    'purchase_code' => env('PURCHASE_CODE'),
    'buyer_username' => env('BUYER_USERNAME'),
    'purchase_status' => env('PURCHASE_STATUS', 0),
];
