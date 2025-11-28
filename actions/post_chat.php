<?php
require __DIR__ . '/../includes/functions.php';
$user = require_login($pdo);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('dashboard.php');
}

validate_csrf($_POST['csrf'] ?? '');
$courseId = (int)($_POST['course_id'] ?? 0);
$message = trim($_POST['message'] ?? '');

if ($message === '') {
    set_flash('error', 'မက်ဆေ့ခ်ျ မျက်နှာပြင်ကို ဖြည့်စွက်ပါ။');
    redirect('chat.php?course_id=' . $courseId);
}

if (!ensure_course_access($pdo, $courseId)) {
    set_flash('error', 'ဤကဏ္ဍသို့ မေးမြန်းခွင့်မရှိပါ။');
    redirect('dashboard.php');
}

$stmt = $pdo->prepare('INSERT INTO chat_messages (course_id, user_id, message) VALUES (?, ?, ?)');
$stmt->execute([$courseId, $user['id'], $message]);

log_activity($pdo, (int) $user['id'], 'Post Chat', 'Course ID: ' . $courseId, $user['role'] ?? null);

set_flash('success', 'မက်ဆေ့ခ်ျပို့ပြီးပါပြီ။');
redirect('chat.php?course_id=' . $courseId);
