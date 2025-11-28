<?php
require __DIR__ . '/includes/functions.php';

$lessonId = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT l.*, c.title AS course_title, c.id AS course_id FROM lessons l JOIN courses c ON l.course_id = c.id WHERE l.id = ?');
$stmt->execute([$lessonId]);
$lesson = $stmt->fetch();

if (!$lesson) {
    set_flash('error', 'မူရင်းသင်ခန်းစာ မတွေ့ပါ။');
    redirect('courses.php');
}

$currentUser = current_user($pdo);
$hasFullAccess = ensure_course_access($pdo, (int) $lesson['course_id']);

if (!$lesson['is_free'] && !$hasFullAccess) {
    set_flash('error', 'ဤသင်ခန်းစာကို ကြည့်ရှုရန် စာရင်းသွင်းထားရပါမည်။');
    redirect('course.php?id=' . $lesson['course_id']);
}

if ($currentUser) {
    $viewStmt = $pdo->prepare('INSERT INTO lesson_views (lesson_id, user_id, views) VALUES (?, ?, 1)
        ON DUPLICATE KEY UPDATE views = views + 1, updated_at = NOW()');
    $viewStmt->execute([$lessonId, $currentUser['id']]);
}

$pageTitle = 'ဗီဒီယိုကြည့်ရှု';
require __DIR__ . '/partials/header.php';
?>

<section class="section">
    <a href="course.php?id=<?= $lesson['course_id']; ?>">&larr; <?= h($lesson['course_title']); ?> သို့ ပြန်သွားရန်</a>
    <div class="box" style="margin-top:1rem;">
        <h1><?= h($lesson['title']); ?></h1>
        <p><?= h($lesson['duration_minutes']); ?> မိနစ် · <?= $lesson['is_free'] ? 'အခမဲ့' : 'စာရင်းသွင်းသူ'; ?></p>
        <video controls style="width:100%; border-radius:1rem; background:#000;" poster="<?= h($lesson['poster_url'] ?? ''); ?>">
            <source src="<?= h($lesson['video_url']); ?>" type="video/mp4">
            ဗီဒီယိုကို ကြည့်ရှုနိုင်ရန် သင့်ဘရောက်စာကို အပ်ဒိတ်လုပ်ပါ။
        </video>
        <p style="margin-top:1rem;"><?= nl2br(h($lesson['summary'] ?? '')); ?></p>
    </div>
</section>

<?php require __DIR__ . '/partials/footer.php'; ?>
