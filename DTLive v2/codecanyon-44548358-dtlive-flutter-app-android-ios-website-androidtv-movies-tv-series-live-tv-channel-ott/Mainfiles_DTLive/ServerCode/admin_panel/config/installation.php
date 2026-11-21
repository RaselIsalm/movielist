<?php

return [
    // Explicitly bypasses the installer/license gate; normal admin authentication still applies.
    'allow_unverified_admin' => env('DTLIVE_ALLOW_UNVERIFIED_ADMIN', false),
    'purchase_code' => env('PURCHASE_CODE'),
    'buyer_username' => env('BUYER_USERNAME'),
    'purchase_status' => env('PURCHASE_STATUS', 0),
];
