<?php
require __DIR__ . '/../includes/functions.php';
$user = require_login($pdo);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('instructors.php');
}

validate_csrf($_POST['csrf'] ?? '');
$instructorId = (int)($_POST['instructor_id'] ?? 0);

$instructorStmt = $pdo->prepare('SELECT id, user_id FROM instructors WHERE id = ? LIMIT 1');
$instructorStmt->execute([$instructorId]);
$instructor = $instructorStmt->fetch();

if (!$instructor) {
    set_flash('error', 'ရွေးချယ်ထားသော ဆရာကို မတွေ့ရှိနိုင်ပါ။');
    redirect('instructors.php');
}

if ((int) $instructor['user_id'] === (int) $user['id']) {
    set_flash('error', 'မိမိကိုယ်ကို Like မပေးနိုင်ပါ။');
    redirect('instructors.php');
}

$stmt = $pdo->prepare('SELECT id FROM instructor_likes WHERE instructor_id = ? AND user_id = ?');
$stmt->execute([$instructorId, $user['id']]);
$row = $stmt->fetch();

if ($row) {
    $pdo->prepare('DELETE FROM instructor_likes WHERE id = ?')->execute([$row['id']]);
    set_flash('success', 'Follow ဖြုတ်ပြီးပါပြီ။');
    log_activity($pdo, (int) $user['id'], 'Unfollow Instructor', 'Instructor ID: ' . $instructorId, $user['role'] ?? null);
} else {
    $pdo->prepare('INSERT INTO instructor_likes (instructor_id, user_id) VALUES (?, ?)')->execute([$instructorId, $user['id']]);
    set_flash('success', 'Follow ပြုလုပ်ပြီးပါပြီ။');
    log_activity($pdo, (int) $user['id'], 'Follow Instructor', 'Instructor ID: ' . $instructorId, $user['role'] ?? null);
}

redirect('instructors.php');
