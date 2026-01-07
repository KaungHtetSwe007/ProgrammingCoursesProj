<?php
require __DIR__ . '/includes/functions.php';
$user = require_login($pdo);
$isAdmin = is_admin($pdo);
$isInstructor = is_instructor($pdo);
$pageTitle = 'ကျွန်ုပ်၏ဘုတ်';
$extraHead = <<<HTML
<link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Myanmar:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
HTML;
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

$showActivityLogs = $isAdmin || $isInstructor;
$activityLogs = $showActivityLogs ? recent_logs($pdo, $user['id']) : [];

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

if (!function_exists('pcp_activity_status')) {
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
}

if (!function_exists('pcp_activity_icon')) {
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
}

if (!function_exists('pcp_date_label')) {
    function pcp_date_label(string $date): string
    {
        $ts = strtotime($date);
        $today = strtotime('today');
        $yesterday = strtotime('yesterday');
        if ($ts >= $today) return 'Today';
        if ($ts >= $yesterday) return 'Yesterday';
        return date('M j, Y', $ts);
    }
}

if (!function_exists('pcp_date_time')) {
    function pcp_date_time(string $date): string
    {
        return date('M j, Y • H:i', strtotime($date));
    }
}

if (!function_exists('render_activity_feed')) {
    function render_activity_feed(array $grouped, array $all, string $heading = 'Activity Logs', string $title = 'Recent Actions', string $emptyText = 'Activity log မရှိသေးပါ။')
    {
        static $injected = false;
        ?>
        <?php if (!$injected): ?>
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
        <?php $injected = true; endif; ?>

        <section class="section">
            <div class="activity-shell">
                <div class="activity-header">
                    <div>
                        <div class="eyebrow"><?= h($heading); ?></div>
                        <h2><?= h($title); ?></h2>
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
                    <?php if (!$all): ?>
                        <div class="empty-state">
                            <strong><?= h($emptyText); ?></strong>
                            <p class="muted-text">Actions ဖြစ်ပေါ်လာတာနဲ့ ပြသပေးပါမည်။</p>
                        </div>
                    <?php endif; ?>

                    <?php foreach ($grouped as $label => $items): ?>
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
                    <?php foreach ($all as $item): ?>
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
        </section>
        <?php
    }
}

$activityTransformed = [];
$activityGrouped = [];
if ($showActivityLogs) {
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

    foreach ($activityTransformed as $item) {
        $label = pcp_date_label($item['created_at']);
        $activityGrouped[$label][] = $item;
    }
}

