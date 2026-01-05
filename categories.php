<?php
require_once __DIR__ . '/header.php';

$pdo = get_pdo();
$userId = require_user_id();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_id'])) {
        $delete = $pdo->prepare('DELETE FROM categories WHERE id = :id AND user_id = :user_id');
        $delete->execute(['id' => $_POST['delete_id'], 'user_id' => $userId]);
        redirect_with_message('categories.php', 'Kategori dihapus.', 'info');
    }

    $name = trim($_POST['name'] ?? '');
    $type = $_POST['type'] ?? '';
    $categoryId = $_POST['id'] ?? null;

    if ($name === '') {
        $errors[] = 'Nama kategori wajib diisi.';
    }
    if (!in_array($type, ['income', 'expense'], true)) {
        $errors[] = 'Tipe kategori harus income atau expense.';
    }

    if (!$errors) {
        if ($categoryId) {
            $update = $pdo->prepare('UPDATE categories SET name = :name, type = :type WHERE id = :id AND user_id = :user_id');
            $update->execute(['name' => $name, 'type' => $type, 'id' => $categoryId, 'user_id' => $userId]);
            redirect_with_message('categories.php', 'Kategori diperbarui.');
        } else {
            $insert = $pdo->prepare('INSERT INTO categories (user_id, name, type) VALUES (:user_id, :name, :type)');
            $insert->execute(['user_id' => $userId, 'name' => $name, 'type' => $type]);
            redirect_with_message('categories.php', 'Kategori ditambahkan.');
        }
    }
}

$editId = $_GET['edit'] ?? null;
$editingCategory = null;
if ($editId) {
    $stmt = $pdo->prepare('SELECT * FROM categories WHERE id = :id AND user_id = :user_id');
    $stmt->execute(['id' => $editId, 'user_id' => $userId]);
    $editingCategory = $stmt->fetch();
    if (!$editingCategory) {
        redirect_with_message('categories.php', 'Kategori tidak ditemukan.', 'danger');
    }
}

$incomeCategories = fetch_categories('income');
$expenseCategories = fetch_categories('expense');
?>
<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-3">
    <div>
        <h1 class="h3 mb-1">Kategori</h1>
        <p class="text-muted mb-0">Kelola kategori income dan expense agar pencatatan lebih rapih.</p>
    </div>
</div>
<div class="row g-3">
    <div class="col-lg-5">
        <div class="card shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2 class="h5 mb-0"><?php echo $editingCategory ? 'Edit Kategori' : 'Tambah Kategori'; ?></h2>
                    <?php if ($editingCategory): ?>
                        <a href="categories.php" class="btn btn-sm btn-outline-secondary">Batal</a>
                    <?php endif; ?>
                </div>
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
                    <input type="hidden" name="id" value="<?php echo htmlspecialchars($editingCategory['id'] ?? ''); ?>">
                    <div>
                        <label class="form-label">Nama Kategori</label>
                        <input type="text" name="name" class="form-control form-control-lg" value="<?php echo htmlspecialchars($editingCategory['name'] ?? ''); ?>" required placeholder="Contoh: Gaji, Makan, Transport">
                    </div>
                    <div>
                        <label class="form-label">Tipe</label>
                        <div class="btn-group w-100" role="group">
                            <input type="radio" class="btn-check" name="type" id="income" value="income" <?php echo ($editingCategory['type'] ?? '') === 'income' ? 'checked' : 'checked'; ?>>
                            <label class="btn btn-outline-success btn-lg" for="income">Income</label>
                            <input type="radio" class="btn-check" name="type" id="expense" value="expense" <?php echo ($editingCategory['type'] ?? '') === 'expense' ? 'checked' : ''; ?>>
                            <label class="btn btn-outline-danger btn-lg" for="expense">Expense</label>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary btn-lg"><?php echo $editingCategory ? 'Simpan Perubahan' : 'Simpan Kategori'; ?></button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="row g-3">
            <div class="col-md-6">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h2 class="h5 mb-0">Kategori Income</h2>
                            <span class="badge bg-success"><?php echo count($incomeCategories); ?></span>
                        </div>
                        <?php if (!$incomeCategories): ?>
                            <p class="text-muted">Belum ada kategori income. Tambahkan minimal satu.</p>
                        <?php else: ?>
                            <ul class="list-group list-group-flush">
                                <?php foreach ($incomeCategories as $cat): ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <div><?php echo htmlspecialchars($cat['name']); ?></div>
                                        <div class="d-flex gap-2">
                                            <a class="btn btn-sm btn-outline-secondary" href="?edit=<?php echo $cat['id']; ?>">Edit</a>
                                            <form method="post" onsubmit="return confirm('Hapus kategori ini?');">
                                                <input type="hidden" name="delete_id" value="<?php echo $cat['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger">Hapus</button>
                                            </form>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h2 class="h5 mb-0">Kategori Expense</h2>
                            <span class="badge bg-danger"><?php echo count($expenseCategories); ?></span>
                        </div>
                        <?php if (!$expenseCategories): ?>
                            <p class="text-muted">Belum ada kategori expense. Tambahkan minimal satu.</p>
                        <?php else: ?>
                            <ul class="list-group list-group-flush">
                                <?php foreach ($expenseCategories as $cat): ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <div><?php echo htmlspecialchars($cat['name']); ?></div>
                                        <div class="d-flex gap-2">
                                            <a class="btn btn-sm btn-outline-secondary" href="?edit=<?php echo $cat['id']; ?>">Edit</a>
                                            <form method="post" onsubmit="return confirm('Hapus kategori ini?');">
                                                <input type="hidden" name="delete_id" value="<?php echo $cat['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger">Hapus</button>
                                            </form>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/footer.php'; ?>
