<?php
declare(strict_types=1);

require __DIR__ . '/../includes/functions.php';
$user = require_login($pdo);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('dashboard.php');
}

validate_csrf($_POST['csrf'] ?? '');
$messageId = (int)($_POST['message_id'] ?? 0);

$msgStmt = $pdo->prepare('SELECT course_id FROM chat_messages WHERE id = ?');
$msgStmt->execute([$messageId]);
$message = $msgStmt->fetch();

if (!$message) {
    set_flash('error', 'မက်ဆေ့ချ် မတွေ့ပါ။');
    redirect('dashboard.php');
}

if (!ensure_course_access($pdo, (int)$message['course_id'])) {
    set_flash('error', 'ဤမက်ဆေ့ချ်အတွက် လုပ်ဆောင်ခွင့်မရှိပါ။');
    redirect('dashboard.php');
}

$check = $pdo->prepare('SELECT id FROM chat_message_likes WHERE message_id = ? AND user_id = ?');
$check->execute([$messageId, $user['id']]);
$existing = $check->fetch();

if ($existing) {
    $pdo->prepare('DELETE FROM chat_message_likes WHERE id = ?')->execute([$existing['id']]);
} else {
    $insert = $pdo->prepare('INSERT INTO chat_message_likes (message_id, user_id) VALUES (?, ?)');
    $insert->execute([$messageId, $user['id']]);
}

redirect('chat.php?course_id=' . $message['course_id']);
