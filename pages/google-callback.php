<?php
session_start();

require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';

try {
    if (isset($_GET['code'])) {
        // Exchange code for token
        $clientId = '14679695374-2ouitfeqp4mso0h2vnu17avhhnqqe5ei.apps.googleusercontent.com';
        $clientSecret = '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';
        $redirectUri = 'http://localhost/pages/google-callback.php';
        
        $response = file_get_contents('https://oauth2.googleapis.com/token', false, stream_context_create([
            'http' => [
                'method' => 'POST',
                'content' => http_build_query([
                    'code' => $_GET['code'],
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                    'redirect_uri' => $redirectUri,
                    'grant_type' => 'authorization_code'
                ])
            ]
        ]));
        
        $data = json_decode($response, true);
        
        if (!isset($data['error']) && isset($data['id_token'])) {
            // Decode JWT token (simplified - assumes valid)
            $parts = explode('.', $data['id_token']);
            $decoded = json_decode(base64_decode($parts[1]), true);
            
            $email = $decoded['email'] ?? null;
            $name = $decoded['name'] ?? 'User';
            $picture = $decoded['picture'] ?? null;

            if (!$email) {
                throw new Exception('No email in token');
            }

            // Check if user exists
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user) {
                // Login existing user
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['user_name'] = $user['username'];
                $_SESSION['role'] = json_decode($user['roles'], true)[0] ?? 'user';
                
                header("Location: " . site_url());
                exit;
            } else {
                // Create new user
                $username = preg_replace('/[^a-zA-Z0-9_-]/', '', str_replace(' ', '_', $name));
                $username = substr($username, 0, 20) ?: 'user_' . time();
                
                // Check if username exists
                $check = $pdo->prepare("SELECT id FROM users WHERE username = ?");
                $check->execute([$username]);
                if ($check->fetch()) {
                    $username .= '_' . rand(1000, 9999);
                }

                $stmt = $pdo->prepare("
                    INSERT INTO users (username, email, avatar, roles, password_hash)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $username,
                    $email,
                    $picture,
                    json_encode(['reader']),
                    password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT)
                ]);

                $_SESSION['user_id'] = $pdo->lastInsertId();
                $_SESSION['username'] = $username;
                $_SESSION['user_name'] = $username;
                $_SESSION['role'] = 'user';

                header("Location: " . site_url());
                exit;
            }
        } else {
            throw new Exception('Google token exchange failed');
        }
    } else {
        throw new Exception('Missing code');
    }
} catch (Exception $e) {
    header("Location: " . site_url('/pages/login.php?error=Google login failed: ' . urlencode($e->getMessage())));
    exit;
}

