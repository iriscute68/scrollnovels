<?php
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/auth.php';

require_admin();

$search = isset($_POST["search"]) ? $_POST["search"] : "";
$status = isset($_POST["status"]) ? $_POST["status"] : "";

$sql = "SELECT * FROM posts WHERE 1=1";
$params = [];

if ($search !== "") {
    $sql .= " AND title LIKE ?";
    $params[] = "%$search%";
}

if ($status !== "") {
    $sql .= " AND status = ?";
    $params[] = $status;
}

$sql .= " ORDER BY created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<table class='w-full border text-left'>
<tr class='bg-gray-100'>
<th class='p-2'>ID</th>
<th class='p-2'>Title</th>
<th class='p-2'>Status</th>
<th class='p-2'>Created</th>
<th class='p-2'>Actions</th>
</tr>";

foreach ($data as $b) {
    echo "<tr class='border-b'>
    <td class='p-2'>{$b['id']}</td>
    <td class='p-2'>{$b['title']}</td>
    <td class='p-2'>
        " . ($b['status']=="published" 
            ? "<span class='text-green-600'>Published</span>"
            : "<span class='text-yellow-600'>Draft</span>"
        ) . "
    </td>
    <td class='p-2'>{$b['created_at']}</td>

    <td class='p-2 flex gap-2'>
        <a href='blog_edit.php?id={$b['id']}' class='px-2 py-1 bg-blue-500 text-white rounded'>Edit</a>
        <button onclick='deleteBlog({$b["id"]})' class='px-2 py-1 bg-red-500 text-white rounded'>Delete</button>

        " . ($b['status']=="draft" 
            ? "<button onclick='publishBlog({$b["id"]})' class='px-2 py-1 bg-green-600 text-white rounded'>Publish</button>"
            : ""
        ) . "
    </td>
    </tr>";
}

echo "</table>";
?>
