<?php
require_once __DIR__ . '/../includes/config.php';
requireAdmin();

$db = db();

// Date range (last 7 days)
$days = [];
$dailyData = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $label = date('D d', strtotime("-$i days"));
    $row = $db->query("
        SELECT 
            COUNT(*) as total,
            SUM(status='completed') as completed,
            SUM(status='cancelled') as cancelled,
            SUM(status='waiting' OR status='in_progress') as active
        FROM tokens WHERE queue_date = '$date'
    ")->fetch_assoc();
    $days[] = $label;
    $dailyData[] = $row;
}

// Per-doctor stats
$doctorStats = $db->query("
    SELECT d.name, d.specialty,
        COUNT(t.id) as total,
        SUM(t.status='completed') as completed,
        SUM(t.status='cancelled') as cancelled,
        SUM(t.status='waiting' OR t.status='in_progress') as active
    FROM doctors d
    LEFT JOIN tokens t ON d.id = t.doctor_id
    GROUP BY d.id
    ORDER BY total DESC
")->fetch_all(MYSQLI_ASSOC);

// Overall totals
$overall = $db->query("
    SELECT 
        COUNT(*) as total,
        SUM(status='completed') as completed,
        SUM(status='cancelled') as cancelled,
        COUNT(DISTINCT patient_id) as patients
    FROM tokens
")->fetch_assoc();

// Today
$today = date('Y-m-d');
$todayStats = $db->query("
    SELECT COUNT(*) as total, SUM(status='completed') as completed, SUM(status='waiting') as waiting
    FROM tokens WHERE queue_date = '$today'
")->fetch_assoc();

include __DIR__ . '/../includes/header.php';
?>
<div class="page-wrapper">
<div class="container">
    <div class="page-header">
        <h1>Analytics Dashboard</h1>
        <p>Performance overview and queue statistics</p>
    </div>

    <!-- OVERALL STATS -->
    <div class="grid grid-4 mb-2">
        <div class="stat-card teal">
            <div class="stat-label">All-time Tokens</div>
            <div class="stat-value"><?= $overall['total'] ?></div>
        </div>
        <div class="stat-card green">
            <div class="stat-label">Completed</div>
            <div class="stat-value"><?= $overall['completed'] ?></div>
            <div class="stat-sub"><?= $overall['total'] > 0 ? round(($overall['completed']/$overall['total'])*100) : 0 ?>% completion rate</div>
        </div>
        <div class="stat-card amber">
            <div class="stat-label">Today Waiting</div>
            <div class="stat-value"><?= $todayStats['waiting'] ?? 0 ?></div>
            <div class="stat-sub">Right now</div>
        </div>
        <div class="stat-card red">
            <div class="stat-label">Total Patients</div>
            <div class="stat-value"><?= $overall['patients'] ?></div>
            <div class="stat-sub">Unique patients</div>
        </div>
    </div>

    <!-- LAST 7 DAYS CHART -->
    <div class="card mb-2">
        <div class="card-header"><h3>Last 7 Days — Daily Tokens</h3></div>
        <div class="card-body">
            <div style="display:flex; align-items:flex-end; gap:12px; height:180px; padding-top:1rem">
                <?php 
                $maxVal = max(array_column($dailyData, 'total') ?: [1]);
                foreach ($dailyData as $i => $d): 
                    $h = $maxVal > 0 ? round(($d['total'] / $maxVal) * 150) : 0;
                    $ch = $maxVal > 0 ? round(($d['completed'] / $maxVal) * 150) : 0;
                ?>
                <div style="flex:1; display:flex; flex-direction:column; align-items:center; gap:6px">
                    <div style="font-size:0.75rem; color:var(--muted)"><?= $d['total'] ?></div>
                    <div style="width:100%; position:relative; height:<?= $h ?>px; background:rgba(13,148,136,0.15); border-radius:6px 6px 0 0; display:flex; flex-direction:column; justify-content:flex-end">
                        <div style="width:100%; height:<?= $ch ?>px; background:var(--teal); border-radius:6px 6px 0 0"></div>
                    </div>
                    <div style="font-size:0.72rem; color:var(--muted); text-align:center"><?= $days[$i] ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="d-flex gap-2 mt-2" style="font-size:0.8rem; color:var(--muted)">
                <span>🟢 Completed</span>
                <span>⬜ Total</span>
            </div>
        </div>
    </div>

    <!-- PER DOCTOR STATS -->
    <div class="card">
        <div class="card-header"><h3>Doctor Performance</h3></div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Doctor</th>
                        <th>Specialty</th>
                        <th>Total Tokens</th>
                        <th>Completed</th>
                        <th>Cancelled</th>
                        <th>Active</th>
                        <th>Completion Rate</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($doctorStats as $ds): 
                        $rate = $ds['total'] > 0 ? round(($ds['completed']/$ds['total'])*100) : 0;
                    ?>
                    <tr>
                        <td class="fw-600"><?= htmlspecialchars($ds['name']) ?></td>
                        <td><?= $ds['specialty'] ?></td>
                        <td><?= $ds['total'] ?></td>
                        <td style="color:var(--green); font-weight:600"><?= $ds['completed'] ?></td>
                        <td style="color:var(--red)"><?= $ds['cancelled'] ?></td>
                        <td style="color:var(--amber)"><?= $ds['active'] ?></td>
                        <td>
                            <div style="display:flex; align-items:center; gap:8px">
                                <div class="progress-bar" style="width:100px">
                                    <div class="progress-fill" style="width:<?= $rate ?>%"></div>
                                </div>
                                <span style="font-size:0.82rem; font-weight:600"><?= $rate ?>%</span>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
