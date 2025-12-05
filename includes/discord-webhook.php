<?php
// includes/discord-webhook.php - Discord webhook notifications for admin alerts

/**
 * Send Discord webhook notification for ad proof uploaded
 */
function notifyDiscordAdProof($ad, $user, $book, $imageUrl = null)
{
    $webhook = $_ENV['DISCORD_WEBHOOK_URL'] ?? getenv('DISCORD_WEBHOOK_URL');
    
    if (empty($webhook)) {
        error_log('Discord webhook URL not configured');
        return false;
    }

    $embed = [
        'title' => '📸 New Ad Proof Uploaded',
        'description' => "User: **{$user['username']}** ({$user['email']})\nBook: **{$book['title']}**\nAd ID: #{$ad['id']}\nAmount: **\${$ad['amount']}**",
        'color' => 5814783,
        'fields' => [
            [
                'name' => 'Package',
                'value' => number_format($ad['package_views']) . ' views',
                'inline' => true
            ],
            [
                'name' => 'Status',
                'value' => ucfirst($ad['payment_status']),
                'inline' => true
            ],
            [
                'name' => 'Next Steps',
                'value' => 'Review in admin dashboard and approve/reject',
                'inline' => false
            ]
        ],
        'timestamp' => date('c')
    ];

    if (!empty($imageUrl)) {
        $embed['thumbnail'] = ['url' => $imageUrl];
        $embed['image'] = ['url' => $imageUrl];
    }

    $payload = [
        'username' => 'Scroll Novels Ad System',
        'avatar_url' => 'https://emoji.gg/assets/emoji/4034_books.png',
        'embeds' => [$embed]
    ];

    $ch = curl_init($webhook);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode < 200 || $httpCode >= 300) {
        error_log("Discord webhook failed (HTTP $httpCode): $result");
        return false;
    }

    return true;
}

/**
 * Send Discord notification when ad is approved
 */
function notifyDiscordAdApproved($ad, $user, $book, $boostLevel, $note = '')
{
    $webhook = $_ENV['DISCORD_WEBHOOK_URL'] ?? getenv('DISCORD_WEBHOOK_URL');
    
    if (empty($webhook)) return false;

    $embed = [
        'title' => '✅ Ad Approved & Book Boosted',
        'description' => "Book: **{$book['title']}** has been boosted!\nUser: **{$user['username']}**",
        'color' => 3066993,
        'fields' => [
            [
                'name' => 'Boost Level',
                'value' => $boostLevel . '/10',
                'inline' => true
            ],
            [
                'name' => 'Amount Paid',
                'value' => '$' . $ad['amount'],
                'inline' => true
            ]
        ],
        'timestamp' => date('c')
    ];

    if (!empty($note)) {
        $embed['fields'][] = [
            'name' => 'Admin Note',
            'value' => $note,
            'inline' => false
        ];
    }

    $payload = [
        'username' => 'Scroll Novels Ad System',
        'content' => '✅ Ad Approved!',
        'embeds' => [$embed]
    ];

    $ch = curl_init($webhook);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    
    $result = curl_exec($ch);
    curl_close($ch);

    return true;
}

/**
 * Send Discord notification when ad is rejected
 */
function notifyDiscordAdRejected($ad, $user, $book, $reason = '')
{
    $webhook = $_ENV['DISCORD_WEBHOOK_URL'] ?? getenv('DISCORD_WEBHOOK_URL');
    
    if (empty($webhook)) return false;

    $embed = [
        'title' => '❌ Ad Rejected',
        'description' => "Book: **{$book['title']}**\nUser: **{$user['username']}**",
        'color' => 15158332,
        'fields' => [
            [
                'name' => 'Amount',
                'value' => '$' . $ad['amount'],
                'inline' => true
            ]
        ],
        'timestamp' => date('c')
    ];

    if (!empty($reason)) {
        $embed['fields'][] = [
            'name' => 'Reason',
            'value' => $reason,
            'inline' => false
        ];
    }

    $payload = [
        'username' => 'Scroll Novels Ad System',
        'content' => '❌ Ad Rejected',
        'embeds' => [$embed]
    ];

    $ch = curl_init($webhook);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    
    curl_exec($ch);
    curl_close($ch);

    return true;
}
