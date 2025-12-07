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

$featuredCourse = $courses[0] ?? null;
$featuredLesson = $lessons[0] ?? null;
$featuredBook = $books[0] ?? null;
?>

<section class="hero hero-grid">
    <div class="hero-lede reveal show">
        <span class="pill pill-glow">သင်ယူသူ · ဆရာ · အက်ဒ်မင်</span>
        <h1>Unified Learning Canvas for Code, Content & Community</h1>
        <p>ဤပေါင်းစပ်ရုံအတွက် သင်တန်းများ၊ သင်ခန်းစာများ၊ စာအုပ်ဒေါင်းလုဒ်များနှင့် Live Activity Logs များကို တစ်နေရာတည်းတွင် တိုးတက်အောင် လေ့လာနိုင်သည်။</p>
        <div class="cta-row">
            <a href="register.php" class="btn">အခမဲ့ စတင်ရန်</a>
            <a href="courses.php" class="btn-ghost">သင်တန်းများ ကြည့်ရန်</a>
        </div>
        <div class="hero-stats">
            <div class="stat-bubble">
                <small>သင်တန်းများ</small>
                <strong><?= count($courses); ?>+</strong>
                <span>နောက်ဆုံး တင်သွင်း</span>
            </div>
            <div class="stat-bubble">
                <small>သင်ခန်းစာ</small>
                <strong><?= count($lessons); ?>+</strong>
                <span>ဗီဒီယိုနှင့် စာသား</span>
            </div>
            <div class="stat-bubble">
                <small>စာအုပ်</small>
                <strong><?= count($books); ?>+</strong>
                <span>ဒေါင်းလုဒ်ဖိုင်များ</span>
            </div>
        </div>
    </div>
    <div class="hero-stack reveal show">
        <div class="panel-card panel-highlight">
            <div class="panel-label">လတ်တလော သင်ခန်းစာ</div>
            <?php if ($featuredLesson): ?>
                <h3><?= h($featuredLesson['title']); ?></h3>
                <p class="muted-text"><?= h($featuredLesson['course_title']); ?> · <?= h($featuredLesson['duration_minutes']); ?> မိနစ်</p>
                <p><?= h(excerpt($featuredLesson['summary'] ?? '', 110)); ?></p>
                <div class="panel-actions">
                    <a class="btn btn-small" href="watch_lesson.php?id=<?= $featuredLesson['id']; ?>">ကြည့်ရှု</a>
                    <a class="btn-ghost btn-small" href="course.php?id=<?= $featuredLesson['course_id']; ?>">သင်တန်းသို့</a>
                </div>
            <?php else: ?>
                <h3>သင်ခန်းစာများ မရှိသေးပါ</h3>
                <p class="muted-text">schema.sql အတိုင်း ထည့်သွင်းပြီး စတင်ပါ။</p>
            <?php endif; ?>
        </div>
        <div class="panel-card panel-compact">
            <div class="panel-row">
                <div>
                    <div class="panel-label">ထူးခြားသော သင်တန်း</div>
                    <h4><?= $featuredCourse ? h($featuredCourse['title']) : 'သင်တန်း ထည့်ရန်'; ?></h4>
                    <p class="muted-text"><?= $featuredCourse ? h($featuredCourse['language'] . ' · ' . $featuredCourse['level']) : 'admin panel မှ ထည့်သွင်းပါ'; ?></p>
                </div>
                <?php if ($featuredCourse): ?>
                    <a class="chip-link" href="course.php?id=<?= $featuredCourse['id']; ?>">အသေးစိတ်</a>
                <?php else: ?>
                    <a class="chip-link" href="admin.php">ထည့်သွင်း</a>
                <?php endif; ?>
            </div>
            <div class="panel-row">
                <div>
                    <div class="panel-label">Top Download</div>
                    <h4><?= $featuredBook ? h($featuredBook['title']) : 'စာအုပ်မရှိသေး'; ?></h4>
                    <p class="muted-text"><?= $featuredBook ? h($featuredBook['language'] . ' · ' . $featuredBook['file_size'] . ' MB') : 'books table ထည့်ပါ'; ?></p>
                </div>
                <a class="chip-link" href="books.php">ဖတ်ရှု</a>
            </div>
        </div>
    </div>
