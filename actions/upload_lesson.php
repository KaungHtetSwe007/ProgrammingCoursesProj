<?php
declare(strict_types=1);

require __DIR__ . '/../includes/functions.php';
$user = require_login($pdo);

if (!is_instructor($pdo) && !is_admin($pdo)) {
    set_flash('error', 'ဆရာများ/အက်ဒ်မင်များသာ Lesson Upload လုပ်နိုင်ပါသည်။');
    redirect('dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('dashboard.php');
}

validate_csrf($_POST['csrf'] ?? '');

$courseId = (int)($_POST['course_id'] ?? 0);
$title = trim($_POST['title'] ?? '');
$summary = trim($_POST['summary'] ?? '');
$duration = max(1, (int)($_POST['duration_minutes'] ?? 0));
$posterUrl = trim($_POST['poster_url'] ?? '');

if ($title === '') {
    set_flash('error', 'သင်ခန်းစာခေါင်းစဉ် ထည့်ပေးပါ။');
    redirect('dashboard.php');
}

// Validate course ownership unless admin.
if (!is_admin($pdo)) {
    $courseStmt = $pdo->prepare('SELECT c.id FROM courses c JOIN instructors i ON c.instructor_id = i.id WHERE c.id = ? AND i.user_id = ?');
    $courseStmt->execute([$courseId, $user['id']]);
    if (!$courseStmt->fetch()) {
        set_flash('error', 'ဤသင်တန်းကို သင်၏ Mentor အကောင့်နှင့် မသက်ဆိုင်ပါ။');
        redirect('dashboard.php');
    }
} else {
    $courseStmt = $pdo->prepare('SELECT id FROM courses WHERE id = ?');
    $courseStmt->execute([$courseId]);
    if (!$courseStmt->fetch()) {
        set_flash('error', 'သင်တန်းမတွေ့ပါ။');
        redirect('dashboard.php');
    }
}

if (!isset($_FILES['video_file']) || $_FILES['video_file']['error'] !== UPLOAD_ERR_OK) {
    set_flash('error', 'ဗီဒီယို ဖိုင် upload မအောင်မြင်ပါ။');
    redirect('dashboard.php');
}

$file = $_FILES['video_file'];
$allowedTypes = [
    'video/mp4' => 'mp4',
    'video/webm' => 'webm'
];

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $file['tmp_name']);

if (!isset($allowedTypes[$mime])) {
    set_flash('error', 'MP4 သို့မဟုတ် WebM ဖိုင်သာ လက်ခံပါသည်။');
    redirect('dashboard.php');
}

if ($file['size'] > 100 * 1024 * 1024) { // 100MB
    set_flash('error', 'ဖိုင်အရွယ်အစား 100MB ထက် မကျော်သင့်ပါ။');
    redirect('dashboard.php');
}

$ext = $allowedTypes[$mime];
$targetDir = __DIR__ . '/../storage/videos';
if (!is_dir($targetDir)) {
    mkdir($targetDir, 0755, true);
}

$filename = 'lesson_' . $courseId . '_' . time() . '.' . $ext;
$targetPath = $targetDir . '/' . $filename;

if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
    set_flash('error', 'ဖိုင်ကို သိမ်းဆည်း၍ မရနိုင်ပါ။');
    redirect('dashboard.php');
}

$publicPath = 'storage/videos/' . $filename;

// Determine is_free based on existing lessons (first 2 free).
$countStmt = $pdo->prepare('SELECT COUNT(*) AS cnt FROM lessons WHERE course_id = ?');
$countStmt->execute([$courseId]);
$existingCount = (int) $countStmt->fetchColumn();
$isFree = $existingCount < 2 ? 1 : 0;
$position = $existingCount + 1;

$insert = $pdo->prepare('
    INSERT INTO lessons (course_id, title, summary, video_url, video_file_path, poster_url, duration_minutes, position, is_free)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
');
$insert->execute([
    $courseId,
    $title,
    $summary,
    $publicPath,
    $publicPath,
    $posterUrl ?: null,
    $duration,
    $position,
    $isFree
]);

set_flash('success', 'သင်ခန်းစာ Upload အောင်မြင်ပါသည်။ ' . ($isFree ? 'ဤသင်ခန်းစာကို အခမဲ့ကြည့်ရှုနိုင်သည်။' : 'ဤသင်ခန်းစာကို စာရင်းသွင်းသူများသာ ကြည့်ရှုနိုင်သည်။'));
redirect('dashboard.php');
