<?php
$pageTitle = 'စာအုပ်စာရင်း';
$extraHead = <<<HTML
<link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Myanmar:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
HTML;
require __DIR__ . '/partials/header.php';

$search = trim($_GET['q'] ?? '');
$searchLike = $search !== '' ? '%' . (function_exists('mb_strtolower') ? mb_strtolower($search, 'UTF-8') : strtolower($search)) . '%' : null;

$currentUser = $currentUser ?? current_user($pdo);
$userId = $currentUser['id'] ?? null;

$sql = '
    SELECT
        b.*,
        i.display_name AS mentor_name,
        (SELECT ROUND(AVG(br.rating), 1) FROM book_ratings br WHERE br.book_id = b.id) AS avg_rating,
        (SELECT COUNT(*) FROM book_ratings br WHERE br.book_id = b.id) AS rating_count,
        (SELECT br.rating FROM book_ratings br WHERE br.book_id = b.id AND br.user_id = ?) AS user_rating
    FROM books b
    LEFT JOIN instructors i ON b.instructor_id = i.id
';
$params = [$userId];
if ($searchLike) {
    $sql .= ' WHERE LOWER(b.title) LIKE ? OR LOWER(b.language) LIKE ? OR LOWER(i.display_name) LIKE ? ';
    $params[] = $searchLike;
    $params[] = $searchLike;
    $params[] = $searchLike;
}
$sql .= ' ORDER BY b.language, b.created_at DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$books = $stmt->fetchAll();
$booksByLanguage = [];
foreach ($books as $book) {
    $booksByLanguage[$book['language']][] = $book;
}

$languageOptions = array_keys($booksByLanguage);
sort($languageOptions, SORT_NATURAL | SORT_FLAG_CASE);

$favoriteIds = [];
if ($currentUser) {
    $favStmt = $pdo->prepare('SELECT book_id FROM favorite_books WHERE user_id = ?');
    $favStmt->execute([$currentUser['id']]);
    $favoriteIds = array_column($favStmt->fetchAll(), 'book_id');
}

if (!function_exists('render_book_stars')) {
    function render_book_stars(float $rating): string
    {
        $full = (int) floor($rating);
        $half = ($rating - $full) >= 0.5;
        $html = '';
        for ($i = 1; $i <= 5; $i++) {
            if ($i <= $full) {
                $html .= '<i class="bi bi-star-fill"></i>';
            } elseif ($half) {
                $html .= '<i class="bi bi-star-half"></i>';
                $half = false;
            } else {
                $html .= '<i class="bi bi-star"></i>';
            }
        }
        return $html;
    }
}
?>

