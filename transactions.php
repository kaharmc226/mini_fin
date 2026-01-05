<?php
require_once __DIR__ . '/header.php';

$pdo = get_pdo();
$userId = require_user_id();
$userId = current_user_id();

$errors = [];
$isEditing = isset($_GET['edit']);
$editingTransaction = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['quick_category'])) {
        $name = trim($_POST['category_name'] ?? '');
        $type = $_POST['category_type'] ?? '';
        if ($name === '' || !in_array($type, ['income', 'expense'], true)) {
            $errors[] = 'Nama kategori dan tipe wajib diisi.';
        } else {
            $insert = $pdo->prepare('INSERT INTO categories (user_id, name, type) VALUES (:user_id, :name, :type)');
            $insert->execute(['user_id' => $userId, 'name' => $name, 'type' => $type]);
            redirect_with_message('transactions.php', 'Kategori berhasil ditambahkan.');
        }
    }

    if (isset($_POST['transaction_form'])) {
        $date = $_POST['date'] ?? date('Y-m-d');
        $type = $_POST['type'] ?? '';
        $categoryId = $_POST['category_id'] ?? null;
        $amount = (float) ($_POST['amount'] ?? 0);
        $note = trim($_POST['note'] ?? '');
        $transactionId = $_POST['transaction_id'] ?? null;

        if (!validate_date($date)) {
            $errors[] = 'Tanggal tidak valid.';
        }
        if (!in_array($type, ['income', 'expense'], true)) {
            $errors[] = 'Tipe transaksi harus income atau expense.';
        }
        if (!$categoryId) {
            $errors[] = 'Kategori wajib dipilih.';
        }
        if ($amount <= 0) {
            $errors[] = 'Nominal harus lebih dari 0.';
        }

        if (!$errors) {
            if ($transactionId) {
                $update = $pdo->prepare('UPDATE transactions SET date = :date, type = :type, category_id = :category_id, amount = :amount, note = :note WHERE id = :id AND user_id = :user_id');
                $update->execute([
                    'date' => $date,
                    'type' => $type,
                    'category_id' => $categoryId,
                    'amount' => $amount,
                    'note' => $note,
                    'id' => $transactionId,
                    'user_id' => $userId,
                ]);
                redirect_with_message('transactions.php', 'Transaksi berhasil diperbarui.');
            } else {
                $insert = $pdo->prepare('INSERT INTO transactions (user_id, category_id, date, type, amount, note) VALUES (:user_id, :category_id, :date, :type, :amount, :note)');
                $insert->execute([
                    'user_id' => $userId,
                    'category_id' => $categoryId,
                    'date' => $date,
                    'type' => $type,
                    'amount' => $amount,
                    'note' => $note,
                ]);
                redirect_with_message('transactions.php', 'Transaksi berhasil ditambahkan.');
            }
        }
    }
}

if (isset($_POST['delete_id'])) {
    $delete = $pdo->prepare('DELETE FROM transactions WHERE id = :id AND user_id = :user_id');
    $delete->execute(['id' => $_POST['delete_id'], 'user_id' => $userId]);
    redirect_with_message('transactions.php', 'Transaksi berhasil dihapus.', 'info');
}

if ($isEditing) {
    $stmt = $pdo->prepare('SELECT * FROM transactions WHERE id = :id AND user_id = :user_id');
    $stmt->execute(['id' => $_GET['edit'], 'user_id' => $userId]);
    $editingTransaction = $stmt->fetch();
    if (!$editingTransaction) {
        redirect_with_message('transactions.php', 'Data transaksi tidak ditemukan.', 'danger');
    }
}

// Filters
$search = trim($_GET['search'] ?? '');
$typeFilter = $_GET['type'] ?? '';
$categoryFilter = $_GET['category'] ?? '';
$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';

$sql = 'SELECT t.*, c.name as category_name FROM transactions t LEFT JOIN categories c ON t.category_id = c.id WHERE t.user_id = :user_id';
$params = ['user_id' => $userId];

