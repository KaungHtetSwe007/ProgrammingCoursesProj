<?php
require __DIR__ . '/includes/functions.php';
$user = require_login($pdo);

if (!is_admin($pdo)) {
    set_flash('error', 'Admin အတွက်သာ အသုံးပြုနိုင်ပါသည်။');
    redirect('index.php');
}

$pageTitle = 'အက်ဒ်မင်';
require __DIR__ . '/partials/header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf($_POST['csrf'] ?? '');
    $formType = $_POST['form_type'] ?? '';

    if ($formType === 'enrollment_decision') {
        $enrollId = (int)($_POST['enroll_id'] ?? 0);
        $status = $_POST['status'] ?? 'pending';
        $stmt = $pdo->prepare('UPDATE enrollments SET status = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?');
        $stmt->execute([$status, current_user($pdo)['id'], $enrollId]);
        log_activity($pdo, (int) $user['id'], 'Enrollment Decision', 'Enrollment ID: ' . $enrollId . ' -> ' . $status, $user['role'] ?? null);
        set_flash('success', 'ဦးစားပေးသင်တန်းတက်ရောက်မှုကို ပြင်ဆင်ပြီးပါပြီ။');
        redirect('admin.php');
    }

    if ($formType === 'instructor_income') {
        $instructorId = (int)($_POST['instructor_id'] ?? 0);
        $income = (int)($_POST['income'] ?? 0);
        $stmt = $pdo->prepare('UPDATE instructors SET annual_income = ? WHERE id = ?');
        $stmt->execute([$income, $instructorId]);
        log_activity($pdo, (int) $user['id'], 'Update Instructor Income', 'Instructor ID: ' . $instructorId, $user['role'] ?? null);
        set_flash('success', 'ဝင်ငွေသတ်မှတ်ချက်ကို ပြင်ဆင်ပြီးပါပြီ။');
        redirect('admin.php');
    }

    if ($formType === 'add_instructor') {
        $displayName = trim($_POST['display_name'] ?? '');
        $primaryLanguage = trim($_POST['primary_language'] ?? '');
        $title = trim($_POST['title'] ?? '');
        $bio = trim($_POST['bio'] ?? '');
        $userId = (int)($_POST['user_id'] ?? 0);

        if ($displayName && $primaryLanguage) {
            $stmt = $pdo->prepare('INSERT INTO instructors (display_name, primary_language, title, bio, user_id) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$displayName, $primaryLanguage, $title, $bio, $userId ?: null]);
            log_activity($pdo, (int) $user['id'], 'Add Instructor', $displayName, $user['role'] ?? null);
            set_flash('success', 'ဆရာအသစ် ထည့်သွင်းပြီးပါပြီ။');
        }
        redirect('admin.php');
    }
}

