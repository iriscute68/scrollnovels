<?php
// contest.php
$contests = $pdo->query("SELECT * FROM contests WHERE end_date > NOW()")->fetchAll();
?>
<h2>Writing Contests</h2>
<?php foreach ($contests as $c): ?>
<div class="card">
    <div class="card-body">
        <h5><?= $c['title'] ?></h5>
        <p>Prize: $<?= $c['prize'] ?></p>
        <a href="/submit-contest.php?id=<?= $c['id'] ?>" class="btn btn-primary">Enter</a>
    </div>
</div>
<?php endforeach; ?>
