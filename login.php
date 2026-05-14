<?php
include 'db.php';
session_start();

if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$error = '';
if (isset($_POST['login'])) {
    $stmt = $conn->prepare("SELECT user_id, username, password FROM users WHERE username=?");
    $stmt->bind_param("s", $_POST['username']);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        if (password_verify($_POST['password'], $row['password'])) {
            $_SESSION['user_id']  = $row['user_id'];
            $_SESSION['username'] = $row['username'];
            header("Location: index.php");
            exit();
        }
    }
    $error = 'Invalid username or password.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Cinema Vault — Sign In</title>
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
    url('https://images.unsplash.com/photo-1489599849927-2ee91cede3ba?q=80&w=1974&auto=format&fit=crop') center/cover no-repeat;
  display:flex;
  flex-direction:column;
  overflow:hidden;
  color:var(--text);
}

/* cinematic glow */
body::before{
  content:'';
  position:fixed;
  inset:0;
  background:
    radial-gradient(circle at top left, rgba(214,168,95,.16), transparent 28%),
    radial-gradient(circle at bottom right, rgba(99,102,241,.14), transparent 25%);
  pointer-events:none;
}

/* top nav */
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

/* center layout */
.center{
  flex:1;
  display:flex;
  align-items:center;
  justify-content:center;
  position:relative;
  z-index:2;
  padding:20px;
}

/* card */
.card{
  width:min(430px,95vw);
  padding:50px 42px;
  border-radius:28px;
  background:var(--card);
  backdrop-filter:blur(20px);
  border:1px solid var(--border);
  box-shadow:
    0 10px 40px rgba(0,0,0,.45),
    0 0 0 1px rgba(255,255,255,.02);
}

/* mini heading */
.card-top{
  color:var(--gold);
  font-size:.78rem;
  font-weight:600;
  letter-spacing:3px;
  margin-bottom:14px;
  text-transform:uppercase;
}

/* title */
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

/* forms */
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

/* button */
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

/* error */
.error{
  background:rgba(255,0,0,.08);
  border:1px solid rgba(255,0,0,.18);
  color:#ff8c8c;
  padding:14px;
  border-radius:14px;
  margin-bottom:20px;
  font-size:.9rem;
}

/* divider */
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

/* links */
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

/* responsive */
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
<nav class="nav"><a class="logo" href="#">CINEMA VAULT</a></nav>
<div class="center">
  <div class="card">
    <div class="card-top">Welcome Back</div>
<h1>Sign in to your vault</h1>
<p class="subtitle">Continue tracking your favorite movies and watchlists.</p>
    <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <form method="POST">
      <div class="form-group">
        <label>Username</label>
        <input type="text" name="username" required autofocus>
      </div>
      <div class="form-group">
  <label>Password</label>

  <div style="position:relative;">
    <input type="password" name="password" id="password" required>

    <button type="button" id="togglePassword"
  style="
    position:absolute;
    right:16px;
    top:50%;
    transform:translateY(-50%);
    background:none;
    border:none;
    cursor:pointer;
    padding:0;
    display:flex;
    align-items:center;
    justify-content:center;
  ">

  <svg id="eyeIcon" xmlns="http://www.w3.org/2000/svg"
    width="24"
    height="24"
    viewBox="0 0 24 24"
    fill="none"
    stroke="#bdbdbd"
    stroke-width="2"
    stroke-linecap="round"
    stroke-linejoin="round">

    <path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z"></path>
    <circle cx="12" cy="12" r="3"></circle>

  </svg>
</button>
  </div>
</div>
      <button type="submit" name="login" class="btn-submit">Sign In</button>
    </form>
    <div class="divider">— or —</div>
    <a class="link" href="register.php">New here? <span>Create an account</span></a>
  </div>
</div>
</body>
<script>
const password = document.getElementById('password');
const togglePassword = document.getElementById('togglePassword');
const eyeIcon = document.getElementById('eyeIcon');

togglePassword.addEventListener('click', function () {

  const type =
    password.getAttribute('type') === 'password'
      ? 'text'
      : 'password';

  password.setAttribute('type', type);

  if(type === 'text'){
    eyeIcon.innerHTML = `
      <path d="M17.94 17.94A10.94 10.94 0 0 1 12 19
      c-7 0-11-7-11-7a21.77 21.77 0 0 1 5.06-5.94"></path>

      <path d="M1 1l22 22"></path>

      <path d="M9.53 9.53a3 3 0 0 0 4.24 4.24"></path>

      <path d="M14.47 14.47L9.53 9.53"></path>
    `;
  } else {
    eyeIcon.innerHTML = `
      <path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z"></path>
      <circle cx="12" cy="12" r="3"></circle>
    `;
  }
});
</script>
</html>