if ($search !== '') {
    $sql .= ' AND (t.note LIKE :search)';
    $params['search'] = '%' . $search . '%';
}
if ($typeFilter && in_array($typeFilter, ['income', 'expense'], true)) {
    $sql .= ' AND t.type = :type';
    $params['type'] = $typeFilter;
}
if ($categoryFilter) {
    $sql .= ' AND t.category_id = :category_id';
    $params['category_id'] = $categoryFilter;
}
if ($startDate && validate_date($startDate)) {
    $sql .= ' AND t.date >= :start_date';
    $params['start_date'] = $startDate;
}
if ($endDate && validate_date($endDate)) {
    $sql .= ' AND t.date <= :end_date';
    $params['end_date'] = $endDate;
}

$sql .= ' ORDER BY t.date DESC, t.id DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$transactions = $stmt->fetchAll();

$incomeCategories = fetch_categories('income');
$expenseCategories = fetch_categories('expense');
$allCategories = array_merge($incomeCategories, $expenseCategories);

$currentType = $editingTransaction['type'] ?? ($_POST['type'] ?? 'expense');
?>
<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-3">
    <div>
        <h1 class="h3 mb-1">Transaksi</h1>
        <p class="text-muted mb-0">Catat pemasukan/pengeluaran dengan cepat. Form 1 layar, tombol besar, dan validasi jelas.</p>
    </div>
