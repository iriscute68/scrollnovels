<li class="nav-item dropdown">
    <a class="nav-link dropdown-toggle position-relative" href="#" data-bs-toggle="dropdown">
        <i class="fas fa-bell"></i>
        <span id="notif-badge" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size:0.6rem; display:none;">
            0
        </span>
    </a>
    <ul class="dropdown-menu dropdown-menu-end">
        <li><a class="dropdown-item" href="<?= rtrim(SITE_URL, '/') ?>/pages/notification.php">View All Notifications</a></li>
    </ul>
</li>

<script>
setInterval(() => {
    fetch('<?= rtrim(SITE_URL, '/') ?>/api/unread-count.php')
        .then(r => r.text())
        .then(count => {
            const badge = document.getElementById('notif-badge');
            if (count > 0) {
                badge.textContent = count > 99 ? '99+' : count;
                badge.style.display = 'block';
            } else {
                badge.style.display = 'none';
            }
        });
}, 15000);
</script>                     
