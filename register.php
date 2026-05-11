<?php
/**
 * register.php
 * User registration — styled to match the Cinema Vault dashboard.
 */

require_once 'db.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$error   = '';
$success = '';

if (isset($_POST['register'])) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm']  ?? '';

    if ($username === '' || $password === '') {
        $error = 'Please fill in all fields.';
    } elseif (strlen($username) < 3) {
        $error = 'Username must be at least 3 characters.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        // Check if username already exists
        $check = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $check->bind_param('s', $username);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $error = 'Username is already taken.';
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt   = $conn->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
            $stmt->bind_param('ss', $username, $hashed);
            $stmt->execute();
            header('Location: login.php?registered=1');
            exit();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Cinema Vault — Create Account</title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=Outfit:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
/* ─── RESET & BASE ─────────────────────────────────── */
*, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }

:root {
    --gold:       #d4a853;
    --gold-light: #e8c87a;
    --gold-dim:   rgba(212,168,83,0.15);
    --bg:         #080810;
    --surface:    #0f0f1a;
    --surface2:   #161625;
    --surface3:   #1e1e30;
    --border:     rgba(212,168,83,0.12);
    --text:       #e8e6e0;
    --muted:      #7a7898;
    --danger:     #ef4444;
    --success:    #22c55e;
    --radius:     14px;
}

html { scroll-behavior: smooth; }

body {
    background: var(--bg);
    color: var(--text);
    font-family: 'Outfit', sans-serif;
    font-weight: 400;
    min-height: 100vh;
    overflow-x: hidden;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 24px;
}

/* ─── GRAIN OVERLAY ────────────────────────────────── */
body::before {
    content: '';
    position: fixed;
    inset: 0;
    background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noise'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noise)' opacity='0.04'/%3E%3C/svg%3E");
    pointer-events: none;
    z-index: 0;
    opacity: .5;
}

/* ─── AMBIENT GLOW ORBS ────────────────────────────── */
.glow-orb {
    position: fixed;
    border-radius: 50%;
    filter: blur(120px);
    pointer-events: none;
    z-index: 0;
    opacity: .18;
}
.glow-orb-1 { width:600px; height:600px; background:var(--gold);  top:-200px; left:-150px; }
.glow-orb-2 { width:400px; height:400px; background:#6c3db5; bottom:-100px; right:-100px; }

/* ─── PAGE WRAPPER ─────────────────────────────────── */
.page {
    position: relative;
    z-index: 1;
    width: 100%;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 36px;
}

/* ─── BRAND LOGO ───────────────────────────────────── */
.brand {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
    text-decoration: none;
}

.brand-label {
    font-size: 10px;
    letter-spacing: 4px;
    text-transform: uppercase;
    color: var(--gold);
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 8px;
}
.brand-label::before,
.brand-label::after {
    content: '';
    display: inline-block;
    width: 20px; height: 1px;
    background: var(--gold);
}

.brand-title {
    font-family: 'Playfair Display', serif;
    font-size: clamp(28px, 5vw, 42px);
    font-weight: 900;
    line-height: 1;
    background: linear-gradient(135deg, #fff 30%, var(--gold-light));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

/* ─── CARD ─────────────────────────────────────────── */
.card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 20px;
    padding: 40px;
    width: 100%;
    max-width: 420px;
    box-shadow: 0 24px 80px rgba(0,0,0,0.6);
    position: relative;
    overflow: hidden;
}

/* subtle top shimmer */
.card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 1px;
    background: linear-gradient(90deg, transparent, var(--gold), transparent);
    opacity: .6;
}

.card-header {
    margin-bottom: 28px;
}

.card-eyebrow {
    font-size: 11px;
    letter-spacing: 2px;
    text-transform: uppercase;
    color: var(--gold);
    font-weight: 500;
    margin-bottom: 8px;
}

.card-title {
    font-family: 'Playfair Display', serif;
    font-size: 28px;
    font-weight: 700;
    line-height: 1.1;
}

.card-subtitle {
    font-size: 13px;
    color: var(--muted);
    margin-top: 6px;
}

/* ─── ALERT BANNERS ────────────────────────────────── */
.alert {
    padding: 12px 16px;
    border-radius: 10px;
    font-size: 13px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}
.alert-error {
    background: rgba(239,68,68,.1);
    border: 1px solid rgba(239,68,68,.3);
    color: #fca5a5;
}
.alert-success {
    background: rgba(34,197,94,.1);
    border: 1px solid rgba(34,197,94,.3);
    color: #86efac;
}

/* ─── FORM ─────────────────────────────────────────── */
.form-group {
    margin-bottom: 16px;
    position: relative;
}

.form-label {
    display: block;
    font-size: 11px;
    letter-spacing: 1.5px;
    text-transform: uppercase;
    color: var(--muted);
    margin-bottom: 8px;
    font-weight: 500;
}

.input-wrap {
    position: relative;
}

.input-icon {
    position: absolute;
    left: 14px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--muted);
    pointer-events: none;
    display: flex;
}

