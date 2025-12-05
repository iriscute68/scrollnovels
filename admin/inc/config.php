<?php
// admin/inc/config.php
return [
  'db' => [
    'host' => 'localhost',
    'name' => 'scroll_novels',
    'user' => 'root',
    'pass' => '',
    'charset' => 'utf8mb4'
  ],
  'paystack' => [
    'secret' => 'sk_live_YOUR_SECRET_KEY_HERE',
    'public' => 'pk_live_YOUR_PUBLIC_KEY_HERE',
    'callback' => 'https://scrollnovels.com/admin/paystack_callback.php'
  ],
  'site' => [
    'name' => 'Scroll Novels',
    'url' => 'https://scrollnovels.com'
  ]
];
?>
