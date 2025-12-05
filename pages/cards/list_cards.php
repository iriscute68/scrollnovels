<?php
/**
 * List Saved Cards - Display user's saved cards
 */
require_once __DIR__ . '/config.php';

// Fetch user's saved cards
try {
    $stmt = $pdo->prepare("
    SELECT id, card_last_4, card_brand, card_exp_month, card_exp_year, bank, created_at, is_default
    FROM saved_cards
    WHERE user_id = ?
    ORDER BY is_default DESC, created_at DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $cards = $stmt->fetchAll();
} catch (Exception $e) {
    $cards = [];
}
?>
<?php
    $page_title = 'My Cards - Scroll Novels';
    $page_head = '';
    require_once __DIR__ . '/../../includes/header.php';
?>
    <div class="max-w-3xl mx-auto p-6">
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center justify-between mb-6">
                <h1 class="text-3xl font-bold text-emerald-600">My Payment Cards</h1>
                <a href="<?= site_url('/pages/cards/add_card.php') ?>" class="px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 transition">+ Add Card</a>
            </div>

            <?php if (empty($cards)): ?>
                <div class="text-center py-12">
                    <p class="text-gray-600 mb-4">You haven't saved any cards yet.</p>
                    <a href="<?= site_url('/pages/cards/add_card.php') ?>" class="text-emerald-600 hover:underline font-semibold">Add your first card</a>
                </div>
            <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($cards as $card): ?>
                        <div class="border border-gray-200 rounded-lg p-4 flex items-center justify-between hover:shadow-md transition">
                            <div class="flex items-center gap-4">
                                <div class="text-3xl">
                                    <?php
                                    $brand = strtoupper($card['card_brand'] ?? 'CARD');
                                    echo match($brand) {
                                        'VISA' => '💳',
                                        'MASTERCARD' => '💳',
                                        'AMEX' => '💳',
                                        'VERVE' => '💳',
                                        default => '💳'
                                    };
                                    ?>
                                </div>
                                <div>
                                    <div class="font-semibold">
                                        <?= htmlspecialchars($brand) ?> •••• <?= htmlspecialchars($card['card_last_4']) ?>
                                    </div>
                                    <div class="text-sm text-gray-600">
                                        <?= htmlspecialchars($card['bank'] ?? 'Unknown bank') ?>
                                        <?php if ($card['card_exp_month'] && $card['card_exp_year']): ?>
                                            | Expires <?= str_pad($card['card_exp_month'], 2, '0', STR_PAD_LEFT) ?>/<?= $card['card_exp_year'] ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-xs text-gray-500 mt-1">
                                        Added <?= date('M d, Y', strtotime($card['created_at'])) ?>
                                    </div>
                                </div>
                            </div>

                            <div class="flex items-center gap-2">
                                <?php if ($card['is_default']): ?>
                                    <span class="px-3 py-1 bg-green-100 text-green-700 rounded-full text-sm font-semibold">Default</span>
                                <?php else: ?>
                                    <button onclick="setDefault(<?= $card['id'] ?>)" class="px-3 py-1 text-sm text-emerald-600 hover:bg-emerald-50 rounded transition">Set Default</button>
                                <?php endif; ?>
                                <button onclick="deleteCard(<?= $card['id'] ?>)" class="px-3 py-1 text-sm text-red-600 hover:bg-red-50 rounded transition">Delete</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <hr class="my-6">

            <div>
                <a href="<?= site_url('/pages/profile.php') ?>" class="text-emerald-600 hover:underline">← Back to Profile</a>
            </div>
        </div>
    </div>

    <script>
    function deleteCard(cardId) {
        if (!confirm('Delete this card?')) return;
        
        const form = new FormData();
        form.append('id', cardId);
        form.append('_csrf', '<?php echo csrf_token(); ?>');

        fetch('<?= site_url('/pages/cards/delete_card.php') ?>', {
            method: 'POST',
            body: form
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                alert('Card deleted');
                location.reload();
            } else {
                alert('Error: ' + (data.message || 'Failed'));
            }
        })
        .catch(err => alert('Error: ' + err.message));
    }

    function setDefault(cardId) {
        const form = new FormData();
        form.append('id', cardId);
        form.append('action', 'set_default');
        form.append('_csrf', '<?php echo csrf_token(); ?>');

        fetch('<?= site_url('/pages/cards/update_card.php') ?>', {
            method: 'POST',
            body: form
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Error: ' + (data.message || 'Failed'));
            }
        })
        .catch(err => alert('Error: ' + err.message));
    }
    </script>
</body>
</html>
