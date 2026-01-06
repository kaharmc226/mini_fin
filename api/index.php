<?php
// Simple PHP API for expense tracking with SQLite (default) or custom PDO DSN.

declare(strict_types=1);

header('Content-Type: application/json');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

$corsOrigin = getenv('API_CORS_ORIGIN') ?: '*';
header("Access-Control-Allow-Origin: {$corsOrigin}");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$schemaPath = __DIR__ . '/../schema.sql';

$config = [
    'dsn' => getenv('DATABASE_DSN') ?: 'sqlite:' . realpath(__DIR__ . '/../data/expenses.sqlite'),
    'user' => getenv('DATABASE_USER') ?: null,
    'password' => getenv('DATABASE_PASSWORD') ?: null,
];

try {
    $db = new PDO($config['dsn'], $config['user'], $config['password']);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'DB connection failed', 'details' => $e->getMessage()]);
    exit;
}

ensureSchema($db, $schemaPath);

$method = $_SERVER['REQUEST_METHOD'];
$path = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');

try {
    route($method, $path, $db);
} catch (Throwable $t) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error', 'details' => $t->getMessage()]);
}

function ensureSchema(PDO $db, string $schemaPath): void
{
    // Only auto-run schema for SQLite; other DBs should be migrated separately.
    if ($db->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'sqlite') {
        return;
    }

    if (!file_exists($schemaPath)) {
        return;
    }
    $hasTables = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='expenses'")->fetch();
    if ($hasTables) {
        return;
    }
    $schema = file_get_contents($schemaPath);
    $db->exec($schema);
}

function route(string $method, string $path, PDO $db): void
{
    if ($path === 'health') {
        echo json_encode(['status' => 'ok']);
        return;
    }

    if ($path === 'categories') {
        $method === 'GET' ? listCategories($db) : ($method === 'POST' ? createCategory($db) : notFound());
        return;
    }

    if ($path === 'expenses') {
        $method === 'GET' ? listExpenses($db) : ($method === 'POST' ? createExpense($db) : notFound());
        return;
    }

    if ($path === 'summary/daily') {
        $method === 'GET' ? summaryDaily($db) : notFound();
        return;
    }

    if ($path === 'summary/categories') {
        $method === 'GET' ? summaryCategories($db) : notFound();
        return;
    }

    if ($path === 'summary/monthly') {
        $method === 'GET' ? summaryMonthly($db) : notFound();
        return;
    }

    notFound();
}

function listCategories(PDO $db): void
{
    $stmt = $db->query('SELECT id, name, color, created_at FROM categories ORDER BY name ASC');
    echo json_encode(['data' => $stmt->fetchAll()]);
}

function createCategory(PDO $db): void
{
    $body = readJson();
    $name = trim($body['name'] ?? '');
    $color = trim($body['color'] ?? '');

    if ($name === '') {
        badRequest('Category name is required.');
    }

    $stmt = $db->prepare('INSERT INTO categories (name, color) VALUES (:name, :color)');
    try {
        $stmt->execute([':name' => $name, ':color' => $color ?: null]);
    } catch (PDOException $e) {
        if (str_contains($e->getMessage(), 'UNIQUE')) {
            badRequest('Category already exists.');
        }
        throw $e;
    }

    http_response_code(201);
    echo json_encode(['id' => (int)$db->lastInsertId(), 'name' => $name, 'color' => $color ?: null]);
}

function listExpenses(PDO $db): void
{
    [$from, $to] = dateRangeFromQuery();
    $params = [];
    $where = [];

    if ($from) {
        $where[] = 'datetime(e.occurred_at) >= datetime(:from)';
        $params[':from'] = $from;
    }
    if ($to) {
        $where[] = 'datetime(e.occurred_at) <= datetime(:to)';
        $params[':to'] = $to;
    }

    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    $sql = "SELECT e.id, e.amount, e.note, e.occurred_at, e.created_at, e.category_id, c.name AS category_name, c.color AS category_color
            FROM expenses e
            LEFT JOIN categories c ON e.category_id = c.id
            $whereSql
            ORDER BY datetime(e.occurred_at) DESC, e.id DESC";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    echo json_encode(['data' => $stmt->fetchAll()]);
}

function createExpense(PDO $db): void
{
    $body = readJson();
    $amount = (float)($body['amount'] ?? 0);
    $categoryId = $body['category_id'] ?? null;
    $note = trim($body['note'] ?? '');
    $occurredAt = trim($body['occurred_at'] ?? '');

    if ($amount <= 0) {
        badRequest('Amount must be greater than zero.');
    }
    if ($categoryId === null || $categoryId === '') {
        badRequest('Category is required.');
    }
    if ($occurredAt === '') {
        badRequest('occurred_at is required (ISO date or datetime).');
    }

    $stmt = $db->prepare('INSERT INTO expenses (amount, category_id, note, occurred_at) VALUES (:amount, :category_id, :note, :occurred_at)');
    $stmt->execute([
        ':amount' => $amount,
        ':category_id' => $categoryId,
        ':note' => $note ?: null,
        ':occurred_at' => $occurredAt,
    ]);

    http_response_code(201);
    echo json_encode(['id' => (int)$db->lastInsertId()]);
}

