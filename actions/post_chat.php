<?php
require __DIR__ . '/../includes/functions.php';
$user = require_login($pdo);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('dashboard.php');
}

validate_csrf($_POST['csrf'] ?? '');
$courseId = (int)($_POST['course_id'] ?? 0);
$message = trim($_POST['message'] ?? '');
$file = $_FILES['attachment'] ?? null;
$maxUploadSize = 8 * 1024 * 1024;
$allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'zip', 'txt', 'doc', 'docx', 'ppt', 'pptx', 'csv', 'mp4'];

if ($message === '') {
    set_flash('error', 'မက်ဆေ့ခ်ျ မျက်နှာပြင်ကို ဖြည့်စွက်ပါ။');
    redirect('chat.php?course_id=' . $courseId);
}

if (!ensure_course_access($pdo, $courseId)) {
    set_flash('error', 'ဤကဏ္ဍသို့ မေးမြန်းခွင့်မရှိပါ။');
    redirect('dashboard.php');
}

$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare('INSERT INTO chat_messages (course_id, user_id, message) VALUES (?, ?, ?)');
    $stmt->execute([$courseId, $user['id'], $message]);
    $messageId = (int)$pdo->lastInsertId();

    if ($file && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException('ဖိုင် အပ်လုတ် မအောင်မြင်ပါ။ Error code ' . $file['error']);
        }
        if ((int)$file['size'] > $maxUploadSize) {
            throw new RuntimeException('ဖိုင်အရွယ်အစား 8MB အထက်မဖြစ်ရ။');
        }

        $originalName = $file['name'] ?? 'attachment';
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if ($ext && !in_array($ext, $allowedExtensions, true)) {
            throw new RuntimeException('ဖိုင်အမျိုးအစား ခွင့်ပြုထားသည်များ - ' . implode(', ', $allowedExtensions));
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']) ?: 'application/octet-stream';
        $isImage = strpos($mime, 'image/') === 0;

        $uploadDir = __DIR__ . '/../storage/chat';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $safeExt = $ext ?: 'bin';
        $newName = 'chat_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $safeExt;
        $targetPath = $uploadDir . '/' . $newName;
        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            throw new RuntimeException('ဖိုင် အပ်လုတ် မအောင်မြင်ပါ။');
        }

        $publicPath = 'storage/chat/' . $newName;
        $attachStmt = $pdo->prepare('
            INSERT INTO chat_attachments (message_id, reply_id, user_id, path, original_name, mime_type, size_bytes)
            VALUES (?, NULL, ?, ?, ?, ?, ?)
        ');
        $attachStmt->execute([
            $messageId,
            $user['id'],
            $publicPath,
            $originalName,
            $mime,
            (int) $file['size'],
        ]);
    }

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    set_flash('error', 'မက်ဆေ့ချ် မသိမ်းဆည်းနိုင်ပါ: ' . $e->getMessage());
    redirect('chat.php?course_id=' . $courseId);
}

log_activity($pdo, (int) $user['id'], 'Post Chat', 'Course ID: ' . $courseId, $user['role'] ?? null);

set_flash('success', 'မက်ဆေ့ခ်ျပို့ပြီးပါပြီ။');
redirect('chat.php?course_id=' . $courseId);
