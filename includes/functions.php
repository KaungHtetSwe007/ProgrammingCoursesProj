<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

function current_user(PDO $pdo): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }

    static $cachedUser = null;
    if ($cachedUser && $cachedUser['id'] === $_SESSION['user_id']) {
        return $cachedUser;
    }

    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$_SESSION['user_id']]);
    $cachedUser = $stmt->fetch() ?: null;

    return $cachedUser;
}

function url_for(string $path): string
{
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }

    $base = APP_BASE_PATH ?: '';
    if ($path !== '' && $path[0] === '/') {
        return ($base ?: '') . $path;
    }

    $prefix = $base ? $base . '/' : '/';
    return $prefix . ltrim($path, '/');
}

function require_login(PDO $pdo): array
{
    $user = current_user($pdo);
    if (!$user) {
        redirect('login.php');
    }

    return $user;
}

function is_admin(PDO $pdo): bool
{
    $user = current_user($pdo);
    return $user && $user['role'] === 'admin';
}

function is_instructor(PDO $pdo): bool
{
    $user = current_user($pdo);
    return $user && $user['role'] === 'instructor';
}

function is_student(PDO $pdo): bool
{
    $user = current_user($pdo);
    return $user && $user['role'] === 'student';
}

function avatar_url(?array $user): string
{
    if (!$user) {
        return 'assets/images/avatar-placeholder.svg';
    }

    if (!empty($user['avatar_path'])) {
        return h($user['avatar_path']);
    }

    return 'assets/images/avatar-placeholder.svg';
}

function h(string $text): string
{
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

function redirect(string $path): void
{
    header('Location: ' . url_for($path));
    exit;
}

function ensure_course_access(PDO $pdo, int $courseId): bool
{
    $user = current_user($pdo);
    if (!$user) {
        return false;
    }

    if (is_admin($pdo) || is_instructor($pdo)) {
        return true;
    }

    $stmt = $pdo->prepare('SELECT status FROM enrollments WHERE course_id = ? AND user_id = ? LIMIT 1');
    $stmt->execute([$courseId, $user['id']]);
    $row = $stmt->fetch();

    return $row && $row['status'] === 'approved';
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
    }

    return $_SESSION['csrf_token'];
}

function validate_csrf(string $token): void
{
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        exit('လုံခြုံရေးတံဆိပ်မမှန်ကန်ပါ။ ပြန်လည်စတင်ပါ။');
    }
}

function set_flash(string $key, string $message): void
{
    $_SESSION['flash'][$key] = $message;
}

function get_flash(string $key): ?string
{
    if (!empty($_SESSION['flash'][$key])) {
        $message = $_SESSION['flash'][$key];
        unset($_SESSION['flash'][$key]);
        return $message;
    }

    return null;
}

function format_currency(int $amount): string
{
    return number_format($amount, 0, '.', ',') . ' ကျပ်';
}

function excerpt(string $text, int $limit = 120): string
{
    $text = trim($text);
    if ($text === '') {
        return '';
    }

    if (function_exists('mb_strimwidth')) {
        return mb_strimwidth($text, 0, $limit, '...');
    }

    return strlen($text) > $limit ? substr($text, 0, $limit - 3) . '...' : $text;
}

function get_lesson_views(PDO $pdo, int $lessonId, int $userId): int
{
    $stmt = $pdo->prepare('SELECT views FROM lesson_views WHERE lesson_id = ? AND user_id = ?');
    $stmt->execute([$lessonId, $userId]);
    $row = $stmt->fetch();

    return $row ? (int) $row['views'] : 0;
}

function enrollment_label(string $status): string
{
    $map = [
        'pending' => 'စောင့်ဆိုင်းဆဲ',
        'approved' => 'အတည်ပြုပြီး',
        'rejected' => 'ပယ်ချထား'
    ];

    return $map[$status] ?? $status;
}

function log_activity(PDO $pdo, ?int $userId, string $action, string $context = '', ?string $role = null): void
{
    try {
        $stmt = $pdo->prepare('INSERT INTO activity_logs (user_id, role, action, context) VALUES (?, ?, ?, ?)');
        $stmt->execute([$userId, $role, $action, $context]);
    } catch (Throwable $e) {
        // Failing to log should never break user flow.
    }
}

function recent_logs(PDO $pdo, int $userId, int $limit = 8): array
{
    try {
        $stmt = $pdo->prepare('SELECT * FROM activity_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT ?');
        $stmt->bindValue(1, $userId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    } catch (Throwable $e) {
        return [];
    }
}
