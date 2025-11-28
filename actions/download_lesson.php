<?php
require __DIR__ . '/../includes/functions.php';
$user = require_login($pdo);

validate_csrf($_GET['csrf'] ?? '');
$lessonId = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare('SELECT l.*, c.id AS course_id FROM lessons l JOIN courses c ON l.course_id = c.id WHERE l.id = ?');
$stmt->execute([$lessonId]);
$lesson = $stmt->fetch();

if (!$lesson) {
    set_flash('error', 'သင်ခန်းစာမတွေ့ပါ။');
    redirect('courses.php');
}

if (!ensure_course_access($pdo, (int)$lesson['course_id'])) {
    set_flash('error', 'ဒေါင်းလုဒ်လုပ်ခွင့်မရှိပါ။');
    redirect('course.php?id=' . $lesson['course_id']);
}

$filePath = __DIR__ . '/../' . $lesson['video_file_path'];
if (!is_file($filePath)) {
    set_flash('error', 'ဗီဒီယိုဖိုင် မတွေ့ပါ။ storage/videos ထဲရရှိနေကြောင်း သေချာစေပါ။');
    redirect('course.php?id=' . $lesson['course_id']);
}

log_activity($pdo, (int) $user['id'], 'Download Lesson', $lesson['title'], $user['role'] ?? null);

header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . basename($filePath) . '"');
header('Content-Length: ' . filesize($filePath));
readfile($filePath);
exit;
