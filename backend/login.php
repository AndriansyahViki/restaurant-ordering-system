<?php
// login.php
require_once '../config.php';
session_start();

// Perbaikan: Cek session yang benar
if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

// === TAMBAH INI: AUTO-CREATE USER ===
$conn = getConnection();
$check_admin = $conn->query("SELECT * FROM users WHERE role = 'admin' LIMIT 1");
if ($check_admin->num_rows == 0) {
    $admin_email = "admin@resto.com";
    $admin_password = "admin123";
    $admin_password_hash = password_hash($admin_password, PASSWORD_DEFAULT);
    
    $stmt = $conn->prepare("INSERT INTO users (name, email, password, role) VALUES ('Administrator', ?, ?, 'admin')");
    $stmt->bind_param("ss", $admin_email, $admin_password_hash);
    $stmt->execute();
    echo "<!-- DEBUG: Admin user created -->";
}

$check_cashier = $conn->query("SELECT * FROM users WHERE role = 'cashier' LIMIT 1");
if ($check_cashier->num_rows == 0) {
    $cashier_email = "cashier@resto.com";
    $cashier_password = "cashier123";
    $cashier_password_hash = password_hash($cashier_password, PASSWORD_DEFAULT);
    
    $stmt = $conn->prepare("INSERT INTO users (name, email, password, role) VALUES ('Cashier', ?, ?, 'cashier')");
    $stmt->bind_param("ss", $cashier_email, $cashier_password_hash);
    $stmt->execute();
    echo "<!-- DEBUG: Cashier user created -->";
}
$conn->close();
// === END AUTO-CREATE ===

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    // Validasi input
    if (empty($username) || empty($password)) {
        $err = "Username dan password harus diisi.";
    } else {
        $conn = getConnection();
        $stmt = $conn->prepare("SELECT user_id, name, email, password, role FROM users WHERE email = ? OR name = ? LIMIT 1");
        $stmt->bind_param("ss", $username, $username);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($res->num_rows === 1) {
            $user = $res->fetch_assoc();
            
            // === TAMBAH DEBUG DI SINI ===
            echo "<!-- DEBUG: Input password: $password -->";
            echo "<!-- DEBUG: Stored password: " . $user['password'] . " -->";
            echo "<!-- DEBUG: Password length: " . strlen($user['password']) . " -->";
            echo "<!-- DEBUG: Password verify result: " . (password_verify($password, $user['password']) ? 'TRUE' : 'FALSE') . " -->";
            
            if (password_verify($password, $user['password'])) {
                // login success
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['name'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['user_role'] = $user['role']; // Tambahkan ini juga
                $_SESSION['logged_in'] = true;
                
                echo "<!-- DEBUG: Login successful, redirecting... -->";
                header('Location: ../index.php');
                exit;
            } else {
                $err = "Password salah.";
            }
        } else {
            $err = "User tidak ditemukan.";
        }
        $conn->close();
    }
}

?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Silahkan Login</title>
<link rel="stylesheet" href="../CSS/style.css">
<style>
    .login-page {
        background: #f5f5f5;
        font-family: Arial, sans-serif;
        margin: 0;
        padding: 0;
        display: flex;
        justify-content: center;
        align-items: center;
        min-height: 100vh;
    }
    .login-box {
        background: white;
        padding: 2rem;
        border-radius: 8px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        width: 100%;
        max-width: 400px;
    }
    .login-box h2 {
        margin-bottom: 1.5rem;
        text-align: center;
        color: #333;
    }
    .login-box input {
        width: 100%;
        padding: 10px;
        margin: 8px 0;
        border: 1px solid #ddd;
        border-radius: 4px;
        box-sizing: border-box;
    }
    .login-box button {
        width: 100%;
        padding: 10px;
        background: #007bff;
        color: white;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        margin-top: 1rem;
    }
    .login-box button:hover {
        background: #0056b3;
    }
    .error {
        color: red;
        background: #ffeaea;
        padding: 10px;
        border-radius: 4px;
        margin-bottom: 1rem;
        text-align: center;
    }
    .login-box p {
        margin: 10px 0;
        font-size: 0.9rem;
        color: #666;
    }
    .register-link {
    text-align: center;
    margin-top: 1rem;
    padding-top: 1rem;
    border-top: 1px solid #eee;
}
.register-link a {
    color: #28a745;
    text-decoration: none;
    font-weight: bold;
}
.register-link a:hover {
    text-decoration: underline;
}
</style>
</head>
<body class="login-page">
<div class="login-box">
    <h2>Silahkan Login</h2>
    <?php if($err): ?>
        <div class="error"><?=htmlspecialchars($err)?></div>
    <?php endif; ?>
    <form method="post">
        <input type="text" name="username" placeholder="Email atau Username" required>
        <input type="password" name="password" placeholder="Password" required>
        <button type="submit">Login</button>
    </form>
<div class="register-link">
    Belum punya akun? <a href="register.php">Daftar di sini</a>
</div>

    <!-- <p>Admin sample: <b>admin@</b> / admin123</p>
    <p>Cashier sample: <b>cashier@.local</b> / cashier123</p> -->
</div>
</body>
</html>