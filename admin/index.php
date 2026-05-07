<?php
require_once __DIR__ . '/../includes/config.php';
requireAdmin();

$db = db();
$today = date('Y-m-d');

$stats = $db->query("
    SELECT 
        SUM(queue_date = '$today') as today_total,
        SUM(queue_date = '$today' AND status = 'waiting') as waiting,
        SUM(queue_date = '$today' AND status = 'in_progress') as in_progress,
        SUM(queue_date = '$today' AND status = 'completed') as completed,
        SUM(queue_date = '$today' AND status = 'cancelled') as cancelled
    FROM tokens
")->fetch_assoc();

$doctors = $db->query("
    SELECT d.*,
        COUNT(CASE WHEN t.queue_date='$today' AND t.status='waiting' THEN 1 END) as waiting,
        COUNT(CASE WHEN t.queue_date='$today' AND t.status='in_progress' THEN 1 END) as in_progress,
        COUNT(CASE WHEN t.queue_date='$today' AND t.status='completed' THEN 1 END) as completed
    FROM doctors d
    LEFT JOIN tokens t ON d.id = t.doctor_id
    GROUP BY d.id
    ORDER BY d.name
")->fetch_all(MYSQLI_ASSOC);

include __DIR__ . '/../includes/header.php';
?>
<div class="page-wrapper">
<div class="container">
    <div class="page-header d-flex justify-between align-center">
        <div>
            <h1>Admin Dashboard</h1>
            <p><?= date('l, d F Y') ?></p>
        </div>
        <span class="live-badge">Live</span>
    </div>

    <!-- STATS -->
    <div class="grid grid-4 mb-2">
        <div class="stat-card teal">
            <div class="stat-label">Total Today</div>
            <div class="stat-value"><?= $stats['today_total'] ?? 0 ?></div>
            <div class="stat-sub">All tokens</div>
        </div>
        <div class="stat-card amber">
            <div class="stat-label">Waiting</div>
            <div class="stat-value"><?= $stats['waiting'] ?? 0 ?></div>
            <div class="stat-sub">In queue</div>
        </div>
        <div class="stat-card green">
            <div class="stat-label">Completed</div>
            <div class="stat-value"><?= $stats['completed'] ?? 0 ?></div>
            <div class="stat-sub">Done today</div>
        </div>
        <div class="stat-card red">
            <div class="stat-label">Cancelled</div>
            <div class="stat-value"><?= $stats['cancelled'] ?? 0 ?></div>
            <div class="stat-sub">Today</div>
        </div>
    </div>

    <!-- QUICK ACTIONS -->
    <div class="d-flex gap-2 mb-2 flex-wrap">
        <a href="<?= BASE_URL ?>/admin/queue.php" class="btn btn-primary btn-lg">📋 Manage Queue</a>
        <a href="<?= BASE_URL ?>/admin/analytics.php" class="btn btn-outline">📊 Analytics</a>
    </div>

    <!-- DOCTOR STATUS -->
    <div class="card">
        <div class="card-header"><h3>Doctor Queue Overview</h3></div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Doctor</th>
                        <th>Specialty</th>
                        <th>Room</th>
                        <th>Waiting</th>
                        <th>In Progress</th>
                        <th>Completed</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($doctors as $d): ?>
                    <tr>
                        <td class="fw-600"><?= htmlspecialchars($d['name']) ?></td>
                        <td><?= $d['specialty'] ?></td>
                        <td><?= $d['room'] ?></td>
                        <td><span style="color:var(--amber); font-weight:600"><?= $d['waiting'] ?></span></td>
                        <td><span style="color:var(--teal); font-weight:600"><?= $d['in_progress'] ?></span></td>
                        <td><span style="color:var(--green); font-weight:600"><?= $d['completed'] ?></span></td>
                        <td>
                            <span class="badge" style="background:<?= $d['available'] ? '#dcfce7' : '#fee2e2' ?>; color:<?= $d['available'] ? '#166534' : '#991b1b' ?>">
                                <?= $d['available'] ? '🟢 Available' : '🔴 Unavailable' ?>
                            </span>
                        </td>
                        <td><a href="<?= BASE_URL ?>/admin/queue.php?doctor=<?= $d['id'] ?>" class="btn btn-ghost btn-sm">Manage</a></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
<script>setTimeout(() => location.reload(), 30000);</script>
