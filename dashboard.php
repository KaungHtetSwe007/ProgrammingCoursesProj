<?php
require __DIR__ . '/includes/functions.php';
$user = require_login($pdo);
$isAdmin = is_admin($pdo);
$isInstructor = is_instructor($pdo);
$pageTitle = 'ကျွန်ုပ်၏ဘုတ်';
require __DIR__ . '/partials/header.php';

$enrollments = [];
$progressByCourse = [];
$favoriteBooks = [];
$instructorRecord = null;
$instructorCourses = [];
$lessonsByCourse = [];
$instructorBooks = [];

if (!$isAdmin && !$isInstructor) {
    $enrollStmt = $pdo->prepare('
        SELECT e.*, c.title, c.language, c.id AS course_id
        FROM enrollments e
        JOIN courses c ON e.course_id = c.id
        WHERE e.user_id = ?
        ORDER BY e.created_at DESC
    ');
    $enrollStmt->execute([$user['id']]);
    $enrollments = $enrollStmt->fetchAll();

    $progressStmt = $pdo->prepare('
        SELECT l.course_id,
               COUNT(DISTINCT l.id) AS total_lessons,
               COUNT(DISTINCT CASE WHEN lv.views IS NOT NULL THEN l.id END) AS finished_lessons
        FROM lessons l
        LEFT JOIN lesson_views lv ON lv.lesson_id = l.id AND lv.user_id = ?
        GROUP BY l.course_id
    ');
    $progressStmt->execute([$user['id']]);
    foreach ($progressStmt->fetchAll() as $row) {
        $progressByCourse[$row['course_id']] = $row;
    }

    $favStmt = $pdo->prepare('
        SELECT b.*
        FROM favorite_books fb
        JOIN books b ON fb.book_id = b.id
        WHERE fb.user_id = ?
    ');
    $favStmt->execute([$user['id']]);
    $favoriteBooks = $favStmt->fetchAll();
}

if ($isInstructor) {
    $insStmt = $pdo->prepare('SELECT * FROM instructors WHERE user_id = ? LIMIT 1');
    $insStmt->execute([$user['id']]);
    $instructorRecord = $insStmt->fetch();

    if ($instructorRecord) {
        $courseStmt = $pdo->prepare('SELECT * FROM courses WHERE instructor_id = ? ORDER BY created_at DESC');
        $courseStmt->execute([$instructorRecord['id']]);
        $instructorCourses = $courseStmt->fetchAll();

        if ($instructorCourses) {
            $ids = array_column($instructorCourses, 'id');
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $lessonStmt = $pdo->prepare("
                SELECT * FROM lessons WHERE course_id IN ($placeholders) ORDER BY course_id, position, created_at DESC
            ");
            $lessonStmt->execute($ids);
            foreach ($lessonStmt->fetchAll() as $lesson) {
                $lessonsByCourse[$lesson['course_id']][] = $lesson;
            }
        }

        $bookStmt = $pdo->prepare('SELECT * FROM books WHERE instructor_id = ? ORDER BY created_at DESC');
        $bookStmt->execute([$instructorRecord['id']]);
        $instructorBooks = $bookStmt->fetchAll();
    }
}

$activityLogs = recent_logs($pdo, $user['id']);

$adminCounts = [];
$topIncome = [];
if ($isAdmin) {
    $countStmt = $pdo->query('
        SELECT
            (SELECT COUNT(*) FROM users WHERE role = "student") AS students,
            (SELECT COUNT(*) FROM users WHERE role = "instructor") AS instructors,
            (SELECT COUNT(*) FROM enrollments WHERE status = "pending") AS pending_enrollments,
            (SELECT COUNT(*) FROM courses) AS courses
    ');
    $adminCounts = $countStmt->fetch() ?: [];

    $incomeStmt = $pdo->query('SELECT display_name, annual_income FROM instructors ORDER BY annual_income DESC LIMIT 5');
    $topIncome = $incomeStmt->fetchAll() ?: [];
}
?>

<section class="section">
    <div class="two-column">
        <div class="box reveal">
            <h2>မင်္ဂလာပါ <?= h($user['name']); ?> ✨</h2>
            <?php if ($isAdmin): ?>
                <p>Admin အနေဖြင့် Mentor/Student စီမံခန့်ခွဲခြင်း၊ Pending Enrollments ထိန်းခြင်းနှင့် ဝင်ငွေ Graph၊ Logs များကို ဒီနေရာမှ တစ်ချက်တည်း ကြည့်ရှုနိုင်ပါသည်။</p>
                <div style="display:flex; gap:0.8rem; flex-wrap:wrap; align-items:center;">
                    <img src="<?= avatar_url($user); ?>" alt="avatar" style="width:70px;height:70px;border-radius:50%;object-fit:cover;">
                    <a class="btn" href="admin.php">Admin Panel</a>
                    <a class="btn-ghost" href="instructors.php">Mentor စာရင်း</a>
                    <a class="btn-ghost" href="courses.php">သင်တန်း စီမံ</a>
                </div>
            <?php elseif ($isInstructor): ?>
                <p>Mentor Studio မှ သင်တန်းအလိုက် ဗီဒီယိုသင်ခန်းစာများကို Upload/ထိန်းချုပ်နိုင်ပါသည်။ ပထမ ၂ ခုသာ အခမဲ့၊ ကျန် Lesson များကို Enrolled သင်တန်းဝင်များသာ ကြည့်နိုင်စေပါသည်။</p>
                <div style="display:flex; gap:0.8rem; flex-wrap:wrap; align-items:center;">
                    <img src="<?= avatar_url($user); ?>" alt="avatar" style="width:70px;height:70px;border-radius:50%;object-fit:cover;">
                    <a class="btn-ghost" href="instructors.php">ပရိုဖိုင်ကြည့်</a>
                    <a class="btn-ghost" href="courses.php">သင်တန်းများ</a>
                </div>
            <?php else: ?>
                <p>သင်တန်းများ၊ Favourite စာအုပ်များနှင့် Chat Community ကို ဒီနေရာထဲကနေ တစ်ချက်တည်းထိန်းချုပ်ပါ။ Theme toggle နှင့် Profile Upload အား အသုံးပြုနိုင်ပါပြီ။</p>
                <div style="display:flex; gap:0.8rem; flex-wrap:wrap; align-items:center;">
                    <img src="<?= avatar_url($user); ?>" alt="avatar" style="width:70px;height:70px;border-radius:50%;object-fit:cover;">
                    <form method="post" action="actions/upload_avatar.php" enctype="multipart/form-data">
                        <input type="hidden" name="csrf" value="<?= csrf_token(); ?>">
                        <div class="form-group" style="margin-bottom:0.4rem;">
                            <label>Profile Image (JPG/PNG)</label>
                            <input type="file" name="avatar" accept="image/*" required>
                        </div>
                        <button class="btn-ghost btn-small" type="submit">Upload</button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
        <div class="box reveal">
            <h3>Quick Stats</h3>
            <?php if ($isAdmin): ?>
                <p>ကျောင်းသား <?= h($adminCounts['students'] ?? 0); ?> ဦး</p>
                <p>Mentor <?= h($adminCounts['instructors'] ?? 0); ?> ဦး</p>
                <p>Pending Enrollments <?= h($adminCounts['pending_enrollments'] ?? 0); ?> ခု</p>
                <p>သင်တန်း <?= h($adminCounts['courses'] ?? 0); ?> ခု</p>
            <?php elseif ($isInstructor): ?>
                <p>သင်တန်း <?= count($instructorCourses); ?> ခု</p>
                <p>သင်ခန်းစာ <?= array_sum(array_map('count', $lessonsByCourse)); ?> ခု</p>
                <p>Activity Logs <?= count($activityLogs); ?> ခု</p>
            <?php else: ?>
                <p>သင်တန်းဝင်မှု <?= count($enrollments); ?> ခု</p>
                <p>Favourite စာအုပ် <?= count($favoriteBooks); ?> ခု</p>
                <p>Activity Logs <?= count($activityLogs); ?> ခု</p>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php if (!$isAdmin && !$isInstructor): ?>
<section class="section">
    <div class="eyebrow">သင်တန်းတက်ရောက်မှုများ</div>
    <h2>သင်တန်းတက်ရောက်မှုများ</h2>
    <div class="cards">
        <?php foreach ($enrollments as $enroll): ?>
            <?php
            $progressRow = $progressByCourse[$enroll['course_id']] ?? ['finished_lessons' => 0, 'total_lessons' => 0];
            $total = (int)($progressRow['total_lessons'] ?? 0);
            $finished = (int)($progressRow['finished_lessons'] ?? 0);
            $percent = $total ? (int) round(($finished / $total) * 100) : 0;
            ?>
            <article class="card reveal">
                <span class="tag"><?= h($enroll['language']); ?></span>
                <h3><?= h($enroll['title']); ?></h3>
                <p>အခြေအနေ -
                    <span class="status-pill status-<?= h($enroll['status']); ?>"><?= h(enrollment_label($enroll['status'])); ?></span>
                </p>
                <div class="progress-bar"><span style="width:<?= $percent; ?>%"></span></div>
                <small><?= $finished; ?>/<?= $total; ?> သင်ခန်းစာ ပြီးပါပြီ</small>
                <div style="display:flex; gap:0.5rem; flex-wrap:wrap;">
                    <a class="btn" href="course.php?id=<?= $enroll['course_id']; ?>">သင်ခန်းစာများ</a>
                    <?php if ($enroll['status'] === 'approved'): ?>
                        <a class="btn-ghost" href="chat.php?course_id=<?= $enroll['course_id']; ?>">Chat</a>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
        <?php if (!$enrollments): ?>
            <p>သင်တန်းတက်ရောက်ခြင်းများမရှိသေးပါ။ <a href="courses.php">သင်တန်းရွေးချယ်ပါ</a></p>
        <?php endif; ?>
    </div>
</section>

<section class="section">
    <h2>Favourite စာအုပ်များ</h2>
    <div class="cards">
        <?php foreach ($favoriteBooks as $book): ?>
            <article class="card reveal">
                <h3><?= h($book['title']); ?></h3>
                <p><?= h($book['language']); ?></p>
                <div>
                    <a class="btn" href="actions/download_book.php?id=<?= $book['id']; ?>&csrf=<?= csrf_token(); ?>">ဒေါင်းလုဒ်</a>
                </div>
            </article>
        <?php endforeach; ?>
        <?php if (!$favoriteBooks): ?>
            <p>Favourite မရှိသေးပါ။ စာအုပ်စာရင်းသို့ သွားပြီး သတ်မှတ်ပါ။</p>
        <?php endif; ?>
    </div>
</section>

<section class="section">
    <div class="eyebrow">Activity Logs</div>
    <h2>ကျွန်ုပ်၏ Recent Actions</h2>
    <div class="cards">
        <?php foreach ($activityLogs as $log): ?>
            <article class="card reveal">
                <h3><?= h($log['action']); ?></h3>
                <p class="muted-text"><?= h($log['context']); ?></p>
                <small class="muted-text"><?= h($log['created_at']); ?></small>
            </article>
        <?php endforeach; ?>
        <?php if (!$activityLogs): ?>
            <p>Activity log မရှိသေးပါ။ သင်တန်းတက်ခြင်း၊ ဒေါင်းလုဒ်လုပ်ခြင်းများ ပြုလုပ်ပါ။</p>
        <?php endif; ?>
    </div>
</section>
<?php else: ?>
<section class="section">
    <div class="eyebrow">Income Insight</div>
    <h2>Mentor Annual Income (Top 5)</h2>
    <div class="cards">
        <?php
        $maxIncome = 0;
        foreach ($topIncome as $row) {
            $maxIncome = max($maxIncome, (int) $row['annual_income']);
        }
        ?>
        <?php foreach ($topIncome as $row): ?>
            <?php $width = $maxIncome ? (int) round(($row['annual_income'] / $maxIncome) * 100) : 0; ?>
            <article class="card reveal">
                <h3><?= h($row['display_name']); ?></h3>
                <p class="muted-text">ဝင်ငွေ - <?= format_currency((int) $row['annual_income']); ?></p>
                <div class="progress-bar"><span style="width:<?= $width; ?>%"></span></div>
            </article>
        <?php endforeach; ?>
        <?php if (!$topIncome): ?>
            <p>Mentor ဝင်ငွေဒေတာ မရှိသေးပါ။</p>
        <?php endif; ?>
    </div>
</section>

<section class="section">
    <div class="eyebrow">Activity Logs</div>
    <h2>ကျွန်ုပ်၏ Recent Actions</h2>
    <div class="cards">
        <?php foreach ($activityLogs as $log): ?>
            <article class="card reveal">
                <h3><?= h($log['action']); ?></h3>
                <p class="muted-text"><?= h($log['context']); ?></p>
                <small class="muted-text"><?= h($log['created_at']); ?></small>
            </article>
        <?php endforeach; ?>
        <?php if (!$activityLogs): ?>
            <p>Activity log မရှိသေးပါ။ Admin Panel တွင် အလုပ်လုပ်ခြင်းကို စတင်ပါ။</p>
        <?php endif; ?>
    </div>
</section>
<?php endif; ?>

<?php if ($isInstructor): ?>
<section class="section">
    <div class="eyebrow">Mentor Studio</div>
    <h2>သင်ခန်းစာ Upload (ပထမ ၂ ခုအခမဲ့)</h2>
    <form method="post" action="actions/upload_lesson.php" enctype="multipart/form-data">
        <input type="hidden" name="csrf" value="<?= csrf_token(); ?>">
        <div class="form-group">
            <label>သင်တန်းရွေးချယ်ပါ</label>
            <select name="course_id" required>
                <option value="">-- သင်တန်းရွေး --</option>
                <?php foreach ($instructorCourses as $course): ?>
                    <option value="<?= $course['id']; ?>"><?= h($course['title']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>သင်ခန်းစာခေါင်းစဉ်</label>
            <input type="text" name="title" required>
        </div>
        <div class="form-group">
            <label>အကျဉ်းချုပ်</label>
            <textarea name="summary" rows="3"></textarea>
        </div>
        <div class="form-group">
            <label>ဗီဒီယိုဖိုင် (MP4/WebM)</label>
            <input type="file" name="video_file" accept="video/mp4,video/webm" required>
        </div>
        <div class="form-group">
            <label>ဗီဒီယို ကြာချိန် (မိနစ်)</label>
            <input type="number" name="duration_minutes" min="1" max="300" value="15" required>
        </div>
        <div class="form-group">
            <label>Poster Image URL (optional)</label>
            <input type="text" name="poster_url" placeholder="https://...">
        </div>
        <button class="btn" type="submit">သင်ခန်းစာ Upload</button>
        <p class="muted-text" style="margin-top:0.3rem;">အလိုအလျောက်: သင်တန်းအတွက် ပထမ Lesson ၂ ခုကို အခမဲ့မြင်ရသည့် is_free=1 သတ်မှတ်မည်။ ကျန် Lesson များကို ထိုးစေရန် စာရင်းသွင်းထားရမည်။</p>
    </form>
</section>

<section class="section">
    <div class="eyebrow">Mentor Library</div>
    <h2>စာအုပ် Upload (Cover + Language)</h2>
    <form method="post" action="actions/upload_book.php" enctype="multipart/form-data">
        <input type="hidden" name="csrf" value="<?= csrf_token(); ?>">
        <div class="form-group">
            <label>စာအုပ်ခေါင်းစဉ်</label>
            <input type="text" name="title" required>
        </div>
        <div class="form-group">
            <label>Programming Language (ဥပမာ - PHP, Python)</label>
            <input type="text" name="language" required>
        </div>
        <div class="form-group">
            <label>အကျဉ်းချုပ်</label>
            <textarea name="description" rows="3"></textarea>
        </div>
        <div class="form-group">
            <label>Book File (PDF/EPUB)</label>
            <input type="file" name="book_file" accept=".pdf,.epub,application/pdf,application/epub+zip" required>
        </div>
        <div class="form-group">
            <label>Cover (JPG/PNG/WebP)</label>
            <input type="file" name="cover" accept="image/*">
        </div>
        <button class="btn" type="submit">စာအုပ် Upload</button>
        <p class="muted-text" style="margin-top:0.3rem;">Mentor အဖြစ်တင်ထားသော စာအုပ်များကို User များက Language/Author အလိုက် ကြည့်ရှုဒေါင်းလုဒ်နိုင်ပါသည်။</p>
    </form>
</section>

<section class="section">
    <div class="eyebrow">ကျွန်ုပ်၏ သင်ခန်းစာများ</div>
    <h2>Course အလိုက် စုစည်းထားသော Lesson များ</h2>
    <div class="cards">
        <?php foreach ($instructorCourses as $course): ?>
            <article class="card reveal">
                <h3><?= h($course['title']); ?></h3>
                <p class="muted-text"><?= h($course['language']); ?> · <?= h($course['level']); ?></p>
                <?php foreach ($lessonsByCourse[$course['id']] ?? [] as $lesson): ?>
                    <div class="video-meta" style="margin:0.4rem 0;">
                        <span class="tag"><?= $lesson['is_free'] ? 'Free' : 'Premium'; ?></span>
                        <span><?= h($lesson['title']); ?> (<?= h($lesson['duration_minutes']); ?> မိနစ်)</span>
                    </div>
                <?php endforeach; ?>
                <?php if (empty($lessonsByCourse[$course['id']])): ?>
                    <p class="muted-text">Lesson မထည့်ရသေးပါ။</p>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
        <?php if (!$instructorCourses): ?>
            <p>သင်တန်းမရှိသေးပါ။ Admin မှ သင်တန်းသတ်မှတ်ပေးပါ။</p>
        <?php endif; ?>
    </div>
</section>

<section class="section">
    <div class="eyebrow">ကျွန်ုပ်၏ စာအုပ်များ</div>
    <h2>Mentor ရေးသား/ရွေးချယ်ထားသော စာအုပ်များ</h2>
    <div class="cards">
        <?php foreach ($instructorBooks as $book): ?>
            <article class="card book-card reveal">
                <div class="book-cover" style="<?= $book['cover_path'] ? 'background-image:url(' . h($book['cover_path']) . ');' : ''; ?>"></div>
                <div>
                    <span class="tag"><?= h($book['language']); ?></span>
                    <h3><?= h($book['title']); ?></h3>
                    <p class="muted-text"><?= h($book['file_size']); ?> MB</p>
                    <p><?= nl2br(h($book['description'])); ?></p>
                    <a class="chip-link" href="actions/download_book.php?id=<?= $book['id']; ?>&csrf=<?= csrf_token(); ?>">ဒေါင်းလုဒ်</a>
                </div>
            </article>
        <?php endforeach; ?>
        <?php if (!$instructorBooks): ?>
            <p>Mentor အဖြစ်တင်ထားသော စာအုပ်မရှိသေးပါ။</p>
        <?php endif; ?>
    </div>
</section>
<?php endif; ?>

<?php require __DIR__ . '/partials/footer.php'; ?>
