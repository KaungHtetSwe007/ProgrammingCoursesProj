<?php
require __DIR__ . '/../includes/functions.php';
$user = require_login($pdo);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('books.php');
}

validate_csrf($_POST['csrf'] ?? '');
$bookId = (int)($_POST['book_id'] ?? 0);
$rating = (int)($_POST['rating'] ?? 0);

if ($bookId <= 0 || $rating < 1 || $rating > 5) {
    set_flash('error', 'စာအုပ်အတွက် အဆင့်သတ်မှတ်ချက် မမှန်ကန်ပါ။');
    redirect('books.php');
}

$bookCheck = $pdo->prepare('SELECT id FROM books WHERE id = ? LIMIT 1');
$bookCheck->execute([$bookId]);
if (!$bookCheck->fetch()) {
    set_flash('error', 'စာအုပ် မတွေ့ပါ။ ထပ်မံကြိုးစားပါ။');
    redirect('books.php');
}

$stmt = $pdo->prepare('
    INSERT INTO book_ratings (book_id, user_id, rating)
    VALUES (?, ?, ?)
    ON DUPLICATE KEY UPDATE rating = VALUES(rating), updated_at = CURRENT_TIMESTAMP
');
$stmt->execute([$bookId, $user['id'], $rating]);

set_flash('success', 'စာအုပ်အတွက် သင့် Rating ကို သိမ်းဆည်းပြီးပါပြီ။');
log_activity($pdo, (int) $user['id'], 'Rate Book', 'Book ID: ' . $bookId . ' Rating: ' . $rating, $user['role'] ?? null);

redirect('books.php');
