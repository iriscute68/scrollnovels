<?php
// admin/login.php
session_start();

// Use main site database connection
require_once __DIR__ . '/../config/db.php';

$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $u = $_POST['username'] ?? '';
  $p = $_POST['password'] ?? '';
  
  // Validate input
  if (empty($u) || empty($p)) {
    $err = 'Username and password are required';
  } else {
    // Try users table for admin/superadmin roles
    try {
      $stmt = $pdo->prepare("SELECT id, username, email, password, role FROM users WHERE (username = ? OR email = ?) LIMIT 1");
      $stmt->execute([$u, $u]);
      $user = $stmt->fetch(PDO::FETCH_ASSOC);
      
      if ($user && !empty($user['password'])) {
        // Check password
        if (password_verify($p, $user['password'])) {
          // Verify user is admin, superadmin, or moderator
          if (in_array($user['role'] ?? '', ['admin', 'super_admin', 'moderator'])) {
            $_SESSION['admin_id'] = $user['id'];
            $_SESSION['admin_username'] = $user['username'];
            $_SESSION['admin_role'] = $user['role'];
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['username'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['roles'] = json_encode([$user['role']]);
            $_SESSION['logged_in'] = true;
            
            // Redirect to dashboard
            header('Location: dashboard.php?tab=overview', true, 302);
            exit;
          } else {
            $err = 'Access denied: Admin privileges required';
          }
        } else {
          $err = 'Invalid password';
        }
      } else {
        $err = 'User not found';
      }
    } catch (Exception $e) {
      error_log('Admin login error: ' . $e->getMessage());
      $err = 'Server error: ' . $e->getMessage();
    }
  }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin Login</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    body {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    }
  </style>
</head>
<body class="flex items-center justify-center min-h-screen">
  <div class="w-full max-w-md p-8 bg-white rounded-lg shadow-lg">
    <h2 class="text-3xl font-bold mb-2 text-gray-900">Admin Login</h2>
    <p class="text-gray-600 mb-6">Scroll Novels Administration</p>
    
    <?php if($err):?>
      <div class="p-3 bg-red-100 border border-red-400 text-red-700 rounded mb-4">
        <?= htmlspecialchars($err) ?>
      </div>
    <?php endif;?>
    
    <form method="post">
      <div class="mb-4">
        <label class="block text-sm font-semibold text-gray-700 mb-2">Username</label>
        <input name="username" type="text" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-purple-500" placeholder="Enter username" />
      </div>
      
      <div class="mb-6">
        <label class="block text-sm font-semibold text-gray-700 mb-2">Password</label>
        <input name="password" type="password" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-purple-500" placeholder="Enter password" />
      </div>
      
      <button type="submit" class="w-full py-2 px-4 bg-purple-600 text-white font-semibold rounded-lg hover:bg-purple-700 transition">
        Login
      </button>
    </form>
  </div>
</body>
</html>
