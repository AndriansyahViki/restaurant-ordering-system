<?php
require_once '../config.php';

// Jika sudah login, redirect ke index
if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$success = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $role = 'user'; // Default role untuk user baru

    // Validasi input
    if (empty($name) || empty($email) || empty($password)) {
        $err = "Semua field harus diisi.";
    } elseif ($password !== $confirm_password) {
        $err = "Password dan konfirmasi password tidak cocok.";
    } elseif (strlen($password) < 6) {
        $err = "Password minimal 6 karakter.";
    } else {
        $conn = getConnection();
        
        // Cek apakah email sudah terdaftar
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($res->num_rows > 0) {
            $err = "Email sudah terdaftar.";
        } else {
            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Insert user baru
            $stmt = $conn->prepare("INSERT INTO users (name, email, password, role, created_at) VALUES (?, ?, ?, ?, NOW())");
            $stmt->bind_param("ssss", $name, $email, $hashed_password, $role);
            
            if ($stmt->execute()) {
                $success = "Registrasi berhasil! Silakan login.";
                
                // Auto login setelah registrasi (optional)
                // $user_id = $conn->insert_id;
                // $_SESSION['user_id'] = $user_id;
                // $_SESSION['username'] = $name;
                // $_SESSION['role'] = $role;
                // $_SESSION['logged_in'] = true;
                // header('Location: index.php');
                // exit;
            } else {
                $err = "Terjadi kesalahan saat registrasi. Silakan coba lagi.";
            }
        }
        $conn->close();
    }
}
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Registrasi Akun</title>
<style>
    .register-page {
        background: #f5f5f5;
        font-family: Arial, sans-serif;
        margin: 0;
        padding: 0;
        display: flex;
        justify-content: center;
        align-items: center;
        min-height: 100vh;
    }
    .register-box {
        background: white;
        padding: 2rem;
        border-radius: 8px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        width: 100%;
        max-width: 400px;
    }
    .register-box h2 {
        margin-bottom: 1.5rem;
        text-align: center;
        color: #333;
    }
    .register-box input {
        width: 100%;
        padding: 10px;
        margin: 8px 0;
        border: 1px solid #ddd;
        border-radius: 4px;
        box-sizing: border-box;
    }
    .register-box button {
        width: 100%;
        padding: 10px;
        background: #28a745;
        color: white;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        margin-top: 1rem;
        font-weight: bold;
    }
    .register-box button:hover {
        background: #218838;
    }
    .error {
        color: red;
        background: #ffeaea;
        padding: 10px;
        border-radius: 4px;
        margin-bottom: 1rem;
        text-align: center;
    }
    .success {
        color: #155724;
        background: #d4edda;
        padding: 10px;
        border-radius: 4px;
        margin-bottom: 1rem;
        text-align: center;
    }
    .register-box p {
        margin: 10px 0;
        font-size: 0.9rem;
        color: #666;
    }
    .login-link {
        text-align: center;
        margin-top: 1rem;
    }
    .login-link a {
        color: #007bff;
        text-decoration: none;
    }
    .login-link a:hover {
        text-decoration: underline;
    }
    .form-group {
        margin-bottom: 15px;
    }
    .form-group label {
        display: block;
        margin-bottom: 5px;
        font-weight: bold;
        color: #333;
    }
</style>
</head>
<body class="register-page">
<div class="register-box">
    <h2>Registrasi Akun Baru</h2>
    
    <?php if($success): ?>
        <div class="success"><?=htmlspecialchars($success)?></div>
    <?php endif; ?>
    
    <?php if($err): ?>
        <div class="error"><?=htmlspecialchars($err)?></div>
    <?php endif; ?>
    
    <form method="post">
        <div class="form-group">
            <label for="name">Nama Lengkap:</label>
            <input type="text" id="name" name="name" placeholder="Masukkan nama lengkap" 
                   value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>" required>
        </div>
        
        <div class="form-group">
            <label for="email">Email:</label>
            <input type="email" id="email" name="email" placeholder="Masukkan email" 
                   value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
        </div>
        
        <div class="form-group">
            <label for="password">Password:</label>
            <input type="password" id="password" name="password" placeholder="Minimal 6 karakter" required>
        </div>
        
        <div class="form-group">
            <label for="confirm_password">Konfirmasi Password:</label>
            <input type="password" id="confirm_password" name="confirm_password" placeholder="Ulangi password" required>
        </div>
        
        <button type="submit">Daftar Sekarang</button>
    </form>
    
    <div class="login-link">
        Sudah punya akun? <a href="login.php">Login di sini</a>
    </div>
    
    <div class="guest-link">
        <a href="../index.php">↶ Lanjutkan sebagai Guest</a>
    </div>
</div>

<script>
// Validasi password match
document.getElementById('confirm_password').addEventListener('input', function() {
    const password = document.getElementById('password').value;
    const confirmPassword = this.value;
    
    if (confirmPassword !== '' && password !== confirmPassword) {
        this.style.borderColor = 'red';
    } else {
        this.style.borderColor = '#ddd';
    }
});
</script>
</body>
</html>