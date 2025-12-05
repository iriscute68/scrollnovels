<?php
// premium.php
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/db.php';
requireLogin();

$user_coins = $pdo->query("SELECT coins FROM users WHERE id={$_SESSION['user_id']}")->fetchColumn();
?>

<?php
    $page_title = 'Premium - Scroll Novels';
    $page_head = '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">';
    require_once __DIR__ . '/../includes/header.php';
?>

<div class="container mt-4">
    <h2>Premium Content</h2>
    <p>You have <strong><?= $user_coins ?> coins</strong></p>

    <div class="row">
        <div class="col-md-4">
            <div class="card">
                <div class="card-body text-center">
                    <h5>100 Coins</h5>
                    <p>$9.99</p>
                    <form action="<?= rtrim(SITE_URL, '/') ?>/api/buy-coins.php" method="POST">
                        <input type="hidden" name="pack" value="100">
                        <script src="https://js.stripe.com/v3/"></script>
                        <button type="submit" class="btn btn-success">Buy</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    </div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<script src="https://js.stripe.com/v3/"></script>
</body></html>