.form-input {
    width: 100%;
    padding: 13px 16px 13px 42px;
    background: var(--surface2);
    border: 1px solid var(--border);
    border-radius: 12px;
    color: var(--text);
    font-family: 'Outfit', sans-serif;
    font-size: 14px;
    outline: none;
    transition: border-color .2s, box-shadow .2s, background .2s;
}
.form-input:focus {
    border-color: var(--gold);
    box-shadow: 0 0 0 3px rgba(212,168,83,0.1);
    background: var(--surface3);
}
.form-input::placeholder { color: var(--muted); }

/* password toggle */
.toggle-pw {
    position: absolute;
    right: 14px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    color: var(--muted);
    cursor: pointer;
    padding: 0;
    display: flex;
    transition: color .2s;
}
.toggle-pw:hover { color: var(--text); }

/* strength bar */
.strength-bar {
    display: flex;
    gap: 4px;
    margin-top: 8px;
}
.strength-segment {
    flex: 1;
    height: 3px;
    border-radius: 99px;
    background: var(--surface3);
    transition: background .3s;
}
.strength-label {
    font-size: 11px;
    color: var(--muted);
    margin-top: 4px;
}

/* ─── SUBMIT BUTTON ────────────────────────────────── */
.btn-submit {
    width: 100%;
    padding: 14px;
    margin-top: 8px;
    background: linear-gradient(135deg, var(--gold), #b8892e);
    color: #000;
    border: none;
    border-radius: var(--radius);
    font-family: 'Outfit', sans-serif;
    font-weight: 700;
    font-size: 15px;
    cursor: pointer;
    transition: transform .2s, box-shadow .2s;
    box-shadow: 0 4px 20px rgba(212,168,83,0.3);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    letter-spacing: .5px;
}
.btn-submit:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 28px rgba(212,168,83,0.45);
}
.btn-submit:active { transform: translateY(0); }

/* ─── DIVIDER ──────────────────────────────────────── */
.divider {
    display: flex;
    align-items: center;
    gap: 12px;
    margin: 22px 0;
    color: var(--muted);
    font-size: 12px;
}
.divider::before, .divider::after {
    content: '';
    flex: 1;
    height: 1px;
    background: var(--border);
}

/* ─── BOTTOM LINK ──────────────────────────────────── */
.bottom-link {
    text-align: center;
    font-size: 13px;
    color: var(--muted);
}
.bottom-link a {
    color: var(--gold-light);
    text-decoration: none;
    font-weight: 600;
    transition: color .2s;
}
.bottom-link a:hover { color: var(--gold); text-decoration: underline; }

/* ─── FOOTER ───────────────────────────────────────── */
.footer {
    font-size: 12px;
    color: var(--muted);
    text-align: center;
    opacity: .6;
}

/* ─── SCROLLBAR ────────────────────────────────────── */
::-webkit-scrollbar { width: 6px; }
::-webkit-scrollbar-track { background: var(--bg); }
::-webkit-scrollbar-thumb { background: var(--surface3); border-radius: 99px; }

/* ─── RESPONSIVE ───────────────────────────────────── */
@media(max-width:480px){
    .card { padding: 28px 20px; }
}
</style>
</head>
<body>

<!-- Ambient glows -->
<div class="glow-orb glow-orb-1"></div>
<div class="glow-orb glow-orb-2"></div>

