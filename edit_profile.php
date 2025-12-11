<?php
session_start();
include 'includes/config.php';
if (!isset($_SESSION['user_id'])) {
  header("Location: login.php");
  exit();
}

$user_id = $_SESSION['user_id'];

// Fetch current user details
$stmt = $conn->prepare("SELECT name, email FROM users WHERE id=?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Handle form submit
$message = "";
if ($_SERVER["REQUEST_METHOD"] == "POST") {
  $name = trim($_POST['name']);
  $email = trim($_POST['email']);
  $password = trim($_POST['password']);

  if ($name != "" && $email != "") {
    if ($password != "") {
      $hashed = password_hash($password, PASSWORD_DEFAULT);
      $update = $conn->prepare("UPDATE users SET name=?, email=?, password=? WHERE id=?");
      $update->bind_param("sssi", $name, $email, $hashed, $user_id);
    } else {
      $update = $conn->prepare("UPDATE users SET name=?, email=? WHERE id=?");
      $update->bind_param("ssi", $name, $email, $user_id);
    }

    if ($update->execute()) {
      $message = "<div class='success'>✅ Profile updated successfully!</div>";
      $_SESSION['user_name'] = $name;
      $_SESSION['user_email'] = $email;
    } else {
      $message = "<div class='error'>❌ Something went wrong. Try again.</div>";
    }
  } else {
    $message = "<div class='error'>⚠️ Please fill all required fields.</div>";
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Edit Profile | SkillTracker</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
<style>
body {
  font-family: 'Poppins', sans-serif;
  background: linear-gradient(120deg, #0f2027, #203a43, #2c5364);
  color: #fff;
  display: flex;
  justify-content: center;
  align-items: center;
  height: 100vh;
}
.container {
  background: rgba(255,255,255,0.05);
  backdrop-filter: blur(15px);
  padding: 40px;
  border-radius: 20px;
  box-shadow: 0 0 20px rgba(0,255,224,0.2);
  width: 400px;
  text-align: center;
}
h2 {
  color: #00ffe0;
  margin-bottom: 15px;
}
form {
  display: flex;
  flex-direction: column;
  gap: 12px;
}
input {
  padding: 10px;
  border-radius: 10px;
  border: none;
  background: rgba(255,255,255,0.1);
  color: #fff;
}
input::placeholder { color: rgba(255,255,255,0.7); }
button {
  background: linear-gradient(90deg, #00ffe0, #00b3ff);
  border: none;
  padding: 10px;
  border-radius: 25px;
  color: #000;
  font-weight: 600;
  cursor: pointer;
  transition: 0.3s;
}
button:hover { transform: scale(1.05); }
a {
  color: #00ffe0;
  text-decoration: none;
  display: inline-block;
  margin-top: 10px;
}
.success {
  background: rgba(0,255,150,0.1);
  border-left: 4px solid #00ff99;
  padding: 10px;
  margin-bottom: 10px;
  border-radius: 8px;
}
.error {
  background: rgba(255,0,0,0.1);
  border-left: 4px solid #ff5555;
  padding: 10px;
  margin-bottom: 10px;
  border-radius: 8px;
}
</style>
</head>
<body>
<div class="container">
  <h2>👤 Edit Your Profile</h2>
  <?php echo $message; ?>
  <form method="POST">
    <input type="text" name="name" placeholder="Full Name" value="<?php echo htmlspecialchars($user['name']); ?>" required>
    <input type="email" name="email" placeholder="Email Address" value="<?php echo htmlspecialchars($user['email']); ?>" required>
    <input type="password" name="password" placeholder="New Password (optional)">
    <button type="submit">Update Profile</button>
  </form>
  <a href="dashboard.php">← Back to Dashboard</a>
</div>
</body>
</html>
