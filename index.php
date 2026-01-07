<?php
$pageTitle = 'သင်ကြားမှုစင်တာ';
require __DIR__ . '/partials/header.php';

$currentUser = $currentUser ?? current_user($pdo);
$userId = $currentUser['id'] ?? null;

$searchTerm = trim($_GET['q'] ?? '');
$languageFilter = trim($_GET['lang'] ?? '');
$levelFilter = trim($_GET['level'] ?? '');
$courseLimit = $searchTerm ? 9 : 6;
$lessonLimit = $searchTerm ? 9 : 6;
$bookLimit = $searchTerm ? 9 : 6;

$languageOptions = $levelOptions = [];
try {
    $languageOptions = $pdo->query('SELECT DISTINCT language FROM courses ORDER BY language')->fetchAll(PDO::FETCH_COLUMN) ?: [];
} catch (PDOException $e) {
    $languageOptions = [];
}

try {
    $levelOptions = $pdo->query('SELECT DISTINCT level FROM courses ORDER BY level')->fetchAll(PDO::FETCH_COLUMN) ?: [];
} catch (PDOException $e) {
    $levelOptions = [];
}

try {
    $courseSql = '
        SELECT c.id, c.language, c.title, c.description, c.level, c.duration_weeks, c.price, c.created_at,
               i.display_name AS instructor_name, i.photo_url AS instructor_photo,
               (SELECT ROUND(AVG(cr.rating), 1) FROM course_ratings cr WHERE cr.course_id = c.id) AS avg_rating,
               (SELECT COUNT(*) FROM course_ratings cr WHERE cr.course_id = c.id) AS rating_count,
               (SELECT cr.rating FROM course_ratings cr WHERE cr.course_id = c.id AND cr.user_id = :user_id) AS user_rating
        FROM courses c
        LEFT JOIN instructors i ON c.instructor_id = i.id
    ';
    $courseConditions = [];
    $courseParams = [];
    if ($searchTerm !== '') {
        $courseConditions[] = '(c.title LIKE :search OR c.language LIKE :search)';
        $courseParams[':search'] = '%' . $searchTerm . '%';
    }
    if ($languageFilter !== '') {
        $courseConditions[] = 'c.language = :lang';
        $courseParams[':lang'] = $languageFilter;
    }
    if ($levelFilter !== '') {
        $courseConditions[] = 'LOWER(c.level) = LOWER(:level)';
        $courseParams[':level'] = $levelFilter;
    }
    if ($courseConditions) {
        $courseSql .= ' WHERE ' . implode(' AND ', $courseConditions);
    }
    $courseSql .= ' ORDER BY c.created_at DESC LIMIT :limit';

    $courseStmt = $pdo->prepare($courseSql);
    foreach ($courseParams as $key => $value) {
        $courseStmt->bindValue($key, $value);
    }
    $courseStmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $courseStmt->bindValue(':limit', $courseLimit, PDO::PARAM_INT);
    $courseStmt->execute();
    $courses = $courseStmt->fetchAll() ?: [];
} catch (PDOException $e) {
    $courses = [];
}

try {
    $bookSql = '
        SELECT b.id, b.title, b.language, b.description, b.file_size, b.cover_path, b.created_at,
               i.display_name AS instructor_name,
               (SELECT ROUND(AVG(br.rating), 1) FROM book_ratings br WHERE br.book_id = b.id) AS avg_rating,
               (SELECT COUNT(*) FROM book_ratings br WHERE br.book_id = b.id) AS rating_count,
               (SELECT br.rating FROM book_ratings br WHERE br.book_id = b.id AND br.user_id = :b_user_id) AS user_rating
        FROM books b
        LEFT JOIN instructors i ON b.instructor_id = i.id
    ';
    $bookConditions = [];
    $bookParams = [];
    if ($searchTerm !== '') {
        $bookConditions[] = '(b.title LIKE :b_search OR b.language LIKE :b_search)';
        $bookParams[':b_search'] = '%' . $searchTerm . '%';
    }
    if ($languageFilter !== '') {
        $bookConditions[] = 'b.language = :b_lang';
        $bookParams[':b_lang'] = $languageFilter;
    }
    if ($bookConditions) {
        $bookSql .= ' WHERE ' . implode(' AND ', $bookConditions);
    }
    $bookSql .= ' ORDER BY b.created_at DESC LIMIT :b_limit';

    $bookStmt = $pdo->prepare($bookSql);
    foreach ($bookParams as $key => $value) {
        $bookStmt->bindValue($key, $value);
    }
    $bookStmt->bindValue(':b_user_id', $userId, PDO::PARAM_INT);
    $bookStmt->bindValue(':b_limit', $bookLimit, PDO::PARAM_INT);
    $bookStmt->execute();
    $books = $bookStmt->fetchAll() ?: [];
} catch (PDOException $e) {
    $books = [];
}

