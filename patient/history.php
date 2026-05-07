<?php
require_once __DIR__ . '/../includes/config.php';
requirePatient();

$uid = $_SESSION['user_id'];
$db = db();

$history = $db->query("
    SELECT t.*, d.name as doctor_name, d.specialty, d.room
    FROM tokens t
    JOIN doctors d ON t.doctor_id = d.id
    WHERE t.patient_id = $uid
    ORDER BY t.created_at DESC
    LIMIT 50
")->fetch_all(MYSQLI_ASSOC);

$stats = $db->query("
    SELECT 
        COUNT(*) as total,
        SUM(status='completed') as completed,
        SUM(status='cancelled') as cancelled,
        SUM(status='waiting' OR status='in_progress') as active
    FROM tokens WHERE patient_id = $uid
")->fetch_assoc();

include __DIR__ . '/../includes/header.php';
?>
<div class="page-wrapper">
<div class="container">
    <div class="page-header">
        <h1>Visit History</h1>
        <p>All your past and current appointments</p>
    </div>

    <div class="grid grid-3 mb-2">
        <div class="stat-card teal">
            <div class="stat-label">Total Visits</div>
            <div class="stat-value"><?= $stats['total'] ?></div>
        </div>
        <div class="stat-card green">
            <div class="stat-label">Completed</div>
            <div class="stat-value"><?= $stats['completed'] ?></div>
        </div>
        <div class="stat-card red">
            <div class="stat-label">Cancelled</div>
            <div class="stat-value"><?= $stats['cancelled'] ?></div>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h3>All Records</h3></div>
        <?php if (empty($history)): ?>
            <div class="empty-state"><div class="icon">📭</div><h3>No history yet</h3></div>
        <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Token</th>
                        <th>Doctor</th>
                        <th>Room</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($history as $h): ?>
                    <tr>
                        <td><strong style="color:var(--teal)"><?= $h['token_number'] ?></strong></td>
                        <td>
                            <div class="fw-600"><?= htmlspecialchars($h['doctor_name']) ?></div>
                            <div class="text-muted" style="font-size:0.8rem"><?= $h['specialty'] ?></div>
                        </td>
                        <td><?= $h['room'] ?></td>
                        <td>
                            <?= date('d M Y', strtotime($h['queue_date'])) ?>
                            <div class="text-muted" style="font-size:0.78rem"><?= date('h:i A', strtotime($h['created_at'])) ?></div>
                        </td>
                        <td><span class="badge badge-<?= $h['status'] === 'in_progress' ? 'progress' : $h['status'] ?>"><?= ucfirst(str_replace('_',' ',$h['status'])) ?></span></td>
                        <td style="max-width:200px; font-size:0.85rem"><?= $h['notes'] ? htmlspecialchars($h['notes']) : '<span class="text-muted">—</span>' ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
