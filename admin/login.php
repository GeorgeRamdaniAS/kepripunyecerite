<?php
session_start();
include_once '../config/database.php';
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $user = trim($_POST['username'] ?? '');
    $pass = $_POST['password'] ?? '';
    $stmt = $pdo->prepare("SELECT * FROM admin WHERE username = ?");
    $stmt->execute([$user]);
    $admin = $stmt->fetch();
    if($admin && password_verify($pass, $admin['password'])) {
        session_regenerate_id(true);
        $_SESSION['admin_logged'] = true;
        $_SESSION['admin_id'] = $admin['id'];
        header("Location: index.php");
        exit;
    } else $error = "Username atau password salah!";
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Login Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f4f7f9; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .login-card {
            max-width: 400px;
            margin: 100px auto;
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.08);
            border: 1px solid #eef2f6;
        }
        .login-card h3 { color: #0A3D5C; font-weight: 700; }
        .login-card .btn-primary { background: #1A7A8C; border: none; border-radius: 50px; padding: 10px; font-weight: 600; }
        .login-card .btn-primary:hover { background: #0A3D5C; }
    </style>
</head>
<body>
<div class="container">
    <div class="login-card">
        <h3 class="text-center">🔐 Admin Login</h3>
        <p class="text-center text-muted small">Masuk ke panel admin Kepri Punya Cerite</p>
        <?php if(isset($error)) echo "<div class='alert alert-danger'>$error</div>"; ?>
        <form method="POST">
            <input type="text" name="username" class="form-control mb-3" placeholder="Username" required>
            <input type="password" name="password" class="form-control mb-3" placeholder="Password" required>
            <button class="btn btn-primary w-100">Masuk</button>
        </form>
    </div>
</div>
</body>
</html>