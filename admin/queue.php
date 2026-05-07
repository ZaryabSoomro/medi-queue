<?php
require_once __DIR__ . '/../includes/config.php';
requireAdmin();

$db = db();
$today = date('Y-m-d');
$error = '';
$success = match($_GET['msg'] ?? '') {
    'next'      => 'Next patient called!',
    'cancel'    => 'Token cancelled.',
    'emergency' => 'Emergency token added.',
    'updated'   => 'Doctor updated.',
    default     => ''
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'next') {
        $doctorId = (int)$_POST['doctor_id'];
        
        // Mark current in_progress as completed
        $db->query("UPDATE tokens SET status='completed', completed_at=NOW() WHERE doctor_id=$doctorId AND status='in_progress' AND queue_date='$today'");
        
        // Get next waiting token
        $next = $db->query("SELECT * FROM tokens WHERE doctor_id=$doctorId AND status='waiting' AND queue_date='$today' ORDER BY priority DESC, id ASC LIMIT 1")->fetch_assoc();
        if ($next) {
            $db->query("UPDATE tokens SET status='in_progress', called_at=NOW() WHERE id={$next['id']}");
            sendNotification($next['patient_id'], $next['id'], "🔔 Token {$next['token_number']} — It's your turn! Please proceed to the doctor's room now.");
            $success = "Token {$next['token_number']} is now being called.";
        } else {
            $success = 'Queue is empty for this doctor.';
        }

        // ✅ 2 log aagay check - SIRF next action ke andar
        $twoAhead = $db->query("
            SELECT t.*, u.email, u.name as patient_name
            FROM tokens t JOIN users u ON t.patient_id = u.id
            WHERE t.doctor_id = $doctorId 
            AND t.status = 'waiting' 
            AND t.queue_date = '$today'
            ORDER BY t.priority DESC, t.id ASC
            LIMIT 3
        ")->fetch_all(MYSQLI_ASSOC);

        if (isset($twoAhead[2])) {
            $p = $twoAhead[2];
            sendEmailAlert($p['email'], $p['patient_name'], $p['token_number'], 3);
        }
        redirect('/admin/queue.php?doctor=' . $doctorId . '&msg=next');
    }

    if ($action === 'cancel') {
        $tokenId = (int)$_POST['token_id'];
        $token = $db->query("SELECT * FROM tokens WHERE id=$tokenId")->fetch_assoc();
        if ($token) {
            $db->query("UPDATE tokens SET status='cancelled' WHERE id=$tokenId");
            sendNotification($token['patient_id'], $tokenId, "❌ Your token {$token['token_number']} has been cancelled by the clinic.");
            $success = "Token {$token['token_number']} cancelled.";
        }
            redirect('/admin/queue.php?msg=cancel');

    }

    if ($action === 'emergency') {
        $doctorId = (int)$_POST['doctor_id'];
        $patientName = sanitize($_POST['patient_name']);
        $phone = sanitize($_POST['patient_phone']);
        $notes = sanitize($_POST['notes']);

        $stmt = $db->prepare("SELECT id FROM users WHERE phone = ? AND role = 'patient'");
        $stmt->bind_param("s", $phone);
        $stmt->execute();
        $existingUser = $stmt->get_result()->fetch_assoc();

        if ($existingUser) {
            $patientId = $existingUser['id'];
        } else {
            $tmpEmail = 'emergency_' . time() . '@clinic.local';
            $tmpPass = password_hash('temp1234', PASSWORD_DEFAULT);
            $stmt = $db->prepare("INSERT INTO users (name, email, phone, password, role) VALUES (?, ?, ?, ?, 'patient')");
            $stmt->bind_param("ssss", $patientName, $tmpEmail, $phone, $tmpPass);
            $stmt->execute();
            $patientId = $db->insert_id;
        }

        $tokenNum = generateToken($doctorId);
        $stmt = $db->prepare("INSERT INTO tokens (token_number, patient_id, doctor_id, notes, queue_date, status, priority) VALUES (?, ?, ?, ?, ?, 'waiting', 10)");
        $stmt->bind_param("siiss", $tokenNum, $patientId, $doctorId, $notes, $today);
        if ($stmt->execute()) {
            $tokenId = $db->insert_id;
            sendNotification($patientId, $tokenId, "🚨 Emergency token $tokenNum issued.");
            $success = "Emergency token $tokenNum added with priority for $patientName.";
        }
            redirect('/admin/queue.php?msg=emergency');

    }

    if ($action === 'toggle_doctor') {
        $docId = (int)$_POST['doctor_id'];
        $db->query("UPDATE doctors SET available = NOT available WHERE id = $docId");
        $success = 'Doctor availability updated.';
            redirect('/admin/queue.php?msg=updated');

    }
}

