<?php
$unread = 0;
if (isLoggedIn() && isPatient()) {
    $uid = (int)$_SESSION['user_id'];
    $res = db()->query("SELECT COUNT(*) as cnt FROM notifications WHERE patient_id = $uid AND is_read = 0");
    $unread = $res->fetch_assoc()['cnt'];
}
$initial = isLoggedIn() ? strtoupper(substr($_SESSION['name'], 0, 1)) : '';
$base = BASE_URL;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= SITE_NAME ?> — Smart Queue System</title>
    <link rel="stylesheet" href="<?= $base ?>/assets/css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
</head>
<body>
<nav class="navbar">
    <a href="<?= isAdmin() ? $base.'/admin/' : $base.'/patient/' ?>" class="nav-brand">
        🏥 Medi<span>Queue</span>
    </a>

    <?php if (isLoggedIn()): ?>
    <div class="nav-links">
        <?php if (isPatient()): ?>
            <a href="<?= $base ?>/patient/">Dashboard</a>
            <a href="<?= $base ?>/patient/book.php">Book Token</a>
            <a href="<?= $base ?>/patient/queue.php">My Queue</a>
            <a href="<?= $base ?>/patient/history.php">History</a>
            <a href="<?= $base ?>/patient/notifications.php">
                Notifications<?php if ($unread > 0): ?><span class="notif-dot"></span><?php endif; ?>
            </a>
        <?php else: ?>
            <a href="<?= $base ?>/admin/">Dashboard</a>
            <a href="<?= $base ?>/admin/queue.php">Manage Queue</a>
            <a href="<?= $base ?>/admin/analytics.php">Analytics</a>
        <?php endif; ?>
    </div>
    <div class="nav-user">
        <div class="avatar"><?= $initial ?></div>
        <span style="font-size:0.85rem;color:var(--slate)"><?= htmlspecialchars($_SESSION['name']) ?></span>
        <a href="<?= $base ?>/logout.php" class="btn btn-ghost btn-sm">Logout</a>
    </div>
    <?php endif; ?>
</nav>
