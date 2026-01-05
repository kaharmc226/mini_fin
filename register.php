<?php
require_once __DIR__ . '/helpers.php';

if (isset($_SESSION['user'])) {
    header('Location: dashboard.php');
    exit;
}

$name = '';
$email = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($name === '') {
        $errors[] = 'Nama wajib diisi.';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Email tidak valid.';
    }

    if (strlen($password) < 6) {
        $errors[] = 'Password minimal 6 karakter.';
    }

    if (!$errors) {
        $pdo = get_pdo();
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);

        if ($stmt->fetch()) {
            $errors[] = 'Email sudah digunakan.';
        } else {
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $insert = $pdo->prepare('INSERT INTO users (name, email, password_hash) VALUES (:name, :email, :password_hash)');
            $insert->execute([
                'name' => $name,
                'email' => $email,
                'password_hash' => $passwordHash,
            ]);
            redirect_with_message('login.php', 'Registrasi berhasil, silakan masuk.');
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Daftar | MiniFin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body class="bg-light">
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="card shadow-sm border-0">
                <div class="card-body p-4">
                    <h1 class="h4 mb-3 text-center">Buat Akun Baru</h1>
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
                            <label class="form-label">Nama</label>
                            <input type="text" name="name" class="form-control form-control-lg" value="<?php echo htmlspecialchars($name); ?>" required placeholder="Nama panggilan Anda">
                        </div>
                        <div>
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control form-control-lg" value="<?php echo htmlspecialchars($email); ?>" required placeholder="nama@email.com">
                        </div>
                        <div>
                            <label class="form-label">Password</label>
                            <input type="password" name="password" class="form-control form-control-lg" required placeholder="Minimal 6 karakter">
                        </div>
                        <button type="submit" class="btn btn-primary btn-lg w-100">Daftar</button>
                        <p class="text-center mb-0">Sudah punya akun? <a href="login.php">Masuk</a></p>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
