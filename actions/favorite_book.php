<?php
require __DIR__ . '/../includes/functions.php';
$user = require_login($pdo);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('books.php');
}

validate_csrf($_POST['csrf'] ?? '');
$bookId = (int)($_POST['book_id'] ?? 0);

$check = $pdo->prepare('SELECT id FROM favorite_books WHERE user_id = ? AND book_id = ?');
$check->execute([$user['id'], $bookId]);
$row = $check->fetch();

if ($row) {
    $pdo->prepare('DELETE FROM favorite_books WHERE id = ?')->execute([$row['id']]);
    set_flash('success', 'Favourite မှ ဖယ်ရှားပြီးပါပြီ။');
    log_activity($pdo, (int) $user['id'], 'Unfavourite Book', 'Book ID: ' . $bookId, $user['role'] ?? null);
} else {
    $pdo->prepare('INSERT INTO favorite_books (user_id, book_id) VALUES (?, ?)')->execute([$user['id'], $bookId]);
    set_flash('success', 'Favourite ထည့်ပြီးပါပြီ။');
    log_activity($pdo, (int) $user['id'], 'Favourite Book', 'Book ID: ' . $bookId, $user['role'] ?? null);
}

redirect('books.php');
