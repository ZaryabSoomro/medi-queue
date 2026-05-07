<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);

define('DB_HOST', 'sql205.infinityfree.com');
define('DB_USER', 'if0_41855559');
define('DB_PASS', 'Sentinalprime03');
define('DB_NAME', 'if0_41855559_queue_system');

define('SITE_NAME', 'MediQueue');

/*
|--------------------------------------------------------------------------
| BASE URL
|--------------------------------------------------------------------------
*/

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    ? 'https'
    : 'http';

$host = $_SERVER['HTTP_HOST'];

define('BASE_URL', $protocol . '://' . $host);

define('BASE_PATH', realpath(__DIR__ . '/..'));

session_start();

/*
|--------------------------------------------------------------------------
| DATABASE
|--------------------------------------------------------------------------
*/

function db() {

    static $conn = null;

    if ($conn === null) {

        $conn = mysqli_connect(
            DB_HOST,
            DB_USER,
            DB_PASS,
            DB_NAME
        );

        if (!$conn) {

            die(
                '<div style="font-family:sans-serif;padding:20px;color:red;">
                    <h2>Database Connection Failed</h2>
                    <p>' . mysqli_connect_error() . '</p>
                </div>'
            );
        }

        mysqli_set_charset($conn, 'utf8mb4');
    }

    return $conn;
}

/*
|--------------------------------------------------------------------------
| AUTH
|--------------------------------------------------------------------------
*/

function isLoggedIn() {

    return isset($_SESSION['user_id']);
}

function isAdmin() {

    return isset($_SESSION['role']) &&
           $_SESSION['role'] === 'admin';
}

function isPatient() {

    return isset($_SESSION['role']) &&
           $_SESSION['role'] === 'patient';
}

/*
|--------------------------------------------------------------------------
| PROTECTION
|--------------------------------------------------------------------------
*/

function requireLogin() {

    if (!isLoggedIn()) {

        redirect('/index.php');
    }
}

function requireAdmin() {

    if (!isAdmin()) {

        redirect('/index.php');
    }
}

function requirePatient() {

    if (!isPatient()) {

        redirect('/index.php');
    }
}

/*
|--------------------------------------------------------------------------
| HELPERS
|--------------------------------------------------------------------------
*/

function redirect($path) {

    header('Location: ' . BASE_URL . $path);

    exit;
}

function sanitize($data) {

    return htmlspecialchars(
        strip_tags(trim($data))
    );
}

function url($path = '') {

    return BASE_URL . $path;
}

/*
|--------------------------------------------------------------------------
| TOKENS
|--------------------------------------------------------------------------
*/

function generateToken($doctorId) {

    $db = db();

    $today = date('Y-m-d');

    $prefix = chr(64 + $doctorId);

    $stmt = mysqli_prepare(
        $db,
        "SELECT COUNT(*) as cnt
         FROM tokens
         WHERE doctor_id = ?
         AND queue_date = ?"
    );

    mysqli_stmt_bind_param(
        $stmt,
        "is",
        $doctorId,
        $today
    );

    mysqli_stmt_execute($stmt);

    $result = mysqli_fetch_assoc(
        mysqli_stmt_get_result($stmt)
    );

    $num = str_pad(
        $result['cnt'] + 1,
        3,
        '0',
        STR_PAD_LEFT
    );

    return $prefix . $num;
}

function getQueuePosition($tokenId) {

    $db = db();

    $tokenId = (int)$tokenId;

    $result = mysqli_query(
        $db,
        "SELECT *
         FROM tokens
         WHERE id = $tokenId"
    );

    $token = mysqli_fetch_assoc($result);

    if (!$token) {

        return null;
    }

    $stmt = mysqli_prepare(
        $db,
        "SELECT COUNT(*) as pos
         FROM tokens
         WHERE doctor_id = ?
         AND queue_date = ?
         AND status = 'waiting'
         AND (
            priority > ?
            OR (
                priority = ?
                AND id <= ?
            )
         )"
    );

    mysqli_stmt_bind_param(
        $stmt,
        "isiii",
        $token['doctor_id'],
        $token['queue_date'],
        $token['priority'],
        $token['priority'],
        $tokenId
    );

    mysqli_stmt_execute($stmt);

    $result = mysqli_fetch_assoc(
        mysqli_stmt_get_result($stmt)
    );

    return $result['pos'];
}

function getEstimatedWait($tokenId) {

    $db = db();

    $tokenId = (int)$tokenId;

    $result = mysqli_query(
        $db,
        "SELECT t.*, d.avg_time_minutes
         FROM tokens t
         JOIN doctors d
         ON t.doctor_id = d.id
         WHERE t.id = $tokenId"
    );

    $token = mysqli_fetch_assoc($result);

    if (!$token) {

        return 0;
    }

    $pos = getQueuePosition($tokenId);

    return ($pos - 1) * $token['avg_time_minutes'];
}

/*
|--------------------------------------------------------------------------
| NOTIFICATIONS
|--------------------------------------------------------------------------
*/

function sendNotification(
    $patientId,
    $tokenId,
    $message
) {

    $db = db();

    $stmt = mysqli_prepare(
        $db,
        "INSERT INTO notifications
        (patient_id, token_id, message)
        VALUES (?, ?, ?)"
    );

    mysqli_stmt_bind_param(
        $stmt,
        "iis",
        $patientId,
        $tokenId,
        $message
    );

    mysqli_stmt_execute($stmt);
}

/*
|--------------------------------------------------------------------------
| EMAIL ALERTS
|--------------------------------------------------------------------------
*/

function sendEmailAlert(
    $toEmail,
    $toName,
    $tokenNumber,
    $position
) {

    $phpmailerPath =
        __DIR__ . '/PHPMailer/src/PHPMailer.php';

    if (!file_exists($phpmailerPath)) {

        return;
    }

    require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';

    require_once __DIR__ . '/PHPMailer/src/SMTP.php';

    require_once __DIR__ . '/PHPMailer/src/Exception.php';

    $mail =
        new PHPMailer\PHPMailer\PHPMailer(true);

    try {

        $mail->isSMTP();

        $mail->Host = 'smtp.gmail.com';

        $mail->SMTPAuth = true;

        $mail->Username =
            'zaryabsoomro01@gmail.com';

        $mail->Password =
            'dnxo bans vxhq pyls';

        $mail->SMTPSecure = 'tls';

        $mail->Port = 587;

        $mail->setFrom(
            'zaryabsoomro01@gmail.com',
            'MediQueue'
        );

        $mail->addAddress(
            $toEmail,
            $toName
        );

        $mail->Subject =
            "Token Alert - $tokenNumber";

        $mail->isHTML(true);

        $mail->Body = "
        <div style='font-family:sans-serif;padding:20px'>
            <h2 style='color:#00c2a8'>
                MediQueue Alert
            </h2>

            <p>
                Assalam o Alaikum
                <b>$toName</b>
            </p>

            <p>
                Sirf 2 log aap se aagay hain.
            </p>

            <p>
                Token:
                <b>$tokenNumber</b>
            </p>
        </div>
        ";

        $mail->send();

    } catch (Exception $e) {

        error_log(
            'Mail Error: ' .
            $mail->ErrorInfo
        );
    }
}
?>