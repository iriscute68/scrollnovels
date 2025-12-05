<?php
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/auth.php';

require_admin();

$comp_id = intval($_POST['comp_id'] ?? 0);
$judge = isset($_POST['judge']) ? $_POST['judge'] : '';
$sort = isset($_POST['sort']) ? $_POST['sort'] : 'score';

$sql = "SELECT ce.*, s.title as story_title, u.username FROM competition_entries ce 
        JOIN stories s ON s.id = ce.story_id 
        JOIN users u ON u.id = ce.user_id
        WHERE ce.competition_id = ?";
$params = [$comp_id];

if ($sort === 'votes') {
  $sql .= " ORDER BY ce.votes DESC";
} elseif ($sort === 'views') {
  $sql .= " ORDER BY ce.views DESC";
} else {
  $sql .= " ORDER BY ce.total_score DESC";
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$entries = $stmt->fetchAll();

echo "<table class='w-full border text-left'>
<tr class='bg-gray-100'>
<th class='p-2'>Story</th>
<th class='p-2'>Author</th>
<th class='p-2'>Score</th>
<th class='p-2'>Votes</th>
<th class='p-2'>Views</th>
<th class='p-2'>Status</th>
<th class='p-2'>Actions</th>
</tr>";

foreach ($entries as $e) {
  echo "<tr class='border-b'>
  <td class='p-2'>" . htmlspecialchars($e['story_title']) . "</td>
  <td class='p-2'>" . htmlspecialchars($e['username']) . "</td>
  <td class='p-2'>" . number_format($e['total_score'], 2) . "</td>
  <td class='p-2'>" . $e['votes'] . "</td>
  <td class='p-2'>" . number_format($e['views']) . "</td>
  <td class='p-2'><span class='px-2 py-1 rounded text-sm bg-blue-100'>" . htmlspecialchars($e['status']) . "</span></td>
  <td class='p-2'>
    <button onclick='submitJudgeScore({$e['id']})' class='px-2 py-1 bg-green-600 text-white rounded text-sm'>Score</button>
  </td>
  </tr>";
}

echo "</table>";
?>
