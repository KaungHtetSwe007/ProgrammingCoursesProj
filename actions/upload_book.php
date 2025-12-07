<?php
declare(strict_types=1);

require __DIR__ . '/../includes/functions.php';

$user = require_login($pdo);

if (!is_instructor($pdo)) {
    set_flash('error', 'Mentor များသာ စာအုပ်တင်နိုင်ပါသည်။');
    redirect('dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('dashboard.php');
}

validate_csrf($_POST['csrf'] ?? '');

$title = trim($_POST['title'] ?? '');
$language = trim($_POST['language'] ?? '');
$description = trim($_POST['description'] ?? '');

if ($title === '' || $language === '') {
    set_flash('error', 'စာအုပ်ခေါင်းစဉ်နှင့် Programming Language ကို ဖြည့်ပါ။');
    redirect('dashboard.php');
}

// Find instructor record
$insStmt = $pdo->prepare('SELECT id FROM instructors WHERE user_id = ? LIMIT 1');
$insStmt->execute([$user['id']]);
$instructor = $insStmt->fetch();
if (!$instructor) {
    set_flash('error', 'Mentor profile မရှိသေးပါ။ Instructor စာရင်းထဲတွင် အသစ်ထည့်ပါ။');
    redirect('dashboard.php');
}

$bookFile = $_FILES['book_file'] ?? null;
$coverFile = $_FILES['cover'] ?? null;

if (!$bookFile || $bookFile['error'] !== UPLOAD_ERR_OK) {
    set_flash('error', 'စာအုပ်ဖိုင်ကို ထည့်ပါ။');
    redirect('dashboard.php');
}

$allowedBookTypes = [
    'application/pdf' => 'pdf',
    'application/epub+zip' => 'epub',
    'application/octet-stream' => 'bin', // fallback, still saved with original extension
];
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$bookMime = finfo_file($finfo, $bookFile['tmp_name']);
$bookExt = $allowedBookTypes[$bookMime] ?? pathinfo($bookFile['name'], PATHINFO_EXTENSION);

$bookDir = __DIR__ . '/../storage/books';
if (!is_dir($bookDir)) {
    mkdir($bookDir, 0755, true);
}

$bookName = 'book_' . $user['id'] . '_' . time() . '.' . $bookExt;
$bookPath = $bookDir . '/' . $bookName;

if (!move_uploaded_file($bookFile['tmp_name'], $bookPath)) {
    set_flash('error', 'စာအုပ်ဖိုင်ကို သိမ်းဆည်း၍ မရနိုင်ပါ။');
    redirect('dashboard.php');
}

$coverPublicPath = null;
if ($coverFile && $coverFile['error'] === UPLOAD_ERR_OK) {
    $allowedCover = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $coverMime = finfo_file($finfo, $coverFile['tmp_name']);
    if (!isset($allowedCover[$coverMime])) {
        set_flash('error', 'Cover သည် JPG/PNG/WebP ဖြစ်ရပါမည်။');
        redirect('dashboard.php');
    }
    if ($coverFile['size'] > 3 * 1024 * 1024) {
        set_flash('error', 'Cover ဖိုင်အရွယ်အစား 3MB ထက် မကျော်ရပါ။');
        redirect('dashboard.php');
    }

    $coverDir = __DIR__ . '/../storage/book_covers';
    if (!is_dir($coverDir)) {
        mkdir($coverDir, 0755, true);
    }

    $coverExt = $allowedCover[$coverMime];
    $coverName = 'cover_' . $user['id'] . '_' . time() . '.' . $coverExt;
    $coverPath = $coverDir . '/' . $coverName;
    if (!move_uploaded_file($coverFile['tmp_name'], $coverPath)) {
        set_flash('error', 'Cover ကို သိမ်းဆည်း၍ မရနိုင်ပါ။');
        redirect('dashboard.php');
    }
    $coverPublicPath = 'storage/book_covers/' . $coverName;
}

$fileSizeMb = round(filesize($bookPath) / (1024 * 1024), 2);

$stmt = $pdo->prepare('
    INSERT INTO books (title, language, description, file_path, file_size, cover_path, instructor_id)
    VALUES (?, ?, ?, ?, ?, ?, ?)
');
$stmt->execute([
    $title,
    $language,
    $description,
    'storage/books/' . $bookName,
    $fileSizeMb,
    $coverPublicPath,
    $instructor['id'],
]);

log_activity($pdo, (int) $user['id'], 'Upload Book', $title, $user['role'] ?? null);
set_flash('success', 'စာအုပ်တင်ပြီးပါပြီ။');
redirect('dashboard.php');
