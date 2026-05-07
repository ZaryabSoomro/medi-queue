<?php
require_once __DIR__ . '/../includes/config.php';
requirePatient();

$uid = (int)$_SESSION['user_id'];
$db = db();

$today = date('Y-m-d');
$tokens = $db->query("
    SELECT t.*, d.name as doctor_name, d.specialty, d.room
    FROM tokens t JOIN doctors d ON t.doctor_id = d.id
    WHERE t.patient_id = $uid AND t.queue_date = '$today'
    ORDER BY t.created_at DESC
")->fetch_all(MYSQLI_ASSOC);

$totalVisits = $db->query("SELECT COUNT(*) as cnt FROM tokens WHERE patient_id=$uid AND status='completed'")->fetch_assoc()['cnt'];
$unread      = $db->query("SELECT COUNT(*) as cnt FROM notifications WHERE patient_id=$uid AND is_read=0")->fetch_assoc()['cnt'];
$activeCount = count(array_filter($tokens, fn($t) => in_array($t['status'],['waiting','in_progress'])));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>MediQueue — Dashboard</title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=Manrope:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box}
:root{
  --ink:#0b0f1a;--ink2:#1c2333;
  --teal:#00c2a8;--teal2:#00a18c;--tealfade:rgba(0,194,168,.12);
  --slate:#4a5568;--muted:#8892a4;--border:#e8ecf3;
  --bg:#f5f7fb;--white:#fff;
  --red:#ff4d6d;--amber:#ffb703;--green:#06d6a0;--blue:#4361ee;
  --r:12px;--rL:20px;
}
body{font-family:'Manrope',sans-serif;background:var(--bg);color:var(--ink);min-height:100vh;font-size:14.5px}

