<?php
session_start();
include 'koneksi.php';

// Cek jika sudah login, alihkan ke dashboard yang sesuai
if (isset($_SESSION['level'])) {
    if ($_SESSION['level'] == 'admin') {
        header('Location: dashboard.php');
        exit();
    } elseif ($_SESSION['level'] == 'user') {
        // Mengarahkan ke dashboard user baru
        header('Location: user_dashboard.php'); 
        exit();
    }
}

$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];

    // Daftar Admin (5 Akun)
    $admins = [
        'admin1' => 'admin1',
        'admin2' => 'admin2',
        'admin3' => 'admin3',
        'admin4' => 'admin4',
        'admin5' => 'admin5'
    ];

    // Daftar User (5 Akun)
    $users = [
        'user1' => 'user1',
        'user2' => 'user2',
        'user3' => 'user3',
        'user4' => 'user4',
        'user5' => 'user5'
    ];

    if (array_key_exists($username, $admins) && $admins[$username] === $password) {
        // Login Admin Berhasil
        $_SESSION['level'] = 'admin';
        $_SESSION['user'] = $username;
        header('Location: dashboard.php');
        exit();
    } elseif (array_key_exists($username, $users) && $users[$username] === $password) {
        // Login User Berhasil
        $_SESSION['level'] = 'user';
        $_SESSION['user'] = $username;
        header('Location: user_dashboard.php');
        exit();
    } else {
        $error = "Username atau Password salah.";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login BoBall System</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="style.css"> 
    <style>
        body { background-color: #007bff; }
        .card-login { max-width: 400px; margin-top: 100px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="card card-login shadow-lg mx-auto">
            <div class="card-header bg-success text-white text-center">
                <h4>⚽ Login BoBall System</h4>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                <form action="login.php" method="POST">
                    <div class="mb-3">
                        <label for="username" class="form-label">Username</label>
                        <input type="text" class="form-control" id="username" name="username" required>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Login</button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>