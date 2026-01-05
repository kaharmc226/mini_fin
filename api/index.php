<?php
// Simple PHP + SQLite API for expense tracking.

declare(strict_types=1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$dbPath = __DIR__ . '/../data/expenses.sqlite';
$schemaPath = __DIR__ . '/../schema.sql';

try {
    $db = new PDO('sqlite:' . $dbPath);
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
        $where[] = 'occurred_at >= :from';
        $params[':from'] = $from;
    }
    if ($to) {
        $where[] = 'occurred_at <= :to';
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
    $stmt = $db->prepare('SELECT date(occurred_at) AS day, SUM(amount) AS total FROM expenses WHERE occurred_at >= :from GROUP BY day ORDER BY day ASC');
    $stmt->execute([':from' => $from->format('Y-m-d H:i:s')]);
    echo json_encode(['data' => $stmt->fetchAll()]);
}

function summaryCategories(PDO $db): void
{
    $days = max(1, (int)($_GET['days'] ?? 30));
    $from = (new DateTimeImmutable())->modify("-{$days} days")->setTime(0, 0);
    $stmt = $db->prepare('SELECT c.id, c.name, c.color, SUM(e.amount) AS total FROM expenses e LEFT JOIN categories c ON e.category_id = c.id WHERE e.occurred_at >= :from GROUP BY c.id, c.name, c.color ORDER BY total DESC');
    $stmt->execute([':from' => $from->format('Y-m-d H:i:s')]);
    echo json_encode(['data' => $stmt->fetchAll()]);
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
