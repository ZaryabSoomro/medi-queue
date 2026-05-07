<?php
require_once __DIR__ . '/includes/config.php';

if (isLoggedIn()) {
    redirect(isAdmin() ? '/admin/' : '/patient/');
}

$error = '';
$success = '';
$activeTab = 'login';
$activeModal = ''; // 'patient' or 'admin'

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'login') {
        $activeModal = 'patient';
        $email = sanitize($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $db = db();
        $stmt = $db->prepare("SELECT * FROM users WHERE email = ? AND role = 'patient'");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['name']    = $user['name'];
            $_SESSION['role']    = $user['role'];
            redirect('/patient/');
        } else {
            $error = 'Invalid email or password.';
        }
    }

    if ($action === 'admin_login') {
        $activeModal = 'admin';
        $email = sanitize($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $db = db();
        $stmt = $db->prepare("SELECT * FROM users WHERE email = ? AND role = 'admin'");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['name']    = $user['name'];
            $_SESSION['role']    = $user['role'];
            redirect('/admin/');
        } else {
            $error = 'Invalid admin credentials.';
        }
    }

    if ($action === 'signup') {
        $activeModal = 'patient';
        $activeTab = 'signup';
        $name     = sanitize($_POST['name'] ?? '');
        $email    = sanitize($_POST['email'] ?? '');
        $phone    = sanitize($_POST['phone'] ?? '');
        $password = $_POST['password'] ?? '';
        if (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters.';
        } else {
            $db = db();
            $chk = $db->prepare("SELECT id FROM users WHERE email = ?");
            $chk->bind_param("s", $email);
            $chk->execute();
            if ($chk->get_result()->num_rows > 0) {
                $error = 'An account with this email already exists.';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $db->prepare("INSERT INTO users (name, email, phone, password, role) VALUES (?, ?, ?, ?, 'patient')");
                $stmt->bind_param("ssss", $name, $email, $phone, $hash);
                $stmt->execute() ? $success = 'Account created! You can now log in.' : $error = 'Something went wrong.';
                if ($success) { $activeTab = 'login'; }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MediQueue — Smart Hospital Queue Management</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,700;1,400&family=Outfit:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --ink: #0d1117;
            --ink-soft: #2a3040;
            --muted: #6b7280;
            --border: #e5e7eb;
            --surface: #f9fafb;
            --white: #ffffff;
            --teal: #0f766e;
            --teal-light: #14b8a6;
            --teal-dim: #ccfbf1;
            --amber: #d97706;
            --amber-dim: #fef3c7;
            --red-dim: #fee2e2;
            --red: #dc2626;
            --green: #059669;
            --green-dim: #d1fae5;
            --shadow-sm: 0 1px 3px rgba(0,0,0,.08), 0 1px 2px rgba(0,0,0,.05);
            --shadow-md: 0 4px 20px rgba(0,0,0,.10);
            --shadow-lg: 0 20px 60px rgba(0,0,0,.15);
            --radius: 14px;
        }

        html { scroll-behavior: smooth; }

        body {
            font-family: 'Outfit', sans-serif;
            background: var(--white);
            color: var(--ink);
            line-height: 1.6;
            overflow-x: hidden;
        }

        /* ── NAV ── */
        nav {
            position: fixed; top: 0; left: 0; right: 0; z-index: 100;
            display: flex; align-items: center; justify-content: space-between;
            padding: 1rem 5%;
            background: rgba(255,255,255,0.9);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--border);
            transition: box-shadow .3s;
        }
        nav.scrolled { box-shadow: var(--shadow-md); }
        .nav-logo { display: flex; align-items: center; gap: .5rem; text-decoration: none; }
        .nav-logo-icon {
            width: 38px; height: 38px; background: var(--teal);
            border-radius: 10px; display: grid; place-items: center;
            font-size: 1.1rem;
        }
        .nav-logo-text { font-family: 'Playfair Display', serif; font-size: 1.25rem; color: var(--ink); font-weight: 700; }
        .nav-links { display: flex; gap: 2rem; list-style: none; }
        .nav-links a { text-decoration: none; color: var(--muted); font-size: .9rem; font-weight: 500; transition: color .2s; }
        .nav-links a:hover { color: var(--teal); }
        .nav-actions { display: flex; gap: .75rem; align-items: center; }
        .btn { display: inline-flex; align-items: center; gap: .4rem; padding: .6rem 1.25rem; border-radius: 8px; font-family: 'Outfit', sans-serif; font-size: .875rem; font-weight: 600; cursor: pointer; border: none; transition: all .2s; text-decoration: none; }
        .btn-ghost { background: transparent; color: var(--ink-soft); border: 1.5px solid var(--border); }
        .btn-ghost:hover { border-color: var(--teal); color: var(--teal); background: var(--teal-dim); }
        .btn-teal { background: var(--teal); color: #fff; }
        .btn-teal:hover { background: #0a5c55; transform: translateY(-1px); box-shadow: 0 6px 20px rgba(15,118,110,.3); }
        .btn-amber { background: var(--amber); color: #fff; }
        .btn-amber:hover { background: #b45309; transform: translateY(-1px); box-shadow: 0 6px 20px rgba(217,119,6,.3); }
        .btn-full { width: 100%; justify-content: center; padding: .85rem 1.5rem; font-size: .95rem; border-radius: 10px; }
        .btn-lg { padding: .9rem 2rem; font-size: 1rem; }

        /* ── HERO ── */
        .hero {
            min-height: 100vh;
            display: grid; place-items: center;
            padding: 6rem 5% 4rem;
            background:
                radial-gradient(ellipse 80% 60% at 70% 40%, rgba(20,184,166,.08) 0%, transparent 60%),
                radial-gradient(ellipse 50% 40% at 10% 80%, rgba(217,119,6,.05) 0%, transparent 50%),
                linear-gradient(180deg, #f0fdfa 0%, #ffffff 60%);
            position: relative;
            overflow: hidden;
        }
        .hero::before {
            content: '';
            position: absolute; inset: 0;
            background-image: radial-gradient(var(--border) 1px, transparent 1px);
            background-size: 28px 28px;
            opacity: .45;
            pointer-events: none;
        }
        .hero-inner {
            max-width: 1100px; width: 100%;
            display: grid; grid-template-columns: 1fr 1fr; gap: 4rem; align-items: center;
            position: relative; z-index: 1;
        }
        .hero-badge {
            display: inline-flex; align-items: center; gap: .5rem;
            background: var(--teal-dim); color: var(--teal);
            padding: .35rem .85rem; border-radius: 99px; font-size: .8rem; font-weight: 600;
            margin-bottom: 1.25rem; border: 1px solid rgba(20,184,166,.25);
        }
        .hero-badge span { width: 7px; height: 7px; background: var(--teal-light); border-radius: 50%; animation: pulse 2s infinite; }
        @keyframes pulse { 0%,100%{opacity:1} 50%{opacity:.3} }
        .hero h1 {
            font-family: 'Playfair Display', serif;
            font-size: clamp(2.4rem, 4.5vw, 3.4rem);
            line-height: 1.15;
            letter-spacing: -.02em;
            color: var(--ink);
            margin-bottom: 1.25rem;
        }
        .hero h1 em { font-style: italic; color: var(--teal); }
        .hero p {
            font-size: 1.05rem; color: var(--muted);
            max-width: 480px; margin-bottom: 2rem; line-height: 1.75;
        }
        .hero-ctas { display: flex; gap: .75rem; flex-wrap: wrap; }
        .hero-stats {
            display: flex; gap: 2rem; margin-top: 2.5rem;
            padding-top: 2rem; border-top: 1px solid var(--border);
        }
        
        .stat-num { font-family: 'Playfair Display', serif; font-size: 1.6rem; font-weight: 700; color: var(--ink); }
        .stat-label { font-size: .78rem; color: var(--muted); font-weight: 500; text-transform: uppercase; letter-spacing: .06em; }

        /* ── QUEUE CARD (hero visual) ── */
        .hero-visual { display: flex; flex-direction: column; gap: 1rem; }
        .queue-board {
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            box-shadow: var(--shadow-lg);
            overflow: hidden;
        }
        .queue-board-header {
            background: var(--teal); color: #fff;
            padding: 1rem 1.25rem;
            display: flex; justify-content: space-between; align-items: center;
        }
        .queue-board-header h3 { font-size: .9rem; font-weight: 600; }
        .live-dot { display: flex; align-items: center; gap: .4rem; font-size: .75rem; opacity: .85; }
        .live-dot::before { content: ''; width: 7px; height: 7px; background: #6ee7b7; border-radius: 50%; animation: pulse 1.5s infinite; }
        .queue-list { padding: .75rem; display: flex; flex-direction: column; gap: .5rem; }
        .queue-item {
            display: flex; align-items: center; gap: .85rem;
            padding: .75rem 1rem; border-radius: 8px;
            border: 1px solid var(--border);
            transition: all .2s;
        }
        .queue-item.now { background: var(--teal-dim); border-color: rgba(20,184,166,.4); }
        .queue-item.next { background: var(--amber-dim); border-color: rgba(217,119,6,.25); }
        .queue-num {
            width: 34px; height: 34px; border-radius: 8px;
            display: grid; place-items: center; font-weight: 700; font-size: .85rem; flex-shrink: 0;
        }
        .queue-item.now .queue-num { background: var(--teal); color: #fff; }
        .queue-item.next .queue-num { background: var(--amber); color: #fff; }
        .queue-item:not(.now):not(.next) .queue-num { background: var(--surface); color: var(--muted); border: 1px solid var(--border); }
        .queue-info { flex: 1; }
        .queue-name { font-size: .85rem; font-weight: 600; color: var(--ink); }
        .queue-meta { font-size: .75rem; color: var(--muted); }
        .queue-badge { font-size: .7rem; font-weight: 600; padding: .2rem .6rem; border-radius: 99px; }
        .badge-now { background: var(--teal); color: #fff; }
        .badge-next { background: var(--amber); color: #fff; }
        .badge-wait { background: var(--surface); color: var(--muted); border: 1px solid var(--border); }

        .mini-card {
            background: var(--white); border: 1px solid var(--border);
            border-radius: var(--radius); padding: 1rem 1.25rem; box-shadow: var(--shadow-sm);
            display: flex; align-items: center; gap: .85rem;
        }
        .mini-card-icon { font-size: 1.4rem; }
        .mini-card-label { font-size: .75rem; color: var(--muted); font-weight: 500; }
        .mini-card-value { font-size: 1.1rem; font-weight: 700; color: var(--ink); font-family: 'Playfair Display', serif; }

        /* ── FEATURES ── */
        .section { padding: 5rem 5%; max-width: 1200px; margin: 0 auto; }
        .section-tag {
            display: inline-block; font-size: .75rem; font-weight: 700;
            letter-spacing: .1em; text-transform: uppercase;
            color: var(--teal); margin-bottom: .75rem;
        }
        .section-title {
            font-family: 'Playfair Display', serif;
            font-size: clamp(1.8rem, 3vw, 2.5rem);
            color: var(--ink); margin-bottom: .75rem; line-height: 1.2;
        }
        .section-sub { color: var(--muted); max-width: 500px; font-size: .95rem; }
        .features-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem; margin-top: 3rem;
        }
        .feat-card {
            padding: 1.75rem;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            background: var(--white);
            transition: all .25s;
            position: relative; overflow: hidden;
        }
        .feat-card::before {
            content: ''; position: absolute;
            top: 0; left: 0; right: 0; height: 3px;
            background: var(--teal); transform: scaleX(0); transform-origin: left;
            transition: transform .3s;
        }
        .feat-card:hover { box-shadow: var(--shadow-md); transform: translateY(-3px); }
        .feat-card:hover::before { transform: scaleX(1); }
        .feat-icon { font-size: 1.75rem; margin-bottom: 1rem; }
        .feat-title { font-size: 1rem; font-weight: 700; color: var(--ink); margin-bottom: .5rem; }
        .feat-desc { font-size: .875rem; color: var(--muted); line-height: 1.65; }

        /* ── HOW IT WORKS ── */
        .how-section { background: var(--surface); padding: 5rem 5%; }
        .how-inner { max-width: 1100px; margin: 0 auto; }
        .steps-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.5rem; margin-top: 3rem; }
        .step-card { text-align: center; padding: 2rem 1.5rem; }
        .step-num {
            width: 52px; height: 52px; border-radius: 50%;
            background: var(--teal); color: #fff;
            display: grid; place-items: center; margin: 0 auto 1.25rem;
            font-family: 'Playfair Display', serif; font-size: 1.2rem; font-weight: 700;
        }
        .step-title { font-weight: 700; font-size: 1rem; margin-bottom: .5rem; }
        .step-desc { font-size: .85rem; color: var(--muted); line-height: 1.65; }

        /* ── CTA STRIP ── */
        .cta-strip {
            background: linear-gradient(135deg, var(--teal) 0%, #0a5c55 100%);
            padding: 4rem 5%; text-align: center; color: #fff;
        }
        .cta-strip h2 { font-family: 'Playfair Display', serif; font-size: clamp(1.6rem, 3vw, 2.2rem); margin-bottom: .75rem; }
        .cta-strip p { opacity: .85; margin-bottom: 2rem; font-size: .95rem; }
        .cta-buttons { display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap; }
        .btn-white { background: #fff; color: var(--teal); }
        .btn-white:hover { background: var(--teal-dim); }
        .btn-outline-white { background: transparent; color: #fff; border: 1.5px solid rgba(255,255,255,.5); }
        .btn-outline-white:hover { background: rgba(255,255,255,.1); border-color: #fff; }

        /* ── FOOTER ── */
        footer {
            padding: 2rem 5%; background: var(--ink); color: rgba(255,255,255,.5);
            display: flex; justify-content: space-between; align-items: center; font-size: .8rem;
        }
        footer a { color: rgba(255,255,255,.5); text-decoration: none; }
        footer a:hover { color: var(--teal-light); }

        /* ── MODAL ── */
        .modal-overlay {
            display: none; position: fixed; inset: 0; z-index: 200;
            background: rgba(13,17,23,.65); backdrop-filter: blur(4px);
            align-items: center; justify-content: center; padding: 1rem;
        }
        .modal-overlay.open { display: flex; animation: fadeIn .2s; }
        @keyframes fadeIn { from{opacity:0} to{opacity:1} }
        .modal {
            background: var(--white); border-radius: 18px;
            width: 100%; max-width: 440px;
            box-shadow: var(--shadow-lg); position: relative;
            animation: slideUp .25s ease;
        }
        @keyframes slideUp { from{transform:translateY(20px);opacity:0} to{transform:translateY(0);opacity:1} }
        .modal-header {
            padding: 1.75rem 1.75rem 0;
            display: flex; justify-content: space-between; align-items: flex-start;
        }
        .modal-header-info h2 { font-family: 'Playfair Display', serif; font-size: 1.4rem; color: var(--ink); }
        .modal-header-info p { font-size: .82rem; color: var(--muted); margin-top: .25rem; }
        .modal-close {
            width: 32px; height: 32px; border: 1.5px solid var(--border); border-radius: 8px;
            background: none; cursor: pointer; display: grid; place-items: center;
            color: var(--muted); font-size: 1.1rem; transition: all .15s; flex-shrink: 0;
        }
        .modal-close:hover { background: var(--surface); color: var(--ink); }
        .modal-body { padding: 1.5rem 1.75rem 1.75rem; }
        .modal-tabs { display: flex; gap: 0; border: 1.5px solid var(--border); border-radius: 10px; overflow: hidden; margin-bottom: 1.5rem; }
        .modal-tab { flex: 1; padding: .6rem; font-family: 'Outfit', sans-serif; font-size: .85rem; font-weight: 600; border: none; background: none; cursor: pointer; color: var(--muted); transition: all .2s; }
        .modal-tab.active { background: var(--teal); color: #fff; }
        .admin-modal-badge {
            display: flex; align-items: center; gap: .6rem;
            background: var(--amber-dim); border: 1px solid rgba(217,119,6,.3);
            border-radius: 10px; padding: .75rem 1rem; margin-bottom: 1.25rem;
            font-size: .82rem; color: #92400e;
        }
        .form-group { margin-bottom: 1rem; }
        .form-label { display: block; font-size: .8rem; font-weight: 600; color: var(--ink-soft); margin-bottom: .4rem; letter-spacing: .02em; }
        .form-control {
            width: 100%; padding: .7rem 1rem; border: 1.5px solid var(--border);
            border-radius: 8px; font-family: 'Outfit', sans-serif; font-size: .875rem;
            color: var(--ink); background: var(--white); transition: border-color .2s, box-shadow .2s; outline: none;
        }
        .form-control:focus { border-color: var(--teal); box-shadow: 0 0 0 3px rgba(20,184,166,.12); }
        .form-control::placeholder { color: #c0c8d2; }
        .alert { padding: .7rem 1rem; border-radius: 8px; font-size: .83rem; font-weight: 500; margin-bottom: 1rem; }
        .alert-error { background: var(--red-dim); color: var(--red); border: 1px solid rgba(220,38,38,.2); }
        .alert-success { background: var(--green-dim); color: var(--green); border: 1px solid rgba(5,150,105,.2); }
        .demo-hint { font-size: .75rem; color: var(--muted); text-align: center; margin-top: .85rem; background: var(--surface); border-radius: 7px; padding: .5rem .75rem; }
        .demo-hint strong { color: var(--ink-soft); }
        .divider { display: flex; align-items: center; gap: .75rem; margin: 1rem 0; color: var(--muted); font-size: .75rem; }
        .divider::before, .divider::after { content: ''; flex: 1; height: 1px; background: var(--border); }

        @media (max-width: 768px) {
            .hero-inner { grid-template-columns: 1fr; gap: 2.5rem; }
            .hero-visual { display: none; }
            .nav-links { display: none; }
            footer { flex-direction: column; gap: .5rem; text-align: center; }
        }
    </style>
</head>
<body>

<!-- ── NAV ── -->
<nav id="navbar">
    <a class="nav-logo" href="#">
        <div class="nav-logo-icon">🏥</div>
        <span class="nav-logo-text">MediQueue</span>
    </a>
    <ul class="nav-links">
        <li><a href="#features">Features</a></li>
        <li><a href="#how">How it works</a></li>
    </ul>
    <div class="nav-actions">
        <button class="btn btn-ghost" onclick="openModal('admin')">Admin Portal</button>
        <button class="btn btn-teal" onclick="openModal('patient')">Patient Login</button>
    </div>
</nav>

<!-- ── HERO ── -->
<section class="hero">
    <div class="hero-inner">
        <div class="hero-content">
            <div class="hero-badge"><span></span> Live Queue Management</div>
            <h1>No more waiting in the <em>dark</em> at the clinic.</h1>
            <p>MediQueue gives patients real-time queue visibility and lets staff manage appointments effortlessly — from check-in to consultation.</p>
            <div class="hero-ctas">
                <button class="btn btn-teal btn-lg" onclick="openModal('patient')">Get Started Free →</button>
                <a href="#how" class="btn btn-ghost btn-lg">See how it works</a>
            </div>
            <div class="hero-stats">
                <div class="stat-item">
                    <div class="stat-num">12 min</div>
                    <div class="stat-label">Avg wait reduced</div>
                </div>
                <div class="stat-item">
                    <div class="stat-num">98%</div>
                    <div class="stat-label">Patient satisfaction</div>
                </div>
                <div class="stat-item">
                    <div class="stat-num">500+</div>
                    <div class="stat-label">Clinics served</div>
                </div>
            </div>
        </div>

        <div class="hero-visual">
            <div class="queue-board">
                <div class="queue-board-header">
                    <h3>Today's Queue · General OPD</h3>
                    <span class="live-dot">Live</span>
                </div>
                <div class="queue-list">
                    <div class="queue-item now">
                        <div class="queue-num">01</div>
                        <div class="queue-info">
                            <div class="queue-name">Ahmed Raza</div>
                            <div class="queue-meta">General Checkup · Dr. Farooq</div>
                        </div>
                        <span class="queue-badge badge-now">Now</span>
                    </div>
                    <div class="queue-item next">
                        <div class="queue-num">02</div>
                        <div class="queue-info">
                            <div class="queue-name">Sara Malik</div>
                            <div class="queue-meta">Follow-up · Dr. Farooq</div>
                        </div>
                        <span class="queue-badge badge-next">Next</span>
                    </div>
                    <div class="queue-item">
                        <div class="queue-num">03</div>
                        <div class="queue-info">
                            <div class="queue-name">Bilal Khan</div>
                            <div class="queue-meta">~8 min wait</div>
                        </div>
                        <span class="queue-badge badge-wait">Waiting</span>
                    </div>
                    <div class="queue-item">
                        <div class="queue-num">04</div>
                        <div class="queue-info">
                            <div class="queue-name">Nadia Iqbal</div>
                            <div class="queue-meta">~15 min wait</div>
                        </div>
                        <span class="queue-badge badge-wait">Waiting</span>
                    </div>
                </div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
                <div class="mini-card">
                    <div class="mini-card-icon">⏱️</div>
                    <div>
                        <div class="mini-card-label">Avg Wait</div>
                        <div class="mini-card-value">8 min</div>
                    </div>
                </div>
                <div class="mini-card">
                    <div class="mini-card-icon">👥</div>
                    <div>
                        <div class="mini-card-label">In Queue</div>
                        <div class="mini-card-value">4 patients</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ── FEATURES ── -->
<section class="section" id="features">
    <span class="section-tag">Features</span>
    <h2 class="section-title">Everything your clinic needs</h2>
    <p class="section-sub">From front desk to consultation room, MediQueue keeps every part of your workflow connected.</p>
    <div class="features-grid">
        <div class="feat-card">
            <div class="feat-icon">📋</div>
            <div class="feat-title">Smart Queue Booking</div>
            <div class="feat-desc">Patients book slots online in seconds. No paper tokens, no crowded waiting rooms.</div>
        </div>
        <div class="feat-card">
            <div class="feat-icon">🔴</div>
            <div class="feat-title">Live Status Updates</div>
            <div class="feat-desc">Real-time queue position visible to patients — they know exactly when to arrive.</div>
        </div>
        <div class="feat-card">
            <div class="feat-icon">🩺</div>
            <div class="feat-title">Doctor Assignment</div>
            <div class="feat-desc">Admins assign patients to available doctors and manage schedules effortlessly.</div>
        </div>
        <div class="feat-card">
            <div class="feat-icon">📊</div>
            <div class="feat-title">Admin Dashboard</div>
            <div class="feat-desc">Full overview of today's appointments, queue stats, and patient history in one view.</div>
        </div>
        <div class="feat-card">
            <div class="feat-icon">🔔</div>
            <div class="feat-title">Status Notifications</div>
            <div class="feat-desc">Patients get notified when their turn is near — no anxious waiting required.</div>
        </div>
        <div class="feat-card">
            <div class="feat-icon">🔐</div>
            <div class="feat-title">Role-based Access</div>
            <div class="feat-desc">Separate secure portals for patients and administrators with distinct permissions.</div>
        </div>
    </div>
</section>

<!-- ── HOW IT WORKS ── -->
<div class="how-section" id="how">
    <div class="how-inner">
        <span class="section-tag">How it works</span>
        <h2 class="section-title">Simple for patients, powerful for staff</h2>
        <div class="steps-grid">
            <div class="step-card">
                <div class="step-num">1</div>
                <div class="step-title">Create an account</div>
                <div class="step-desc">Patients sign up in under a minute with their name, email, and phone number.</div>
            </div>
            <div class="step-card">
                <div class="step-num">2</div>
                <div class="step-title">Book your slot</div>
                <div class="step-desc">Choose a doctor, select a time, and get added to the live queue instantly.</div>
            </div>
            <div class="step-card">
                <div class="step-num">3</div>
                <div class="step-title">Track in real-time</div>
                <div class="step-desc">Watch your queue position live from your phone — arrive just in time.</div>
            </div>
            <div class="step-card">
                <div class="step-num">4</div>
                <div class="step-title">Get seen faster</div>
                <div class="step-desc">Walk in when it's your turn, consult the doctor, and you're done.</div>
            </div>
        </div>
    </div>
</div>

<!-- ── CTA STRIP ── -->
<div class="cta-strip">
    <h2>Ready to streamline your clinic?</h2>
    <p>Join hundreds of clinics already using MediQueue to save time and delight patients.</p>
    <div class="cta-buttons">
        <button class="btn btn-white btn-lg" onclick="openModal('patient')">Patient Sign Up →</button>
        <button class="btn btn-outline-white btn-lg" onclick="openModal('admin')">Admin Login</button>
    </div>
</div>

<!-- ── FOOTER ── -->
<footer>
    <span>© 2025 MediQueue. All rights reserved.</span>
    <span>Built for clinics that care.</span>
</footer>


<!-- ══════════════════════════════════════════
     PATIENT MODAL (Login + Sign Up tabs)
═══════════════════════════════════════════ -->
<div class="modal-overlay" id="modal-patient" onclick="closeModalOutside(event, 'patient')">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-header-info">
                <h2>Patient Portal</h2>
                <p>Access your queue and appointments</p>
            </div>
            <button class="modal-close" onclick="closeModal('patient')">✕</button>
        </div>
        <div class="modal-body">

            <?php if ($activeModal === 'patient' && $error): ?>
                <div class="alert alert-error"><?= $error ?></div>
            <?php endif; ?>
            <?php if ($activeModal === 'patient' && $success): ?>
                <div class="alert alert-success"><?= $success ?></div>
            <?php endif; ?>

            <div class="modal-tabs">
                <button class="modal-tab <?= $activeTab==='login'?'active':'' ?>" id="ptab-login" onclick="switchPTab('login')">Login</button>
                <button class="modal-tab <?= $activeTab==='signup'?'active':'' ?>" id="ptab-signup" onclick="switchPTab('signup')">Sign Up</button>
            </div>

            <!-- PATIENT LOGIN -->
            <div id="pform-login" style="display:<?= $activeTab==='login'?'block':'none' ?>">
                <form method="POST">
                    <input type="hidden" name="action" value="login">
                    <div class="form-group">
                        <label class="form-label">Email Address</label>
                        <input type="email" name="email" class="form-control" placeholder="you@example.com" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control" placeholder="••••••••" required>
                    </div>
                    <button type="submit" class="btn btn-teal btn-full">Login to my account →</button>
                </form>
                <div class="demo-hint">
                    <strong>Demo:</strong> ahmed@gmail.com / password
                </div>
            </div>

            <!-- PATIENT SIGN UP -->
            <div id="pform-signup" style="display:<?= $activeTab==='signup'?'block':'none' ?>">
                <form method="POST">
                    <input type="hidden" name="action" value="signup">
                    <div class="form-group">
                        <label class="form-label">Full Name</label>
                        <input type="text" name="name" class="form-control" placeholder="Your full name" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email Address</label>
                        <input type="email" name="email" class="form-control" placeholder="you@example.com" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Phone Number</label>
                        <input type="text" name="phone" class="form-control" placeholder="03001234567" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control" placeholder="Min. 6 characters" required>
                    </div>
                    <button type="submit" class="btn btn-teal btn-full">Create my account →</button>
                </form>
            </div>

        </div>
    </div>
</div>


<!-- ══════════════════════════════════════════
     ADMIN MODAL (Login only — no sign up)
═══════════════════════════════════════════ -->
<div class="modal-overlay" id="modal-admin" onclick="closeModalOutside(event, 'admin')">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-header-info">
                <h2>Admin Portal</h2>
                <p>Staff & clinic management access</p>
            </div>
            <button class="modal-close" onclick="closeModal('admin')">✕</button>
        </div>
        <div class="modal-body">

            <?php if ($activeModal === 'admin' && $error): ?>
                <div class="alert alert-error"><?= $error ?></div>
            <?php endif; ?>

            <div class="admin-modal-badge">
                🔒 <span>Restricted access — authorized staff only</span>
            </div>

            <form method="POST">
                <input type="hidden" name="action" value="admin_login">
                <div class="form-group">
                    <label class="form-label">Admin Email</label>
                    <input type="email" name="email" class="form-control" placeholder="admin@clinic.com" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-control" placeholder="••••••••" required>
                </div>
                <button type="submit" class="btn btn-amber btn-full">Access Admin Dashboard →</button>
            </form>
            <div class="demo-hint">
                <strong>Demo:</strong> admin@clinic.com / password
            </div>

        </div>
    </div>
</div>


<script>
// ── Modal open/close ──
function openModal(type) {
    document.getElementById('modal-' + type).classList.add('open');
    document.body.style.overflow = 'hidden';
}
function closeModal(type) {
    document.getElementById('modal-' + type).classList.remove('open');
    document.body.style.overflow = '';
}
function closeModalOutside(e, type) {
    if (e.target === document.getElementById('modal-' + type)) closeModal(type);
}
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        closeModal('patient');
        closeModal('admin');
    }
});

// ── Patient modal tabs ──
function switchPTab(tab) {
    document.getElementById('pform-login').style.display  = tab === 'login'  ? 'block' : 'none';
    document.getElementById('pform-signup').style.display = tab === 'signup' ? 'block' : 'none';
    document.getElementById('ptab-login').classList.toggle('active',  tab === 'login');
    document.getElementById('ptab-signup').classList.toggle('active', tab === 'signup');
}

// ── Auto-open modal if server returned an error/success (after POST) ──
<?php if ($activeModal): ?>
openModal('<?= $activeModal ?>');
<?php if ($activeModal === 'patient' && $activeTab === 'signup'): ?>
switchPTab('signup');
<?php endif; ?>
<?php endif; ?>

// ── Navbar shadow on scroll ──
window.addEventListener('scroll', () => {
    document.getElementById('navbar').classList.toggle('scrolled', window.scrollY > 20);
});
</script>
</body>
</html>