try {
    $lessonSql = '
        SELECT l.id, l.course_id, l.title, l.summary, l.duration_minutes, l.is_free, l.poster_url, l.created_at,
               c.title AS course_title, c.language
        FROM lessons l
        JOIN courses c ON c.id = l.course_id
    ';
    $lessonConditions = [];
    $lessonParams = [];
    if ($searchTerm !== '') {
        $lessonConditions[] = '(l.title LIKE :l_search OR c.title LIKE :l_search OR c.language LIKE :l_search)';
        $lessonParams[':l_search'] = '%' . $searchTerm . '%';
    }
    if ($languageFilter !== '') {
        $lessonConditions[] = 'c.language = :l_lang';
        $lessonParams[':l_lang'] = $languageFilter;
    }
    if ($lessonConditions) {
        $lessonSql .= ' WHERE ' . implode(' AND ', $lessonConditions);
    }
    $lessonSql .= ' ORDER BY l.created_at DESC, l.position ASC LIMIT :l_limit';

    $lessonStmt = $pdo->prepare($lessonSql);
    foreach ($lessonParams as $key => $value) {
        $lessonStmt->bindValue($key, $value);
    }
    $lessonStmt->bindValue(':l_limit', $lessonLimit, PDO::PARAM_INT);
    $lessonStmt->execute();
    $lessons = $lessonStmt->fetchAll() ?: [];
} catch (PDOException $e) {
    $lessons = [];
}

$totalCourses = $totalLessons = $totalBooks = 0;

try {
    $totalCourses = (int) $pdo->query('SELECT COUNT(*) FROM courses')->fetchColumn();
} catch (PDOException $e) {
    $totalCourses = count($courses);
}

try {
    $totalLessons = (int) $pdo->query('SELECT COUNT(*) FROM lessons')->fetchColumn();
} catch (PDOException $e) {
    $totalLessons = count($lessons);
}

try {
    $totalBooks = (int) $pdo->query('SELECT COUNT(*) FROM books')->fetchColumn();
} catch (PDOException $e) {
    $totalBooks = count($books);
}

