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
$file = $_FILES['attachment'] ?? null;
$maxUploadSize = 8 * 1024 * 1024;
$allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'zip', 'txt', 'doc', 'docx', 'ppt', 'pptx', 'csv', 'mp4'];

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

$pdo->beginTransaction();
try {
    $insert = $pdo->prepare('INSERT INTO chat_replies (message_id, user_id, reply_text) VALUES (?, ?, ?)');
    $insert->execute([$messageId, $user['id'], $replyText]);
    $replyId = (int)$pdo->lastInsertId();

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

        $uploadDir = __DIR__ . '/../storage/chat';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $safeExt = $ext ?: 'bin';
        $newName = 'chat_reply_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $safeExt;
        $targetPath = $uploadDir . '/' . $newName;
        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            throw new RuntimeException('ဖိုင် အပ်လုတ် မအောင်မြင်ပါ။');
        }

        $publicPath = 'storage/chat/' . $newName;
        $attachStmt = $pdo->prepare('
            INSERT INTO chat_attachments (message_id, reply_id, user_id, path, original_name, mime_type, size_bytes)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ');
        $attachStmt->execute([
            $messageId,
            $replyId,
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
    set_flash('error', 'Reply မသိမ်းဆည်းနိုင်ပါ: ' . $e->getMessage());
    redirect('chat.php?course_id=' . $courseId);
}

redirect('chat.php?course_id=' . $courseId);
