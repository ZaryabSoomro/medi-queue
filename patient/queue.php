<?php
require_once __DIR__ . '/../includes/config.php';
requirePatient();

$uid = $_SESSION['user_id'];
$db = db();
$today = date('Y-m-d');

// Get all today's tokens for this patient
$tokens = $db->query("
    SELECT t.*, d.name as doctor_name, d.specialty, d.room, d.avg_time_minutes
    FROM tokens t
    JOIN doctors d ON t.doctor_id = d.id
    WHERE t.patient_id = $uid AND t.queue_date = '$today'
    ORDER BY t.created_at DESC
")->fetch_all(MYSQLI_ASSOC);

// Specific token focus
$focusTokenId = (int)($_GET['token'] ?? 0);

include __DIR__ . '/../includes/header.php';
?>
<div class="page-wrapper">
<div class="container">
    <div class="page-header d-flex justify-between align-center">
        <div>
            <h1>Queue Status</h1>
            <p>Live updates for your tokens today</p>
        </div>
        <span class="live-badge">Auto-refreshing</span>
    </div>

    <?php if (empty($tokens)): ?>
        <div class="empty-state card">
            <div class="card-body">
                <div class="icon">📋</div>
                <h3>No active tokens today</h3>
                <p>Book a token to see your queue position</p>
                <a href="<?= BASE_URL ?>/patient/book.php" class="btn btn-primary mt-2">Book Token</a>
            </div>
        </div>
    <?php else: ?>
        <div class="grid grid-2">
        <?php foreach ($tokens as $t):
            $pos = getQueuePosition($t['id']);
            $wait = getEstimatedWait($t['id']);
            $isActive = $t['status'] === 'waiting' || $t['status'] === 'in_progress';
            $isFocused = ($focusTokenId === $t['id']);

            // Progress percent
            $totalToday = $db->query("SELECT COUNT(*) as cnt FROM tokens WHERE doctor_id={$t['doctor_id']} AND queue_date='$today'")->fetch_assoc()['cnt'];
            $completed = $db->query("SELECT COUNT(*) as cnt FROM tokens WHERE doctor_id={$t['doctor_id']} AND queue_date='$today' AND status='completed'")->fetch_assoc()['cnt'];
            $percent = $totalToday > 0 ? round(($completed / $totalToday) * 100) : 0;
        ?>
        <div class="card <?= $isFocused ? 'focused' : '' ?>" style="<?= $isFocused ? 'border-color:var(--teal); box-shadow:0 0 0 3px rgba(13,148,136,0.15)' : '' ?>">
            <div class="card-header">
                <div>
                    <div class="fw-600"><?= htmlspecialchars($t['doctor_name']) ?></div>
                    <div class="text-muted" style="font-size:0.8rem"><?= $t['specialty'] ?> · <?= $t['room'] ?></div>
                </div>
                <span class="badge badge-<?= $t['status'] === 'in_progress' ? 'progress' : $t['status'] ?>">
                    <?= $t['status'] === 'in_progress' ? '🔵 In Progress' : ucfirst(str_replace('_',' ',$t['status'])) ?>
                </span>
            </div>
            <div class="card-body">
                <div class="queue-card-inner" style="padding:1rem 0">
                    <div class="stat-label">Token Number</div>
                    <span class="token-number"><?= $t['token_number'] ?></span>

                    <?php if ($t['status'] === 'waiting'): ?>
                        <div class="queue-position">
                            You are <strong>#<?= $pos ?></strong> in queue
                        </div>
                        <div style="color:var(--muted); font-size:0.85rem; margin-top:0.35rem">
                            ~<?= $wait ?> minutes estimated wait
                        </div>

                        <div class="progress-bar mt-2" style="max-width:200px; margin:1rem auto 0">
                            <div class="progress-fill" style="width:<?= $percent ?>%"></div>
                        </div>
                        <div style="font-size:0.78rem; color:var(--muted); margin-top:5px"><?= $percent ?>% of queue completed</div>

                    <?php elseif ($t['status'] === 'in_progress'): ?>
                        <div class="queue-position" style="color:var(--teal); font-weight:600">
                            🟢 It's your turn! Please proceed to <?= $t['room'] ?>
                        </div>
                    <?php elseif ($t['status'] === 'completed'): ?>
                        <div class="queue-position" style="color:var(--green)">✅ Visit completed</div>
                    <?php elseif ($t['status'] === 'cancelled'): ?>
                        <div class="queue-position" style="color:var(--red)">❌ Token cancelled</div>
                    <?php endif; ?>
                </div>

                <?php if ($t['notes']): ?>
                    <div style="background:var(--bg); padding:10px 14px; border-radius:8px; font-size:0.85rem; color:var(--muted); margin-top:0.5rem">
                        <strong>Notes:</strong> <?= htmlspecialchars($t['notes']) ?>
                    </div>
                <?php endif; ?>

                <div style="font-size:0.78rem; color:var(--muted); margin-top:0.75rem; text-align:center">
                    Booked at <?= date('h:i A', strtotime($t['created_at'])) ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>

<script>
// Auto-refresh every 20 seconds
setTimeout(() => location.reload(), 20000);
</script>
