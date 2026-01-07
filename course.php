<?php
require __DIR__ . '/includes/functions.php';

$courseId = (int)($_GET['id'] ?? 0);
$courseStmt = $pdo->prepare('
    SELECT c.*, i.display_name, i.title AS instructor_title, i.bio, i.photo_url, u.avatar_path
    FROM courses c
    LEFT JOIN instructors i ON c.instructor_id = i.id
    LEFT JOIN users u ON u.id = i.user_id
    WHERE c.id = ?
');
$courseStmt->execute([$courseId]);
$course = $courseStmt->fetch();

if (!$course) {
    set_flash('error', 'မတွေ့ရှိသော သင်တန်း ID ဖြစ်ပါသည်။');
    redirect('courses.php');
}

$lessonStmt = $pdo->prepare('SELECT * FROM lessons WHERE course_id = ? ORDER BY position');
$lessonStmt->execute([$courseId]);
$lessons = $lessonStmt->fetchAll();

$currentUser = current_user($pdo);
$courseRating = [
    'avg' => null,
    'count' => 0,
    'user' => null,
];

try {
    $ratingStmt = $pdo->prepare('SELECT ROUND(AVG(rating), 1) AS avg_rating, COUNT(*) AS rating_count FROM course_ratings WHERE course_id = ?');
    $ratingStmt->execute([$courseId]);
    if ($row = $ratingStmt->fetch()) {
        $courseRating['avg'] = $row['avg_rating'] !== null ? (float) $row['avg_rating'] : null;
        $courseRating['count'] = (int) $row['rating_count'];
    }
} catch (PDOException $e) {
    $courseRating['avg'] = null;
}

if ($currentUser) {
    try {
        $userRateStmt = $pdo->prepare('SELECT rating FROM course_ratings WHERE course_id = ? AND user_id = ? LIMIT 1');
        $userRateStmt->execute([$courseId, $currentUser['id']]);
        $userRatingRow = $userRateStmt->fetch();
        if ($userRatingRow) {
            $courseRating['user'] = (int) $userRatingRow['rating'];
        }
    } catch (PDOException $e) {
        $courseRating['user'] = null;
    }
}

$enrollment = null;
$payment = null;
$paymentAccounts = payment_accounts();

if ($currentUser) {
    $enrollStmt = $pdo->prepare('SELECT * FROM enrollments WHERE course_id = ? AND user_id = ? LIMIT 1');
    $enrollStmt->execute([$courseId, $currentUser['id']]);
    $enrollment = $enrollStmt->fetch();

    if ($enrollment) {
        try {
            $payStmt = $pdo->prepare('SELECT * FROM enrollment_payments WHERE enrollment_id = ? LIMIT 1');
            $payStmt->execute([$enrollment['id']]);
            $payment = $payStmt->fetch() ?: null;
        } catch (PDOException $e) {
            $payment = null;
        }
    }
}

$hasFullAccess = ensure_course_access($pdo, $courseId);
$pageTitle = $course['title'];
require __DIR__ . '/partials/header.php';
?>

<section class="section">
    <div class="two-column">
        <div class="box reveal course-hero">
            <div class="course-hero-top">
                <span class="tag"><?= h($course['language']); ?></span>
                <h1><?= h($course['title']); ?></h1>
                <p class="muted-text"><?= nl2br(h($course['description'])); ?></p>
            </div>

            <div class="course-meta-grid">
                <span class="meta-pop-lite">
                    <span class="icon-chip" aria-hidden="true"><svg viewBox="0 0 24 24"><path fill="currentColor" d="m12 2l-2.5 7.5l-7.5.5l6 4.5l-2.2 7.5L12 17l5.2 5l-2.2-7.5l6-4.5l-7.5-.5Z"/></svg></span>
                    <strong>အဆင့်</strong> <?= h(ucfirst($course['level'])); ?>
                </span>
                <span class="meta-pop-lite">
                    <span class="icon-chip" aria-hidden="true"><svg viewBox="0 0 24 24"><path fill="currentColor" d="M12 3a9 9 0 1 0 9 9a9 9 0 0 0-9-9Zm0 3v6l4 2"/></svg></span>
                    <strong>ကြာချိန်</strong> <?= h($course['duration_weeks']); ?> ပတ်
                </span>
                <span class="meta-pop-lite">
                    <span class="icon-chip" aria-hidden="true"><svg viewBox="0 0 24 24"><path fill="currentColor" d="M5 5h14v4H5zm0 6h14v2H5zm0 4h14v2H5z"/></svg></span>
                    <strong>စျေးနှုန်း</strong> <?= format_currency((int) $course['price']); ?>
                </span>
                <span class="meta-pop-lite">
                    <span class="icon-chip" aria-hidden="true"><svg viewBox="0 0 24 24"><path fill="currentColor" d="M12 12a5 5 0 1 0-5-5a5 5 0 0 0 5 5m0 2c-4 0-7 1.8-7 4v1h14v-1c0-2.2-3-4-7-4"/></svg></span>
                    <strong>ဆရာ</strong> <?= h($course['display_name'] ?: 'မသတ်မှတ်ရ'); ?>
                </span>
            </div>

            <div class="rating-card">
                <div class="rating-row">
                    <?= render_stars($courseRating['avg']); ?>
                    <strong class="rating-number"><?= $courseRating['avg'] ? number_format($courseRating['avg'], 1) . ' / 5' : 'N/A'; ?></strong>
                    <small class="muted-text"><?= $courseRating['count'] ? $courseRating['count'] . ' ratings' : 'Rating မရှိသေးပါ။'; ?></small>
                </div>
                <?php if ($currentUser): ?>
                    <form class="rating-form" method="post" action="actions/rate_course.php">
                        <input type="hidden" name="csrf" value="<?= csrf_token(); ?>">
                        <input type="hidden" name="course_id" value="<?= $course['id']; ?>">
                        <div class="star-inputs" aria-label="သင်တန်းအဆင့်ပေးရန်">
                            <?php for ($i = 5; $i >= 1; $i--): ?>
                                <input type="radio" name="rating" value="<?= $i; ?>" id="course-rate-<?= $course['id']; ?>-<?= $i; ?>" <?= $courseRating['user'] === $i ? 'checked' : ''; ?>>
                                <label for="course-rate-<?= $course['id']; ?>-<?= $i; ?>">★</label>
                            <?php endfor; ?>
                        </div>
                        <div class="rating-actions">
                            <span class="muted-text"><?= $courseRating['user'] ? 'သင့် Rating - ' . $courseRating['user'] . '/5' : 'သင့်အဆင့်သတ်မှတ်ချက်ပေးပါ'; ?></span>
                            <button class="btn-ghost btn-small" type="submit">Rating သိမ်းဆည်း</button>
                        </div>
                    </form>
                <?php else: ?>
                    <p class="muted-text">အကောင့်ဝင်ပြီးသင်တန်းကို အဆင့်သတ်မှတ်ပေးနိုင်ပါသည်။</p>
                <?php endif; ?>
            </div>

                <?php if ($currentUser): ?>
                    <?php if ($enrollment && $enrollment['status'] === 'approved'): ?>
                        <p>သင်တန်းအခြေအနေ -
                            <span class="status-pill status-<?= h($enrollment['status']); ?>">
                                <?= h(enrollment_label($enrollment['status'])); ?>
                            </span>
                        </p>
                        <?php if ($payment): ?>
                            <div class="payment-summary">
                                <div>
                                    <small class="muted-text">ငွေပေးချေမှု</small>
                                    <p><strong><?= h($payment['account_name']); ?></strong> · <?= h($payment['account_number']); ?></p>
                                    <p class="muted-text">နောက်ဆုံး၆လုံး - <?= h($payment['transaction_last6']); ?></p>
                                </div>
                                <div class="slip-preview">
                                    <img src="<?= h($payment['slip_path']); ?>" alt="Payment slip">
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php elseif (is_student($pdo)): ?>
                        <?php if ($enrollment): ?>
                            <p>သင်တန်းအခြေအနေ -
                                <span class="status-pill status-<?= h($enrollment['status']); ?>">
                                    <?= h(enrollment_label($enrollment['status'])); ?>
                                </span>
                                <small class="muted-text"> (ပေးချေမှု ပြောင်းလဲလိုပါက ချက်ချင်း ဖြည့်ပါ)</small>
                            </p>
                        <?php endif; ?>
                        <div class="payment-panel">
                            <div>
                                <div class="eyebrow">ငွေပေးချေရာ</div>
                                <h3>မုဒ်ရွေး၍ Pay Slip တင်ပါ</h3>
                                <p class="muted-text">Enter top up amount နေရာတွင် Transaction နံပါတ် နောက်ဆုံး၆လုံးသာ ရိုက်ထည့်ပါ။</p>
                                <form method="post" action="actions/submit_payment.php" enctype="multipart/form-data" class="payment-form">
                                    <input type="hidden" name="csrf" value="<?= csrf_token(); ?>">
                                    <input type="hidden" name="course_id" value="<?= $course['id']; ?>">
                                    <?php $defaultAccountKey = array_key_first($paymentAccounts); ?>
                                    <label class="form-group">
                                        <span>Enter your top-up amount (နောက်ဆုံး ၆ လုံး)</span>
                                        <input type="text" name="transaction_last6" pattern="\d{6}" inputmode="numeric" maxlength="6" placeholder="ဥပမာ - 123456" required>
                                    </label>
                                    <label class="form-group">
                                        <span>Upload your transfer receipt</span>
                                        <input type="file" name="slip" accept="image/*" required>
                                    </label>
                                    <div class="payment-grid">
                                        <?php foreach ($paymentAccounts as $key => $account): ?>
                                            <label class="payment-card">
                                                <input type="radio" name="account_key" value="<?= h($key); ?>" <?= $key === $defaultAccountKey ? 'checked' : ''; ?>>
                                                <span class="channel"><?= h(strtoupper($account['label'])); ?></span>
                                                <strong><?= h($account['name']); ?></strong>
                                                <span class="muted-text"><?= h($account['number']); ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                    <button class="btn" type="submit">ပေးချေပြီး အတည်ပြုစောင့်မည်</button>
                                </form>
                            </div>
                        </div>
                    <?php else: ?>
                        <p class="muted-text">သင်တန်းစာရင်းသွင်းခြင်းသည် ကျောင်းသားများအတွက်သာ ဖြစ်ပါသည်။</p>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="course-cta">
                        <div class="course-cta-text">
                            <p>လောလောဆယ် ဗီဒီယို ၂ ခုသာ ကြည့်ရှုနိုင်ပါသေးသည်။</p>
                            <p class="muted-text">စာရင်းသွင်းပြီးမှ အပြည့်အဝ အသုံးပြုနိုင်ပါသည်။</p>
                        </div>
                        <div class="course-cta-actions">
                            <a class="btn btn-cta" href="register.php">စာရင်းသွင်းပါ</a>
                            <a class="btn-ghost btn-cta" href="login.php">လော့ဂ်အင်</a>
                        </div>
                    </div>
                <?php endif; ?>
        </div>

        <div class="box reveal instructor-profile">
            <?php
            $instructorPhoto = $course['avatar_path'] ?: ($course['photo_url'] ?? '');
            $instructorName = $course['display_name'] ?? 'မသတ်မှတ်ရ';
            $instructorTitle = $course['instructor_title'] ?? 'Lead Instructor';
            ?>
            <div class="instructor-head">
                <div class="avatar-shell instructor-avatar">
                    <?php if (!empty($instructorPhoto)): ?>
                        <img class="avatar-img" src="<?= h($instructorPhoto); ?>" alt="<?= h($instructorName); ?>">
                    <?php else: ?>
                        <div class="avatar-fallback"><?= h(strtoupper(mb_substr($instructorName, 0, 1))); ?></div>
                    <?php endif; ?>
                </div>
                <div>
                    <div class="eyebrow">သင်တန်းဆရာ</div>
                    <h3 class="instructor-name"><?= h($instructorName); ?></h3>
                    <p class="muted-text"><?= h($instructorTitle); ?></p>
                    <div class="instructor-meta-row">
                        <span class="meta-pop-lite">
                            <span class="icon-chip" aria-hidden="true"><svg viewBox="0 0 24 24"><path fill="currentColor" d="M12 12a5 5 0 1 0-5-5a5 5 0 0 0 5 5m0 2c-4 0-7 1.8-7 4v1h14v-1c0-2.2-3-4-7-4"/></svg></span>
                            <strong>အဆင့်</strong> <?= h(ucfirst($course['level'])); ?>
                        </span>
                        <span class="meta-pop-lite">
                            <span class="icon-chip" aria-hidden="true"><svg viewBox="0 0 24 24"><path fill="currentColor" d="M12 3a9 9 0 1 0 9 9a9 9 0 0 0-9-9Zm0 3v6l4 2"/></svg></span>
                            <strong>ကြာချိန်</strong> <?= h($course['duration_weeks']); ?> ပတ်
                        </span>
                    </div>
                </div>
            </div>
            <p class="muted-text"><?= nl2br(h($course['bio'] ?? 'အချက်အလက်များကို အက်ဒ်မင်မှ ထည့်ပါ။')); ?></p>
            <?php if ($currentUser && $course['instructor_id']): ?>
                <form class="instructor-actions" method="post" action="actions/like_instructor.php">
                    <input type="hidden" name="csrf" value="<?= csrf_token(); ?>">
                    <input type="hidden" name="instructor_id" value="<?= $course['instructor_id']; ?>">
                    <button class="btn-ghost" type="submit">ဆရာကို Like ပေးမည်</button>
                    <a class="chip-link" href="instructors.php">Profile ကြည့်ရန်</a>
                </form>
            <?php else: ?>
                <p>ဆရာ၏ ပရိုဖိုင်ကို <a href="instructors.php">ဒီနေရာ</a>တွင်ကြည့်ပါ။</p>
            <?php endif; ?>
        </div>
    </div>
</section>

<section class="section">
    <div class="eyebrow">ဗီဒီယိုသင်ခန်းစာများ</div>
    <!-- <h2>ဂရပ်ဖစ် Poster + ကြာချိန်ပြ</h2> -->
    <div class="cards">
        <?php foreach ($lessons as $lesson): ?>
            <?php
            $isFree = (bool)$lesson['is_free'];
            $canView = $isFree || $hasFullAccess;
            ?>
            <article class="card video-card reveal">
                <div class="video-meta">
                    <span class="tag"><?= h($isFree ? 'Free' : 'Premium'); ?></span>
                    <span><?= h($lesson['duration_minutes']); ?> မိနစ်</span>
                </div>
                <h3><?= h($lesson['title']); ?></h3>
                <p><?= h(excerpt($lesson['summary'] ?? '', 140)); ?></p>
                <p class="muted-text"><?= $isFree ? 'အခမဲ့ကြည့်ရှုနိုင်' : 'စာရင်းသွင်းသူများသာ ကြည့်ရှုနိုင်'; ?></p>
                <?php if ($canView): ?>
                    <a class="btn" href="watch_lesson.php?id=<?= $lesson['id']; ?>">ကြည့်ရှုရန်</a>
                    <?php if ($hasFullAccess): ?>
                        <a class="btn-ghost" href="actions/download_lesson.php?id=<?= $lesson['id']; ?>&csrf=<?= csrf_token(); ?>">ဒေါင်းလုဒ်</a>
                    <?php endif; ?>
                <?php else: ?>
                    <small>စာရင်းသွင်းပြီးမှ အပြည့်အဝ အသုံးပြုနိုင်ပါသည်။</small>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
        <?php if (!$lessons): ?>
            <p>သင်ခန်းစာများ မထည့်ရသေးပါ။ database/schema.sql ထည့်ပါ။</p>
        <?php endif; ?>
    </div>
</section>

<?php require __DIR__ . '/partials/footer.php'; ?>
