<?php
session_start();

require_once __DIR__ . '/db.php';

function ensure_authenticated(): void
{
    if (!isset($_SESSION['user'])) {
        header('Location: login.php');
        exit;
    }
}

function redirect_with_message(string $url, string $message, string $type = 'success'): void
{
    $_SESSION['flash'] = ['message' => $message, 'type' => $type];
    header("Location: {$url}");
    exit;
}

function flash_message(): void
{
    if (!empty($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        echo '<div class="alert alert-' . htmlspecialchars($flash['type']) . ' alert-dismissible fade show" role="alert">';
        echo htmlspecialchars($flash['message']);
        echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
        echo '</div>';
        unset($_SESSION['flash']);
    }
}

function current_user_id(): ?int
{
    return $_SESSION['user']['id'] ?? null;
}

function require_user_id(): int
{
    $userId = current_user_id();
    if (!$userId) {
        redirect_with_message('logout.php', 'Sesi berakhir, silakan login kembali.', 'danger');
    }
    return $userId;
}

function fetch_categories(?string $type = null): array
{
    $pdo = get_pdo();
    $sql = 'SELECT id, name, type FROM categories WHERE user_id = :user_id';
    $params = ['user_id' => current_user_id()];

    if ($type) {
        $sql .= ' AND type = :type';
        $params['type'] = $type;
    }

    $sql .= ' ORDER BY name ASC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function validate_date(string $date): bool
{
    return (bool) DateTime::createFromFormat('Y-m-d', $date);
}
