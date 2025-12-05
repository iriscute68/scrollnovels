<?php
// Migration runner - executes SQL migration file

$config = [
    'host' => 'localhost',
    'user' => 'root',
    'password' => '',
    'database' => 'scroll_novels'
];

try {
    $pdo = new PDO(
        "mysql:host={$config['host']};dbname={$config['database']}",
        $config['user'],
        $config['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    $sql = file_get_contents(__DIR__ . '/migrations/004_complete_schema.sql');
    
    // Split by semicolon and execute each statement
    $statements = array_filter(
        array_map('trim', explode(';', $sql)),
        fn($s) => !empty($s) && !str_starts_with($s, '--')
    );
    
    $count = 0;
    foreach ($statements as $statement) {
        if (trim($statement)) {
            $pdo->exec($statement);
            $count++;
        }
    }
    
    echo "<h2>✅ Migration Successful!</h2>";
    echo "<p>Executed <strong>$count</strong> SQL statements</p>";
    echo "<pre>";
    echo "Tables created/updated:\n";
    echo "- moderation_logs\n";
    echo "- report_assignments\n";
    echo "- rate_limits\n";
    echo "- public_reports\n";
    echo "- moderator_sla\n";
    echo "- triage_rules\n";
    echo "- triage_actions\n";
    echo "- email_templates\n";
    echo "- email_queue\n";
    echo "- admin_activity_logs\n";
    echo "- chapter_logs\n";
    echo "- chapter_monetization\n";
    echo "- story_ratings\n";
    echo "- story_meta\n";
    echo "- notifications\n";
    echo "- announcements\n";
    echo "- user_notification_prefs\n";
    echo "- notification_logs\n";
    echo "- competition_judges\n";
    echo "- competition_entries\n";
    echo "- judge_scores\n";
    echo "- competition_leaderboard\n";
    echo "- competition_payouts\n";
    echo "- paystack_recipients\n";
    echo "- admin_action_logs\n";
    echo "- payments\n";
    echo "- roles\n";
    echo "- permissions\n";
    echo "- role_permissions\n";
    echo "- sanctions\n";
    echo "- appeals\n";
    echo "- achievements (30 base achievements seeded)\n";
    echo "- user_achievements\n";
    echo "- story_moderation\n";
    echo "</pre>";
    echo "<p><a href='/admin/setup_tables.php'>← Back to Setup</a></p>";
    
} catch (Exception $e) {
    echo "<h2>❌ Migration Failed</h2>";
    echo "<p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><strong>File:</strong> " . htmlspecialchars($e->getFile()) . "</p>";
    echo "<p><strong>Line:</strong> " . htmlspecialchars($e->getLine()) . "</p>";
}
?>