$recentCoursesCount = $recentLessonsCount = $recentBooksCount = 0;
try {
    $recentCoursesCount = (int) $pdo->query("SELECT COUNT(*) FROM courses WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();
    $recentLessonsCount = (int) $pdo->query("SELECT COUNT(*) FROM lessons WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();
    $recentBooksCount = (int) $pdo->query("SELECT COUNT(*) FROM books WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();
} catch (PDOException $e) {
    $recentCoursesCount = $recentCoursesCount ?: 0;
    $recentLessonsCount = $recentLessonsCount ?: 0;
    $recentBooksCount = $recentBooksCount ?: 0;
}

$userLessonProgress = [
    'viewed' => 0,
    'total' => $totalLessons,
    'percent' => 0,
];

if ($currentUser && $totalLessons > 0) {
    try {
        $lpStmt = $pdo->prepare('
            SELECT COUNT(DISTINCT CASE WHEN lv.views IS NOT NULL THEN l.id END) AS viewed_lessons,
                   COUNT(DISTINCT l.id) AS total_lessons
            FROM lessons l
            LEFT JOIN lesson_views lv ON lv.lesson_id = l.id AND lv.user_id = ?
        ');
        $lpStmt->execute([$currentUser['id']]);
        if ($row = $lpStmt->fetch()) {
            $userLessonProgress['viewed'] = (int) $row['viewed_lessons'];
            $userLessonProgress['total'] = (int) $row['total_lessons'];
            $userLessonProgress['percent'] = $userLessonProgress['total']
                ? min(100, (int) round(($userLessonProgress['viewed'] / $userLessonProgress['total']) * 100))
                : 0;
        }
    } catch (PDOException $e) {
        $userLessonProgress['viewed'] = 0;
    }
}

$userEnrollCount = 0;
if ($currentUser) {
    try {
        $enrollCountStmt = $pdo->prepare('SELECT COUNT(*) FROM enrollments WHERE user_id = ? AND status = "approved"');
        $enrollCountStmt->execute([$currentUser['id']]);
        $userEnrollCount = (int) $enrollCountStmt->fetchColumn();
    } catch (PDOException $e) {
        $userEnrollCount = 0;
    }
}

$recentCoursesPercent = $totalCourses ? min(100, (int) round(($recentCoursesCount / $totalCourses) * 100)) : 0;
$recentLessonsPercent = $totalLessons ? min(100, (int) round(($recentLessonsCount / $totalLessons) * 100)) : 0;
$recentBooksPercent = $totalBooks ? min(100, (int) round(($recentBooksCount / $totalBooks) * 100)) : 0;
$courseEnrollmentCoverage = $totalCourses ? min(100, (int) round(($userEnrollCount / $totalCourses) * 100)) : 0;

$resumeLesson = null;
$recentEnrollment = null;
if ($currentUser) {
    try {
        $resumeStmt = $pdo->prepare('
            SELECT l.id, l.course_id, l.title, l.summary, l.duration_minutes, l.poster_url,
                   c.title AS course_title
            FROM lesson_views lv
            JOIN lessons l ON l.id = lv.lesson_id
            JOIN courses c ON c.id = l.course_id
            WHERE lv.user_id = ?
            ORDER BY lv.updated_at DESC
            LIMIT 1
        ');
        $resumeStmt->execute([$currentUser['id']]);
        $resumeLesson = $resumeStmt->fetch() ?: null;
    } catch (PDOException $e) {
        $resumeLesson = null;
    }

    try {
        $recentEnrollStmt = $pdo->prepare('
            SELECT e.course_id, c.title, c.language, c.level, c.duration_weeks
            FROM enrollments e
            JOIN courses c ON c.id = e.course_id
            WHERE e.user_id = ?
            ORDER BY e.created_at DESC
            LIMIT 1
        ');
        $recentEnrollStmt->execute([$currentUser['id']]);
        $recentEnrollment = $recentEnrollStmt->fetch() ?: null;
    } catch (PDOException $e) {
        $recentEnrollment = null;
    }
}

$featuredCourse = $courses[0] ?? null;
$featuredLesson = $lessons[0] ?? null;
$featuredBook = $books[0] ?? null;
$lessonMeter = $currentUser && $userLessonProgress['total'] ? $userLessonProgress['percent'] : $recentLessonsPercent;
$bookMeter = $recentBooksPercent;
?>

<section class="hero hero-grid">
    <div class="hero-lede reveal show">
        <span class="pill pill-glow"><?= $currentUser ? 'ဆက်လက်လေ့လာခြင်း' : 'သင်ယူသူ · ဆရာ · အက်ဒ်မင်'; ?></span>
        <h1><?= $currentUser ? 'Welcome back, ' . h($currentUser['name']) : 'Unified Learning Canvas for Code, Content & Community'; ?></h1>
        <p><?= $currentUser
            ? 'သင်တန်းများ၊ သင်ခန်းစာများနှင့် စာအုပ်ဒေါင်းလုဒ်များကို ဆက်လက်လေ့လာပြီး Dashboard သို့ တိုက်ရိုက်သွားနိုင်ပါသည်။'
            : 'သင်တန်းများ၊ သင်ခန်းစာများ၊ စာအုပ်ဒေါင်းလုဒ်များနှင့် Live Activity Logs များကို တစ်နေရာတည်းတွင် တိုးတက်အောင် လေ့လာနိုင်သည်။'; ?></p>
        
        <div class="cta-row">
            <a href="<?= $currentUser ? 'dashboard.php' : 'register.php'; ?>" class="btn"><?= $currentUser ? 'Dashboard သို့' : 'အခမဲ့ စတင်ရန်'; ?></a>
            <a href="courses.php" class="btn-ghost">သင်တန်းများ ကြည့်ရန်</a>
        </div>
        <div class="hero-stats">
            <div class="stat-bubble">
                <small>သင်တန်းများ</small>
                <strong><?= $totalCourses; ?>+</strong>
                <span>ပေါင်းစုံ Language</span>
            </div>
            <div class="stat-bubble">
                <small>သင်ခန်းစာ</small>
                <strong><?= $totalLessons; ?>+</strong>
                <span>ဗီဒီယိုနှင့် စာသား</span>
            </div>
            <div class="stat-bubble">
                <small>စာအုပ်</small>
                <strong><?= $totalBooks; ?>+</strong>
                <span>ဒေါင်းလုဒ်ဖိုင်များ</span>
            </div>
        </div>
        <div class="progress-grid">
            <?php if ($currentUser && $userLessonProgress['total'] > 0): ?>
                <div class="progress-chip">
                    <span class="progress-label">ကြည့်ပြီး သင်ခန်းစာ</span>
                    <strong><?= $userLessonProgress['viewed']; ?> / <?= $userLessonProgress['total']; ?></strong>
                    <div class="progress-inline">
                        <div class="progress-meter small"><span style="width:<?= $userLessonProgress['percent']; ?>%"></span></div>
                        <span class="progress-value"><?= $userLessonProgress['percent']; ?>%</span>
                    </div>
                </div>
            <?php endif; ?>
            <?php if ($currentUser): ?>
                <div class="progress-chip">
                    <span class="progress-label">အတန်းဝင်ပြီးသား Courses</span>
                    <strong><?= $userEnrollCount; ?> / <?= $totalCourses; ?></strong>
                    <div class="progress-inline">
                        <div class="progress-meter small"><span style="width:<?= $courseEnrollmentCoverage; ?>%"></span></div>
                        <span class="progress-value"><?= $courseEnrollmentCoverage; ?>%</span>
                    </div>
                </div>
            <?php endif; ?>
            <div class="progress-chip">
                <span class="progress-label">လစဉ် ထည့်သွင်းသစ်</span>
                <strong><?= $recentCoursesCount; ?> courses · <?= $recentLessonsCount; ?> lessons · <?= $recentBooksCount; ?> books</strong>
                <div class="progress-inline">
                    <div class="progress-meter small"><span style="width:<?= $recentCoursesPercent; ?>%"></span></div>
                    <span class="progress-value"><?= $recentCoursesPercent; ?>%</span>
                </div>
            </div>
        </div>
    </div>
    <div class="hero-stack reveal show">
        <div class="panel-card panel-highlight">
            <div class="panel-label"><?= $resumeLesson ? 'ဆက်ကြည့်ရန် Lesson' : 'လတ်တလော သင်ခန်းစာများ'; ?></div>
            <?php if ($resumeLesson): ?>
                <h3><?= h($resumeLesson['title']); ?></h3>
                <p class="muted-text"><?= h($resumeLesson['course_title']); ?> · <?= h($resumeLesson['duration_minutes']); ?> မိနစ်</p>
                <p><?= h(excerpt($resumeLesson['summary'] ?? '', 110)); ?></p>
                <div class="panel-actions">
                    <a class="btn btn-small" href="watch_lesson.php?id=<?= $resumeLesson['id']; ?>">ဆက်ကြည့်</a>
                    <a class="btn-ghost btn-small" href="course.php?id=<?= $resumeLesson['course_id']; ?>">သင်တန်းသို့</a>
                </div>
            <?php elseif ($featuredLesson): ?>
                <h3><?= h($featuredLesson['title']); ?></h3>
                <p class="muted-text"><?= h($featuredLesson['course_title']); ?> · <?= h($featuredLesson['duration_minutes']); ?> မိနစ်</p>
                <p><?= h(excerpt($featuredLesson['summary'] ?? '', 110)); ?></p>
                <div class="panel-actions">
                    <a class="btn btn-small" href="watch_lesson.php?id=<?= $featuredLesson['id']; ?>">ကြည့်ရှုရန်</a>
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
                    <div class="panel-label"><?= $recentEnrollment ? 'တက်ရောက်နေသည့် သင်တန်း' : 'ထူးခြားသော သင်တန်း'; ?></div>
                    <h4><?= $recentEnrollment ? h($recentEnrollment['title']) : ($featuredCourse ? h($featuredCourse['title']) : 'သင်တန်း ထည့်ရန်'); ?></h4>
                    <p class="muted-text">
                        <?php if ($recentEnrollment): ?>
                            <?= h($recentEnrollment['language'] . ' · ' . $recentEnrollment['level'] . ' · ' . $recentEnrollment['duration_weeks'] . ' ပတ်'); ?>
                        <?php elseif ($featuredCourse): ?>
                            <?= h($featuredCourse['language'] . ' · ' . $featuredCourse['level']); ?>
                        <?php else: ?>
                            admin panel မှ ထည့်သွင်းပါ
                        <?php endif; ?>
                    </p>
                </div>
                <?php if ($recentEnrollment): ?>
                    <a class="chip-link" href="course.php?id=<?= $recentEnrollment['course_id']; ?>">ဆက်လေ့လာရန်</a>
                <?php elseif ($featuredCourse): ?>
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
                    <?php if (!empty($featuredBook['instructor_name'])): ?>
                        <p class="muted-text">by <?= h($featuredBook['instructor_name']); ?></p>
                    <?php endif; ?>
                </div>
                <a class="chip-link" href="books.php">ဖတ်ရှုရန်</a>
            </div>
        </div>
    </div>
</section>

<section class="section courses-showcase">
    <div class="section-header">
        <div>
            <div class="eyebrow">သင်တန်းများ</div>
            <h2>Premium Courses & Bootcamps</h2>
            <!-- <p class="muted-text">အပြည့်အဝ ကျက်သရေရှိသော mentor-led လမ်းကြောင်းများ၊ ရှင်းလင်းသော သင်ကြားမှု အဖြေများ။</p> -->
            <?php if ($searchTerm || $languageFilter || $levelFilter): ?>
                <p class="muted-text">Filter - <?= $searchTerm ? 'စကားစု: "' . h($searchTerm) . '"' : ' '; ?> <?= $languageFilter ? 'ဘာသာစကား: ' . h($languageFilter) : ''; ?> <?= $levelFilter ? 'အဆင့်: ' . h($levelFilter) : ''; ?></p>
            <?php endif; ?>
        </div>
        <a href="courses.php" class="btn-ghost btn-small">အားလုံးကြည့်ရန်</a>
    </div>

    <div class="course-grid">
        <?php foreach ($courses as $idx => $course): ?>
            <?php
            $levelKey = strtolower($course['level']);
            $levelLabel = $course['level'] ?: 'Mixed level';
            $isFeatured = $idx === 0;
            $priceText = format_currency((int) $course['price']);
            $durationWeeks = (int) ($course['duration_weeks'] ?? 0);
            $instructorName = $course['instructor_name'] ?: 'မသတ်မှတ်ရသေး';
            $courseCreatedAt = !empty($course['created_at']) ? strtotime($course['created_at']) : null;
            $isNewCourse = $courseCreatedAt ? $courseCreatedAt >= strtotime('-30 days') : false;
            $monogram = strtoupper(substr($course['language'], 0, 2));
            if (function_exists('mb_substr')) {
                $monogram = mb_strtoupper(mb_substr($course['language'], 0, 2));
            }
            $durationProgress = $durationWeeks ? min(100, max(20, (int) round(($durationWeeks / 12) * 100))) : 40;
            $avgRating = $course['avg_rating'] !== null ? (float) $course['avg_rating'] : null;
            $ratingCount = (int) ($course['rating_count'] ?? 0);
            ?>
            <article
                class="course-card-pro reveal<?= $isFeatured ? ' course-card-featured' : ''; ?>"
                tabindex="0"
                role="link"
                data-link="course.php?id=<?= $course['id']; ?>"
                aria-label="<?= h($course['title']); ?>"
            >
                <?php if ($isFeatured): ?>
                    <!-- <span class="featured-pill">Featured</span> -->
                <?php endif; ?>
                <div class="course-card-body">
                    <div class="course-card-cover" aria-hidden="true" style="display:flex;align-items:center;gap:0.75rem;">
                        <div class="course-thumb" style="width:56px;height:56px;border-radius:14px;display:flex;align-items:center;justify-content:center;font-weight:800;color:#0b1224;background:linear-gradient(135deg, color-mix(in srgb, var(--brand-soft) 70%, var(--accent)), color-mix(in srgb, var(--brand) 70%, var(--surface)));overflow:hidden;">
                            <?php if (!empty($course['instructor_photo'])): ?>
                                <img src="<?= h($course['instructor_photo']); ?>" alt="<?= h($instructorName); ?>" style="width:100%;height:100%;object-fit:cover;">
                            <?php else: ?>
                                <span><?= h($monogram); ?></span>
                            <?php endif; ?>
                        </div>
                        <div>
                            <small class="muted-text"><?= $courseCreatedAt ? 'Updated ' . date('M j', $courseCreatedAt) : 'Fresh course'; ?></small>
                            <div class="eyebrow"><?= h($course['language']); ?></div>
                        </div>
                    </div>
                    <div class="course-card-top">
                        <span class="badge-soft"><?= h($course['language']); ?></span>
                        <span class="badge-soft subtle"><?= h($levelLabel); ?></span>
                        <?php if ($isNewCourse): ?>
                            <span class="badge-soft">New</span>
                        <?php endif; ?>
                    </div>
                    <div class="course-card-title">
                        <h3><?= h($course['title']); ?></h3>
                        <p class="muted-text line-clamp-2"><?= h(excerpt($course['description'] ?? '', 140)); ?></p>
                    </div>
                    <div class="course-card-meta">
                        <span class="meta meta-pop">
                            <span class="icon-chip" aria-hidden="true"><svg viewBox="0 0 24 24"><path fill="currentColor" d="M12 12a5 5 0 1 0-5-5a5 5 0 0 0 5 5m0 2c-4 0-7 1.8-7 4v1h14v-1c0-2.2-3-4-7-4"/></svg></span>
                            <strong><?= h($instructorName); ?></strong>
                        </span>
                        <span class="meta meta-pop">
                            <span class="icon-chip" aria-hidden="true"><svg viewBox="0 0 24 24"><path fill="currentColor" d="M12 3a9 9 0 1 0 9 9a9 9 0 0 0-9-9Zm0 3v6l4 2"/></svg></span>
                            <strong><?= $durationWeeks ?: 6; ?> ပတ်</strong>
                        </span>
                        <span class="meta meta-pop">
                            <span class="icon-chip" aria-hidden="true"><svg viewBox="0 0 24 24"><path fill="currentColor" d="m12 2l-2.5 7.5l-7.5.5l6 4.5l-2.2 7.5L12 17l5.2 5l-2.2-7.5l6-4.5l-7.5-.5Z"/></svg></span>
                            <strong><?= h(ucfirst($levelLabel)); ?></strong>
                        </span>
                    </div>
                    <div class="rating-row">
                        <?= render_stars($avgRating); ?>
                        <strong class="rating-number"><?= $avgRating ? number_format($avgRating, 1) . ' / 5' : 'N/A'; ?></strong>
                        <small class="muted-text"><?= $ratingCount ? $ratingCount . ' ratings' : 'Rating မရှိသေးပါ။'; ?></small>
                    </div>
                    <div class="course-card-bottom">
                        <div class="price-block">
                            <span class="price-label">သင်တန်းကြေး</span>
                            <strong><?= $priceText; ?></strong>
                        </div>
                        <div class="cta-stack">
                            <a class="btn course-btn" href="course.php?id=<?= $course['id']; ?>">Enroll</a>
                            <a class="text-link" href="course.php?id=<?= $course['id']; ?>">View syllabus</a>
                        </div>
                    </div>
                </div>
                <div class="course-card-progress">
                    <div class="progress-meter small"><span style="width:<?= $durationProgress; ?>%"></span></div>
                    <span class="progress-value"><?= $durationWeeks ?: 6; ?> ပတ်</span>
                </div>
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
            <h2> အသစ်တင်ထားသော ဗီဒီယိုများ</h2>
            <div class="progress-inline">
                <span class="progress-label"><?= $currentUser ? 'သင်ခန်းစာ ကြည့်ရှုမှု' : 'လစဉ် ထည့်သွင်းသစ်'; ?></span>
                <div class="progress-meter small"><span style="width:<?= $lessonMeter; ?>%"></span></div>
                <span class="progress-value"><?= $lessonMeter; ?>%</span>
            </div>
        </div>
        <a href="dashboard.php" class="btn-ghost btn-small">Dashboard သို့</a>
    </div>
    <div class="cards">
        <?php foreach ($lessons as $lesson): ?>
            <?php
            $lessonDuration = (int) ($lesson['duration_minutes'] ?? 0);
            $lessonProgress = $lessonDuration ? min(100, max(12, (int) round(($lessonDuration / 60) * 100))) : 0;
            $lessonCreatedAt = !empty($lesson['created_at']) ? strtotime($lesson['created_at']) : null;
            $isNewLesson = $lessonCreatedAt ? $lessonCreatedAt >= strtotime('-30 days') : false;
            $lessonMonogram = strtoupper(substr($lesson['course_title'] ?? '', 0, 2));
            if (function_exists('mb_substr')) {
                $lessonMonogram = mb_strtoupper(mb_substr($lesson['course_title'] ?? '', 0, 2));
            }
            ?>
            <article class="card video-card reveal">
                <div class="media-thumb" style="position:relative;border-radius:14px;overflow:hidden;background:var(--surface);">
                    <?php if (!empty($lesson['poster_url'])): ?>
                        <img src="<?= h($lesson['poster_url']); ?>" alt="<?= h($lesson['title']); ?> thumbnail" style="width:100%;height:160px;object-fit:cover;">
                    <?php else: ?>
                        <div style="height:160px;display:flex;align-items:center;justify-content:center;font-weight:800;background:linear-gradient(135deg, color-mix(in srgb, var(--accent) 60%, var(--brand-soft)), color-mix(in srgb, var(--brand) 60%, var(--surface)));color:#0b1224;">
                            <?= h($lessonMonogram); ?>
                        </div>
                    <?php endif; ?>
                    <span class="tag" style="position:absolute;bottom:10px;left:10px;"><?= h($lesson['language']); ?></span>
                    <?php if ($isNewLesson): ?>
                        <span class="tag" style="position:absolute;top:10px;left:10px;">New</span>
                    <?php endif; ?>
                </div>
                <div class="video-meta">
                    <span class="tag"><?= h($lesson['is_free'] ? 'Free' : 'Premium'); ?></span>
                    <span><?= h($lesson['duration_minutes']); ?> မိနစ်</span>
                </div>
                <h3><?= h($lesson['title']); ?></h3>
                <p class="muted-text"><?= h($lesson['course_title']); ?></p>
                <p><?= h(excerpt($lesson['summary'] ?? '', 120)); ?></p>
                <div class="progress-inline">
                    <span class="progress-label">Watch time</span>
                    <div class="progress-meter small"><span style="width:<?= $lessonProgress; ?>%"></span></div>
                    <span class="progress-value"><?= $lessonProgress; ?>%</span>
                </div>
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
        <div class="box reveal box-gradient book-carousel-shell">
            <div class="section-header book-carousel-header">
                <div>
                    <div class="eyebrow">စာအုပ် Download</div>
                    <h2>အသစ်တင်ထားသော စာအုပ်များ</h2>
                    <div class="progress-inline">
                        <span class="progress-label">အသစ် တင်ထားသော စာအုပ်များ</span>
                        <div class="progress-meter small"><span style="width:<?= $bookMeter; ?>%"></span></div>
                        <span class="progress-value"><?= $bookMeter; ?>%</span>
                    </div>
                </div>
                <div class="book-carousel-actions">
                    <a class="chip-link" href="books.php">စာအုပ် အားလုံး</a>
                    <?php if ($books): ?>
                        <div class="carousel-nav" aria-label="Book carousel navigation">
                            <button class="carousel-arrow" type="button" data-target="bookCarousel" data-direction="prev" aria-label="Previous books">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M15 5L8 12L15 19" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </button>
                            <button class="carousel-arrow" type="button" data-target="bookCarousel" data-direction="next" aria-label="Next books">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M9 5L16 12L9 19" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php if ($books): ?>
                <div class="book-carousel" id="bookCarousel">
                    <?php foreach ($books as $book): ?>
                        <?php
                        $bookSize = (float) ($book['file_size'] ?? 0);
                        $bookSizeValue = $bookSize ? number_format($bookSize, 1) : ($book['file_size'] ?? 'N/A');
                        $bookCreatedAt = !empty($book['created_at']) ? strtotime($book['created_at']) : null;
                        $isNewBook = $bookCreatedAt ? $bookCreatedAt >= strtotime('-30 days') : false;
                        $bookMonogram = strtoupper(substr($book['title'], 0, 2));
                        if (function_exists('mb_substr')) {
                            $bookMonogram = mb_strtoupper(mb_substr($book['title'], 0, 2));
                        }
                        $avgRating = $book['avg_rating'] !== null ? (float) $book['avg_rating'] : null;
                        $ratingCount = (int) ($book['rating_count'] ?? 0);
                        ?>
                        <article class="book-slide reveal" role="group" aria-label="<?= h($book['title']); ?>">
                            <div class="book-slide__header">
                                <span class="badge-soft"><?= h($book['language']); ?></span>
                                <?php if ($isNewBook): ?>
                                    <span class="badge-soft subtle">New</span>
                                <?php endif; ?>
                            </div>
                            <div class="book-slide__body">  
                                <div class="book-slide__cover">
                                    <?php if (!empty($book['cover_path'])): ?>
                                        <img src="<?= h(str_replace('\\\\', '/', $book['cover_path'])); ?>" alt="<?= h($book['title']); ?> cover">
                                    <?php else: ?>
                                        <span><?= h($bookMonogram); ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="book-slide__content">
                                    <h3><?= h($book['title']); ?></h3>
                                    <p class="muted-text"><?= h(excerpt($book['description'] ?? '', 120)); ?></p>
                                    <div class="rating-row">
                                        <?= render_stars($avgRating); ?>
                                        <strong class="rating-number"><?= $avgRating ? number_format($avgRating, 1) . ' / 5' : 'N/A'; ?></strong>
                                        <small class="muted-text"><?= $ratingCount ? $ratingCount . ' ratings' : 'Rating မရှိသေးပါ။'; ?></small>
                                    </div>
                                    <div class="book-slide__meta">
                                        <span><?= h($bookSizeValue); ?> MB</span>
                                        <?php if (!empty($book['instructor_name'])): ?>
                                            <span class="dot">•</span>
                                            <span><?= h($book['instructor_name']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="book-slide__actions">
                                        <a class="btn btn-small" href="actions/download_book.php?id=<?= $book['id']; ?>&csrf=<?= csrf_token(); ?>">ဒေါင်းလုဒ်</a>
                                        <a class="btn-ghost btn-small" href="books.php">အသေးစိတ်</a>
                                    </div>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p>စာအုပ်များမရှိသေးပါ။ database/schema.sql အတိုင်း ထည့်သွင်းပါ။</p>
            <?php endif; ?>
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