/* NAVBAR */
.navbar{background:var(--ink);height:62px;display:flex;align-items:center;padding:0 2rem;gap:1.5rem;position:sticky;top:0;z-index:200;box-shadow:0 2px 24px rgba(11,15,26,.45)}
.brand{font-family:'Syne',sans-serif;font-weight:800;font-size:1.2rem;color:#fff;text-decoration:none;display:flex;align-items:center;gap:7px;letter-spacing:-.02em;flex-shrink:0}
.brand .dot{color:var(--teal)}
.nav-links{display:flex;gap:2px;flex:1}
.nav-links a{color:#8892a4;text-decoration:none;font-size:.84rem;font-weight:500;padding:6px 13px;border-radius:8px;transition:all .18s}
.nav-links a:hover{color:#fff;background:rgba(255,255,255,.07)}
.nav-links a.active{color:var(--teal);background:rgba(0,194,168,.13)}
.nav-right{display:flex;align-items:center;gap:10px;margin-left:auto}
.nav-avatar{width:33px;height:33px;border-radius:50%;background:linear-gradient(135deg,var(--teal),#007a6a);display:flex;align-items:center;justify-content:center;font-family:'Syne',sans-serif;font-weight:700;font-size:.8rem;color:#fff;flex-shrink:0}
.nav-name{font-size:.82rem;color:#c8d0de;font-weight:500}
.btn-logout{background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.11);color:#8892a4;font-size:.77rem;font-weight:600;padding:5px 12px;border-radius:7px;cursor:pointer;text-decoration:none;font-family:'Manrope',sans-serif;transition:all .18s}
.btn-logout:hover{color:#fff;border-color:rgba(255,255,255,.24)}
.notif-count{background:var(--red);color:#fff;font-size:.67rem;font-weight:700;padding:1px 6px;border-radius:100px;margin-left:2px}

/* PAGE */
.page{padding:2.5rem 2rem 4rem;max-width:1160px;margin:0 auto}

/* HERO */
.hero{background:var(--ink);border-radius:var(--rL);padding:2rem 2.5rem;margin-bottom:1.75rem;display:flex;align-items:center;justify-content:space-between;gap:2rem;position:relative;overflow:hidden}
.hero::before{content:'';position:absolute;top:-80px;right:-80px;width:300px;height:300px;background:radial-gradient(circle,rgba(0,194,168,.18) 0%,transparent 70%);pointer-events:none}
.hero::after{content:'';position:absolute;bottom:-60px;left:30%;width:200px;height:200px;background:radial-gradient(circle,rgba(67,97,238,.1) 0%,transparent 70%);pointer-events:none}
.hero-text h1{font-family:'Syne',sans-serif;font-size:1.7rem;font-weight:700;color:#fff;letter-spacing:-.03em;line-height:1.2}
.hero-text h1 span{color:var(--teal)}
.hero-text p{color:var(--muted);font-size:.87rem;margin-top:.4rem}
.hero-actions{display:flex;gap:10px;flex-shrink:0;flex-wrap:wrap}

/* BUTTONS */
.btn{display:inline-flex;align-items:center;gap:7px;padding:10px 20px;border-radius:10px;font-size:.84rem;font-weight:600;font-family:'Manrope',sans-serif;border:none;cursor:pointer;text-decoration:none;transition:all .18s;white-space:nowrap}
.btn-primary{background:var(--teal);color:var(--ink)}
.btn-primary:hover{background:#00d4b8;transform:translateY(-1px);box-shadow:0 6px 20px rgba(0,194,168,.35)}
.btn-ghost{background:rgba(255,255,255,.08);color:#fff;border:1px solid rgba(255,255,255,.14)}
.btn-ghost:hover{background:rgba(255,255,255,.14)}
.btn-outline{background:transparent;color:var(--teal);border:1.5px solid var(--teal)}
.btn-outline:hover{background:var(--teal);color:var(--ink)}
.btn-sm{padding:7px 14px;font-size:.79rem}

/* STATS */
.stats-row{display:grid;grid-template-columns:repeat(4,1fr);gap:1rem;margin-bottom:1.75rem}
.stat{background:var(--white);border-radius:var(--r);padding:1.25rem 1.5rem;border:1px solid var(--border);position:relative;overflow:hidden;transition:transform .2s,box-shadow .2s}
.stat:hover{transform:translateY(-2px);box-shadow:0 8px 30px rgba(11,15,26,.08)}
.stat-accent{position:absolute;top:0;left:0;width:4px;height:100%;border-radius:12px 0 0 12px}
.stat.teal .stat-accent{background:var(--teal)}
.stat.green .stat-accent{background:var(--green)}
.stat.amber .stat-accent{background:var(--amber)}
.stat.blue .stat-accent{background:var(--blue)}
.stat-icon{font-size:1.35rem;margin-bottom:.7rem;display:block}
.stat-val{font-family:'Syne',sans-serif;font-size:2rem;font-weight:700;color:var(--ink);line-height:1;letter-spacing:-.03em}
.stat-label{font-size:.72rem;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.07em;margin-top:.35rem}

/* SECTION */
.section-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem}
.section-title{font-family:'Syne',sans-serif;font-size:1.05rem;font-weight:700;color:var(--ink);letter-spacing:-.02em}
.live-pill{display:inline-flex;align-items:center;gap:5px;font-size:.71rem;font-weight:700;color:var(--green);background:rgba(6,214,160,.1);border:1px solid rgba(6,214,160,.25);padding:3px 10px;border-radius:100px;letter-spacing:.05em;text-transform:uppercase}
.live-pill::before{content:'';width:6px;height:6px;border-radius:50%;background:var(--green);animation:blink 1.4s ease-in-out infinite}
@keyframes blink{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.4;transform:scale(.8)}}

/* CARD & TABLE */
.card{background:var(--white);border-radius:var(--rL);border:1px solid var(--border);overflow:hidden}
.table-wrap{overflow-x:auto}
table{width:100%;border-collapse:collapse}
thead th{padding:12px 18px;text-align:left;font-size:.71rem;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.07em;background:#f9fafc;border-bottom:1px solid var(--border)}
tbody td{padding:15px 18px;font-size:.87rem;color:var(--slate);border-bottom:1px solid #f0f2f7;vertical-align:middle}
tbody tr:last-child td{border-bottom:none}
tbody tr{transition:background .15s}
tbody tr:hover td{background:#fafbff}

.token-chip{font-family:'Syne',sans-serif;font-size:1.05rem;font-weight:700;color:var(--teal);background:var(--tealfade);padding:4px 10px;border-radius:8px;letter-spacing:.05em;display:inline-block}
.doc-name{font-weight:600;color:var(--ink);font-size:.87rem}
.doc-spec{font-size:.77rem;color:var(--muted);margin-top:2px}

/* BADGES */
.badge{display:inline-flex;align-items:center;gap:4px;padding:4px 10px;border-radius:100px;font-size:.72rem;font-weight:700;letter-spacing:.02em}
.badge-waiting{background:#fff8e6;color:#b45309;border:1px solid #fde68a}
.badge-progress{background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe}
.badge-completed{background:#f0fdf4;color:#15803d;border:1px solid #bbf7d0}
.badge-cancelled{background:#fff1f2;color:#be123c;border:1px solid #fecdd3}

/* EMPTY */
.empty{text-align:center;padding:4rem 2rem}
.empty-icon{font-size:3.5rem;display:block;margin-bottom:1rem;opacity:.5}
.empty h3{font-family:'Syne',sans-serif;font-size:1.1rem;color:var(--ink);margin-bottom:.5rem}
.empty p{color:var(--muted);font-size:.87rem;margin-bottom:1.5rem}

/* ANIMATIONS */
.fade-in{animation:fadeUp .4s ease both}
@keyframes fadeUp{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}
.d1{animation-delay:.06s}.d2{animation-delay:.12s}.d3{animation-delay:.18s}.d4{animation-delay:.24s}.d5{animation-delay:.3s}

@media(max-width:900px){.stats-row{grid-template-columns:repeat(2,1fr)}.hero{flex-direction:column;align-items:flex-start}}
@media(max-width:580px){.page{padding:1.25rem 1rem 3rem}.navbar{padding:0 1rem}.nav-links{display:none}}
</style>
</head>
<body>

<nav class="navbar">
  <a href="<?= BASE_URL ?>/patient/" class="brand">🏥 Medi<span class="dot">Queue</span></a>
  <div class="nav-links">
    <a href="<?= BASE_URL ?>/patient/" class="active">Dashboard</a>
    <a href="<?= BASE_URL ?>/patient/book.php">Book Token</a>
    <a href="<?= BASE_URL ?>/patient/queue.php">My Queue</a>
    <a href="<?= BASE_URL ?>/patient/history.php">History</a>
    <a href="<?= BASE_URL ?>/patient/notifications.php">Notifications<?php if($unread):?><span class="notif-count"><?=$unread?></span><?php endif;?></a>
  </div>
  <div class="nav-right">
    <div class="nav-avatar"><?= strtoupper(substr($_SESSION['name'],0,1)) ?></div>
    <span class="nav-name"><?= htmlspecialchars($_SESSION['name']) ?></span>
    <a href="<?= BASE_URL ?>/logout.php" class="btn-logout">Logout</a>
  </div>
</nav>

<div class="page">

  <!-- HERO -->
  <div class="hero fade-in">
    <div class="hero-text">
      <h1>Welcome back, <span><?= htmlspecialchars(explode(' ',$_SESSION['name'])[0]) ?></span> 👋</h1>
      <p><?= date('l, d F Y') ?> &nbsp;·&nbsp; <?= $activeCount ?> active token<?= $activeCount!==1?'s':'' ?> today</p>
    </div>
    <div class="hero-actions">
      <a href="<?= BASE_URL ?>/patient/book.php" class="btn btn-primary">＋ Book Token</a>
      <a href="<?= BASE_URL ?>/patient/queue.php" class="btn btn-ghost">📋 My Queue</a>
    </div>
  </div>

  <!-- STATS -->
  <div class="stats-row">
    <div class="stat teal fade-in d1"><div class="stat-accent"></div><span class="stat-icon">🎫</span><div class="stat-val"><?= count($tokens) ?></div><div class="stat-label">Today's Tokens</div></div>
    <div class="stat green fade-in d2"><div class="stat-accent"></div><span class="stat-icon">✅</span><div class="stat-val"><?= $totalVisits ?></div><div class="stat-label">Total Visits</div></div>
    <div class="stat amber fade-in d3"><div class="stat-accent"></div><span class="stat-icon">🔔</span><div class="stat-val"><?= $unread ?></div><div class="stat-label">Unread Alerts</div></div>
    <div class="stat blue fade-in d4"><div class="stat-accent"></div><span class="stat-icon">📅</span><div class="stat-val" style="font-size:1.35rem"><?= date('d M') ?></div><div class="stat-label"><?= date('Y') ?></div></div>
  </div>

  <!-- TABLE -->
  <div class="section-head fade-in d5">
    <span class="section-title">Today's Tokens</span>
    <span class="live-pill">Live</span>
  </div>

  <div class="card fade-in d5">
    <?php if(empty($tokens)): ?>
      <div class="empty">
        <span class="empty-icon">🎫</span>
        <h3>No tokens booked today</h3>
        <p>Select a doctor and generate your queue token to get started.</p>
        <a href="<?= BASE_URL ?>/patient/book.php" class="btn btn-primary">Book a Token</a>
      </div>
    <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr><th>Token</th><th>Doctor</th><th>Room</th><th>Status</th><th>Position</th><th>Est. Wait</th><th>Action</th></tr>
        </thead>
        <tbody>
          <?php foreach($tokens as $t):
            $pos  = $t['status']==='waiting' ? getQueuePosition($t['id']) : null;
            $wait = $t['status']==='waiting' ? getEstimatedWait($t['id']) : null;
            $sk   = $t['status']==='in_progress' ? 'progress' : $t['status'];
            $sl   = $t['status']==='in_progress' ? '🔵 In Progress' : ucfirst(str_replace('_',' ',$t['status']));
          ?>
          <tr>
            <td><span class="token-chip"><?= $t['token_number'] ?></span></td>
            <td><div class="doc-name"><?= htmlspecialchars($t['doctor_name']) ?></div><div class="doc-spec"><?= htmlspecialchars($t['specialty']) ?></div></td>
            <td style="color:var(--teal);font-weight:600;font-size:.83rem"><?= $t['room'] ?></td>
            <td><span class="badge badge-<?= $sk ?>"><?= $sl ?></span></td>
            <td><?= $pos!==null ? '<span style="font-family:Syne,sans-serif;font-weight:700;font-size:1rem">#'.$pos.'</span>' : '<span style="color:var(--muted)">—</span>' ?></td>
            <td><?= $wait!==null ? '<span style="font-weight:600">'.$wait.' min</span>' : '<span style="color:var(--muted)">—</span>' ?></td>
            <td>
              <?php if($t['status']==='waiting'): ?>
                <a href="<?= BASE_URL ?>/patient/queue.php?token=<?= $t['id'] ?>" class="btn btn-outline btn-sm">Track →</a>
              <?php elseif($t['status']==='in_progress'): ?>
                <span style="color:var(--green);font-weight:700;font-size:.82rem">🟢 Your Turn!</span>
              <?php else: ?><span style="color:var(--muted)">—</span><?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>

</div>
<script>setTimeout(()=>location.reload(),30000);</script>
</body>
</html>
