<?php
declare(strict_types=1);

require __DIR__ . '/../includes/functions.php';

$user = require_login($pdo);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('courses.php');
}

validate_csrf($_POST['csrf'] ?? '');

$courseId = (int)($_POST['course_id'] ?? 0);
$accountKey = $_POST['account_key'] ?? '';
$transactionLast6 = preg_replace('/\D/', '', $_POST['transaction_last6'] ?? '');
$transactionLast6 = substr($transactionLast6, -6);

$accounts = payment_accounts();
$account = $accounts[$accountKey] ?? null;

if (!$courseId || !$account) {
    set_flash('error', 'ရွေးချယ်ထားသော ငွေပေးချေမှုအကောင့် မမှန်ကန်ပါ။ ထပ်စမ်းပါ။');
    redirect('course.php?id=' . $courseId);
}

if (strlen($transactionLast6) !== 6) {
    set_flash('error', 'ငွေလွှဲအမှတ်အတိအကျ ၆ လုံးကို ရိုက်ထည့်ပါ။');
    redirect('course.php?id=' . $courseId);
}

if (!isset($_FILES['slip']) || $_FILES['slip']['error'] !== UPLOAD_ERR_OK) {
    set_flash('error', 'ပေးချေမှု ရရှိရာ screenshot ကို ထည့်ပါ။');
    redirect('course.php?id=' . $courseId);
}

$file = $_FILES['slip'];
$allowedTypes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $file['tmp_name']);

if (!isset($allowedTypes[$mime])) {
    set_flash('error', 'JPG/PNG/WebP ဖိုင်များသာ လက်ခံပါသည်။');
    redirect('course.php?id=' . $courseId);
}

if ($file['size'] > 5 * 1024 * 1024) {
    set_flash('error', 'ဖိုင်အရွယ်အစား 5MB ထက် မကျော်ရပါ။');
    redirect('course.php?id=' . $courseId);
}

$ext = $allowedTypes[$mime];
$targetDir = __DIR__ . '/../storage/payments';
if (!is_dir($targetDir)) {
    mkdir($targetDir, 0755, true);
}

$filename = 'payment_' . $user['id'] . '_' . time() . '.' . $ext;
$targetPath = $targetDir . '/' . $filename;

if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
    set_flash('error', 'ဖိုင်ကို သိမ်းဆည်း၍ မရနိုင်ပါ။ ထပ်စမ်းပါ။');
    redirect('course.php?id=' . $courseId);
}

$publicPath = 'storage/payments/' . $filename;

try {
    $pdo->beginTransaction();

    $enrollStmt = $pdo->prepare('SELECT id, status FROM enrollments WHERE course_id = ? AND user_id = ? LIMIT 1 FOR UPDATE');
    $enrollStmt->execute([$courseId, $user['id']]);
    $enrollment = $enrollStmt->fetch();

    if (!$enrollment) {
        $insertEnroll = $pdo->prepare('INSERT INTO enrollments (course_id, user_id, status) VALUES (?, ?, "pending")');
        $insertEnroll->execute([$courseId, $user['id']]);
        $enrollmentId = (int)$pdo->lastInsertId();
    } else {
        $enrollmentId = (int)$enrollment['id'];

        if ($enrollment['status'] === 'approved') {
            $pdo->rollBack();
            set_flash('error', 'ယခုပင် သင်တန်းအတည်ပြုပြီးဖြစ်သည်။');
            redirect('course.php?id=' . $courseId);
        }

        if ($enrollment['status'] === 'rejected') {
            $resetStmt = $pdo->prepare('UPDATE enrollments SET status = "pending", reviewed_by = NULL, reviewed_at = NULL WHERE id = ?');
            $resetStmt->execute([$enrollmentId]);
        }
    }

    $existingStmt = $pdo->prepare('SELECT id, slip_path FROM enrollment_payments WHERE enrollment_id = ?');
    $existingStmt->execute([$enrollmentId]);
    $existing = $existingStmt->fetch();

    $paymentStmt = $pdo->prepare('
        INSERT INTO enrollment_payments (enrollment_id, account_channel, account_name, account_number, transaction_last6, slip_path)
        VALUES (?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            account_channel = VALUES(account_channel),
            account_name = VALUES(account_name),
            account_number = VALUES(account_number),
            transaction_last6 = VALUES(transaction_last6),
            slip_path = VALUES(slip_path),
            created_at = CURRENT_TIMESTAMP
    ');
    $paymentStmt->execute([
        $enrollmentId,
        $account['channel'],
        $account['name'],
        $account['number'],
        $transactionLast6,
        $publicPath,
    ]);

    $pdo->commit();

    if ($existing && !empty($existing['slip_path'])) {
        $oldPath = __DIR__ . '/../' . $existing['slip_path'];
        if (is_file($oldPath)) {
            @unlink($oldPath);
        }
    }

    log_activity($pdo, (int) $user['id'], 'Submit Payment', 'Course ID: ' . $courseId, $user['role'] ?? null);
    set_flash('success', 'ပေးချေမှုအချက်အလက် မျှဝေပြီးပါပြီ။ အက်ဒ်မင် အတည်ပြုအောင် စောင့်ပါ။');
    redirect('course.php?id=' . $courseId);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    set_flash('error', 'အချက်အလက်သိမ်းဆည်းရာတွင် ပြသာနာဖြစ်ခဲ့သည်။ ထပ်စမ်းပါ။');
    redirect('course.php?id=' . $courseId);
}
