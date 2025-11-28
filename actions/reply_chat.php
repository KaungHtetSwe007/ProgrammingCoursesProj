<?php
declare(strict_types=1);

require __DIR__ . '/../includes/functions.php';
$user = require_login($pdo);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('dashboard.php');
}

validate_csrf($_POST['csrf'] ?? '');
$courseId = (int)($_POST['course_id'] ?? 0);
$messageId = (int)($_POST['message_id'] ?? 0);
$replyText = trim($_POST['reply_text'] ?? '');

if ($replyText === '') {
    set_flash('error', 'Reply ကို ဖြည့်ပေးပါ။');
    redirect('chat.php?course_id=' . $courseId);
}

$msgStmt = $pdo->prepare('SELECT course_id FROM chat_messages WHERE id = ?');
$msgStmt->execute([$messageId]);
$message = $msgStmt->fetch();

if (!$message) {
    set_flash('error', 'မက်ဆေ့ချ် မတွေ့ပါ။');
    redirect('dashboard.php');
}

if ((int)$message['course_id'] !== $courseId) {
    set_flash('error', 'မက်ဆေ့ချ်သည် သင့် Course မဟုတ်ပါ။');
    redirect('dashboard.php');
}

if (!ensure_course_access($pdo, $courseId)) {
    set_flash('error', 'Reply မလုပ်ခင် Course ထဲဝင်ရောက်ရန်လိုပါသည်။');
    redirect('dashboard.php');
}

$insert = $pdo->prepare('INSERT INTO chat_replies (message_id, user_id, reply_text) VALUES (?, ?, ?)');
$insert->execute([$messageId, $user['id'], $replyText]);

redirect('chat.php?course_id=' . $courseId);
