<?php
$pageTitle = 'သင်တန်းစာရင်း';
require __DIR__ . '/partials/header.php';

$stmt = $pdo->query('
    SELECT c.*, i.display_name AS instructor_name
    FROM courses c
    LEFT JOIN instructors i ON c.instructor_id = i.id
    ORDER BY c.language, c.title
');
$courses = $stmt->fetchAll();
?>

<section class="section">
    <div class="eyebrow">သင်တန်းစာရင်း</div>
    <h1>Programming Language တစ်ခုချင်းစီအတွက် Full Stack လေ့လာမှု</h1>
    <p class="muted-text">သင်ခန်းစာ၊ စာအုပ်များကို ထည့်သွင်းထားပါသည်။ Animation အသစ်နဲ့ စတင်ကြည့်ပါ။</p>

    <div class="cards">
        <?php foreach ($courses as $course): ?>
            <article class="card reveal">
                <div>
                    <span class="tag"><?= h($course['language']); ?></span>
                    <h3><?= h($course['title']); ?></h3>
                    <p><?= h(excerpt($course['description'], 120)); ?></p>
                    <p>ဆရာ - <?= h($course['instructor_name'] ?: 'မသတ်မှတ်ရသေး'); ?></p>
                </div>
                <div>
                    <p>စျေးနှုန်း - <?= format_currency((int)$course['price']); ?></p>
                    <p>အခမဲ့ကြည့်ရှုပြုနိုင်သော သင်ခန်းစာ ၂ ခုပါဝင်</p>
                </div>
                <a class="btn" style="text-align:center;" href="course.php?id=<?= $course['id']; ?>">အသေးစိတ်ကြည့်</a>
            </article>
        <?php endforeach; ?>
        <?php if (!$courses): ?>
            <p>သင်တန်းများကို database/schema.sql အတိုင်း ထည့်သွင်းပါ။</p>
        <?php endif; ?>
    </div>
</section>

<?php require __DIR__ . '/partials/footer.php'; ?>