</section>

<section class="section">
    <div class="section-header">
        <div>
            <div class="eyebrow">သင်တန်းများ</div>
            <h2>အသစ်တင်သင်တန်းများ</h2>
            <p class="muted-text">သင်ယူသင့်သော programming languages များကို storyline style ဖြင့် ပြသထားသည်။</p>
        </div>
        <a href="courses.php" class="btn-ghost btn-small">အားလုံးကြည့်</a>
    </div>
    <div class="cards">
        <?php foreach ($courses as $course): ?>
            <article class="card course-card reveal">
                <div class="card-top">
                    <span class="tag"><?= h($course['language']); ?></span>
                    <span class="pill thin-pill"><?= h($course['level']); ?></span>
                </div>
                <h3><?= h($course['title']); ?></h3>
                <p class="muted-text"><?= format_currency((int) $course['price']); ?></p>
                <a class="chip-link" href="course.php?id=<?= $course['id']; ?>">သင်တန်း အသေးစိတ်</a>
            </article>
        <?php endforeach; ?>
        <?php if (!$courses): ?>
            <p>သင်တန်းစာရင်း မရှိသေးပါ။ Admin Panel မှ ထည့်သွင်းပါ။</p>
        <?php endif; ?>
    </div>
</section>

<section class="section">
    <div class="section-header">
        <div>
            <div class="eyebrow">ဗီဒီယိုသင်ခန်းစာများ</div>
            <h2>အခမဲ့ ကြည့်ရှုနိုင် သို့မဟုတ် အသစ်တင်ထားသည်များ</h2>
        </div>
        <a href="dashboard.php" class="btn-ghost btn-small">Dashboard သို့</a>
    </div>
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
                <div class="inline-actions">
                    <a class="btn btn-small" href="watch_lesson.php?id=<?= $lesson['id']; ?>">ကြည့်ရှု</a>
                    <a class="btn-ghost btn-small" href="course.php?id=<?= $lesson['course_id']; ?>">သင်တန်းသို့</a>
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
        <div class="box reveal box-gradient">
            <div class="section-header">
                <div>
                    <div class="eyebrow">စာအုပ် Download</div>
                    <h2>အသစ်တင်ထားသော စာအုပ်များ</h2>
                </div>
                <a class="chip-link" href="books.php">စာအုပ် အားလုံး</a>
            </div>
            <div class="cards">
                <?php foreach (array_slice($books, 0, 3) as $book): ?>
                    <article class="card book-card reveal">
                        <div class="book-cover">
                            <?php if (!empty($book['cover_path'])): ?>
                                <img src="<?= h(str_replace('\\\\', '/', $book['cover_path'])); ?>" alt="<?= h($book['title']); ?> cover">
                            <?php endif; ?>
                        </div>
                        <div>
                            <strong><?= h($book['title']); ?></strong>
                            <p class="muted-text"><?= h($book['language']); ?> · <?= h($book['file_size']); ?> MB</p>
                            <a href="books.php" class="chip-link">ဖတ်ရှု / ဒေါင်းလုဒ်</a>
                        </div>
                    </article>
                <?php endforeach; ?>
                <?php if (!$books): ?>
                    <p>စာအုပ်များမရှိသေးပါ။ database/schema.sql အတိုင်း ထည့်သွင်းပါ။</p>
                <?php endif; ?>
            </div>
        </div>
        <div class="box reveal box-lined">
            <div class="eyebrow">Community & Logs</div>
            <h2>Chat + Activity Log + Profile Upload</h2>
            <p>သင်တန်းဝင်များ၊ ဆရာများနှင့် အက်ဒ်မင်တို့ အတွက် လှုပ်ရှားမှုများကို stream-like view ဖြင့် ကြည့်ရှုနိုင်ပြီး Dashboard သို့ တိုက်ရိုက်ဝင်ရောက် ထိန်းချုပ်နိုင်ပါသည်။</p>
            <a href="dashboard.php" class="btn">Dashboard ဝင်ရောက်မည်</a>
        </div>
    </div>
</section>

<?php require __DIR__ . '/partials/footer.php'; ?>
