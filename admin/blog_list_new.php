<?php require_once __DIR__ . '/inc/header.php'; require_once __DIR__ . '/../inc/auth.php'; require_once __DIR__ . '/../inc/db.php'; require_admin(); ?>

<div class="p-6">
    <h1 class="text-3xl font-bold mb-4">Blog Manager</h1>

    <div class="flex gap-3 mb-4">
        <input id="blogSearch" placeholder="Search blogs..." 
               class="border p-2 rounded w-1/3">

        <select id="blogStatus" class="border p-2 rounded">
            <option value="">All</option>
            <option value="draft">Draft</option>
            <option value="published">Published</option>
        </select>

        <a href="blog_new.php" class="px-4 py-2 bg-blue-600 text-white rounded">
            + New Blog
        </a>
    </div>

    <div id="blogTable"></div>
</div>

<script src="blog_list.js"></script>

<?php require_once __DIR__ . '/inc/footer.php'; ?>
