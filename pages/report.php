<form action="<?= rtrim(SITE_URL, '/') ?>/api/report.php" method="POST">
    <input type="hidden" name="type" value="story">
    <input type="hidden" name="id" value="123">
    <textarea name="reason" required></textarea>
    <button>Report</button>
</form>