function summaryDaily(PDO $db): void
{
    $days = max(1, (int)($_GET['days'] ?? 7));
    $from = (new DateTimeImmutable())->modify("-{$days} days")->setTime(0, 0);
    $stmt = $db->prepare('SELECT date(occurred_at) AS day, SUM(amount) AS total FROM expenses WHERE datetime(occurred_at) >= datetime(:from) GROUP BY day ORDER BY day ASC');
    $stmt->execute([':from' => $from->format('Y-m-d H:i:s')]);
    echo json_encode(['data' => $stmt->fetchAll()]);
}

function summaryCategories(PDO $db): void
{
    $days = max(1, (int)($_GET['days'] ?? 30));
    $from = (new DateTimeImmutable())->modify("-{$days} days")->setTime(0, 0);
    $stmt = $db->prepare('SELECT c.id, c.name, c.color, SUM(e.amount) AS total FROM expenses e LEFT JOIN categories c ON e.category_id = c.id WHERE datetime(e.occurred_at) >= datetime(:from) GROUP BY c.id, c.name, c.color ORDER BY total DESC');
    $stmt->execute([':from' => $from->format('Y-m-d H:i:s')]);
    echo json_encode(['data' => $stmt->fetchAll()]);
}

function summaryMonthly(PDO $db): void
{
    $monthParam = $_GET['month'] ?? '';
    $start = monthStart($monthParam);
    $end = (clone $start)->modify('last day of this month')->setTime(23, 59, 59);

    $range = [
        ':start' => $start->format('Y-m-d H:i:s'),
        ':end' => $end->format('Y-m-d H:i:s'),
    ];

    // Daily totals within month
    $dailyStmt = $db->prepare('SELECT date(occurred_at) AS day, SUM(amount) AS total FROM expenses WHERE datetime(occurred_at) BETWEEN datetime(:start) AND datetime(:end) GROUP BY day ORDER BY day ASC');
    $dailyStmt->execute($range);
    $daily = $dailyStmt->fetchAll();

    // Category totals within month
    $catStmt = $db->prepare('SELECT c.id, c.name, c.color, SUM(e.amount) AS total FROM expenses e LEFT JOIN categories c ON e.category_id = c.id WHERE datetime(e.occurred_at) BETWEEN datetime(:start) AND datetime(:end) GROUP BY c.id, c.name, c.color ORDER BY total DESC');
    $catStmt->execute($range);
    $categories = $catStmt->fetchAll();

    $totalStmt = $db->prepare('SELECT SUM(amount) AS total, COUNT(DISTINCT date(occurred_at)) AS days FROM expenses WHERE datetime(occurred_at) BETWEEN datetime(:start) AND datetime(:end)');
    $totalStmt->execute($range);
    $totals = $totalStmt->fetch();

    $daysInMonth = (int)$start->format('t');
    $total = (float)($totals['total'] ?? 0);
    $average = $daysInMonth > 0 ? $total / $daysInMonth : 0;

    echo json_encode([
        'data' => [
            'month' => $start->format('Y-m'),
            'daily' => $daily,
            'categories' => $categories,
            'total' => $total,
            'average_per_day' => $average,
            'days_with_data' => (int)($totals['days'] ?? 0),
        ],
    ]);
}

function readJson(): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        badRequest('Invalid JSON payload.');
    }
    return $data;
}

function dateRangeFromQuery(): array
{
    $from = $_GET['from'] ?? null;
    $to = $_GET['to'] ?? null;
    return [$from ? trim($from) : null, $to ? trim($to) : null];
}

function monthStart(string $monthParam): DateTimeImmutable
{
    if ($monthParam) {
        $monthParam = trim($monthParam);
        $parts = explode('-', $monthParam);
        if (count($parts) === 2 && ctype_digit($parts[0]) && ctype_digit($parts[1])) {
            return (new DateTimeImmutable(sprintf('%04d-%02d-01', (int)$parts[0], (int)$parts[1])))->setTime(0, 0);
        }
    }
    return (new DateTimeImmutable('first day of this month'))->setTime(0, 0);
}

function badRequest(string $message): void
{
    http_response_code(400);
    echo json_encode(['error' => $message]);
    exit;
}

function notFound(): void
{
    http_response_code(404);
    echo json_encode(['error' => 'Not found']);
    exit;
}