<section class="section book-library">
    <div class="library-header">
        <div class="eyebrow">စာအုပ်စာရင်း</div>
        <h1>Books & Resources Library</h1>
        <p class="muted-text">Programming Language အလိုက် စုစည်းထားသော စာအုပ်များကို အလွယ်တကူ ရှာဖွေဖတ်ရှုနိုင်ပါသည်။</p>
    </div>

    <form method="get" action="books.php" class="search-row">
        <input
            type="search"
            class="form-control"
            id="bookSearch"
            name="q"
            value="<?= h($search); ?>"
            placeholder="Search title / mentor / language"
            aria-label="Search books"
        >
        <select class="form-select" id="bookFilter" aria-label="Filter books">
            <option value="all">All Filters</option>
            <?php if ($languageOptions): ?>
                <optgroup label="Language">
                    <?php foreach ($languageOptions as $lang): ?>
                        <option value="lang:<?= h(strtolower($lang)); ?>"><?= h($lang); ?></option>
                    <?php endforeach; ?>
                </optgroup>
            <?php endif; ?>
            <optgroup label="Rating">
                <option value="rating:4">Rating 4+</option>
                <option value="rating:3">Rating 3+</option>
            </optgroup>
            <optgroup label="Other">
                <option value="newest">Newest</option>
                <option value="type:pdf">PDF</option>
                <option value="type:epub">EPUB</option>
            </optgroup>
        </select>
        <button class="btn btn-primary" type="submit">Search</button>
    </form>

    <?php if ($booksByLanguage): ?>
        <div id="bookGroups">
            <?php foreach ($booksByLanguage as $language => $languageBooks): ?>
                <?php
                $langKey = function_exists('mb_strtolower') ? mb_strtolower($language, 'UTF-8') : strtolower($language);
                ?>
                <div class="book-group" data-group="<?= h($langKey); ?>">
                    <div class="group-header">
                        <h2><?= h($language); ?> စာအုပ်များ</h2>
                        <span class="count-badge" data-count><?= count($languageBooks); ?> books</span>
                    </div>

                    <div class="row g-3 g-lg-4">
                        <?php foreach ($languageBooks as $book): ?>
                            <?php
                            $bookSize = (float) ($book['file_size'] ?? 0);
                            $bookSizeValue = $bookSize ? number_format($bookSize, 1) : ($book['file_size'] ?? 'N/A');
                            $bookCreatedAt = !empty($book['created_at']) ? strtotime($book['created_at']) : null;
                            $isNewBook = $bookCreatedAt ? $bookCreatedAt >= strtotime('-30 days') : false;
                            $avgRating = $book['avg_rating'] !== null ? (float) $book['avg_rating'] : null;
                            $ratingCount = (int) ($book['rating_count'] ?? 0);
                            $userRating = $book['user_rating'] !== null ? (int) $book['user_rating'] : null;
                            $coverPath = !empty($book['cover_path']) ? str_replace('\\', '/', $book['cover_path']) : '';
                            $title = $book['title'] ?? 'Untitled';
                            $mentorName = $book['mentor_name'] ?? 'Mentor';
                            $filePath = $book['file_path'] ?? '';
                            $fileExt = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
                            $fileType = $fileExt ? strtoupper($fileExt) : 'FILE';
                            $displayRating = $avgRating !== null ? number_format($avgRating, 1) : 'N/A';
                            $ratingForStars = $avgRating ?? 0;
                            $reviewText = $ratingCount ? $ratingCount . ' reviews' : 'No reviews';
                            $isFavorite = in_array($book['id'], $favoriteIds, true);
                            $dataTitle = $title . ' ' . $mentorName . ' ' . $book['language'];
                            $dataTitle = function_exists('mb_strtolower') ? mb_strtolower($dataTitle, 'UTF-8') : strtolower($dataTitle);
                            $cardLink = $currentUser
                                ? 'actions/download_book.php?id=' . $book['id'] . '&csrf=' . csrf_token()
                                : 'login.php';
                            ?>
                            <div
                                class="col-12 col-md-6 col-lg-4 book-col"
                                data-language="<?= h(strtolower($book['language'])); ?>"
                                data-title="<?= h($dataTitle); ?>"
                                data-rating="<?= $avgRating ?? 0; ?>"
                                data-new="<?= $isNewBook ? '1' : '0'; ?>"
                                data-type="<?= h(strtolower($fileType)); ?>"
                            >
                                <!-- Book card component -->
                                <article class="book-card" data-link="<?= h($cardLink); ?>" id="book-<?= $book['id']; ?>">
                                    <div class="book-cover">
                                        <?php if ($coverPath): ?>
                                            <img src="<?= h($coverPath); ?>" alt="<?= h($title); ?> cover">
                                        <?php else: ?>
                                            <span><?= h(function_exists('mb_strtoupper') ? mb_strtoupper(mb_substr($title, 0, 2)) : strtoupper(substr($title, 0, 2))); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="book-body">
                                        <div class="title-row">
                                            <h3 class="book-title line-clamp-2"><?= h($title); ?></h3>
                                            <?php if ($isNewBook): ?>
                                                <span class="badge new-badge">New</span>
                                            <?php endif; ?>
                                        </div>
                                        <p class="book-mentor">Mentor · <?= h($mentorName); ?></p>
                                        <div class="meta-row">
                                            <span class="meta-pill"><i class="bi bi-file-earmark-text"></i><?= h($fileType); ?></span>
                                            <span class="meta-pill"><i class="bi bi-hdd"></i><?= h($bookSizeValue); ?> MB</span>
                                            <span class="meta-pill"><i class="bi bi-code-slash"></i><?= h($book['language']); ?></span>
                                        </div>
                                        <div class="rating-row">
                                            <div class="rating-stars"><?= render_book_stars($ratingForStars); ?></div>
                                            <strong><?= h($displayRating); ?></strong>
                                            <span class="rating-meta"><?= h($reviewText); ?></span>
                                        </div>
                                        <div class="action-row">
                                            <?php if ($currentUser): ?>
                                                <a class="btn btn-primary btn-sm btn-download" href="actions/download_book.php?id=<?= $book['id']; ?>&csrf=<?= csrf_token(); ?>">Download</a>
                                            <?php else: ?>
                                                <a class="btn btn-primary btn-sm btn-download" href="login.php">Login to Download</a>
                                            <?php endif; ?>
                                            <div class="icon-actions">
                                                <?php if ($currentUser): ?>
                                                    <form method="post" action="actions/favorite_book.php" class="inline-form">
                                                        <input type="hidden" name="csrf" value="<?= csrf_token(); ?>">
                                                        <input type="hidden" name="book_id" value="<?= $book['id']; ?>">
                                                        <button class="icon-btn fav-toggle<?= $isFavorite ? ' is-fav' : ''; ?>" type="submit" aria-pressed="<?= $isFavorite ? 'true' : 'false'; ?>" aria-label="Favourite">
                                                            <i class="bi bi-heart-fill"></i>
                                                        </button>
                                                    </form>
                                                    <button class="icon-btn rate-toggle<?= $userRating ? ' is-rated' : ''; ?>" type="button" aria-expanded="false" aria-label="Rate">
                                                        <i class="bi bi-star-fill"></i>
                                                    </button>
                                                <?php else: ?>
                                                    <a class="icon-btn" href="login.php" aria-label="Login to favourite">
                                                        <i class="bi bi-heart-fill"></i>
                                                    </a>
                                                    <a class="icon-btn" href="login.php" aria-label="Login to rate">
                                                        <i class="bi bi-star-fill"></i>
                                                    </a>
                                                <?php endif; ?>
                                                <a class="icon-btn" href="books.php#book-<?= $book['id']; ?>" aria-label="Details">
                                                    <i class="bi bi-info-circle"></i>
                                                </a>
                                            </div>
                                        </div>
                                        <?php if ($currentUser): ?>
                                            <div class="rating-panel">
                                                <form class="rating-form" method="post" action="actions/rate_book.php">
                                                    <input type="hidden" name="csrf" value="<?= csrf_token(); ?>">
                                                    <input type="hidden" name="book_id" value="<?= $book['id']; ?>">
                                                    <div class="star-inputs" aria-label="Rate this book">
                                                        <?php for ($i = 5; $i >= 1; $i--): ?>
                                                            <input type="radio" name="rating" value="<?= $i; ?>" id="book-rate-<?= $book['id']; ?>-<?= $i; ?>" <?= $userRating === $i ? 'checked' : ''; ?>>
                                                            <label for="book-rate-<?= $book['id']; ?>-<?= $i; ?>">★</label>
                                                        <?php endfor; ?>
                                                    </div>
                                                    <button class="btn btn-outline-primary btn-sm" type="submit">Save rating</button>
                                                </form>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </article>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="group-divider"></div>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="empty-state d-none" id="emptyState">
            <h3>No books found</h3>
            <p>Search/Filter ကို ပြန်လည်ပြင်ပြီး ထပ်စမ်းကြည့်ပါ။</p>
            <button class="btn btn-outline-primary btn-sm" type="button" id="resetFilters">Clear filters</button>
        </div>
    <?php else: ?>
        <div class="empty-state" id="emptyState">
            <h3>စာအုပ် မတွေ့ပါ</h3>
            <p>စာအုပ်များမရှိသေးပါ။ database/schema.sql ကို အသုံးပြု၍ ထည့်ပါ။</p>
        </div>
    <?php endif; ?>
