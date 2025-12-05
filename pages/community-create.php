<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . SITE_URL . '/auth/login.php');
    exit;
}

include dirname(__DIR__) . '/includes/header.php';
?>
<div class="min-h-screen bg-gradient-to-b from-slate-50 to-white dark:from-slate-900 dark:to-slate-800">
    <div class="max-w-3xl mx-auto px-4 py-12">
        
        <a href="<?= SITE_URL ?>/pages/community.php" class="inline-flex items-center gap-2 text-emerald-600 hover:text-emerald-700 mb-6">
            ← Back to Forum
        </a>

        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-md p-8">
            <h1 class="text-3xl font-bold text-slate-900 dark:text-white mb-2">Create New Topic</h1>
            <p class="text-slate-600 dark:text-slate-300 mb-8">Start a discussion with the community</p>

            <form method="post" action="<?= SITE_URL ?>/api/community-post.php" class="space-y-6" enctype="multipart/form-data">
                
                <!-- Title -->
                <div>
                    <label class="block text-sm font-semibold text-slate-900 dark:text-white mb-2">
                        Topic Title (Required)
                    </label>
                    <input type="text" name="title" placeholder="What's your topic about?" 
                           minlength="10" maxlength="200"
                           class="w-full px-4 py-3 border border-slate-300 dark:border-slate-600 rounded-lg 
                           bg-white dark:bg-slate-700 text-slate-900 dark:text-white placeholder-slate-500"
                           required>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Minimum 10 characters</p>
                </div>

                <!-- Category -->
                <div>
                    <label class="block text-sm font-semibold text-slate-900 dark:text-white mb-2">
                        Category (Required)
                    </label>
                    <select name="category" class="w-full px-4 py-3 border border-slate-300 dark:border-slate-600 rounded-lg 
                           bg-white dark:bg-slate-700 text-slate-900 dark:text-white" required>
                        <option value="">Select a category...</option>
                        <option value="writing-advice">✍️ Writing Advice</option>
                        <option value="feedback">💬 Story Feedback</option>
                        <option value="genres">📚 Genre Discussions</option>
                        <option value="events">🎉 Community Events</option>
                        <option value="technical">🔧 Technical Help</option>
                    </select>
                </div>

                <!-- Content -->
                <div>
                    <label class="block text-sm font-semibold text-slate-900 dark:text-white mb-2">
                        Your Message (Required)
                    </label>
                    <textarea name="content" placeholder="Share your thoughts..." rows="10"
                              class="w-full px-4 py-3 border border-slate-300 dark:border-slate-600 rounded-lg 
                              bg-white dark:bg-slate-700 text-slate-900 dark:text-white placeholder-slate-500 font-mono text-sm"
                              required></textarea>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Markdown formatting supported</p>
                </div>

                <!-- Images -->
                <div>
                    <label class="block text-sm font-semibold text-slate-900 dark:text-white mb-2">
                        Add Images (Optional)
                    </label>
                    <input type="file" name="images[]" accept="image/*" multiple
                           class="w-full px-4 py-3 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white" />
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">JPEG/PNG/WebP up to 2MB each</p>
                </div>

                <!-- Tags -->
                <div>
                    <label class="block text-sm font-semibold text-slate-900 dark:text-white mb-2">
                        Tags (Optional, max 5)
                    </label>
                    <input type="text" name="tags" placeholder="e.g., craft, character, dialogue, worldbuilding"
                           class="w-full px-4 py-3 border border-slate-300 dark:border-slate-600 rounded-lg 
                           bg-white dark:bg-slate-700 text-slate-900 dark:text-white placeholder-slate-500">
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Separate tags with commas</p>
                </div>

                <!-- Guidelines -->
                <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
                    <h3 class="font-semibold text-blue-900 dark:text-blue-300 mb-2">📋 Community Guidelines</h3>
                    <ul class="text-xs text-blue-800 dark:text-blue-300 space-y-1">
                        <li>✓ Be respectful and constructive</li>
                        <li>✓ Stay on topic and relevant</li>
                        <li>✓ No spam or self-promotion without context</li>
                        <li>✓ Give feedback with kindness</li>
                    </ul>
                </div>

                <!-- Buttons -->
                <div class="flex gap-4">
                    <button type="submit" class="px-6 py-3 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-semibold transition">
                        ✍️ Create Topic
                    </button>
                    <a href="<?= SITE_URL ?>/pages/community.php" 
                       class="px-6 py-3 border border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-300 rounded-lg font-semibold hover:bg-slate-100 dark:hover:bg-slate-700 transition">
                        Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>
<?php include dirname(__DIR__) . '/includes/footer.php';

