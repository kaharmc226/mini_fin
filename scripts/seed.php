<?php
// Populate the local SQLite database with dummy expense data for visualization.

declare(strict_types=1);

$dbPath = __DIR__ . '/../data/expenses.sqlite';
$schemaPath = __DIR__ . '/../schema.sql';

$db = new PDO('sqlite:' . $dbPath);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec('PRAGMA foreign_keys = ON');

// Ensure schema + seed categories exist.
$schema = file_get_contents($schemaPath);
$db->exec($schema);

// Basic category IDs that match schema.sql seeds.
$categoryIds = [1, 2, 3, 4, 5, 6, 7, 8];

// Clear existing expenses for a clean demo run.
$db->exec('DELETE FROM expenses');

$now = new DateTimeImmutable();
$entries = [];
$days = 60; // last 60 days

for ($i = 0; $i < 200; $i++) {
    $dayOffset = random_int(0, $days - 1);
    $date = $now->modify("-{$dayOffset} days")->setTime(random_int(7, 22), random_int(0, 59));
    $amount = random_int(10, 200) * 1000; // Rp 10k - 200k
    $category = $categoryIds[array_rand($categoryIds)];
    $note = sampleNote($category);
    $entries[] = [
        'amount' => $amount,
        'category_id' => $category,
        'note' => $note,
        'occurred_at' => $date->format('Y-m-d\\TH:i:s'),
    ];
}

$stmt = $db->prepare('INSERT INTO expenses (amount, category_id, note, occurred_at) VALUES (:amount, :category_id, :note, :occurred_at)');
foreach ($entries as $e) {
    $stmt->execute($e);
}

echo "Seeded " . count($entries) . " expenses into {$dbPath}\n";

function sampleNote(int $categoryId): string
{
$notes = [
        1 => ['Coffee - Kopi Kenangan', 'Lunch - Warung', 'Dinner - Ayam Bakar', 'Snacks - Mart'],
        2 => ['Ride-hailing - Grab', 'Bus ticket', 'Fuel - Pertamina'],
        3 => ['Groceries - Supermarket', 'Market run', 'Fresh produce'],
        4 => ['Electricity bill', 'Internet bill', 'Water bill'],
        5 => ['Clothes - Online shop', 'Shoes', 'Accessories'],
        6 => ['Pharmacy - Vitamins', 'Clinic visit', 'Supplements'],
        7 => ['Movie night', 'Streaming subscription', 'Games top-up'],
        8 => ['Misc purchase', 'Gift', 'Other'],
    ];
    $pool = $notes[$categoryId] ?? ['Expense'];
    return $pool[array_rand($pool)];
}
