<?php
require_once __DIR__ . '/header.php';

$pdo = get_pdo();
$userId = current_user_id();
$startDate = date('Y-m-01');
$endDate = date('Y-m-t');

$summaryStmt = $pdo->prepare('SELECT type, SUM(amount) as total FROM transactions WHERE user_id = :user_id AND date BETWEEN :start AND :end GROUP BY type');
$summaryStmt->execute([
    'user_id' => $userId,
    'start' => $startDate,
    'end' => $endDate,
]);
$income = 0;
$expense = 0;
foreach ($summaryStmt->fetchAll() as $row) {
    if ($row['type'] === 'income') {
        $income = (float) $row['total'];
    }
    if ($row['type'] === 'expense') {
        $expense = (float) $row['total'];
    }
}
$balance = $income - $expense;

$categoryStmt = $pdo->prepare('SELECT c.name, SUM(t.amount) as total FROM transactions t JOIN categories c ON t.category_id = c.id WHERE t.user_id = :user_id AND t.type = "expense" AND t.date BETWEEN :start AND :end GROUP BY c.name ORDER BY total DESC');
$categoryStmt->execute(['user_id' => $userId, 'start' => $startDate, 'end' => $endDate]);
$categoryData = $categoryStmt->fetchAll();

$trendStmt = $pdo->prepare('SELECT date, SUM(CASE WHEN type = "income" THEN amount ELSE 0 END) AS income, SUM(CASE WHEN type = "expense" THEN amount ELSE 0 END) AS expense FROM transactions WHERE user_id = :user_id AND date BETWEEN :start AND :end GROUP BY date ORDER BY date');
$trendStmt->execute(['user_id' => $userId, 'start' => $startDate, 'end' => $endDate]);
$trendData = $trendStmt->fetchAll();

$recentStmt = $pdo->prepare('SELECT t.id, t.date, t.type, t.amount, t.note, c.name AS category_name FROM transactions t LEFT JOIN categories c ON t.category_id = c.id WHERE t.user_id = :user_id ORDER BY t.date DESC, t.id DESC LIMIT 5');
$recentStmt->execute(['user_id' => $userId]);
$recentTransactions = $recentStmt->fetchAll();
?>
<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
    <div>
        <h1 class="h3 mb-1">Dashboard</h1>
        <p class="text-muted mb-0">Pantau pemasukan, pengeluaran, dan saldo bulan ini (<?php echo date('F Y'); ?>).</p>
    </div>
    <a href="transactions.php" class="btn btn-primary btn-lg">+ Catat Transaksi</a>
</div>
<div class="row g-3 mb-3">
    <div class="col-md-4">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <p class="text-muted mb-1">Pemasukan Bulan Ini</p>
                <h2 class="fw-bold text-success">Rp <?php echo number_format($income, 0, ',', '.'); ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <p class="text-muted mb-1">Pengeluaran Bulan Ini</p>
                <h2 class="fw-bold text-danger">Rp <?php echo number_format($expense, 0, ',', '.'); ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <p class="text-muted mb-1">Saldo Bulan Ini</p>
                <h2 class="fw-bold">Rp <?php echo number_format($balance, 0, ',', '.'); ?></h2>
            </div>
        </div>
    </div>
