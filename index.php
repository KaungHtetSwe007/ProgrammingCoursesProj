<?php
$pageTitle = 'သင်ကြားမှုစင်တာ';
require __DIR__ . '/partials/header.php';

try {
    $courseStmt = $pdo->query('SELECT id, language, title, level, price FROM courses ORDER BY created_at DESC LIMIT 3');
    $courses = $courseStmt->fetchAll() ?: [];
} catch (PDOException $e) {
    $courses = [];
}

try {
    $bookStmt = $pdo->query('SELECT id, title, language, file_size FROM books ORDER BY created_at DESC LIMIT 3');
    $books = $bookStmt->fetchAll() ?: [];
} catch (PDOException $e) {
    $books = [];
}

try {
    $lessonStmt = $pdo->query('
        SELECT l.id, l.course_id, l.title, l.summary, l.duration_minutes, l.is_free, c.title AS course_title
        FROM lessons l
        JOIN courses c ON c.id = l.course_id
        ORDER BY l.created_at DESC, l.position ASC
        LIMIT 4
    ');
    $lessons = $lessonStmt->fetchAll() ?: [];
} catch (PDOException $e) {
    $lessons = [];
}
?>

<section class="hero">
    <div class="hero-card reveal show">
        <span class="pill">သင်ယူသူ · ဆရာ · အက်ဒ်မင် အားလုံးအတွက်</span>
        <h1>တက်ကြွ Video Lessons, Downloadable Books & Realtime Logs</h1>
        <p>စာအုပ်ဒေါင်းလုဒ်၊ ဗီဒီယိုသင်ခန်းစာများနှင့် Team Chat ကို တစ်နေရာတည်းမှာပင် ကြည့်၊ လေ့လာ၊ ချိတ်ဆက်နိုင်စေဖို့ အသစ်ထပ်တိုးထားပါပြီ။</p>
        <div style="display:flex; gap:1rem; flex-wrap:wrap; margin-top:1rem;">
            <a href="register.php" class="btn">အခမဲ့ စတင်ပါ</a>
            <a href="courses.php" class="btn-ghost">သင်တန်းများကြည့်</a>
        </div>
        <ul>
            <li>Light / Dark Theme toggle</li>
            <li>ဒေါင်းလုဒ်စာအုပ်များကို Cover Image ဖြင့်</li>
            <li>အက်ဒ်မင်၊ ဆရာ၊ ကျောင်းသား အားလုံးအတွက် Activity Logs</li>
            <li>ဗီဒီယိုသင်ခန်းစာ များစွာ ထပ်တိုးထား</li>
        </ul>
    </div>
    <div class="hero-visual reveal show">
        <div class="floating-card">
            <div class="video-meta"><span>🚀</span> လတ်တလော ကြည့်ရှုမှု</div>
            <strong>Laravel Routing</strong>
            <small>Router Model Binding · 24 mins</small>
            <div class="progress-bar"><span style="width:72%"></span></div>
            <small>Activity log: viewed by 12 learners today</small>
        </div>
        <div class="floating-card" style="margin-top:1rem; animation-delay:0.6s;">
            <div class="video-meta"><span>📕</span> Top Download</div>
            <strong>Python Automation Handbook</strong>
            <small>5.1 MB · PDF · Favourite by 32 students</small>
            <a class="btn-ghost" href="books.php">Download now</a>
        </div>
    </div>
</section>

<section class="section">
    <div class="eyebrow">သင်တန်းများ</div>
    <h2>အသစ်တင်သင်တန်းများ</h2>
    <p class="muted-text">စွမ်းဆောင်ရည်မြင့် သင်တန်းအစီအစဉ်များကို Theme animation အသစ်နှင့် ပြသထားပါတယ်။</p>
    <div class="cards">
        <?php foreach ($courses as $course): ?>
            <article class="card reveal">
                <span class="tag"><?= h($course['language']); ?></span>
                <h3><?= h($course['title']); ?></h3>
                <p>အဆင့် - <?= h($course['level']); ?></p>
                <p><?= format_currency((int) $course['price']); ?></p>
                <a class="btn" style="text-align:center;" href="course.php?id=<?= $course['id']; ?>">အသေးစိတ်</a>
            </article>
        <?php endforeach; ?>
        <?php if (!$courses): ?>
            <p>သင်တန်းစာရင်း မရှိသေးပါ။ Admin Panel မှ ထည့်သွင်းပါ။</p>
        <?php endif; ?>
    </div>
</section>

<section class="section">
    <div class="eyebrow">ဗီဒီယိုသင်ခန်းစာများ</div>
    <h2>အခမဲ့ ကြည့်ရှုနိုင် သို့မဟုတ် အသစ်တင်ထားသည်များ</h2>
    <div class="cards">
        <?php foreach ($lessons as $lesson): ?>
            <article class="card video-card reveal">
                <div class="video-meta">
                    <span class="tag"><?= h($lesson['is_free'] ? 'Free' : 'Premium'); ?></span>
                    <span><?= h($lesson['duration_minutes']); ?> မိနစ်</span>
                </div>
                <h3><?= h($lesson['title']); ?></h3>
                <p class="muted-text"><?= h($lesson['course_title']); ?></p>
                <p><?= h(excerpt($lesson['summary'] ?? '', 120)); ?></p>
                <div style="display:flex; gap:0.6rem; flex-wrap:wrap;">
                    <a class="btn" href="watch_lesson.php?id=<?= $lesson['id']; ?>">ကြည့်ရှု</a>
                    <a class="btn-ghost" href="course.php?id=<?= $lesson['course_id']; ?>">သင်တန်းသို့</a>
                </div>
            </article>
        <?php endforeach; ?>
        <?php if (!$lessons): ?>
            <p>သင်ခန်းစာများ မထည့်ရသေးပါ။ database/schema.sql ထည့်ပါ။</p>
        <?php endif; ?>
    </div>
</section>

<section class="section">
    <div class="two-column">
        <div class="box reveal">
            <div class="eyebrow">စာအုပ် Download</div>
            <h2>သစ်လွင်သော စာအုပ်များ</h2>
            <?php foreach ($books as $book): ?>
                <div class="book-card" style="margin-bottom:1rem;">
                    <div class="book-cover"></div>
                    <div>
                        <strong><?= h($book['title']); ?></strong>
                        <p>ဘာသာ - <?= h($book['language']); ?> · <?= h($book['file_size']); ?> MB</p>
                        <a href="books.php" class="btn-ghost">ဖတ်ရှု / ဒေါင်းလုဒ်</a>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php if (!$books): ?>
                <p>စာအုပ်များမရှိသေးပါ။ database/schema.sql အတိုင်း ထည့်သွင်းပါ။</p>
            <?php endif; ?>
        </div>
        <div class="box reveal">
            <div class="eyebrow">Community & Logs</div>
            <h2>Chat + Activity Log + Profile Upload</h2>
            <p>သင်တန်းဝင်များနှင့် ဆရာများအကြား တက်ကြွဆွေးနွေးမှု၊ အက်ဒ်မင် log များ၊ Profile Photo Upload အားလုံးကို Dashboard ထဲမှ ထိန်းချုပ်နိုင်ပါပြီ။</p>
            <a href="dashboard.php" class="btn">ထိပ်တန်းသင်တန်းဝင် Dashboard</a>
        </div>
    </div>
</section>

<?php require __DIR__ . '/partials/footer.php'; ?>
