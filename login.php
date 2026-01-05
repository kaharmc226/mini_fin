<?php
require_once __DIR__ . '/helpers.php';

if (isset($_SESSION['user'])) {
    header('Location: dashboard.php');
    exit;
}

$email = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Email tidak valid.';
    }

    if (!$password) {
        $errors[] = 'Password wajib diisi.';
    }

    if (!$errors) {
        $pdo = get_pdo();
        $stmt = $pdo->prepare('SELECT id, name, email, password_hash FROM users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user'] = ['id' => $user['id'], 'name' => $user['name'], 'email' => $user['email']];
            redirect_with_message('dashboard.php', 'Selamat datang kembali, ' . $user['name'] . '!');
        } else {
            $errors[] = 'Email atau password salah.';
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login | MiniFin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body class="bg-light">
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="card shadow-sm border-0">
                <div class="card-body p-4">
                    <h1 class="h4 mb-3 text-center">Monitoring Keuangan</h1>
                    <?php if ($errors): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo htmlspecialchars($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    <form method="post" class="d-grid gap-3">
                        <div>
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control form-control-lg" value="<?php echo htmlspecialchars($email); ?>" required placeholder="nama@email.com">
                        </div>
                        <div>
                            <label class="form-label">Password</label>
                            <input type="password" name="password" class="form-control form-control-lg" required placeholder="Minimal 6 karakter">
                        </div>
                        <button type="submit" class="btn btn-primary btn-lg w-100">Masuk</button>
                        <p class="text-center mb-0">Belum punya akun? <a href="register.php">Daftar</a></p>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
