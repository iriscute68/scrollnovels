<?php
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/auth.php';

require_admin();

$search = isset($_POST["search"]) ? $_POST["search"] : "";
$role = isset($_POST["role"]) ? $_POST["role"] : "";
$ban = isset($_POST["ban"]) ? $_POST["ban"] : "";

$sql = "SELECT * FROM users WHERE 1";
$params = [];

if ($search !== "") {
    $sql .= " AND (username LIKE ? OR email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($role !== "") {
    $sql .= " AND role = ?";
    $params[] = $role;
}

if ($ban !== "") {
    $sql .= " AND status = ?";
    $params[] = ($ban == '1' ? 'banned' : 'active');
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<table class='w-full border text-left'>
<tr class='bg-gray-100'>
<th class='p-2'>ID</th>
<th class='p-2'>Username</th>
<th class='p-2'>Email</th>
<th class='p-2'>Role</th>
<th class='p-2'>Status</th>
<th class='p-2'>Actions</th>
</tr>";

foreach ($users as $u) {
    $status_text = ($u['status'] == 'banned') ? 'Banned' : 'Active';
    $status_class = ($u['status'] == 'banned') ? 'text-red-600' : 'text-green-600';
    $role_display = $u['role'] ?? 'reader';
    
    echo "<tr class='border-b'>
    <td class='p-2'>{$u['id']}</td>
    <td class='p-2'>{$u['username']}</td>
    <td class='p-2'>{$u['email']}</td>

    <td class='p-2'>
        <select onchange='changeRole({$u['id']}, this.value)' class='border p-1'>
            <option value='reader' " . ($role_display=='reader'?'selected':'') . ">Reader</option>
            <option value='author' " . ($role_display=='author'?'selected':'') . ">Author</option>
            <option value='moderator' " . ($role_display=='moderator'?'selected':'') . ">Moderator</option>
            <option value='admin' " . ($role_display=='admin'?'selected':'') . ">Admin</option>
        </select>
    </td>

    <td class='p-2'><span class='$status_class'>$status_text</span></td>

    <td class='p-2'>
        " . ($u['status']=='banned' ? 
        "<button onclick='unbanUser({$u['id']})' class='px-2 py-1 bg-green-500 text-white rounded'>Unban</button>" :
        "<button onclick='banUser({$u['id']})' class='px-2 py-1 bg-red-500 text-white rounded'>Ban</button>"
        ) . "
    </td>
    </tr>";
}

echo "</table>";
?>
