<?php
require_once __DIR__ . '/../includes/config.php';
requirePatient();

$uid = $_SESSION['user_id'];
$db = db();
$error = '';
$success = '';
$bookedToken = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $doctorId = (int)$_POST['doctor_id'];
    $notes = sanitize($_POST['notes'] ?? '');
    $today = date('Y-m-d');

    // Check if patient already has a waiting/in_progress token with this doctor today
    $stmt = $db->prepare("SELECT id FROM tokens WHERE patient_id = ? AND doctor_id = ? AND queue_date = ? AND status IN ('waiting','in_progress')");
    $stmt->bind_param("iis", $uid, $doctorId, $today);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $error = 'You already have an active token with this doctor today.';
    } else {
        $tokenNum = generateToken($doctorId);
        $stmt = $db->prepare("INSERT INTO tokens (token_number, patient_id, doctor_id, notes, queue_date) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("siiss", $tokenNum, $uid, $doctorId, $notes, $today);
        if ($stmt->execute()) {
            $tokenId = $db->insert_id;
            $pos = getQueuePosition($tokenId);
            $wait = getEstimatedWait($tokenId);
            sendNotification($uid, $tokenId, "Token $tokenNum booked! You are #$pos in queue. Estimated wait: $wait minutes.");
            $bookedToken = [
                'id' => $tokenId,
                'number' => $tokenNum,
                'position' => $pos,
                'wait' => $wait
            ];
        } else {
            $error = 'Failed to book token. Please try again.';
        }
    }
}

// Get all available doctors with their current queue count
$doctors = $db->query("
    SELECT d.*, 
           COUNT(CASE WHEN t.status IN ('waiting','in_progress') AND t.queue_date = CURDATE() THEN 1 END) as queue_count
    FROM doctors d
    LEFT JOIN tokens t ON d.id = t.doctor_id
    WHERE d.available = 1
    GROUP BY d.id
    ORDER BY d.specialty, d.name
")->fetch_all(MYSQLI_ASSOC);

include __DIR__ . '/../includes/header.php';
?>
<div class="page-wrapper">
<div class="container">
    <div class="page-header">
        <h1>Book a Token</h1>
        <p>Select a doctor and generate your queue token</p>
    </div>

    <?php if ($error): ?><div class="alert alert-error"><?= $error ?></div><?php endif; ?>

    <?php if ($bookedToken): ?>
    <!-- SUCCESS STATE -->
    <div class="card" style="max-width:480px; margin: 0 auto;">
        <div class="card-body">
            <div class="queue-card-inner">
                <div style="font-size:3rem; margin-bottom:1rem">🎉</div>
                <div class="stat-label">Your Token Number</div>
                <span class="token-number"><?= $bookedToken['number'] ?></span>
                <div class="queue-position">
                    You are <strong>#<?= $bookedToken['position'] ?></strong> in queue
                </div>
                <div style="color:var(--muted); font-size:0.88rem; margin-top:0.5rem">
                    Estimated wait: <strong><?= $bookedToken['wait'] ?> minutes</strong>
                </div>

                <hr class="divider">

                <div class="d-flex gap-2 justify-content-center" style="justify-content:center">
                    <a href="<?= BASE_URL ?>/patient/queue.php?token=<?= $bookedToken['id'] ?>" class="btn btn-primary">Track Queue</a>
                    <a href="<?= BASE_URL ?>/patient/book.php" class="btn btn-ghost">Book Another</a>
                </div>
            </div>
        </div>
    </div>

    <?php else: ?>
    <!-- BOOKING FORM -->
    <form method="POST" id="bookForm">
        <input type="hidden" name="doctor_id" id="selectedDoctor" required>

        <div class="card mb-2">
            <div class="card-header"><h3>Select Doctor</h3></div>
            <div class="card-body">
                <div class="grid grid-3">
                    <?php foreach ($doctors as $doc): 
                        $waitEst = $doc['queue_count'] * $doc['avg_time_minutes'];
                    ?>
                    <div class="doctor-card" onclick="selectDoctor(<?= $doc['id'] ?>, this)">
                        <div class="doc-avatar">🩺</div>
                        <div class="doc-name"><?= htmlspecialchars($doc['name']) ?></div>
                        <div class="doc-spec"><?= htmlspecialchars($doc['specialty']) ?></div>
                        <div class="doc-room">📍 <?= $doc['room'] ?></div>
                        <div class="doc-wait">
                            👥 <?= $doc['queue_count'] ?> waiting
                            &nbsp;·&nbsp; ~<?= $waitEst ?> min wait
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div id="noDocError" class="alert alert-error mt-2" style="display:none">Please select a doctor first.</div>
            </div>
        </div>

        <div class="card mb-2">
            <div class="card-header"><h3>Additional Notes (Optional)</h3></div>
            <div class="card-body">
                <div class="form-group" style="margin:0">
                    <textarea name="notes" class="form-control" placeholder="Briefly describe your symptoms or reason for visit..."></textarea>
                </div>
            </div>
        </div>

        <button type="submit" class="btn btn-primary btn-lg" onclick="return validateForm()">Generate Token →</button>
        <a href="<?= BASE_URL ?>/patient/" class="btn btn-ghost btn-lg">Cancel</a>
    </form>
    <?php endif; ?>
</div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>

<script>
function selectDoctor(id, el) {
    document.querySelectorAll('.doctor-card').forEach(c => c.classList.remove('selected'));
    el.classList.add('selected');
    document.getElementById('selectedDoctor').value = id;
    document.getElementById('noDocError').style.display = 'none';
}

function validateForm() {
    if (!document.getElementById('selectedDoctor').value) {
        document.getElementById('noDocError').style.display = 'block';
        return false;
    }
    return true;
}
</script>