</div>
<div class="row g-3">
    <div class="col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h2 class="h5 mb-0">Pengeluaran per Kategori</h2>
                    <span class="text-muted small">Bulan ini</span>
                </div>
                <?php if (!$categoryData): ?>
                    <p class="text-muted">Belum ada pengeluaran bulan ini. Catat transaksi pertama Anda.</p>
                <?php else: ?>
                    <canvas id="categoryChart" height="200"></canvas>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h2 class="h5 mb-0">Tren Harian</h2>
                    <span class="text-muted small">Bulan ini</span>
                </div>
                <?php if (!$trendData): ?>
                    <p class="text-muted">Belum ada data bulan ini. Tambahkan pemasukan/pengeluaran untuk melihat grafik.</p>
                <?php else: ?>
                    <canvas id="trendChart" height="200"></canvas>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<div class="row g-3 mt-1 mb-4">
    <div class="col-lg-7">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2 class="h5 mb-0">Transaksi Terbaru</h2>
                    <a class="small" href="transactions.php">Lihat semua</a>
                </div>
                <?php if (!$recentTransactions): ?>
                    <p class="text-muted mb-0">Belum ada transaksi. Tambahkan pemasukan/pengeluaran pertama Anda.</p>
                <?php else: ?>
                    <div class="table-responsive small">
                        <table class="table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Tanggal</th>
                                    <th>Kategori</th>
                                    <th>Catatan</th>
                                    <th class="text-end">Jumlah</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentTransactions as $trx): ?>
                                    <tr>
                                        <td><?php echo format_date($trx['date']); ?></td>
                                        <td>
                                            <span class="badge bg-light text-dark border"><?php echo htmlspecialchars($trx['category_name'] ?: 'Tidak ada kategori'); ?></span>
                                            <small class="text-muted ms-1"><?php echo $trx['type'] === 'income' ? 'Income' : 'Expense'; ?></small>
                                        </td>
                                        <td><?php echo $trx['note'] ? htmlspecialchars($trx['note']) : '<span class="text-muted">(tanpa catatan)</span>'; ?></td>
                                        <td class="text-end fw-bold <?php echo $trx['type'] === 'income' ? 'text-success' : 'text-danger'; ?>">Rp <?php echo number_format($trx['amount'], 0, ',', '.'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <h2 class="h5 mb-3">Tips Cepat</h2>
                <ul class="list-unstyled small text-muted mb-0">
                    <li class="mb-2">Gunakan tombol <strong>+ Catat Transaksi</strong> untuk input cepat tanpa keluar dari dashboard.</li>
                    <li class="mb-2">Coba filter tanggal/kategori di halaman transaksi untuk menemukan pengeluaran spesifik.</li>
                    <li class="mb-2">Tambahkan kategori lewat <strong>Quick Add</strong> saat mengisi transaksi agar tetap fokus.</li>
                    <li class="mb-0">Lihat tren harian untuk mengecek pola boros dan atur batasan mingguan.</li>
                </ul>
            </div>
        </div>
    </div>
</div>
<script>
<?php if ($categoryData): ?>
    const categoryCtx = document.getElementById('categoryChart');
    new Chart(categoryCtx, {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode(array_column($categoryData, 'name')); ?>,
            datasets: [{
                data: <?php echo json_encode(array_map('floatval', array_column($categoryData, 'total'))); ?>,
                backgroundColor: ['#0d6efd', '#6f42c1', '#d63384', '#20c997', '#fd7e14', '#0dcaf0', '#ffc107'],
            }]
        },
        options: {
            plugins: {
                legend: {position: 'bottom'}
            }
        }
    });
<?php endif; ?>
<?php if ($trendData): ?>
    const trendCtx = document.getElementById('trendChart');
    new Chart(trendCtx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode(array_column($trendData, 'date')); ?>,
            datasets: [
                {
                    label: 'Pemasukan',
                    data: <?php echo json_encode(array_map('floatval', array_column($trendData, 'income'))); ?>,
                    borderColor: '#198754',
                    backgroundColor: 'rgba(25, 135, 84, 0.1)',
                    tension: 0.3,
                    fill: true,
                },
                {
                    label: 'Pengeluaran',
                    data: <?php echo json_encode(array_map('floatval', array_column($trendData, 'expense'))); ?>,
                    borderColor: '#dc3545',
                    backgroundColor: 'rgba(220, 53, 69, 0.1)',
                    tension: 0.3,
                    fill: true,
                }
            ]
        },
        options: {
            plugins: { legend: { position: 'bottom' } },
            scales: { y: { beginAtZero: true } }
        }
    });
<?php endif; ?>
</script>
<?php require __DIR__ . '/footer.php'; ?>
