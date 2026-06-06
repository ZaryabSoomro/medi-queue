<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'queue_system');
define('SITE_NAME', 'MediQueue');

// Auto-detect base URL (works for localhost/queue_system/ or any subfolder)
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$scriptDir = dirname($_SERVER['SCRIPT_NAME']);
// Walk up to find the project root (contains index.php at top level)
$parts = explode('/', trim($scriptDir, '/'));
// Base is always the queue_system folder (first segment after host)
define('BASE_URL', $protocol . '://' . $host . '/' . $parts[0]);
define('BASE_PATH', realpath(__DIR__ . '/..'));

session_start();

function db() {
    static $conn = null;
    if ($conn === null) {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($conn->connect_error) {
            die('<div style="font-family:sans-serif;padding:2rem;color:red;"><h2>Database Error</h2><p>' . $conn->connect_error . '</p><p>Please check your database settings in <code>includes/config.php</code></p></div>');
        }
        $conn->set_charset('utf8mb4');
    }
    return $conn;
}

function isLoggedIn() { return isset($_SESSION['user_id']); }
function isAdmin() { return isset($_SESSION['role']) && $_SESSION['role'] === 'admin'; }
function isPatient() { return isset($_SESSION['role']) && $_SESSION['role'] === 'patient'; }

function requireLogin() {
    if (!isLoggedIn()) { redirect('/index.php'); }
}
function requireAdmin() {
    if (!isAdmin()) { redirect('/index.php'); }
}
function requirePatient() {
    if (!isPatient()) { redirect('/index.php'); }
}

function redirect($path) {
    header('Location: ' . BASE_URL . $path);
    exit;
}

function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

function url($path) {
    return BASE_URL . $path;
}

function generateToken($doctorId) {
    $db = db();
    $today = date('Y-m-d');
    $prefix = chr(64 + $doctorId);
    $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM tokens WHERE doctor_id = ? AND queue_date = ?");
    $stmt->bind_param("is", $doctorId, $today);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $num = str_pad($result['cnt'] + 1, 3, '0', STR_PAD_LEFT);
    return $prefix . $num;
}

function getQueuePosition($tokenId) {
    $db = db();
    $tokenId = (int)$tokenId;
    $token = $db->query("SELECT * FROM tokens WHERE id = $tokenId")->fetch_assoc();
    if (!$token) return null;
    $stmt = $db->prepare("SELECT COUNT(*) as pos FROM tokens WHERE doctor_id = ? AND queue_date = ? AND status = 'waiting' AND (priority > ? OR (priority = ? AND id <= ?))");
    $stmt->bind_param("isiii", $token['doctor_id'], $token['queue_date'], $token['priority'], $token['priority'], $tokenId);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    return $result['pos'];
}

function getEstimatedWait($tokenId) {
    $db = db();
    $tokenId = (int)$tokenId;
    $token = $db->query("SELECT t.*, d.avg_time_minutes FROM tokens t JOIN doctors d ON t.doctor_id = d.id WHERE t.id = $tokenId")->fetch_assoc();
    if (!$token) return 0;
    $pos = getQueuePosition($tokenId);
    return ($pos - 1) * $token['avg_time_minutes'];
}

function sendNotification($patientId, $tokenId, $message) {
    $db = db();
    $stmt = $db->prepare("INSERT INTO notifications (patient_id, token_id, message) VALUES (?, ?, ?)");
    $stmt->bind_param("iis", $patientId, $tokenId, $message);
    $stmt->execute();
}
function sendEmailAlert($toEmail, $toName, $tokenNumber, $position) {
    // PHPMailer via Composer - ya direct download
    require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
    require_once __DIR__ . '/PHPMailer/src/SMTP.php';
    require_once __DIR__ . '/PHPMailer/src/Exception.php';

    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'zaryabsoomro01@gmail.com'; // <-- apni Gmail
        $mail->Password   = 'dnxo bans vxhq pyls';     // <-- App Password
        $mail->SMTPSecure = 'tls';
        $mail->Port       = 587;

        $mail->setFrom('zaryabsoomro01@gmail.com', 'MediQueue');
        $mail->addAddress($toEmail, $toName);
        $mail->Subject = "🔔 Aapki baari qareeb aa rahi hai! Token $tokenNumber";
        $mail->isHTML(true);
        $mail->Body = "
            <div style='font-family:sans-serif;padding:20px'>
                <h2 style='color:#00c2a8'>MediQueue Alert</h2>
                <p>Assalam o Alaikum <b>$toName</b>,</p>
                <p>Sirf <b>2 log</b> aap se aagay hain queue mein.</p>
                <p>Apna token <b style='font-size:1.3rem'>$tokenNumber</b> lekar doctor ke room mein tayar ho jayen.</p>
                <br>
                <small style='color:gray'>MediQueue Automated Alert</small>
            </div>
        ";
        $mail->send();
    } catch (Exception $e) {
        // Email fail ho to koi dikkat nahi, queue chalta rahe
        error_log("Email error: " . $mail->ErrorInfo);
    }
}
