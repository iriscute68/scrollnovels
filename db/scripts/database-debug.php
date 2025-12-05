<?php
/**
 * Debug database schema and test book loading
 */
session_start();
require_once __DIR__ . '/config/db.php';

?>
<!DOCTYPE html>
<html>
<head>
    <title>Database Debug</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 p-8">
    <div class="max-w-4xl mx-auto space-y-8">
        
        <h1 class="text-3xl font-bold text-gray-900">Database Debug & Status</h1>
        
        <!-- Stories Table Schema -->
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-xl font-bold mb-4 text-gray-800">1. Stories Table Structure</h2>
            <?php
            try {
                $result = $pdo->query("DESCRIBE stories");
                $columns = $result->fetchAll(PDO::FETCH_ASSOC);
                ?>
                <table class="w-full border-collapse border border-gray-300">
                    <tr class="bg-gray-200">
                        <th class="border p-2">Field</th>
                        <th class="border p-2">Type</th>
                        <th class="border p-2">Null</th>
                        <th class="border p-2">Key</th>
                    </tr>
                    <?php foreach ($columns as $col): ?>
                    <tr>
                        <td class="border p-2"><?= $col['Field'] ?></td>
                        <td class="border p-2 text-sm"><?= $col['Type'] ?></td>
                        <td class="border p-2"><?= $col['Null'] ?></td>
                        <td class="border p-2"><?= $col['Key'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                </table>
                <?php
            } catch (Exception $e) {
                echo "<p class='text-red-600'>Error: " . $e->getMessage() . "</p>";
            }
            ?>
        </div>

        <!-- Stories Count -->
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-xl font-bold mb-4 text-gray-800">2. Stories in Database</h2>
            <?php
            try {
                $result = $pdo->query("SELECT COUNT(*) as total FROM stories");
                $count = $result->fetch()['total'];
                echo "<p class='text-lg'><strong>Total:</strong> <span class='text-blue-600 text-2xl'>$count</span></p>";
                
                if ($count > 0) {
                    echo "<h3 class='text-lg font-semibold mt-4 mb-2'>Sample Stories:</h3>";
                    $result = $pdo->query("SELECT id, title, status, cover, cover_image FROM stories LIMIT 5");
                    ?>
                    <table class="w-full border-collapse border border-gray-300">
                        <tr class="bg-gray-200">
                            <th class="border p-2">ID</th>
                            <th class="border p-2">Title</th>
                            <th class="border p-2">Status</th>
                            <th class="border p-2">Cover</th>
                            <th class="border p-2">Cover Image</th>
                            <th class="border p-2">Action</th>
                        </tr>
                        <?php
                        foreach ($result as $row) {
                            $hasCover = !empty($row['cover']) || !empty($row['cover_image']);
                            ?>
                            <tr>
                                <td class="border p-2"><?= $row['id'] ?></td>
                                <td class="border p-2"><?= htmlspecialchars($row['title']) ?></td>
                                <td class="border p-2"><span class="px-2 py-1 bg-yellow-200 rounded"><?= $row['status'] ?></span></td>
                                <td class="border p-2 text-sm"><?= $row['cover'] ? 'Yes' : 'No' ?></td>
                                <td class="border p-2 text-sm"><?= $row['cover_image'] ? 'Yes' : 'No' ?></td>
                                <td class="border p-2">
                                    <a href="/scrollnovels/pages/book.php?id=<?= $row['id'] ?>" 
                                       class="text-blue-600 hover:underline">View</a>
                                </td>
                            </tr>
                            <?php
                        }
                        ?>
                    </table>
                    <?php
                } else {
                    echo "<p class='text-red-600 mt-4'>No stories found! Creating sample stories...</p>";
                    
                    // Create sample data
                    try {
                        // Get or create author
                        $stmt = $pdo->query("SELECT id FROM users WHERE role IN ('author', 'admin') LIMIT 1");
                        $author = $stmt->fetch();
                        $authorId = $author['id'] ?? 1;
                        
                        // Create sample stories
                        for ($i = 1; $i <= 3; $i++) {
                            $sql = "INSERT INTO stories (title, author_id, synopsis, description, status) 
                                    VALUES (?, ?, ?, ?, ?)";
                            $stmt = $pdo->prepare($sql);
                            $stmt->execute([
                                "Sample Book $i",
                                $authorId,
                                "Synopsis for book $i",
                                "This is sample book number $i",
                                'published'
                            ]);
                        }
                        echo "<p class='text-green-600 mt-4'>Created 3 sample stories! Refresh page to see them.</p>";
                    } catch (Exception $e) {
                        echo "<p class='text-red-600'>Error creating stories: " . $e->getMessage() . "</p>";
                    }
                }
            } catch (Exception $e) {
                echo "<p class='text-red-600'>Error: " . $e->getMessage() . "</p>";
            }
            ?>
        </div>

        <!-- Users Table (for admin check) -->
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-xl font-bold mb-4 text-gray-800">3. Admin Users</h2>
            <?php
            try {
                $result = $pdo->query("SELECT id, username, email, role FROM users WHERE role IN ('admin', 'super_admin', 'moderator')");
                $admins = $result->fetchAll();
                
                if (empty($admins)) {
                    echo "<p class='text-red-600'>No admin users found!</p>";
                } else {
                    ?>
                    <table class="w-full border-collapse border border-gray-300">
                        <tr class="bg-gray-200">
                            <th class="border p-2">ID</th>
                            <th class="border p-2">Username</th>
                            <th class="border p-2">Email</th>
                            <th class="border p-2">Role</th>
                        </tr>
                        <?php foreach ($admins as $admin): ?>
                        <tr>
                            <td class="border p-2"><?= $admin['id'] ?></td>
                            <td class="border p-2"><?= $admin['username'] ?></td>
                            <td class="border p-2"><?= $admin['email'] ?></td>
                            <td class="border p-2"><span class="px-2 py-1 bg-red-200 rounded"><?= $admin['role'] ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                    <?php
                }
            } catch (Exception $e) {
                echo "<p class='text-red-600'>Error: " . $e->getMessage() . "</p>";
            }
            ?>
        </div>

        <!-- Test Book Page Loading -->
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-xl font-bold mb-4 text-gray-800">4. Test Book Loading</h2>
            <?php
            try {
                $stmt = $pdo->query("SELECT id FROM stories LIMIT 1");
                $story = $stmt->fetch();
                if ($story) {
                    $bookId = $story['id'];
                    
                    // Try the query that book.php uses
                    $testStmt = $pdo->prepare("
                        SELECT s.*, u.id as author_id, u.username as author_name, u.avatar
                        FROM stories s 
                        LEFT JOIN users u ON s.author_id = u.id 
                        WHERE s.id = ?
                    ");
                    $testStmt->execute([$bookId]);
                    $testStory = $testStmt->fetch();
                    
                    if ($testStory) {
                        echo "<p class='text-green-600 mb-4'>Success! Query returned a story.</p>";
                        echo "<p><strong>Story ID:</strong> " . $testStory['id'] . "</p>";
                        echo "<p><strong>Title:</strong> " . htmlspecialchars($testStory['title']) . "</p>";
                        echo "<p class='mt-4'><a href='/scrollnovels/pages/book.php?id=$bookId' class='bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700'>Click to test book page</a></p>";
                    } else {
                        echo "<p class='text-red-600'>Query returned no results!</p>";
                    }
                } else {
                    echo "<p class='text-orange-600'>No stories to test with.</p>";
                }
            } catch (Exception $e) {
                echo "<p class='text-red-600'>Error: " . $e->getMessage() . "</p>";
            }
            ?>
        </div>

    </div>
</body>
</html>