</section>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const cards = Array.from(document.querySelectorAll('.book-col'));
    const groups = Array.from(document.querySelectorAll('.book-group'));
    const searchInput = document.getElementById('bookSearch');
    const filterSelect = document.getElementById('bookFilter');
    const emptyState = document.getElementById('emptyState');
    const resetBtn = document.getElementById('resetFilters');

    const applyFilters = () => {
        const term = (searchInput?.value || '').trim().toLowerCase();
        const filter = filterSelect?.value || 'all';
        let visibleTotal = 0;

        cards.forEach(card => {
            const title = card.dataset.title || '';
            const lang = card.dataset.language || '';
            const rating = Number(card.dataset.rating || 0);
            const isNew = card.dataset.new === '1';
            const type = card.dataset.type || '';

            let matches = term === '' || title.includes(term);

            if (filter.startsWith('lang:')) {
                matches = matches && lang === filter.replace('lang:', '');
            } else if (filter.startsWith('rating:')) {
                matches = matches && rating >= Number(filter.replace('rating:', ''));
            } else if (filter === 'newest') {
                matches = matches && isNew;
            } else if (filter.startsWith('type:')) {
                matches = matches && type === filter.replace('type:', '');
            }

            card.classList.toggle('d-none', !matches);
            if (matches) {
                visibleTotal += 1;
            }
        });

        groups.forEach(group => {
            const visibleCards = group.querySelectorAll('.book-col:not(.d-none)');
            const countBadge = group.querySelector('[data-count]');
            if (countBadge) {
                countBadge.textContent = `${visibleCards.length} books`;
            }
            group.classList.toggle('d-none', visibleCards.length === 0);
        });

        if (emptyState) {
            emptyState.classList.toggle('d-none', visibleTotal > 0);
        }
    };

    document.querySelectorAll('.book-card[data-link]').forEach(card => {
        const link = card.dataset.link;
        if (!link) {
            return;
        }
        card.addEventListener('click', (event) => {
            if (event.target.closest('a') || event.target.closest('button') || event.target.closest('form')) {
                return;
            }
            window.location = link;
        });
    });

    document.addEventListener('click', (event) => {
        const favBtn = event.target.closest('.fav-toggle');
        if (favBtn) {
            favBtn.classList.toggle('is-fav');
            favBtn.setAttribute('aria-pressed', favBtn.classList.contains('is-fav') ? 'true' : 'false');
        }

        const rateBtn = event.target.closest('.rate-toggle');
        if (rateBtn) {
            const card = rateBtn.closest('.book-card');
            if (!card) {
                return;
            }
            card.classList.toggle('show-rating');
            rateBtn.classList.toggle('is-open');
            rateBtn.setAttribute('aria-expanded', rateBtn.classList.contains('is-open') ? 'true' : 'false');
        }
    });

    searchInput?.addEventListener('input', applyFilters);
    filterSelect?.addEventListener('change', applyFilters);
    resetBtn?.addEventListener('click', () => {
        if (searchInput) {
            searchInput.value = '';
        }
        if (filterSelect) {
            filterSelect.value = 'all';
        }
        applyFilters();
    });

    applyFilters();
});
</script>

<?php require __DIR__ . '/partials/footer.php'; ?>
