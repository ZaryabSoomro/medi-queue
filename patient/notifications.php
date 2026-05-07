<?php
require_once __DIR__ . '/../includes/config.php';
requirePatient();

$uid = $_SESSION['user_id'];
$db = db();

// Mark all as read
$db->query("UPDATE notifications SET is_read = 1 WHERE patient_id = $uid");

$notifs = $db->query("
    SELECT n.*, t.token_number, d.name as doctor_name
    FROM notifications n
    JOIN tokens t ON n.token_id = t.id
    JOIN doctors d ON t.doctor_id = d.id
    WHERE n.patient_id = $uid
    ORDER BY n.created_at DESC
    LIMIT 30
")->fetch_all(MYSQLI_ASSOC);

include __DIR__ . '/../includes/header.php';
?>
<div class="page-wrapper">
<div class="container" style="max-width:720px">
    <div class="page-header">
        <h1>Notifications</h1>
        <p>Updates about your queue status</p>
    </div>

    <div class="card">
        <?php if (empty($notifs)): ?>
            <div class="empty-state card-body">
                <div class="icon">🔔</div>
                <h3>No notifications yet</h3>
                <p>You'll get notified when your queue status changes</p>
            </div>
        <?php else: ?>
            <?php foreach ($notifs as $n): ?>
            <div style="padding:1.1rem 1.5rem; border-bottom:1px solid var(--border); display:flex; gap:1rem; align-items:flex-start">
                <div style="width:40px; height:40px; background:rgba(13,148,136,0.1); border-radius:50%; display:flex; align-items:center; justify-content:center; flex-shrink:0; font-size:1.1rem">
                    🔔
                </div>
                <div>
                    <div style="font-size:0.9rem; color:var(--navy)"><?= htmlspecialchars($n['message']) ?></div>
                    <div style="font-size:0.78rem; color:var(--muted); margin-top:4px">
                        Token <?= $n['token_number'] ?> · <?= htmlspecialchars($n['doctor_name']) ?> · <?= date('d M, h:i A', strtotime($n['created_at'])) ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