$adminCounts = [];
$topMentors = [];
if ($isAdmin) {
    $countStmt = $pdo->query('
        SELECT
            (SELECT COUNT(*) FROM users WHERE role = "student") AS students,
            (SELECT COUNT(*) FROM users WHERE role = "instructor") AS instructors,
            (SELECT COUNT(*) FROM enrollments WHERE status = "pending") AS pending_enrollments,
            (SELECT COUNT(*) FROM courses) AS courses
    ');
    $adminCounts = $countStmt->fetch() ?: [];

    $incomeStmt = $pdo->query('SELECT display_name, annual_income, photo_url FROM instructors ORDER BY annual_income DESC LIMIT 5');
    $topRows = $incomeStmt->fetchAll() ?: [];
    foreach ($topRows as $row) {
        $topMentors[] = [
            'name' => $row['display_name'],
            'income' => (int) $row['annual_income'],
            'currency' => 'ကျပ်',
            'avatar' => !empty($row['photo_url']) ? $row['photo_url'] : null,
            'delta' => null,
        ];
    }
}
$incomeTotal = 0;
$highestIncome = 0;
$topMentor = null;
foreach ($topMentors as $mentor) {
    $incomeTotal += $mentor['income'];
    if ($mentor['income'] > $highestIncome) {
        $highestIncome = $mentor['income'];
        $topMentor = $mentor;
    }
}
$mentorCount = count($topMentors);
$incomeAverage = $mentorCount ? (int) round($incomeTotal / $mentorCount) : 0;
$topMentorsJson = json_encode(
    $topMentors,
    JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
);
if ($topMentorsJson === false) {
    $topMentorsJson = '[]';
}
?>

<section class="section">
    <div class="two-column">
        <div class="box reveal">
            <h2>မင်္ဂလာပါ <?= h($user['name']); ?> ✨</h2>
            <?php if ($isAdmin): ?>
                <p>Admin အနေဖြင့် Mentor/Student စီမံခန့်ခွဲခြင်း၊ Pending Enrollments ထိန်းခြင်းနှင့် ဝင်ငွေ Graph၊ Logs များကို ဒီနေရာမှ တစ်ချက်တည်း ကြည့်ရှုနိုင်ပါသည်။</p>
            <?php elseif ($isInstructor): ?>
                <p>Mentor Studio မှ သင်တန်းအလိုက် ဗီဒီယိုသင်ခန်းစာများကို Upload/ထိန်းချုပ်နိုင်ပါသည်။ ပထမ ၂ ခုသာ အခမဲ့၊ ကျန် Lesson များကို Enrolled သင်တန်းဝင်များသာ ကြည့်နိုင်စေပါသည်။</p>
            <?php else: ?>
                <p>သင်တန်းများ၊ Favourite စာအုပ်များနှင့် Chat Community ကို ဒီနေရာထဲကနေ တစ်ချက်တည်းထိန်းချုပ်ပါ။ Theme toggle နှင့် Profile Upload အား အသုံးပြုနိုင်ပါပြီ။</p>
            <?php endif; ?>
            <div style="display:flex; gap:0.8rem; flex-wrap:wrap; align-items:center;">
                <img src="<?= avatar_url($user); ?>" alt="avatar" style="width:70px;height:70px;border-radius:50%;object-fit:cover;">
                <?php if ($isAdmin): ?>
                    <a class="btn" href="admin.php">Admin Panel</a>
                    <a class="btn-ghost" href="instructors.php">Mentor စာရင်း</a>
                    <a class="btn-ghost" href="courses.php">သင်တန်း စီမံ</a>
                <?php elseif ($isInstructor): ?>
                    <a class="btn-ghost" href="instructors.php">ပရိုဖိုင်ကြည့်</a>
                    <a class="btn-ghost" href="courses.php">သင်တန်းများ</a>
                <?php endif; ?>
                <form class="avatar-upload" method="post" action="actions/upload_avatar.php" enctype="multipart/form-data">
                    <input type="hidden" name="csrf" value="<?= csrf_token(); ?>">
                    <label class="file-chip">
                        <input type="file" name="avatar" accept="image/*" required>
                        <span class="file-icon">⬆</span>
                        <span>Choose photo</span>
                    </label>
                    <button class="btn btn-small" type="submit">Upload</button>
                </form>
            </div>
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
            <?php
            $coverPath = !empty($book['cover_path']) ? str_replace('\\', '/', $book['cover_path']) : '';
            ?>
            <article class="card book-card reveal">
                <div class="book-cover">
                    <?php if ($coverPath): ?>
                        <img src="<?= h($coverPath); ?>" alt="<?= h($book['title']); ?> cover">
                    <?php endif; ?>
                </div>
                <div>
                    <span class="tag"><?= h($book['language']); ?></span>
                    <h3><?= h($book['title']); ?></h3>
                    <div>
                        <a class="btn" href="actions/download_book.php?id=<?= $book['id']; ?>&csrf=<?= csrf_token(); ?>">ဒေါင်းလုဒ်</a>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
        <?php if (!$favoriteBooks): ?>
            <p>Favourite မရှိသေးပါ။ စာအုပ်စာရင်းသို့ သွားပြီး သတ်မှတ်ပါ။</p>
        <?php endif; ?>
    </div>
</section>

<?php if ($showActivityLogs): ?>
    <?php
    render_activity_feed(
        $activityGrouped,
        $activityTransformed,
        "Activity Logs",
        "????????? Recent Actions",
        "Activity log ?????????? ???????????????? ??????????????????????????? ??????????"
    );
    ?>
<?php endif; ?>
<?php else: ?>
<style>
    .income-card {
        --income-primary: #3b5bdb;
        --income-accent: #60a5fa;
        --income-ink: #0f172a;
        --income-muted: #64748b;
        --income-border: rgba(15, 23, 42, 0.08);
        position: relative;
        background: #fff;
        border-radius: 20px;
        border: 1px solid var(--income-border);
        box-shadow: 0 24px 60px rgba(15, 23, 42, 0.08);
        overflow: hidden;
        font-family: "Noto Sans Myanmar", "Pyidaungsu", system-ui, "Segoe UI", Arial, sans-serif;
        font-size: 15px;
        line-height: 1.8;
        color: var(--income-ink);
    }
    .income-card > * {
        position: relative;
        z-index: 1;
    }
    .income-card::before {
        content: "";
        position: absolute;
        inset: 0;
        background:
            radial-gradient(circle at 0% 0%, rgba(59, 91, 219, 0.18), transparent 55%),
            radial-gradient(circle at 20% 90%, rgba(96, 165, 250, 0.12), transparent 60%);
        pointer-events: none;
    }
    .income-header {
        display: flex;
        flex-wrap: wrap;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1rem;
        margin-bottom: 1.5rem;
    }
    .income-label {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        font-size: 0.75rem;
        font-weight: 700;
        color: var(--income-muted);
        text-transform: uppercase;
    }
    .income-title {
        margin: 0.3rem 0 0.4rem;
        font-size: 1.55rem;
        font-weight: 700;
    }
    .income-subtitle {
        margin: 0;
        color: var(--income-muted);
        max-width: 520px;
    }
    .income-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 0.6rem;
        align-items: center;
    }
    .income-filter {
        min-width: 170px;
        border-radius: 12px;
        border-color: rgba(148, 163, 184, 0.4);
        box-shadow: none;
        font-size: 0.85rem;
    }
    .income-export {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        border-radius: 12px;
        border: 1px solid rgba(59, 91, 219, 0.4);
        color: var(--income-primary);
        background: #fff;
        padding: 0.45rem 0.85rem;
        font-weight: 600;
        font-size: 0.85rem;
        box-shadow: none;
    }
    .income-export:hover {
        background: rgba(59, 91, 219, 0.08);
        color: var(--income-primary);
    }
    .income-export svg {
        width: 16px;
        height: 16px;
    }
    .income-chart-card {
        background: rgba(59, 91, 219, 0.05);
        border: 1px solid rgba(148, 163, 184, 0.25);
        border-radius: 18px;
        padding: 1.25rem;
        height: 100%;
    }
    .income-chart-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
    }
    .income-chart-title {
        font-weight: 700;
        font-size: 1rem;
    }
    .income-chart-sub {
        color: var(--income-muted);
        font-size: 0.88rem;
    }
    .income-chart-tag {
        background: rgba(59, 91, 219, 0.12);
        color: var(--income-primary);
        padding: 0.2rem 0.65rem;
        border-radius: 999px;
        font-weight: 600;
        font-size: 0.75rem;
    }
    .income-chart-canvas {
        position: relative;
        height: 340px;
        margin-top: 1rem;
    }
    .income-empty {
        height: 340px;
        display: grid;
        place-items: center;
        text-align: center;
        gap: 0.75rem;
        color: var(--income-muted);
        background: rgba(255, 255, 255, 0.6);
        border-radius: 14px;
        border: 1px dashed rgba(148, 163, 184, 0.4);
        padding: 1.25rem;
    }
    .income-empty-icon {
        width: 56px;
        height: 56px;
        border-radius: 50%;
        background: rgba(59, 91, 219, 0.12);
        color: var(--income-primary);
        display: grid;
        place-items: center;
    }
    .income-empty-icon svg {
        width: 28px;
        height: 28px;
    }
    .income-table {
        margin-top: 1rem;
        background: #fff;
        border-radius: 16px;
        border: 1px solid rgba(148, 163, 184, 0.25);
        padding: 0.75rem 0.75rem;
    }
    .income-table .table {
        color: var(--income-ink);
    }
    .income-table thead th {
        font-size: 0.78rem;
        text-transform: uppercase;
        color: var(--income-muted);
        border-bottom: 1px solid rgba(148, 163, 184, 0.3);
    }
    .income-table tbody tr + tr {
        border-top: 1px solid rgba(148, 163, 184, 0.15);
    }
    .income-mentor {
        display: flex;
        align-items: center;
        gap: 0.6rem;
    }
    .income-mentor img,
    .income-avatar-fallback {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        object-fit: cover;
    }
    .income-avatar-fallback {
        background: rgba(59, 91, 219, 0.12);
        color: var(--income-primary);
        display: grid;
        place-items: center;
        font-weight: 700;
    }
    .income-stats {
        background: #f8fafc;
        border: 1px solid rgba(148, 163, 184, 0.25);
        border-radius: 18px;
        padding: 1.5rem;
        height: 100%;
        display: flex;
        flex-direction: column;
        gap: 1.25rem;
    }
    .income-stat {
        padding-bottom: 1rem;
        border-bottom: 1px solid rgba(148, 163, 184, 0.25);
    }
    .income-stat:last-child {
        border-bottom: none;
        padding-bottom: 0;
    }
    .stat-label {
        color: var(--income-muted);
        font-size: 0.85rem;
    }
    .stat-value {
        font-weight: 700;
        font-size: 1.2rem;
        color: var(--income-ink);
    }
    .stat-meta {
        color: var(--income-muted);
        font-size: 0.78rem;
    }
    .income-top-mentor {
        background: rgba(59, 91, 219, 0.08);
        border-radius: 16px;
        padding: 1rem;
        display: grid;
        gap: 0.6rem;
    }
    .income-top-mentor .income-mentor {
        gap: 0.75rem;
    }
    .income-top-mentor .income-mentor span {
        font-weight: 600;
    }
    .income-skeleton {
        border-radius: 12px;
        background: linear-gradient(90deg, rgba(226, 232, 240, 0.6), rgba(226, 232, 240, 0.2), rgba(226, 232, 240, 0.6));
        background-size: 200% 100%;
        animation: income-shimmer 1.6s infinite;
    }
    @keyframes income-shimmer {
        0% { background-position: 200% 0; }
        100% { background-position: -200% 0; }
    }
    @media (max-width: 767.98px) {
        .income-title {
            font-size: 1.35rem;
        }
        .income-actions {
            width: 100%;
        }
        .income-chart-canvas,
        .income-empty {
            height: 300px;
        }
    }
