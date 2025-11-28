<?php
require __DIR__ . '/../includes/functions.php';
$user = require_login($pdo);

validate_csrf($_GET['csrf'] ?? '');
$bookId = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare('SELECT * FROM books WHERE id = ?');
$stmt->execute([$bookId]);
$book = $stmt->fetch();

if (!$book) {
    set_flash('error', 'စာအုပ်မတွေ့ပါ။');
    redirect('books.php');
}

$filePath = __DIR__ . '/../' . $book['file_path'];
if (!is_file($filePath)) {
    set_flash('error', 'ဖိုင်မတွေ့ပါ။ storage/books ထဲတွင် ရှိ/မရှိ စစ်ဆေးပါ။');
    redirect('books.php');
}

log_activity($pdo, (int) $user['id'], 'Download Book', $book['title'], $user['role'] ?? null);

header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . basename($filePath) . '"');
header('Content-Length: ' . filesize($filePath));
readfile($filePath);
exit;