$pendingStmt = $pdo->query('
    SELECT e.*,
           u.name,
           u.email,
           u.avatar_path,
           c.title,
           ep.account_channel,
           ep.account_name,
           ep.account_number,
           ep.transaction_last6,
           ep.slip_path
    FROM enrollments e
    JOIN users u ON e.user_id = u.id
    JOIN courses c ON e.course_id = c.id
    LEFT JOIN enrollment_payments ep ON ep.enrollment_id = e.id
    WHERE e.status = "pending"
    ORDER BY e.created_at ASC
');
$pendingEnrollments = $pendingStmt->fetchAll();

$paymentSubmissions = [];
try {
    $paymentStmt = $pdo->query('
        SELECT ep.*, e.status AS enrollment_status, u.name, u.email, u.avatar_path, c.title
        FROM enrollment_payments ep
        JOIN enrollments e ON ep.enrollment_id = e.id
        JOIN users u ON e.user_id = u.id
        JOIN courses c ON e.course_id = c.id
        WHERE e.status = "pending"
        ORDER BY ep.created_at DESC
    ');
    $paymentSubmissions = $paymentStmt->fetchAll();
} catch (PDOException $e) {
    $paymentSubmissions = [];
}

$instructorsStmt = $pdo->query('SELECT * FROM instructors ORDER BY display_name');
$instructors = $instructorsStmt->fetchAll();

$usersStmt = $pdo->query('SELECT id, name FROM users WHERE role = "instructor" ORDER BY name');
$instructorUsers = $usersStmt->fetchAll();

$insightStmt = $pdo->query('
    SELECT u.name,
           c.title,
           SUM(lv.views) AS total_views
    FROM lesson_views lv
    JOIN users u   ON lv.user_id = u.id
    JOIN lessons l ON lv.lesson_id = l.id
    JOIN courses c ON l.course_id = c.id
    GROUP BY u.id, c.id
    ORDER BY total_views DESC
    LIMIT 5
');

$insights = $insightStmt->fetchAll();

$logsStmt = $pdo->query('
    SELECT al.*, u.name
    FROM activity_logs al
    LEFT JOIN users u ON al.user_id = u.id
    ORDER BY al.created_at DESC
    LIMIT 12
');
$activityLogs = $logsStmt->fetchAll();
?>

<section class="section">
    <div class="section-header">
        <div>
            <div class="eyebrow">ငွေပေးချေမှု</div>
            <h2>Pay Slip + Transaction နံပါတ်</h2>
            <p class="muted-text">ကျောင်းသားများ တင်သွင်းထားသော screenshot နှင့် နောက်ဆုံး၆လုံးကို Admin အဖြစ်အတည်ပြုရန် ကြည့်ပါ။</p>
        </div>
    </div>
    <div class="payment-admin-grid">
        <?php foreach ($paymentSubmissions as $pay): ?>
            <article class="card payment-admin-card">
                <div class="payment-admin-header">
                    <img src="<?= avatar_url($pay); ?>" alt="avatar">
                    <div>
                        <strong><?= h($pay['name']); ?></strong>
                        <p class="muted-text"><?= h($pay['email']); ?></p>
                        <p class="muted-text"><?= h($pay['title']); ?></p>
                        <span class="status-pill status-<?= h($pay['enrollment_status']); ?>"><?= h(enrollment_label($pay['enrollment_status'])); ?></span>
                    </div>
                </div>
                <p><strong><?= h(strtoupper($pay['account_channel'])); ?></strong> · <?= h($pay['account_name']); ?> · <?= h($pay['account_number']); ?></p>
                <p class="muted-text">နောက်ဆုံး Transaction ၆ လုံး - <?= h($pay['transaction_last6']); ?></p>
                <div class="slip-preview">
                    <img src="<?= h($pay['slip_path']); ?>" alt="Payment slip">
                </div>
                <a class="chip-link" href="<?= h($pay['slip_path']); ?>" target="_blank" rel="noreferrer">ဖိုင်ကြည့်မည်</a>
            </article>
        <?php endforeach; ?>
    </div>
    <?php if (!$paymentSubmissions): ?>
        <p>တင်ထားသော Pay Slip မရှိသေးပါ။</p>
    <?php endif; ?>
</section>

<section class="section">
    <h1>အက်ဒ်မင် ထိန်းချုပ်မှု</h1>
    <div class="two-column">
        <div class="box">
            <h3>Pending သင်တန်းတက်ရောက်မှု</h3>
            <?php foreach ($pendingEnrollments as $enroll): ?>
                <div class="card" style="margin-bottom:1rem;">
                    <div style="display:flex; gap:0.9rem; align-items:center; flex-wrap:wrap;">
                        <img src="<?= avatar_url($enroll); ?>" alt="avatar" style="width:52px;height:52px;border-radius:50%;object-fit:cover;border:1px solid var(--divider);">
                        <div>
                            <strong><?= h($enroll['name']); ?></strong>
                            <p class="muted-text"><?= h($enroll['email'] ?? ''); ?></p>
                            <p class="muted-text"><?= h($enroll['title']); ?></p>
                        </div>
                    </div>
                    <?php if (!empty($enroll['account_name'])): ?>
                        <div class="payment-summary" style="margin-top:0.6rem;">
                            <div>
                                <small class="muted-text">ရွေးချယ်ထားသော အကောင့်</small>
                                <p><strong><?= h($enroll['account_name']); ?></strong> · <?= h($enroll['account_number']); ?></p>
                                <p class="muted-text"><?= h(strtoupper($enroll['account_channel'])); ?> · နောက်ဆုံး၆လုံး <?= h($enroll['transaction_last6']); ?></p>
                            </div>
                            <?php if (!empty($enroll['slip_path'])): ?>
                                <div class="slip-preview">
                                    <img src="<?= h($enroll['slip_path']); ?>" alt="Payment slip">
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    <form method="post" style="margin-top:0.5rem; display:flex; gap:0.5rem; flex-wrap:wrap;">
                        <input type="hidden" name="csrf" value="<?= csrf_token(); ?>">
                        <input type="hidden" name="form_type" value="enrollment_decision">
                        <input type="hidden" name="enroll_id" value="<?= $enroll['id']; ?>">
                        <select name="status">
                            <option value="approved">အသိအမှတ်ပြု</option>
                            <option value="rejected">ပယ်ချ</option>
                        </select>
                        <button class="btn" type="submit">သတ်မှတ်မည်</button>
                    </form>
                </div>
            <?php endforeach; ?>
            <?php if (!$pendingEnrollments): ?>
                <p>Pending မရှိပါ။</p>
            <?php endif; ?>
        </div>

        <div class="box">
            <h3>ဆရာ ဝင်ငွေထိန်းချုပ်</h3>
            <?php foreach ($instructors as $instructor): ?>
                <form method="post" style="margin-bottom:0.8rem;">
                    <strong><?= h($instructor['display_name']); ?></strong>
                    <div style="display:flex; gap:0.5rem; align-items:center; flex-wrap:wrap;">
                        <input type="hidden" name="csrf" value="<?= csrf_token(); ?>">
                        <input type="hidden" name="form_type" value="instructor_income">
                        <input type="hidden" name="instructor_id" value="<?= $instructor['id']; ?>">
                        <input type="number" name="income" min="0" value="<?= h($instructor['annual_income']); ?>">
                        <button class="btn-ghost" type="submit">ပြင်ဆင်မည်</button>
                    </div>
                </form>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="section">
    <div class="two-column">
        <div class="box">
            <h3>ဆရာအသစ် ထည့်သွင်းရန်</h3>
            <form method="post">
                <input type="hidden" name="csrf" value="<?= csrf_token(); ?>">
                <input type="hidden" name="form_type" value="add_instructor">
                <div class="form-group">
                    <label>ပြသမည့်နာမည်</label>
                    <input type="text" name="display_name" required>
                </div>
                <div class="form-group">
                    <label>အဓိက Programming Language</label>
                    <input type="text" name="primary_language" required>
                </div>
                <div class="form-group">
                    <label>Title</label>
                    <input type="text" name="title">
                </div>
                <div class="form-group">
                    <label>Bio</label>
                    <textarea name="bio" rows="3"></textarea>
                </div>
                <div class="form-group">
                    <label>ဆရာအကောင့် (ရွေးချယ်နိုင်)</label>
                    <select name="user_id">
                        <option value="0">-- သီးခြား ပြသမည် --</option>
                        <?php foreach ($instructorUsers as $insUser): ?>
                            <option value="<?= $insUser['id']; ?>"><?= h($insUser['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button class="btn" type="submit">ထည့်သွင်းမည်</button>
            </form>
        </div>
        <div class="box">
            <h3>Learning Insights</h3>
            <table>
                <thead>
                <tr>
                    <th>အသုံးပြုသူ</th>
                    <th>သင်တန်း</th>
                    <th>ကြည့်ချိန်</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($insights as $insight): ?>
                    <tr>
                        <td><?= h($insight['name']); ?></td>
                        <td><?= h($insight['title']); ?></td>
                        <td><?= h($insight['total_views']); ?> ကြိမ်</td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$insights): ?>
                    <tr><td colspan="3">ဒေတာမရှိသေးပါ။</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<section class="section">
    <div class="eyebrow">Recent Logs</div>
    <h2>အသုံးပြုသူ Activity</h2>
    <div class="cards">
        <?php foreach ($activityLogs as $log): ?>
            <article class="card reveal">
                <h3><?= h($log['action']); ?></h3>
                <p class="muted-text"><?= h($log['context'] ?? ''); ?></p>
                <p class="muted-text">User: <?= h($log['name'] ?? 'System'); ?> · <?= h($log['role'] ?? ''); ?></p>
                <small class="muted-text"><?= h($log['created_at']); ?></small>
            </article>
        <?php endforeach; ?>
        <?php if (!$activityLogs): ?>
            <p>Log မရှိသေးပါ။</p>
        <?php endif; ?>
    </div>
</section>

<?php require __DIR__ . '/partials/footer.php'; ?>
