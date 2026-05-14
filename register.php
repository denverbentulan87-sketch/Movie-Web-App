<?php
include 'db.php';
session_start();

$error = $success = '';
if (isset($_POST['register'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $confirm  = $_POST['confirm'];

    if (strlen($username) < 3) {
        $error = 'Username must be at least 3 characters.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $check = $conn->prepare("SELECT user_id FROM users WHERE username=?");
        $check->bind_param("s", $username);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $error = 'Username already taken.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (username, password) VALUES (?,?)");
            $stmt->bind_param("ss", $username, $hash);
            $stmt->execute();
            header("Location: login.php?registered=1");
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
<title>Cinema Vault — Register</title>
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Outfit:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
:root{
  --gold:#d6a85f;
  --gold-soft:#f2cf8f;
  --bg:#07070c;
  --card:#0f1118cc;
  --border:rgba(255,255,255,.06);
  --text:#f5f5f5;
  --muted:#9ca3af;
}

*{
  margin:0;
  padding:0;
  box-sizing:border-box;
}

body{
  min-height:100vh;
  font-family:'Outfit',sans-serif;
  background:
    linear-gradient(rgba(0,0,0,.78),rgba(0,0,0,.82)),
    url('https://images.unsplash.com/photo-1517604931442-7e0c8ed2963c?q=80&w=1974&auto=format&fit=crop') center/cover no-repeat;
  display:flex;
  flex-direction:column;
  overflow:hidden;
  color:var(--text);
}

body::before{
  content:'';
  position:fixed;
  inset:0;
  background:
    radial-gradient(circle at top left, rgba(214,168,95,.16), transparent 28%),
    radial-gradient(circle at bottom right, rgba(99,102,241,.14), transparent 25%);
  pointer-events:none;
}

.nav{
  position:relative;
  z-index:2;
  padding:28px 5%;
  display:flex;
  align-items:center;
  justify-content:space-between;
}

.logo{
  text-decoration:none;
  font-family:'Bebas Neue',sans-serif;
  font-size:2.6rem;
  letter-spacing:3px;
  color:var(--gold);
}

.center{
  flex:1;
  display:flex;
  align-items:center;
  justify-content:center;
  position:relative;
  z-index:2;
  padding:20px;
}

.card{
  width:min(440px,95vw);
  padding:50px 42px;
  border-radius:28px;
  background:var(--card);
  backdrop-filter:blur(20px);
  border:1px solid var(--border);
  box-shadow:
    0 10px 40px rgba(0,0,0,.45),
    0 0 0 1px rgba(255,255,255,.02);
}

.card-top{
  color:var(--gold);
  font-size:.78rem;
  font-weight:600;
  letter-spacing:3px;
  margin-bottom:14px;
  text-transform:uppercase;
}

h1{
  font-size:2.8rem;
  line-height:1.05;
  margin-bottom:10px;
  font-weight:700;
  font-family:Georgia,serif;
  color:white;
}

.subtitle{
  color:var(--muted);
  font-size:.96rem;
  margin-bottom:34px;
}

.form-group{
  margin-bottom:18px;
}

label{
  display:block;
  margin-bottom:8px;
  color:#c4c4c4;
  font-size:.78rem;
  font-weight:500;
  letter-spacing:1px;
  text-transform:uppercase;
}

input{
  width:100%;
  height:56px;
  border:none;
  outline:none;
  border-radius:16px;
  padding:0 18px;
  background:rgba(255,255,255,.04);
  border:1px solid rgba(255,255,255,.05);
  color:white;
  font-size:.96rem;
  transition:.25s ease;
}

input::placeholder{
  color:#777;
}

input:focus{
  border-color:rgba(214,168,95,.7);
  box-shadow:0 0 0 4px rgba(214,168,95,.12);
  background:rgba(255,255,255,.06);
}

.btn-submit{
  width:100%;
  height:58px;
  border:none;
  border-radius:18px;
  margin-top:12px;
  background:linear-gradient(135deg,var(--gold-soft),var(--gold));
  color:#111;
  font-size:1rem;
  font-weight:700;
  cursor:pointer;
  transition:.25s ease;
}

.btn-submit:hover{
  transform:translateY(-2px);
  box-shadow:0 12px 30px rgba(214,168,95,.3);
}

.error{
  background:rgba(255,0,0,.08);
  border:1px solid rgba(255,0,0,.18);
  color:#ff8c8c;
  padding:14px;
  border-radius:14px;
  margin-bottom:20px;
  font-size:.9rem;
}

.divider{
  text-align:center;
  color:#666;
  margin:26px 0;
  position:relative;
}

.divider::before,
.divider::after{
  content:'';
  position:absolute;
  top:50%;
  width:38%;
  height:1px;
  background:rgba(255,255,255,.06);
}

.divider::before{ left:0; }
.divider::after{ right:0; }

.link{
  display:block;
  text-align:center;
  text-decoration:none;
  color:#aaa;
  font-size:.94rem;
}

.link span{
  color:var(--gold);
  font-weight:600;
}

.link:hover span{
  text-decoration:underline;
}

@media(max-width:500px){

  .card{
    padding:38px 28px;
    border-radius:24px;
  }

  h1{
    font-size:2.2rem;
  }

  .logo{
    font-size:2rem;
  }
}
</style>
</head>
<body>
<nav class="nav"><a class="logo" href="login.php">CINEMA VAULT</a></nav>
<div class="center">
  <div class="card">
    <div class="card-top">Join Cinema Vault</div>
<h1>Create your account</h1>
<p class="subtitle">Build your personal movie collection and watchlist.</p>
    <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <form method="POST">
      <div class="form-group">
        <label>Username</label>
        <input type="text" name="username" required autofocus>
      </div>
      <div class="form-group">
        <label>Password</label>
        <input type="password" name="password" required>
      </div>
      <div class="form-group">
        <label>Confirm Password</label>
        <input type="password" name="confirm" required>
      </div>
      <button type="submit" name="register" class="btn-submit">Create Account</button>
    </form>
    <div class="divider">— or —</div>
    <a class="link" href="login.php">Already have an account? <span>Sign In</span></a>
  </div>
</div>
</body>
</html>