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
