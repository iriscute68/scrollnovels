<?php
// pages/contests.php - Writing contests listing
require_once dirname(__FILE__) . '/../includes/auth.php';
include dirname(__FILE__) . '/../includes/header.php';

$contests = [
    ['id' => 1, 'title' => 'Monthly Writing Sprint', 'prize' => 500, 'end_date' => '2025-12-31', 'category' => 'Fiction', 'entries' => 124],
    ['id' => 2, 'title' => 'Poetry Showcase', 'prize' => 250, 'end_date' => '2025-12-15', 'category' => 'Poetry', 'entries' => 87],
    ['id' => 3, 'title' => 'Fantasy World Building', 'prize' => 1000, 'end_date' => '2025-11-30', 'category' => 'Fantasy', 'entries' => 203],
];
?>

<main class="max-w-6xl mx-auto px-4 py-8">
    <div class="mb-8">
        <h1 class="text-4xl font-bold text-emerald-600 mb-2">Writing Contests</h1>
        <p class="text-gray-300">Join competitions and showcase your writing talent</p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php foreach ($contests as $c): ?>
            <div class="bg-gray-800 rounded-lg border border-gray-700 overflow-hidden hover:border-emerald-500 transition">
                <div class="bg-gradient-to-r from-emerald-600 to-teal-600 p-4">
                    <h3 class="text-xl font-bold text-white"><?= htmlspecialchars($c['title']) ?></h3>
                </div>
                <div class="p-4 space-y-3">
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-400">Category</span>
                        <span class="text-emerald-400 font-medium"><?= htmlspecialchars($c['category']) ?></span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-400">Prize</span>
                        <span class="text-emerald-400 font-bold">$<?= number_format($c['prize']) ?></span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-400">Entries</span>
                        <span class="text-emerald-400"><?= $c['entries'] ?></span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-400">Ends</span>
                        <span class="text-emerald-400"><?= $c['end_date'] ?></span>
                    </div>
                    <button class="w-full mt-4 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-md font-semibold transition">
                        Enter Contest
                    </button>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</main>

<?php include dirname(__FILE__) . '/../includes/footer.php'; ?>