</div>
<div class="row g-3">
    <div class="col-lg-5">
        <div class="card shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2 class="h5 mb-0"><?php echo $isEditing ? 'Edit Transaksi' : 'Tambah Transaksi'; ?></h2>
                    <?php if ($isEditing): ?>
                        <a href="transactions.php" class="btn btn-sm btn-outline-secondary">Batal</a>
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
                    <input type="hidden" name="transaction_form" value="1">
                    <input type="hidden" name="transaction_id" value="<?php echo htmlspecialchars($editingTransaction['id'] ?? ''); ?>">
                    <div>
                        <label class="form-label">Tanggal</label>
                        <input type="date" name="date" class="form-control form-control-lg" value="<?php echo htmlspecialchars($editingTransaction['date'] ?? $_POST['date'] ?? date('Y-m-d')); ?>" required>
                    </div>
                    <div>
                        <label class="form-label">Tipe</label>
                        <div class="btn-group w-100" role="group">
                            <input type="radio" class="btn-check" name="type" id="typeIncome" value="income" <?php echo $currentType === 'income' ? 'checked' : ''; ?> autocomplete="off">
                            <label class="btn btn-outline-success btn-lg" for="typeIncome">Income</label>
                            <input type="radio" class="btn-check" name="type" id="typeExpense" value="expense" <?php echo $currentType === 'expense' ? 'checked' : ''; ?> autocomplete="off">
                            <label class="btn btn-outline-danger btn-lg" for="typeExpense">Expense</label>
                        </div>
                    </div>
                    <div>
                        <label class="form-label">Kategori</label>
                        <div class="input-group">
                            <select name="category_id" class="form-select form-select-lg" required>
                                <option value="">Pilih kategori</option>
                                <optgroup label="Income">
                                    <?php foreach ($incomeCategories as $cat): ?>
                                        <option value="<?php echo $cat['id']; ?>" <?php echo ($editingTransaction['category_id'] ?? $_POST['category_id'] ?? '') == $cat['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($cat['name']); ?></option>
                                    <?php endforeach; ?>
                                </optgroup>
                                <optgroup label="Expense">
                                    <?php foreach ($expenseCategories as $cat): ?>
                                        <option value="<?php echo $cat['id']; ?>" <?php echo ($editingTransaction['category_id'] ?? $_POST['category_id'] ?? '') == $cat['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($cat['name']); ?></option>
                                    <?php endforeach; ?>
                                </optgroup>
                            </select>
                            <button class="btn btn-outline-secondary btn-lg" type="button" data-bs-toggle="modal" data-bs-target="#quickCategoryModal">+ Quick Add</button>
                        </div>
                    </div>
                    <div>
                        <label class="form-label">Nominal (Rp)</label>
                        <input type="number" step="100" min="0" name="amount" class="form-control form-control-lg" value="<?php echo htmlspecialchars($editingTransaction['amount'] ?? $_POST['amount'] ?? ''); ?>" required placeholder="Contoh: 100000">
                    </div>
                    <div>
                        <label class="form-label">Catatan</label>
                        <textarea name="note" class="form-control" rows="2" placeholder="Opsional, misal: kopi pagi atau gaji bulan ini."><?php echo htmlspecialchars($editingTransaction['note'] ?? $_POST['note'] ?? ''); ?></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary btn-lg"><?php echo $isEditing ? 'Simpan Perubahan' : 'Simpan Transaksi'; ?></button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card shadow-sm mb-3">
            <div class="card-body">
                <form class="row gy-2 gx-2 align-items-end" method="get">
                    <div class="col-md-3">
                        <label class="form-label">Tanggal Mulai</label>
                        <input type="date" name="start_date" class="form-control" value="<?php echo htmlspecialchars($startDate); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Tanggal Akhir</label>
                        <input type="date" name="end_date" class="form-control" value="<?php echo htmlspecialchars($endDate); ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Tipe</label>
                        <select name="type" class="form-select">
                            <option value="">Semua</option>
                            <option value="income" <?php echo $typeFilter === 'income' ? 'selected' : ''; ?>>Income</option>
                            <option value="expense" <?php echo $typeFilter === 'expense' ? 'selected' : ''; ?>>Expense</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Kategori</label>
                        <select name="category" class="form-select">
                            <option value="">Semua</option>
                            <?php foreach ($allCategories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>" <?php echo $categoryFilter == $cat['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($cat['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Cari catatan</label>
                        <input type="text" name="search" class="form-control" placeholder="kopi" value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="col-md-12 d-flex gap-2">
                        <button type="submit" class="btn btn-primary">Filter</button>
                        <a href="transactions.php" class="btn btn-outline-secondary">Reset</a>
                    </div>
                </form>
            </div>
        </div>
        <div class="card shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h2 class="h5 mb-0">Riwayat Transaksi</h2>
                    <span class="text-muted small">Klik baris untuk edit cepat.</span>
                </div>
                <?php if (!$transactions): ?>
                    <p class="text-muted">Belum ada transaksi. Isi form di kiri dan tekan "Simpan Transaksi".</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Tanggal</th>
                                    <th>Tipe</th>
                                    <th>Kategori</th>
                                    <th>Nominal</th>
                                    <th>Catatan</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($transactions as $tx): ?>
                                <tr onclick="window.location='?edit=<?php echo $tx['id']; ?>'" style="cursor:pointer;">
                                    <td><?php echo htmlspecialchars($tx['date']); ?></td>
                                    <td><span class="badge bg-<?php echo $tx['type'] === 'income' ? 'success' : 'danger'; ?>"><?php echo ucfirst($tx['type']); ?></span></td>
                                    <td><?php echo htmlspecialchars($tx['category_name'] ?? '-'); ?></td>
                                    <td class="fw-semibold">Rp <?php echo number_format($tx['amount'], 0, ',', '.'); ?></td>
                                    <td><?php echo htmlspecialchars($tx['note']); ?></td>
                                    <td>
                                        <form method="post" class="d-inline" onsubmit="return confirm('Hapus transaksi ini?');">
                                            <input type="hidden" name="delete_id" value="<?php echo $tx['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger">Hapus</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="quickCategoryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <div class="modal-header">
                    <h5 class="modal-title">Tambah Kategori Cepat</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="quick_category" value="1">
                    <div class="mb-3">
                        <label class="form-label">Nama Kategori</label>
                        <input type="text" name="category_name" class="form-control" required placeholder="Misal: Kopi atau Gaji">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Tipe</label>
                        <select name="category_type" class="form-select" required>
                            <option value="income">Income</option>
                            <option value="expense">Expense</option>
                        </select>
                    </div>
                    <p class="text-muted small mb-0">Kategori baru langsung tersedia di dropdown tanpa pindah halaman.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php require __DIR__ . '/footer.php'; ?>
