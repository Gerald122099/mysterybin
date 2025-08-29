<?php
session_start();
include 'db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $query = $conn->prepare("SELECT * FROM admins WHERE username=?");
    $query->bind_param("s", $username);
    $query->execute();
    $result = $query->get_result();
    $user = $result->fetch_assoc();

    if ($user && $password === $user['password']) {
        $_SESSION['admin'] = $username;
        header("Location: dashboard.php");
    } else {
        $error = "Invalid username or password";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin Login</title>
    <style>
        body { display:flex; justify-content:center; align-items:center; height:100vh; background:#e8f5e9; font-family:Arial, sans-serif; }
        .login-box { background:white; padding:30px; border-radius:10px; box-shadow:0 2px 8px rgba(0,0,0,0.2); width:300px; text-align:center; }
        .login-box h2 { margin-bottom:20px; color:#2e7d32; }
        .login-box input, .login-box button { width:100%; padding:10px; margin:10px 0; border:1px solid #ccc; border-radius:5px; }
        .login-box button { background:#2e7d32; color:white; border:none; cursor:pointer; font-weight:bold; }
        .login-box button:hover { background:#1b5e20; }
        .error { color:red; margin-top:10px; }
    </style>
</head>
<body>
    <form method="post" class="login-box">
        <h2>Admin Login</h2>
        <input type="text" name="username" placeholder="Username" required>
        <input type="password" name="password" placeholder="Password" required>
        <button type="submit">Login</button>
        <?php if (isset($error)) echo "<p class='error'>$error</p>"; ?>
    </form>
</body>
</html>
