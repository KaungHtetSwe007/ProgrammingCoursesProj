<?php
declare(strict_types=1);

require __DIR__ . '/../includes/functions.php';

$user = require_login($pdo);
validate_csrf($_POST['csrf'] ?? '');

if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
    set_flash('error', 'ဖိုင်အပ်လုဒ် မအောင်မြင်ပါ။ ထပ်စမ်းပါ။');
    redirect('dashboard.php');
}

$file = $_FILES['avatar'];
$allowedTypes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $file['tmp_name']);

if (!isset($allowedTypes[$mime])) {
    set_flash('error', 'JPG/PNG/WebP ဖိုင်များသာ လက်ခံပါသည်။');
    redirect('dashboard.php');
}

if ($file['size'] > 2 * 1024 * 1024) { // 2MB
    set_flash('error', 'ဖိုင်အရွယ်အစား 2MB ထက် မကြီးရပါ။');
    redirect('dashboard.php');
}

$ext = $allowedTypes[$mime];
$targetDir = __DIR__ . '/../storage/avatars';
if (!is_dir($targetDir)) {
    mkdir($targetDir, 0755, true);
}

$filename = 'avatar_' . $user['id'] . '_' . time() . '.' . $ext;
$targetPath = $targetDir . '/' . $filename;

if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
    set_flash('error', 'ဖိုင်ကို သိမ်းဆည်း၍ မရနိုင်ပါ။');
    redirect('dashboard.php');
}

$publicPath = 'storage/avatars/' . $filename;
try {
    $stmt = $pdo->prepare('UPDATE users SET avatar_path = ? WHERE id = ?');
    $stmt->execute([$publicPath, $user['id']]);
} catch (PDOException $e) {
    set_flash('error', 'avatar_path column မရှိသေးပါ။ database/schema.sql ထဲက ALTER ကို အသုံးပြု၍ column ထည့်ပြီး ထပ်စမ်းပါ။');
    redirect('dashboard.php');
}

// If this user is also an instructor, sync the instructor profile photo.
if (is_instructor($pdo)) {
    $insStmt = $pdo->prepare('UPDATE instructors SET photo_url = ? WHERE user_id = ?');
    $insStmt->execute([$publicPath, $user['id']]);
}

log_activity($pdo, (int) $user['id'], 'Upload Profile Photo', 'ဖိုင် - ' . $filename, $user['role'] ?? null);

set_flash('success', 'Profile ပုံတင်ပြီးပါပြီ။');
redirect('dashboard.php');