$selectedDoc = (int)($_GET['doctor'] ?? 0);
$doctors = $db->query("SELECT * FROM doctors ORDER BY name")->fetch_all(MYSQLI_ASSOC);
$where = $selectedDoc ? "AND t.doctor_id = $selectedDoc" : "";
$queue = $db->query("
    SELECT t.*, u.name as patient_name, u.phone, d.name as doctor_name, d.room, d.specialty
    FROM tokens t
    JOIN users u ON t.patient_id = u.id
    JOIN doctors d ON t.doctor_id = d.id
    WHERE t.queue_date = '$today' $where
    ORDER BY 
        CASE t.status WHEN 'in_progress' THEN 0 WHEN 'waiting' THEN 1 ELSE 2 END,
        t.priority DESC,
        t.id ASC
")->fetch_all(MYSQLI_ASSOC);

include __DIR__ . '/../includes/header.php';
?>

<div class="page-wrapper">
    <div class="container">
        <div class="page-header d-flex justify-between align-center">
            <div>
                <h1>Manage Queue</h1>
                <p>Today's live queue — <?= date('d F Y') ?></p>
            </div>
            <div class="d-flex gap-1 align-center">
                <span class="live-badge">Live</span>
                <button onclick="document.getElementById('emergencyModal').style.display='flex'" class="btn btn-danger">🚨 Emergency Add</button>
            </div>
        </div>

        <?php if ($error): ?><div class="alert alert-error"><?= $error ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>

        <!-- DOCTOR FILTER + NEXT PATIENT -->
        <div class="card mb-2">
            <div class="card-body" style="display:flex; gap:1rem; align-items:center; flex-wrap:wrap">
                <form method="GET" style="display:flex; gap:0.75rem; align-items:center; flex:1">
                    <label class="form-label" style="margin:0; white-space:nowrap">Filter by Doctor:</label>
                    <select name="doctor" class="form-control" onchange="this.form.submit()" style="max-width:280px">
                        <option value="0">All Doctors</option>
                        <?php foreach ($doctors as $d): ?>
                            <option value="<?= $d['id'] ?>" <?= $selectedDoc === $d['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($d['name']) ?> (<?= $d['specialty'] ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>

                <?php if ($selectedDoc): ?>
                    <form method="POST" onsubmit="return confirm('Mark current patient as done and call next?')">
                        <input type="hidden" name="action" value="next">
                        <input type="hidden" name="doctor_id" value="<?= $selectedDoc ?>">
                        <button class="btn btn-success">✅ Next Patient</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <!-- QUEUE TABLE -->
        <div class="card">
            <div class="card-header">
                <h3>Queue — <?= $selectedDoc ? htmlspecialchars(array_column($doctors, 'name', 'id')[$selectedDoc]) : 'All Doctors' ?></h3>
                <span class="text-muted" style="font-size:0.85rem"><?= count($queue) ?> records</span>
            </div>
            <?php if (empty($queue)): ?>
                <div class="empty-state">
                    <div class="icon">🎉</div>
                    <h3>Queue is empty</h3>
                    <p>No tokens for today yet.</p>
                </div>
            <?php else: ?>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Token</th>
                                <th>Patient</th>
                                <th>Doctor</th>
                                <th>Priority</th>
                                <th>Status</th>
                                <th>Booked At</th>
                                <th>Notes</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($queue as $q): ?>
                                <tr style="<?= $q['status'] === 'in_progress' ? 'background:#f0fdf4' : ($q['priority'] > 0 ? 'background:#fff7ed' : '') ?>">
                                    <td>
                                        <strong style="font-size:1.05rem; color:var(--teal)"><?= $q['token_number'] ?></strong>
                                        <?php if ($q['priority'] > 0): ?><br><span class="badge badge-emergency">🚨 Emergency</span><?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="fw-600"><?= htmlspecialchars($q['patient_name']) ?></div>
                                        <div class="text-muted" style="font-size:0.8rem">📱 <?= $q['phone'] ?></div>
                                    </td>
                                    <td>
                                        <div><?= htmlspecialchars($q['doctor_name']) ?></div>
                                        <div class="text-muted" style="font-size:0.78rem"><?= $q['room'] ?></div>
                                    </td>
                                    <td><?= $q['priority'] > 0 ? '<span style="color:var(--red);font-weight:700">HIGH</span>' : 'Normal' ?></td>
                                    <td><span class="badge badge-<?= $q['status'] === 'in_progress' ? 'progress' : $q['status'] ?>"><?= ucfirst(str_replace('_', ' ', $q['status'])) ?></span></td>
                                    <td style="font-size:0.82rem"><?= date('h:i A', strtotime($q['created_at'])) ?></td>
                                    <td style="font-size:0.82rem; max-width:180px"><?= $q['notes'] ? htmlspecialchars($q['notes']) : '<span class="text-muted">—</span>' ?></td>
                                    <td>
                                        <?php if (in_array($q['status'], ['waiting', 'in_progress'])): ?>
                                            <form method="POST" onsubmit="return confirm('Cancel this token?')">
                                                <input type="hidden" name="action" value="cancel">
                                                <input type="hidden" name="token_id" value="<?= $q['id'] ?>">
                                                <button class="btn btn-danger btn-sm">Cancel</button>
                                            </form>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- EMERGENCY MODAL -->
<div class="modal-overlay" id="emergencyModal" style="display:none" onclick="if(event.target===this)this.style.display='none'">
    <div class="modal">
        <div class="modal-title">🚨 Emergency / Walk-in Patient</div>
        <form method="POST">
            <input type="hidden" name="action" value="emergency">
            <div class="form-group">
                <label class="form-label">Doctor</label>
                <select name="doctor_id" class="form-control" required>
                    <option value="">Select doctor...</option>
                    <?php foreach ($doctors as $d): ?>
                        <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['name']) ?> — <?= $d['specialty'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Patient Name</label>
                <input type="text" name="patient_name" class="form-control" placeholder="Full name" required>
            </div>
            <div class="form-group">
                <label class="form-label">Patient Phone</label>
                <input type="text" name="patient_phone" class="form-control" placeholder="03001234567" required>
            </div>
            <div class="form-group">
                <label class="form-label">Reason / Notes</label>
                <textarea name="notes" class="form-control" placeholder="Emergency reason..."></textarea>
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-danger">Add Emergency Token</button>
                <button type="button" class="btn btn-ghost" onclick="document.getElementById('emergencyModal').style.display='none'">Cancel</button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
<script>
    setTimeout(() => location.reload(), 25000);
</script>