</style>
<section class="section">
    <div class="income-card p-4 p-md-5">
        <div class="income-header">
            <div>
                <div class="income-label">INCOME INSIGHT</div>
                <h2 class="income-title">Mentor Annual Income (Top 5)</h2>
                <p class="income-subtitle">Top 5 mentor များ၏ yearly income ကို လွယ်ကူစွာ နှိုင်းယှဉ်ကြည့်နိုင်သည့် premium dashboard snapshot ဖြစ်ပါတယ်။</p>
            </div>
            <div class="income-actions">
                <select class="form-select form-select-sm income-filter" aria-label="Filter income range">
                    <option selected>This year</option>
                    <option>Last year</option>
                    <option>Last 6 months</option>
                </select>
                <button class="btn btn-sm income-export" type="button" aria-label="Export income report">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M5 20a2 2 0 0 1-2-2v-4h2v4h14v-4h2v4a2 2 0 0 1-2 2H5Zm7-16l5 5h-3v6h-4V9H7l5-5Z"/></svg>
                    Export
                </button>
            </div>
        </div>
        <div class="row g-4 align-items-stretch">
            <div class="col-12 col-lg-8">
                <div class="income-chart-card">
                    <div class="income-chart-head">
                        <div>
                            <div class="income-chart-title">Mentor Income Comparison</div>
                            <div class="income-chart-sub">Annual total (Top 5 mentors)</div>
                        </div>
                        <?php if ($topMentors): ?>
                            <span class="income-chart-tag"><?= count($topMentors); ?> mentors</span>
                        <?php endif; ?>
                    </div>
                    <?php if ($topMentors): ?>
                        <div class="income-chart-canvas">
                            <canvas id="mentorIncomeChart" role="img" aria-label="Mentor annual income chart"></canvas>
                        </div>
                    <?php else: ?>
                        <div class="income-empty" role="status" aria-live="polite">
                            <div class="income-empty-icon">
                                <svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M4 19h16v2H4v-2Zm2-2h2V9H6v8Zm5 0h2V5h-2v12Zm5 0h2v-6h-2v6Z"/></svg>
                            </div>
                            <div>
                                <strong>Mentor ဝင်ငွေဒေတာ မရှိသေးပါ။</strong>
                                <div>Data မထည့်ရသေးပါက အနည်းငယ်ကြာနိုင်ပါတယ်။</div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                <?php if ($topMentors): ?>
                <div class="income-table">
                    <div class="table-responsive">
                        <table class="table table-sm table-borderless align-middle mb-0">
                            <thead>
                                <tr>
                                    <th scope="col">Rank</th>
                                    <th scope="col">Mentor</th>
                                    <th scope="col" class="text-end">Income</th>
                                    <th scope="col" class="text-end">% of total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($topMentors as $index => $mentor): ?>
                                    <?php
                                    $percent = $incomeTotal ? ($mentor['income'] / $incomeTotal) * 100 : 0;
                                    $initial = function_exists('mb_substr') ? mb_substr($mentor['name'], 0, 1, 'UTF-8') : substr($mentor['name'], 0, 1);
                                    ?>
                                    <tr>
                                        <td><?= $index + 1; ?></td>
                                        <td>
                                            <div class="income-mentor">
                                                <?php if (!empty($mentor['avatar'])): ?>
                                                    <img src="<?= h($mentor['avatar']); ?>" alt="<?= h($mentor['name']); ?>">
                                                <?php else: ?>
                                                    <span class="income-avatar-fallback"><?= h($initial); ?></span>
                                                <?php endif; ?>
                                                <span><?= h($mentor['name']); ?></span>
                                            </div>
                                        </td>
                                        <td class="text-end"><?= h(format_currency((int) $mentor['income'])); ?></td>
                                        <td class="text-end"><?= h(number_format($percent, 1)); ?>%</td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <div class="col-12 col-lg-4">
                <div class="income-stats">
                    <div class="income-stat">
                        <div class="stat-label">စုစုပေါင်း ဝင်ငွေ</div>
                        <div class="stat-value"><?= h(format_currency($incomeTotal)); ?></div>
                        <div class="stat-meta">Top 5 mentors စုစုပေါင်း</div>
                    </div>
                    <div class="income-stat">
                        <div class="stat-label">အမြင့်ဆုံး ဝင်ငွေ</div>
                        <div class="stat-value"><?= h(format_currency($highestIncome)); ?></div>
                        <div class="stat-meta"><?= $topMentor ? h($topMentor['name']) : '—'; ?></div>
                    </div>
                    <div class="income-stat">
                        <div class="stat-label">ပျမ်းမျှ ဝင်ငွေ</div>
                        <div class="stat-value"><?= h(format_currency($incomeAverage)); ?></div>
                        <div class="stat-meta">Mentor တစ်ဦးလျှင်</div>
                    </div>
                    <div class="income-top-mentor">
                        <div class="stat-label">Top mentor</div>
                        <?php if ($topMentor): ?>
                            <?php
                            $topInitial = function_exists('mb_substr') ? mb_substr($topMentor['name'], 0, 1, 'UTF-8') : substr($topMentor['name'], 0, 1);
                            ?>
                            <div class="income-mentor">
                                <?php if (!empty($topMentor['avatar'])): ?>
                                    <img src="<?= h($topMentor['avatar']); ?>" alt="<?= h($topMentor['name']); ?>">
                                <?php else: ?>
                                    <span class="income-avatar-fallback"><?= h($topInitial); ?></span>
                                <?php endif; ?>
                                <span><?= h($topMentor['name']); ?></span>
                            </div>
                            <div class="stat-value"><?= h(format_currency((int) $topMentor['income'])); ?></div>
                        <?php else: ?>
                            <div class="text-muted">Mentor ဒေတာ မရှိသေးပါ။</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php if ($isAdmin && !$isInstructor): ?>
