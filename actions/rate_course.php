<?php
require __DIR__ . '/../includes/functions.php';
$user = require_login($pdo);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('courses.php');
}

validate_csrf($_POST['csrf'] ?? '');
$courseId = (int)($_POST['course_id'] ?? 0);
$rating = (int)($_POST['rating'] ?? 0);

if ($courseId <= 0 || $rating < 1 || $rating > 5) {
    set_flash('error', 'အဆင့်သတ်မှတ်မှု မမှန်ကန်ပါ။ ပြန်လည်ကြိုးစားပါ။');
    redirect('course.php?id=' . $courseId);
}

$courseCheck = $pdo->prepare('SELECT id FROM courses WHERE id = ? LIMIT 1');
$courseCheck->execute([$courseId]);
if (!$courseCheck->fetch()) {
    set_flash('error', 'သင်တန်း ရှိမရှိ စစ်ဆေးပြီးထပ်မံကြိုးစားပါ။');
    redirect('courses.php');
}

$stmt = $pdo->prepare('
    INSERT INTO course_ratings (course_id, user_id, rating)
    VALUES (?, ?, ?)
    ON DUPLICATE KEY UPDATE rating = VALUES(rating), updated_at = CURRENT_TIMESTAMP
');
$stmt->execute([$courseId, $user['id'], $rating]);

set_flash('success', 'သင်တန်းအတွက် သင့်အဆင့်သတ်မှတ်ချက်ကို သိမ်းဆည်းပြီးပါပြီ။');
log_activity($pdo, (int) $user['id'], 'Rate Course', 'Course ID: ' . $courseId . ' Rating: ' . $rating, $user['role'] ?? null);

redirect('course.php?id=' . $courseId);
