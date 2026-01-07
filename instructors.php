<?php
require __DIR__ . '/includes/functions.php';

$pageTitle = 'ဆရာများ';
$extraHead = <<<HTML
<link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Myanmar:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
HTML;

$currentUser = current_user($pdo);

$stmt = $pdo->query('
    SELECT i.*, u.name AS user_name, u.avatar_path,
           (SELECT COUNT(*) FROM instructor_likes il WHERE il.instructor_id = i.id) AS likes,
           (SELECT COUNT(*) FROM courses c WHERE c.instructor_id = i.id) AS courses_count,
           (SELECT COUNT(*) FROM enrollments e JOIN courses c ON c.id = e.course_id WHERE c.instructor_id = i.id AND e.status = "approved") AS students_count,
           (SELECT ROUND(AVG(cr.rating), 1)
              FROM course_ratings cr
              JOIN courses c ON c.id = cr.course_id
             WHERE c.instructor_id = i.id) AS avg_rating
    FROM instructors i
    LEFT JOIN users u ON i.user_id = u.id
    ORDER BY likes DESC, i.display_name
');
$instructors = $stmt->fetchAll();

$followedIds = [];
if ($currentUser) {
    $followStmt = $pdo->prepare('SELECT instructor_id FROM instructor_likes WHERE user_id = ?');
    $followStmt->execute([$currentUser['id']]);
    $followedIds = array_map('intval', array_column($followStmt->fetchAll(), 'instructor_id'));
}

$preparedInstructors = [];
$skillFilters = [];
foreach ($instructors as $instructor) {
    $skillsRaw = $instructor['skills'] ?? ($instructor['expertise'] ?? ($instructor['primary_language'] ?? ''));
    $skills = array_values(array_filter(array_map('trim', explode(',', (string) $skillsRaw))));
    $skills = array_values(array_unique($skills));
    foreach ($skills as $skill) {
        if ($skill === '') {
            continue;
        }
        $skillFilters[strtolower($skill)] = $skill;
    }
    $instructor['skills_list'] = $skills;
    $preparedInstructors[] = $instructor;
}

$filterLabels = array_values($skillFilters);
sort($filterLabels, SORT_NATURAL | SORT_FLAG_CASE);

require __DIR__ . '/partials/header.php';
?>

<section class="section instructors-section">
    <div class="instructors-shell">
        <div class="instructors-header">
            <div class="instructors-title">
                <div class="eyebrow">ဆရာအဖွဲ့</div>
                <h1>သင်တန်းဆရာများ <span class="title-sub">Instructors</span></h1>
                <p class="muted-text">အတွေ့အကြုံ၊ Ratings နှင့် ကျောင်းသားအရေအတွက်ကို အလွယ်တကူ စိစစ်နိုင်ပါသည်။</p>
            </div>
            <div class="instructors-controls">
                <div class="instructors-filters">
                    <button type="button" class="btn btn-sm filter-pill active" data-filter="all">All</button>
                    <?php foreach ($filterLabels as $label): ?>
                        <button type="button" class="btn btn-sm filter-pill" data-filter="<?= h(strtolower($label)); ?>">
                            <?= h($label); ?>
                        </button>
                    <?php endforeach; ?>
                </div>
                <div class="instructors-tools">
                    <select class="form-select form-select-sm" id="sortSelect" aria-label="Sort instructors">
                        <option value="likes">Most liked</option>
                        <option value="rating">Top rated</option>
                        <option value="students">Most students</option>
                    </select>
                    <input
                        type="search"
                        class="form-control form-control-sm"
                        id="searchInput"
                        placeholder="Search instructor"
                        aria-label="Search instructor"
                    >
                </div>
            </div>
        </div>

        <?php if ($preparedInstructors): ?>
            <div class="row g-3 g-lg-4 instructors-grid" id="instructorGrid">
                <?php foreach ($preparedInstructors as $instructor): ?>
                    <?php
                    $photo = $instructor['avatar_path'] ?: ($instructor['photo_url'] ?? '');
                    $displayName = $instructor['display_name'] ?: ($instructor['user_name'] ?? 'Instructor');
                    $roleTitle = $instructor['title'] ?: 'Instructor';
                    $skills = $instructor['skills_list'] ?? [];
                    $primarySkills = array_slice($skills, 0, 3);
                    $extraSkillCount = max(0, count($skills) - 3);
                    $experienceYears = isset($instructor['experience_years']) ? (int) $instructor['experience_years'] : (isset($instructor['experience']) ? (int) $instructor['experience'] : null);
                    $likesCount = (int) ($instructor['likes'] ?? 0);
                    $studentsCount = (int) ($instructor['students_count'] ?? 0);
                    $coursesCount = (int) ($instructor['courses_count'] ?? 0);
                    $ratingValue = $instructor['avg_rating'] !== null ? (float) $instructor['avg_rating'] : null;
                    $bio = trim((string) ($instructor['bio'] ?? ''));
                    $bioText = $bio !== '' ? $bio : '—';
                    $bioClass = $bio !== '' ? '' : 'bio-placeholder';
                    $isOwnProfile = $currentUser && (int) $instructor['user_id'] === (int) $currentUser['id'];
                    $isFollowed = $currentUser && in_array((int) $instructor['id'], $followedIds, true);
                    $skillsText = implode(' ', $skills);
                    $skillsLower = function_exists('mb_strtolower') ? mb_strtolower($skillsText, 'UTF-8') : strtolower($skillsText);
                    $nameLower = function_exists('mb_strtolower') ? mb_strtolower($displayName, 'UTF-8') : strtolower($displayName);
                    $initials = function_exists('mb_substr') ? mb_substr($displayName, 0, 2) : substr($displayName, 0, 2);
                    if (function_exists('mb_strtoupper')) {
                        $initials = mb_strtoupper($initials, 'UTF-8');
                    } else {
                        $initials = strtoupper($initials);
                    }
                    ?>
                    <div
                        class="col-12 col-md-6 col-lg-4 col-xl-3 instructor-col"
                        data-skills="<?= h($skillsLower); ?>"
                        data-name="<?= h($nameLower); ?>"
                        data-likes="<?= $likesCount; ?>"
                        data-rating="<?= $ratingValue ?? 0; ?>"
                        data-students="<?= $studentsCount; ?>"
                    >
                        <!-- Instructor card component -->
                        <article class="instructor-card reveal" id="instructor-<?= $instructor['id']; ?>">
                            <div class="card-top">
                                <div class="instructor-avatar">
                                    <?php if (!empty($photo)): ?>
                                        <img src="<?= h($photo); ?>" alt="<?= h($displayName); ?>">
                                    <?php else: ?>
                                        <span class="avatar-fallback"><?= h($initials); ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($instructor['user_id'])): ?>
                                        <span class="verified-badge" title="Verified"><i class="bi bi-patch-check-fill"></i></span>
                                    <?php endif; ?>
                                </div>
                                <div class="instructor-identity">
                                    <h3 class="instructor-name"><?= h($displayName); ?></h3>
                                    <p class="instructor-role"><?= h($roleTitle); ?></p>
                                    <?php if ($primarySkills): ?>
                                        <div class="expertise-badges">
                                            <?php foreach ($primarySkills as $skill): ?>
                                                <span class="skill-badge"><?= h($skill); ?></span>
                                            <?php endforeach; ?>
                                            <?php if ($extraSkillCount > 0): ?>
                                                <span class="skill-badge more-badge">+<?= $extraSkillCount; ?></span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <p class="instructor-bio line-clamp-2 <?= $bioClass; ?>"><?= h($bioText); ?></p>

                            <?php if ($ratingValue || $studentsCount || $coursesCount || $experienceYears): ?>
                                <div class="trust-row">
                                    <?php if ($ratingValue): ?>
                                        <div class="trust-item">
                                            <i class="bi bi-star-fill"></i>
                                            <strong><?= number_format($ratingValue, 1); ?></strong>
                                            <span>Rating</span>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($studentsCount): ?>
                                        <div class="trust-item">
                                            <i class="bi bi-people-fill"></i>
                                            <strong><?= number_format($studentsCount); ?></strong>
                                            <span>Students</span>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($coursesCount): ?>
                                        <div class="trust-item">
                                            <i class="bi bi-journal-code"></i>
                                            <strong><?= $coursesCount; ?></strong>
                                            <span>Courses</span>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($experienceYears): ?>
                                        <div class="trust-item">
                                            <i class="bi bi-clock-history"></i>
                                            <strong><?= $experienceYears; ?>+ yrs</strong>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <div class="card-footer">
                                <span class="likes-pill"><i class="bi bi-heart-fill"></i><?= $likesCount; ?> likes</span>
                                <div class="action-row">
                                    <a class="btn btn-sm btn-outline-primary w-100" href="instructors.php?focus=<?= $instructor['id']; ?>">View Profile</a>
                                    <?php if ($currentUser && !$isOwnProfile): ?>
                                        <form method="post" action="actions/like_instructor.php" class="follow-form w-100">
                                            <input type="hidden" name="csrf" value="<?= csrf_token(); ?>">
                                            <input type="hidden" name="instructor_id" value="<?= $instructor['id']; ?>">
                                            <button class="btn btn-sm btn-follow w-100 <?= $isFollowed ? 'is-following' : ''; ?>" type="submit" data-follow-toggle>
                                                <?= $isFollowed ? 'Following' : 'Follow'; ?>
                                            </button>
                                        </form>
                                    <?php elseif ($isOwnProfile): ?>
                                        <button class="btn btn-sm btn-follow w-100 is-following" type="button" disabled>သင့်ပရိုဖိုင်</button>
                                    <?php else: ?>
                                        <a class="btn btn-sm btn-follow w-100" href="login.php">Follow</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </article>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="empty-state <?= $preparedInstructors ? 'd-none' : ''; ?>" id="emptyState">
            <div class="empty-illustration" aria-hidden="true">
                <svg viewBox="0 0 120 120" width="76" height="76">
                    <circle cx="60" cy="60" r="56" fill="#eaf0ff" />
                    <path d="M38 76c5-12 18-20 22-20s17 8 22 20" fill="#c7d4ff" />
                    <circle cx="48" cy="48" r="6" fill="#8aa5ff" />
                    <circle cx="72" cy="48" r="6" fill="#8aa5ff" />
                </svg>
            </div>
            <h3>ဆရာများ မတွေ့ပါ</h3>
            <p>Filter သို့မဟုတ် Search ကို ပြန်လည်ပြင်ပြီး ထပ်စမ်းကြည့်ပါ။</p>
            <button class="btn btn-sm btn-outline-primary" id="clearFilters" type="button">Clear filters</button>
        </div>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const filterButtons = Array.from(document.querySelectorAll('[data-filter]'));
    const grid = document.getElementById('instructorGrid');
    const cards = grid ? Array.from(grid.querySelectorAll('.instructor-col')) : [];
    const searchInput = document.getElementById('searchInput');
    const sortSelect = document.getElementById('sortSelect');
    const emptyState = document.getElementById('emptyState');
    const clearBtn = document.getElementById('clearFilters');

    const getValue = (card, key) => {
        const raw = card.dataset[key];
        const num = Number(raw);
        return Number.isNaN(num) ? 0 : num;
    };

    const applyFilters = () => {
        const active = filterButtons.find(btn => btn.classList.contains('active'));
        const activeFilter = active ? active.dataset.filter : 'all';
        const term = (searchInput?.value || '').trim().toLowerCase();
        let visible = 0;

        cards.forEach(card => {
            const skills = card.dataset.skills || '';
            const name = card.dataset.name || '';
            const matchesSkill = activeFilter === 'all' || skills.includes(activeFilter);
            const matchesText = term === '' || name.includes(term);
            const show = matchesSkill && matchesText;
            card.classList.toggle('d-none', !show);
            if (show) {
                visible += 1;
            }
        });

        if (emptyState) {
            emptyState.classList.toggle('d-none', visible > 0);
        }
    };

    const applySort = () => {
        if (!grid || !sortSelect) {
            return;
        }
        const key = sortSelect.value;
        const sorted = cards.slice().sort((a, b) => {
            if (key === 'rating') {
                return getValue(b, 'rating') - getValue(a, 'rating');
            }
            if (key === 'students') {
                return getValue(b, 'students') - getValue(a, 'students');
            }
            return getValue(b, 'likes') - getValue(a, 'likes');
        });

        sorted.forEach(card => grid.appendChild(card));
    };

    filterButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            filterButtons.forEach(item => item.classList.remove('active'));
            btn.classList.add('active');
            applyFilters();
        });
    });

    searchInput?.addEventListener('input', applyFilters);
    sortSelect?.addEventListener('change', () => {
        applySort();
        applyFilters();
    });

    clearBtn?.addEventListener('click', () => {
        filterButtons.forEach(item => item.classList.remove('active'));
        filterButtons[0]?.classList.add('active');
        if (searchInput) {
            searchInput.value = '';
        }
        applyFilters();
    });

    document.addEventListener('click', (event) => {
        const button = event.target.closest('[data-follow-toggle]');
        if (!button) {
            return;
        }
        button.classList.toggle('is-following');
        button.textContent = button.classList.contains('is-following') ? 'Following' : 'Follow';
    });

    applySort();
    applyFilters();
});
</script>

<?php require __DIR__ . '/partials/footer.php'; ?>