<?php
render_activity_feed(
    $activityGrouped,
    $activityTransformed,
    "Activity Logs",
    "????????? Recent Actions",
    "Activity log ?????????? Admin Panel ???? ????????????????? ???????"
);
?>
<?php endif; ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(() => {
    const mentors = <?= $topMentorsJson; ?>;
    if (!Array.isArray(mentors) || mentors.length === 0) {
        return;
    }
    if (!window.Chart) {
        return;
    }
    const canvas = document.getElementById('mentorIncomeChart');
    if (!canvas) {
        return;
    }
    const labels = mentors.map((mentor) => mentor.name);
    const values = mentors.map((mentor) => Number(mentor.income || 0));
    const currencies = mentors.map((mentor) => mentor.currency || 'ကျပ်');
    const formatIncome = (value, currency) => {
        if (value === null || value === undefined) {
            return '';
        }
        const formatted = new Intl.NumberFormat('en-US').format(value);
        return `${formatted} ${currency || ''}`.trim();
    };
    const valueLabelPlugin = {
        id: 'valueLabelPlugin',
        afterDatasetsDraw(chart) {
            const { ctx, chartArea } = chart;
            const meta = chart.getDatasetMeta(0);
            ctx.save();
            ctx.font = '600 12px "Noto Sans Myanmar", "Pyidaungsu", system-ui, "Segoe UI", Arial, sans-serif';
            ctx.fillStyle = '#334155';
            ctx.textBaseline = 'middle';
            ctx.textAlign = 'left';
            meta.data.forEach((bar, index) => {
                const value = values[index];
                const label = formatIncome(value, currencies[index]);
                const textWidth = ctx.measureText(label).width;
                const position = bar.tooltipPosition();
                const x = Math.min(position.x + 8, chartArea.right - textWidth - 4);
                const y = position.y;
                ctx.fillText(label, x, y);
            });
            ctx.restore();
        }
    };
    new Chart(canvas, {
        type: 'bar',
        data: {
            labels,
            datasets: [{
                label: 'Annual income',
                data: values,
                backgroundColor(context) {
                    const { chart } = context;
                    const { ctx, chartArea } = chart;
                    if (!chartArea) {
                        return 'rgba(59, 91, 219, 0.95)';
                    }
                    const gradient = ctx.createLinearGradient(chartArea.left, 0, chartArea.right, 0);
                    gradient.addColorStop(0, 'rgba(59, 91, 219, 0.95)');
                    gradient.addColorStop(1, 'rgba(96, 165, 250, 0.92)');
                    return gradient;
                },
                borderRadius: 12,
                borderSkipped: false,
                maxBarThickness: 28
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            layout: {
                padding: { right: 60 }
            },
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: (context) => formatIncome(context.parsed.x, currencies[context.dataIndex])
                    }
                }
            },
            scales: {
                x: {
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(148, 163, 184, 0.25)',
                        drawBorder: false
                    },
                    ticks: {
                        color: '#64748b',
                        font: {
                            family: '"Noto Sans Myanmar", "Pyidaungsu", system-ui, "Segoe UI", Arial, sans-serif',
                            size: 11
                        },
                        callback: (value) => new Intl.NumberFormat('en-US').format(value)
                    }
                },
                y: {
                    grid: { display: false },
                    ticks: {
                        color: '#334155',
                        font: {
                            family: '"Noto Sans Myanmar", "Pyidaungsu", system-ui, "Segoe UI", Arial, sans-serif',
                            size: 12
                        }
                    }
                }
            }
        },
        plugins: [valueLabelPlugin]
    });
})();
</script>
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
<?php if ($showActivityLogs): ?>
    <?php
    render_activity_feed(
        $activityGrouped,
        $activityTransformed,
        "Activity Logs",
        "????????? Recent Actions",
        "Activity log ?????????? ???????????????? ??????????????????????????? ??????????"
    );
    ?>
<?php endif; ?>
<?php endif; ?>

<?php require __DIR__ . '/partials/footer.php'; ?>
