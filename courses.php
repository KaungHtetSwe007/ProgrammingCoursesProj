<?php
$pageTitle = 'သင်တန်းစာရင်း';
require __DIR__ . '/partials/header.php';

$currentUser = current_user($pdo);
$userId = $currentUser['id'] ?? null;

$stmt = $pdo->prepare('
    SELECT
        c.*,
        i.display_name AS instructor_name,
        (SELECT ROUND(AVG(cr.rating), 1) FROM course_ratings cr WHERE cr.course_id = c.id) AS avg_rating,
        (SELECT COUNT(*) FROM course_ratings cr WHERE cr.course_id = c.id) AS rating_count,
        (SELECT cr.rating FROM course_ratings cr WHERE cr.course_id = c.id AND cr.user_id = ?) AS user_rating
    FROM courses c
    LEFT JOIN instructors i ON c.instructor_id = i.id
    ORDER BY c.language, c.title
');
$stmt->execute([$userId]);
$courses = $stmt->fetchAll();
?>

<section class="section">
    <div class="eyebrow">သင်တန်းစာရင်း</div>
    <h1>Programming Language တစ်ခုချင်းစီအတွက် Full Stack လေ့လာမှု</h1>
    <p class="muted-text">သင်ခန်းစာ၊ စာအုပ်များကို ထည့်သွင်းထားပါသည်။</p>

    <div class="cards">
        <?php foreach ($courses as $course): ?>
            <article class="card reveal course-list-card">
                <div class="course-list-header">
                    <span class="tag"><?= h($course['language']); ?></span>
                    <h3><?= h($course['title']); ?></h3>
                    <p class="muted-text line-clamp-2"><?= h(excerpt($course['description'], 150)); ?></p>
                </div>

                <div class="course-list-meta">
                    <span class="meta-pop-lite">
                        <span class="icon-chip" aria-hidden="true"><svg viewBox="0 0 24 24"><path fill="currentColor" d="M12 12a5 5 0 1 0-5-5a5 5 0 0 0 5 5m0 2c-4 0-7 1.8-7 4v1h14v-1c0-2.2-3-4-7-4"/></svg></span>
                        <strong>ဆရာ</strong> <?= h($course['instructor_name'] ?: 'မသတ်မှတ်ရသေး'); ?>
                    </span>
                    <span class="meta-pop-lite">
                        <span class="icon-chip" aria-hidden="true"><svg viewBox="0 0 24 24"><path fill="currentColor" d="M12 3a9 9 0 1 0 9 9a9 9 0 0 0-9-9Zm0 3v6l4 2"/></svg></span>
                        <strong>ကြာချိန်</strong> အတန်းစဉ် ၁၀ ပတ်
                    </span>
                    <span class="meta-pop-lite">
                        <span class="icon-chip" aria-hidden="true"><svg viewBox="0 0 24 24"><path fill="currentColor" d="M5 5h14v4H5zm0 6h14v2H5zm0 4h14v2H5z"/></svg></span>
                        <strong>စျေးနှုန်း</strong> <?= format_currency((int)$course['price']); ?>
                    </span>
                </div>
                <?php
                $avgRating = $course['avg_rating'] !== null ? (float) $course['avg_rating'] : null;
                $ratingCount = (int) ($course['rating_count'] ?? 0);
                ?>
                <div class="rating-row">
                    <?= render_stars($avgRating); ?>
                    <strong class="rating-number"><?= $avgRating ? number_format($avgRating, 1) . ' / 5' : 'N/A'; ?></strong>
                    <small class="muted-text"><?= $ratingCount ? $ratingCount . ' ratings' : 'No ratings yet'; ?></small>
                </div>

                <div class="course-list-footer">
                    <span class="muted-text">အခမဲ့ သင်ခန်းစာ ၂ ခုပါဝင်</span>
                    <a class="btn course-btn" href="course.php?id=<?= $course['id']; ?>">အသေးစိတ်ကြည့်</a>
                </div>
            </article>
        <?php endforeach; ?>
        <?php if (!$courses): ?>
            <p>သင်တန်းများကို database/schema.sql အတိုင်း ထည့်သွင်းပါ။</p>
        <?php endif; ?>
    </div>
</section>

<?php require __DIR__ . '/partials/footer.php'; ?>
