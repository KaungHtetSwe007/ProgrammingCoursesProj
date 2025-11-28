<?php
require __DIR__ . '/../includes/functions.php';
$user = require_login($pdo);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('courses.php');
}

validate_csrf($_POST['csrf'] ?? '');
$courseId = (int)($_POST['course_id'] ?? 0);

$role = $user['role'] ?? 'student';
if ($role !== 'student') {
    set_flash('error', 'သင်တန်းစာရင်းသွင်းခြင်းသည် ကျောင်းသားများအတွက်သာ ဖြစ်ပါသည်။');
    redirect('course.php?id=' . $courseId);
}

$exists = $pdo->prepare('SELECT id, status FROM enrollments WHERE course_id = ? AND user_id = ?');
$exists->execute([$courseId, $user['id']]);
$row = $exists->fetch();

if ($row) {
    set_flash('error', 'သင်တန်းဝင်ခြင်း အနေအထား ' . $row['status'] . ' ဖြစ်နေပြီးသား ဖြစ်နေပါသည်။');
    redirect('course.php?id=' . $courseId);
}

$insert = $pdo->prepare('INSERT INTO enrollments (course_id, user_id, status) VALUES (?, ?, "pending")');
$insert->execute([$courseId, $user['id']]);

log_activity($pdo, (int) $user['id'], 'Enroll Course', 'Course ID: ' . $courseId, $user['role'] ?? null);

set_flash('success', 'သင်တန်းတက်ရောက်ရန် လျှောက်ထားပြီးပါပြီ။ အက်ဒ်မင်၏ အတည်ပြုခြင်းကို စောင့်ပါ။');
redirect('course.php?id=' . $courseId);
