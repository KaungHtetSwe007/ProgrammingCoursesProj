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
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        $errors = [];
        if (!$displayName || !$primaryLanguage) {
            $errors[] = 'နာမည်နှင့် Programming Language သို့မဟုတ် အဓိကဘာသာ ပြည့်စုံရန်လိုအပ်သည်။';
        }

        // If no existing instructor account selected, create a fresh user with instructor role.
        if ($userId <= 0) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'တရားဝင် Instructor email ထည့်ပါ။';
            }
            if (strlen($password) < 6) {
                $errors[] = 'စကားဝှက်သည် အနည်းဆုံး စာလုံး ၆ လုံး လိုအပ်သည်။';
            }

            if (!$errors) {
                $userCheck = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
                $userCheck->execute([$email]);
                if ($userCheck->fetch()) {
                    $errors[] = 'ဤ Email ဖြင့်အကောင့် ရှိပြီးသား ဖြစ်နေသည်။';
                } else {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $insertUser = $pdo->prepare('INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)');
                    $insertUser->execute([$displayName, $email, $hash, 'instructor']);
                    $userId = (int) $pdo->lastInsertId();
                    log_activity($pdo, (int) $user['id'], 'Create Instructor User', $displayName, $user['role'] ?? null);
                }
            }
        }

        if (!$errors) {
            $stmt = $pdo->prepare('INSERT INTO instructors (display_name, primary_language, title, bio, user_id) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$displayName, $primaryLanguage, $title, $bio, $userId ?: null]);
            log_activity($pdo, (int) $user['id'], 'Add Instructor', $displayName, $user['role'] ?? null);
            set_flash('success', 'ဆရာအသစ် ထည့်သွင်းပြီးပါပြီ။');
        } else {
            set_flash('error', implode(' / ', $errors));
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

if (!function_exists('pcp_activity_type')) {
    function pcp_activity_type(string $action): string
    {
        $a = strtolower($action);
        if (str_contains($a, 'login')) return 'login';
        if (str_contains($a, 'download')) return 'download';
        if (str_contains($a, 'upload') || str_contains($a, 'avatar') || str_contains($a, 'photo')) return 'upload';
        if (str_contains($a, 'password') || str_contains($a, 'security')) return 'security';
        if (str_contains($a, 'profile') || str_contains($a, 'bio')) return 'profile';
        if (str_contains($a, 'comment')) return 'comment';
        return 'update';
    }
}

function pcp_activity_status(array $log): string
{
    $a = strtolower($log['action'] ?? '');
    if (str_contains($a, 'fail') || str_contains($a, 'reject')) {
        return 'danger';
    }
    if (str_contains($a, 'attempt')) {
        return 'warning';
    }
    return 'success';
}

function pcp_activity_icon(string $type): string
{
    $icons = [
        'login'    => '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M10 3h8a2 2 0 0 1 2 2v3h-2V5h-8v14h8v-3h2v3a2 2 0 0 1-2 2h-8a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Zm2.59 12.59L15.17 13H7v-2h8.17l-2.58-2.59L14 7l5 5l-5 5Z"/></svg>',
        'upload'   => '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="m11 16l-4-4l1.41-1.41L11 13.17V4h2v9.17l2.59-2.58L17 12Zm-6 2v-2h14v2Z"/></svg>',
        'download' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="m12 16l-5-5l1.41-1.41L11 12.17V4h2v8.17l2.59-2.58L17 11Zm-7 4v-2h14v2Z"/></svg>',
        'profile'  => '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12 12a5 5 0 1 0-5-5a5 5 0 0 0 5 5m0 2c-4 0-7 1.8-7 4v1h14v-1c0-2.2-3-4-7-4"/></svg>',
        'security' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12 2L5 5v6c0 5 3 9.77 7 11c4-1.23 7-6 7-11V5Zm0 2.18l5 2.22v5.4c0 3.87-2.24 7.83-5 9c-2.76-1.17-5-5.13-5-9V6.4Zm0 2.82A2.5 2.5 0 0 0 9.5 11a2.5 2.5 0 0 0 5 0A2.5 2.5 0 0 0 12 8.22M9 11a3 3 0 0 1 6 0c0 1.31-.84 2.42-2 2.83V17h-2v-3.17A3 3 0 0 1 9 11"/></svg>',
        'comment'  => '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M20 17.17V5H6v10h11.17L20 17.17M21 3a1 1 0 0 1 1 1v14l-4-4H5a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1Zm-4 9v2H8v-2Z"/></svg>',
        'update'   => '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M21 12a9 9 0 1 1-2.64-6.36L21 3v6h-6l2.22-2.22A6.99 6.99 0 1 0 19 12Zm-9 2v-4h2v4Zm0 2v-2h2v2Z"/></svg>',
    ];
    return $icons[$type] ?? $icons['update'];
}

function pcp_date_label(string $date): string
{
    $ts = strtotime($date);
    $today = strtotime('today');
    $yesterday = strtotime('yesterday');
    if ($ts >= $today) return 'Today';
    if ($ts >= $yesterday) return 'Yesterday';
    return date('M j, Y', $ts);
}

function pcp_date_time(string $date): string
{
    return date('M j, Y • H:i', strtotime($date));
}

$activityTransformed = [];
foreach ($activityLogs as $log) {
    $type = pcp_activity_type($log['action'] ?? '');
    $status = pcp_activity_status($log);
    $activityTransformed[] = [
        'id' => $log['id'] ?? null,
        'type' => $type,
        'title' => $log['action'] ?? 'Activity',
        'message' => $log['context'] ?? '',
        'created_at' => $log['created_at'] ?? '',
        'meta' => trim(($log['role'] ?? '') . ' · ' . ($log['name'] ?? '')),
        'status' => $status,
        'icon' => pcp_activity_icon($type),
    ];
}

$activityGrouped = [];
foreach ($activityTransformed as $item) {
    $label = pcp_date_label($item['created_at']);
    $activityGrouped[$label][] = $item;
}
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
                    <label>ဆရာအကောင့် (တပြိုင်တည်း ဖန်တီး/ရွေးချယ်)</label>
                    <select name="user_id">
                        <option value="0">အကောင့်အသစ် ဖန်တီးမည်</option>
                        <?php foreach ($instructorUsers as $insUser): ?>
                            <option value="<?= $insUser['id']; ?>"><?= h($insUser['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Instructor Email (အသစ်ဖန်တီးပါက)</label>
                    <input type="email" name="email" placeholder="instructor@email.com">
                </div>
                <div class="form-group">
                    <label>Instructor Password (အသစ်ဖန်တီးပါက)</label>
                    <input type="password" name="password" placeholder="အနည်းဆုံး ၆ လုံး">
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
    <style>
        .activity-shell {
            display: grid;
            gap: 1rem;
            font-family: "Noto Sans Myanmar", "Pyidaungsu", system-ui, -apple-system, "Segoe UI", sans-serif;
            line-height: 1.8;
        }
        .activity-header {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
        }
        .activity-header h2 { margin: 0.15rem 0 0; }
        .activity-filters {
            display: flex;
            flex-wrap: wrap;
            gap: 0.4rem;
            align-items: center;
        }
        .filter-pill {
            border: 1px solid color-mix(in srgb, var(--brand, #2563eb) 40%, var(--divider, #e5e7eb));
            border-radius: 999px;
            padding: 0.32rem 0.85rem;
            background: rgba(37,99,235,0.08);
            color: #0f172a;
            font-weight: 800;
            cursor: pointer;
            transition: all 0.16s ease;
        }
        .filter-pill.is-active {
            background: linear-gradient(120deg, color-mix(in srgb, var(--brand-strong, #2563eb) 80%, #3b82f6), color-mix(in srgb, var(--brand, #2563eb) 80%, #38bdf8));
            color: #fff;
            box-shadow: 0 10px 24px rgba(37,99,235,0.25);
            border-color: transparent;
        }
        .activity-search {
            display: inline-flex;
            align-items: center;
            border: 1px solid var(--divider);
            border-radius: 12px;
            padding: 0.35rem 0.75rem;
            background: rgba(255,255,255,0.92);
            gap: 0.35rem;
        }
        .activity-search input {
            border: none;
            outline: none;
            background: transparent;
            min-width: 180px;
            font-size: 0.95rem;
        }
        .activity-timeline {
            position: relative;
            display: grid;
            gap: 0.65rem;
        }
        .timeline-line {
            position: absolute;
            left: 20px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: rgba(37,99,235,0.2);
        }
        .activity-item {
            border: 1px solid var(--divider);
            border-radius: 18px;
            background: linear-gradient(145deg, color-mix(in srgb, var(--card, #fff) 85%, rgba(37,99,235,0.05)), var(--surface, rgba(255,255,255,0.95)));
            box-shadow: 0 16px 40px -28px var(--shadow, rgba(0,0,0,0.2));
            transition: transform 120ms ease, box-shadow 160ms ease, border-color 160ms ease;
            position: relative;
            padding: 0.4rem;
        }
        .activity-item:hover {
            transform: translateY(-2px);
            border-color: color-mix(in srgb, var(--brand, #2563eb) 50%, var(--divider));
            box-shadow: 0 18px 44px -26px var(--shadow, rgba(0,0,0,0.25));
        }
        .activity-card-body {
            display: flex;
            gap: 0.75rem;
            align-items: flex-start;
        }
        .timeline-dot {
            position: absolute;
            left: 12px;
            top: 18px;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            background: #fff;
            border: 4px solid rgba(37,99,235,0.3);
            box-shadow: 0 6px 14px rgba(0,0,0,0.08);
        }
        .icon-chip {
            width: 44px;
            height: 44px;
            border-radius: 14px;
            background: #e8edff;
            color: #2563eb;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 44px;
        }
        .icon-chip svg { width: 22px; height: 22px; }
        .badge-soft {
            display: inline-flex;
            align-items: center;
            padding: 0.2rem 0.65rem;
            border-radius: 999px;
            font-size: 0.78rem;
            font-weight: 800;
        }
        .badge-soft.success { background: rgba(34,197,94,0.16); color: #15803d; }
        .badge-soft.warning { background: rgba(250,204,21,0.18); color: #b45309; }
        .badge-soft.danger  { background: rgba(239,68,68,0.16); color: #b91c1c; }
        .badge-soft.neutral { background: rgba(107,114,128,0.14); color: #374151; }
        .meta-row {
            display: flex;
            flex-wrap: wrap;
            gap: 0.65rem;
            color: var(--text-muted);
            font-weight: 700;
            font-size: 0.92rem;
        }
        .meta-row span { display: inline-flex; align-items: center; gap: 0.35rem; }
        .date-sep {
            margin-left: 54px;
            font-size: 0.9rem;
            font-weight: 800;
            color: var(--text-muted);
        }
        .activity-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 0.9rem;
        }
        .activity-grid .activity-item { padding: 0; }
        .activity-grid .activity-card-body { padding: 1rem; }
        .empty-state {
            padding: 1.6rem;
            border: 1px dashed var(--divider);
            border-radius: 16px;
            text-align: center;
            background: rgba(255,255,255,0.9);
        }
        .load-more {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            border: 1px solid var(--brand);
            padding: 0.55rem 1.2rem;
            border-radius: 12px;
            background: transparent;
            color: var(--brand-strong, #2563eb);
            font-weight: 800;
            cursor: pointer;
        }
        @media (max-width: 640px) {
            .activity-header { align-items: flex-start; }
            .timeline-line { left: 18px; }
            .timeline-dot { left: 10px; }
            .activity-card-body { flex-direction: row; }
        }
    </style>

    <div class="activity-shell">
        <div class="activity-header">
            <div>
                <div class="eyebrow">ACTIVITY LOGS</div>
                <h2>Recent Actions</h2>
            </div>
            <div class="activity-filters">
                <?php
                $filterLabels = [
                    'all' => 'All',
                    'security' => 'Security',
                    'profile' => 'Profile',
                    'download' => 'Downloads',
                    'upload' => 'Uploads',
                ];
                ?>
                <?php foreach ($filterLabels as $key => $label): ?>
                    <button type="button" class="filter-pill<?= $key === 'all' ? ' is-active' : ''; ?>" data-filter="<?= h($key); ?>"><?= h($label); ?></button>
                <?php endforeach; ?>
                <label class="activity-search" aria-label="Search activity logs">
                    <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="m21 20.29l-3.37-3.37A8.26 8.26 0 0 0 19 11a8 8 0 1 0-8 8a8.26 8.26 0 0 0 5.92-1.37L20.29 21ZM5 11a6 6 0 1 1 6 6a6 6 0 0 1-6-6Z"/></svg>
                    <input type="search" id="activitySearch" placeholder="Search logs">
                </label>
            </div>
        </div>

        <div class="activity-timeline">
            <div class="timeline-line" aria-hidden="true"></div>
            <?php if (!$activityTransformed): ?>
                <div class="empty-state">
                    <strong>Log မရှိသေးပါ</strong>
                    <p class="muted-text">Actions ဖြစ်ပေါ်လာတာနဲ့ ပြသပေးပါမည်။</p>
                </div>
            <?php endif; ?>

            <?php foreach ($activityGrouped as $label => $items): ?>
                <div class="date-sep"><?= h($label); ?></div>
                <?php foreach ($items as $item): ?>
                    <?php
                    $badgeClass = match ($item['status']) {
                        'success' => 'success',
                        'warning' => 'warning',
                        'danger' => 'danger',
                        default => 'neutral',
                    };
                    ?>
                    <article class="activity-item" data-type="<?= h($item['type']); ?>" data-search="<?= h(strtolower($item['title'] . ' ' . $item['message'] . ' ' . $item['meta'])); ?>">
                        <div class="timeline-dot" aria-hidden="true"></div>
                        <div class="activity-card-body">
                            <div class="icon-chip" aria-hidden="true"><?= $item['icon']; ?></div>
                            <div class="flex-grow-1">
                                <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap">
                                    <div>
                                        <div class="fw-semibold"><?= h($item['title']); ?></div>
                                        <div class="muted-text"><?= h($item['message']); ?></div>
                                    </div>
                                    <span class="badge-soft <?= h($badgeClass); ?>"><?= h(ucfirst($item['status'])); ?></span>
                                </div>
                                <div class="meta-row mt-2">
                                    <span>
                                        <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12 20a8 8 0 1 1 8-8a8 8 0 0 1-8 8m0-18a10 10 0 1 0 10 10A10 10 0 0 0 12 2m.5 5h-1.5v6l5.25 3.15l.75-1.23l-4.5-2.67Z"/></svg>
                                        <?= h(pcp_date_time($item['created_at'])); ?>
                                    </span>
                                    <?php if (!empty($item['meta'])): ?>
                                        <span>
                                            <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12 3a9 9 0 1 0 9 9a9 9 0 0 0-9-9m0 2a7 7 0 0 1 6.93 6H17a5 5 0 1 0-5 5.93V19a7 7 0 0 1 0-14Z"/></svg>
                                            <?= h($item['meta']); ?>
                                        </span>
                                    <?php endif; ?>
                                    <span>
                                        <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="m20 11l-7-7v4h-3a8 8 0 0 0-8 8v4a4 4 0 0 0 4-4v-1a3 3 0 0 1 3-3h4v4Z"/></svg>
                                        <?= h($item['type']); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </div>

        <div class="activity-grid">
            <?php foreach ($activityTransformed as $item): ?>
                <?php
                $badgeClass = match ($item['status']) {
                    'success' => 'success',
                    'warning' => 'warning',
                    'danger' => 'danger',
                    default => 'neutral',
                };
                ?>
                <article class="activity-item" data-type="<?= h($item['type']); ?>" data-search="<?= h(strtolower($item['title'] . ' ' . $item['message'] . ' ' . $item['meta'])); ?>">
                    <div class="activity-card-body">
                        <div class="icon-chip" aria-hidden="true"><?= $item['icon']; ?></div>
                        <div class="flex-grow-1">
                            <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap">
                                <div>
                                    <div class="fw-semibold"><?= h($item['title']); ?></div>
                                    <div class="muted-text"><?= h($item['message']); ?></div>
                                </div>
                                <span class="badge-soft <?= h($badgeClass); ?>"><?= h(ucfirst($item['status'])); ?></span>
                            </div>
                            <div class="meta-row mt-2">
                                <span><?= h(pcp_date_time($item['created_at'])); ?></span>
                                <?php if (!empty($item['meta'])): ?>
                                    <span><?= h($item['meta']); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>

        <div class="text-center">
            <button class="load-more" type="button" id="activityLoadMore">
                Load more
            </button>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const filterButtons = Array.from(document.querySelectorAll('.filter-pill'));
            const searchInput = document.getElementById('activitySearch');
            const items = Array.from(document.querySelectorAll('.activity-item'));
            const loadMore = document.getElementById('activityLoadMore');
            let visible = Math.max(6, Math.ceil(items.length * 0.5));

            const applyFilters = () => {
                const activeFilter = (document.querySelector('.filter-pill.is-active') || {}).dataset?.filter || 'all';
                const query = (searchInput?.value || '').toLowerCase().trim();
                let shown = 0;

                items.forEach((el, idx) => {
                    const type = el.dataset.type || '';
                    const haystack = (el.dataset.search || '').toLowerCase();
                    const matchType = activeFilter === 'all' || type === activeFilter;
                    const matchSearch = !query || haystack.includes(query);
                    const withinLimit = idx < visible;
                    const shouldShow = matchType && matchSearch && withinLimit;
                    el.classList.toggle('is-hidden', !shouldShow);
                    if (shouldShow) shown++;
                });
                if (loadMore) {
                    loadMore.disabled = shown >= items.length;
                }
            };

            filterButtons.forEach(btn => {
                btn.addEventListener('click', () => {
                    filterButtons.forEach(b => b.classList.remove('is-active'));
                    btn.classList.add('is-active');
                    applyFilters();
                });
            });

            if (searchInput) {
                searchInput.addEventListener('input', applyFilters);
            }

            if (loadMore) {
                loadMore.addEventListener('click', () => {
                    visible += 4;
                    applyFilters();
                });
            }

            applyFilters();
        });
    </script>
</section>

<?php require __DIR__ . '/partials/footer.php'; ?>
