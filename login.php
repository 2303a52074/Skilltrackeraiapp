<?php
session_start();
include 'includes/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $email = trim($_POST['email']);
  $password = trim($_POST['password']);
  
  $stmt = $conn->prepare("SELECT id, name, password FROM users WHERE email=?");
  $stmt->bind_param("s", $email);
  $stmt->execute();
  $result = $stmt->get_result();

  if ($result->num_rows === 1) {
    $user = $result->fetch_assoc();
    if (password_verify($password, $user['password'])) {
      $_SESSION['user_id'] = $user['id'];
      header("Location: dashboard.php");
      exit;
    } else {
      $error = "Invalid email or password.";
    }
  } else {
    $error = "No account found with this email.";
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login | SkillTracker</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;600;800&display=swap" rel="stylesheet">
<style>
:root {
  --bg1: #02060a;
  --bg2: #04202a;
  --accent: #00ffe0;
  --accent2: #00b3ff;
  --text: #ffffff;
}
* { margin:0; padding:0; box-sizing:border-box; }
body {
  height: 100vh;
  font-family:'Poppins', sans-serif;
  background: radial-gradient(circle at 10% 10%, var(--bg2) 0%, var(--bg1) 60%);
  color: var(--text);
  overflow: hidden;
  display: flex;
  justify-content: center;
  align-items: center;
}

/* particles */
.particle {
  position: absolute;
  width: 6px;
  height: 6px;
  background: var(--accent);
  border-radius: 50%;
  animation: float 6s linear infinite;
  opacity: 0.8;
}
@keyframes float {
  0% { transform: translateY(0) scale(1); opacity: 0.8; }
  50% { transform: translateY(-120px) scale(1.3); opacity: 1; }
  100% { transform: translateY(0) scale(1); opacity: 0.6; }
}

/* container */
.container {
  position: relative;
  z-index: 10;
  background: rgba(255,255,255,0.05);
  backdrop-filter: blur(20px);
  padding: 40px 50px;
  border-radius: 20px;
  text-align: center;
  box-shadow: 0 8px 40px rgba(0,0,0,0.6);
  animation: fadeIn 1.5s ease;
}
@keyframes fadeIn {
  from { opacity: 0; transform: translateY(30px); }
  to { opacity: 1; transform: translateY(0); }
}

/* logo */
.logo {
  font-size: 2.6rem;
  font-weight: 800;
  color: var(--accent);
  text-shadow: 0 0 25px rgba(0,255,224,0.6);
  margin-bottom: 20px;
  animation: glow 2s ease-in-out infinite alternate;
}
@keyframes glow {
  from { text-shadow: 0 0 25px rgba(0,255,224,0.4); }
  to { text-shadow: 0 0 60px rgba(0,255,224,0.9); }
}

/* inputs */
input {
  width: 100%;
  padding: 12px 14px;
  margin: 10px 0;
  border: none;
  border-radius: 12px;
  background: rgba(255,255,255,0.08);
  color: #fff;
  font-size: 1rem;
  outline: none;
}
input::placeholder { color: rgba(255,255,255,0.7); }

button {
  margin-top: 15px;
  width: 100%;
  padding: 12px;
  border: none;
  border-radius: 30px;
  font-weight: 700;
  background: linear-gradient(90deg, var(--accent), var(--accent2));
  color: #000;
  cursor: pointer;
  transition: 0.3s;
}
button:hover {
  transform: translateY(-3px);
  box-shadow: 0 0 25px rgba(0,255,224,0.4);
}

/* link */
.link {
  margin-top: 15px;
  color: rgba(255,255,255,0.8);
  font-size: 0.9rem;
}
.link a {
  color: var(--accent);
  text-decoration: none;
  font-weight: 600;
}

/* error message */
.error {
  color: #ff8080;
  font-size: 0.9rem;
  margin-top: 10px;
}

footer {
  position: absolute;
  bottom: 20px;
  width: 100%;
  text-align: center;
  color: rgba(255,255,255,0.6);
  font-size: 0.9rem;
}
</style>
</head>
<body>
<?php for ($i=0; $i<40; $i++): ?>
<div class="particle" style="top:<?=rand(0,100)?>%; left:<?=rand(0,100)?>%; animation-delay:<?=rand(0,10)/10?>s;"></div>
<?php endfor; ?>

<div class="container">
  <div class="logo">SkillTracker</div>
  <h2>Login</h2>
  <form method="POST">
    <input type="email" name="email" placeholder="Email" required>
    <input type="password" name="password" placeholder="Password" required>
    <button type="submit">Login</button>
  </form>
  <?php if(isset($error)) echo "<div class='error'>$error</div>"; ?>
  <div class="link">Don't have an account? <a href="register.php">Register</a></div>
</div>

<footer>© 2025 SkillTracker — AI-Powered Career Guidance</footer>
</body>
</html>

