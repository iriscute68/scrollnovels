<?php
// config/ads.php - Ads and monetization configuration

return [
    // Patreon membership URL - users redirected here to complete payment
    'patreon_url' => env('PATREON_MEMBERSHIP_URL', 'https://www.patreon.com/c/zakielvtuber/membership'),

    // Ad packages: views => cost mapping
    'packages' => [
        '100k' => [
            'views' => 100000,
            'amount' => 10,
            'label' => '$10 – 100,000 views'
        ],
        '200k' => [
            'views' => 200000,
            'amount' => 20,
            'label' => '$20 – 200,000 views'
        ],
        '300k' => [
            'views' => 300000,
            'amount' => 30,
            'label' => '$30 – 300,000 views'
        ],
        '500k' => [
            'views' => 500000,
            'amount' => 50,
            'label' => '$50 – 500,000 views'
        ],
        '1000k' => [
            'views' => 1000000,
            'amount' => 100,
            'label' => '$100 – 1,000,000 views'
        ]
    ],

    // Boost level calculation: package_views / boost_divisor
    'boost_divisor' => 100000,
    'max_boost_level' => 10,

    // Discord webhook for admin notifications
    'discord_webhook' => env('DISCORD_WEBHOOK_URL'),

    // File upload settings
    'upload_dir' => 'ad_proofs',
    'max_upload_size' => 10240, // 10MB in KB
];