<div class="page">

    <!-- Brand -->
    <a href="index.php" class="brand">
        <div class="brand-label">Cinema Vault</div>
        <div class="brand-title">My Watchlist</div>
    </a>

    <!-- Register Card -->
    <div class="card">

        <div class="card-header">
            <div class="card-eyebrow">New Member</div>
            <div class="card-title">Create your account</div>
            <div class="card-subtitle">Join the vault and start tracking your films.</div>
        </div>

        <!-- Error / Success alerts -->
        <?php if ($error): ?>
        <div class="alert alert-error">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
            </svg>
            <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <form method="POST" action="register.php" novalidate>

            <!-- Username -->
            <div class="form-group">
                <label class="form-label" for="username">Username</label>
                <div class="input-wrap">
                    <span class="input-icon">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/>
                        </svg>
                    </span>
                    <input
                        class="form-input"
                        type="text"
                        id="username"
                        name="username"
                        placeholder="Choose a username"
                        value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                        autocomplete="username"
                        required
                    >
                </div>
            </div>

            <!-- Password -->
            <div class="form-group">
                <label class="form-label" for="password">Password</label>
                <div class="input-wrap">
                    <span class="input-icon">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                            <path d="M7 11V7a5 5 0 0110 0v4"/>
                        </svg>
                    </span>
                    <input
                        class="form-input"
                        type="password"
                        id="password"
                        name="password"
                        placeholder="At least 6 characters"
                        autocomplete="new-password"
                        oninput="checkStrength(this.value)"
                        required
                    >
                    <button type="button" class="toggle-pw" onclick="togglePw('password', this)" aria-label="Show password">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                        </svg>
                    </button>
                </div>
                <!-- Strength bar -->
                <div class="strength-bar" id="strengthBar">
                    <div class="strength-segment" id="seg1"></div>
                    <div class="strength-segment" id="seg2"></div>
                    <div class="strength-segment" id="seg3"></div>
                    <div class="strength-segment" id="seg4"></div>
                </div>
                <div class="strength-label" id="strengthLabel"></div>
            </div>

            <!-- Confirm Password -->
            <div class="form-group">
                <label class="form-label" for="confirm">Confirm Password</label>
                <div class="input-wrap">
                    <span class="input-icon">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M9 12l2 2 4-4"/><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                            <path d="M7 11V7a5 5 0 0110 0v4"/>
                        </svg>
                    </span>
                    <input
                        class="form-input"
                        type="password"
                        id="confirm"
                        name="confirm"
                        placeholder="Repeat your password"
                        autocomplete="new-password"
                        required
                    >
                    <button type="button" class="toggle-pw" onclick="togglePw('confirm', this)" aria-label="Show password">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                        </svg>
                    </button>
                </div>
            </div>

            <button type="submit" name="register" class="btn-submit">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <path d="M16 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/>
                    <circle cx="8.5" cy="7" r="4"/>
                    <line x1="20" y1="8" x2="20" y2="14"/>
                    <line x1="23" y1="11" x2="17" y2="11"/>
                </svg>
                Create Account
            </button>

        </form>

        <div class="divider">or</div>

        <div class="bottom-link">
            Already have an account? <a href="login.php">Sign in now</a>
        </div>

    </div>

    <div class="footer">Cinema Vault &copy; <?= date('Y') ?> &mdash; All rights reserved</div>

</div><!-- /.page -->

<script>
/* ── Password visibility toggle ────────────────────── */
function togglePw(fieldId, btn) {
    const input = document.getElementById(fieldId);
    const isText = input.type === 'text';
    input.type = isText ? 'password' : 'text';
    // swap icon: eye vs eye-off
    btn.innerHTML = isText
        ? `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/></svg>`
        : `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>`;
}

/* ── Password strength meter ───────────────────────── */
function checkStrength(val) {
    let score = 0;
    if (val.length >= 6)  score++;
    if (val.length >= 10) score++;
    if (/[A-Z]/.test(val) && /[0-9]/.test(val)) score++;
    if (/[^A-Za-z0-9]/.test(val)) score++;

    const colors  = ['', '#ef4444', '#f59e0b', '#22c55e', '#16a34a'];
    const labels  = ['', 'Weak', 'Fair', 'Strong', 'Very Strong'];
    const segs    = document.querySelectorAll('.strength-segment');
    const label   = document.getElementById('strengthLabel');

    segs.forEach((s, i) => {
        s.style.background = i < score ? colors[score] : 'var(--surface3)';
    });

    label.textContent    = val.length ? labels[score] : '';
    label.style.color    = colors[score] || 'var(--muted)';
}
</script>

</body>
</html>