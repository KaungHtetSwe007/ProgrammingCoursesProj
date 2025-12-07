<?php
$pageTitle = 'စာအုပ်ဒေါင်းလုဒ်';
require __DIR__ . '/partials/header.php';

$search = trim($_GET['q'] ?? '');
$searchLike = $search !== '' ? '%' . (function_exists('mb_strtolower') ? mb_strtolower($search, 'UTF-8') : strtolower($search)) . '%' : null;

$sql = '
    SELECT b.*, i.display_name AS mentor_name
    FROM books b
    LEFT JOIN instructors i ON b.instructor_id = i.id
';
$params = [];
if ($searchLike) {
    $sql .= ' WHERE LOWER(b.title) LIKE ? OR LOWER(b.language) LIKE ? OR LOWER(i.display_name) LIKE ? ';
    $params = [$searchLike, $searchLike, $searchLike];
}
$sql .= ' ORDER BY b.language, b.created_at DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$books = $stmt->fetchAll();
$booksByLanguage = [];
foreach ($books as $book) {
    $booksByLanguage[$book['language']][] = $book;
}

$currentUser = current_user($pdo);
$favoriteIds = [];
if ($currentUser) {
    $favStmt = $pdo->prepare('SELECT book_id FROM favorite_books WHERE user_id = ?');
    $favStmt->execute([$currentUser['id']]);
    $favoriteIds = array_column($favStmt->fetchAll(), 'book_id');
}
?>

<section class="section">
    <div class="eyebrow">စာအုပ်ဒေါင်းလုဒ်</div>
    <h1>Cover Image + Language Grouped</h1>
    <p class="muted-text">Programming Language အလိုက် Mentor တင်ထားသော PDF/EPUB များကို ဒေါင်းလုဒ်လုပ်ပြီး Favourite ထားနိုင်ပါသည်။</p>

    <form method="get" class="search-form" style="margin:1rem 0; display:flex; gap:0.6rem; flex-wrap:wrap;">
        <input type="text" name="q" value="<?= h($search); ?>" placeholder="Search title / language / mentor">
        <button class="btn" type="submit">Search</button>
        <?php if ($search !== ''): ?>
            <a class="btn-ghost" href="books.php">Clear</a>
        <?php endif; ?>
    </form>

    <?php if ($search !== ''): ?>
        <p class="muted-text">Search: <strong><?= h($search); ?></strong> · <?= count($books); ?> result(s)</p>
    <?php endif; ?>

    <?php foreach ($booksByLanguage as $language => $languageBooks): ?>
        <div class="section-header">
            <div>
                <div class="eyebrow"><?= h($language); ?></div>
                <h2><?= h($language); ?> စာအုပ်များ</h2>
            </div>
        </div>
        <div class="cards">
            <?php foreach ($languageBooks as $book): ?>
                <article class="card book-card reveal">
                    <div class="book-cover">
                        <?php if (!empty($book['cover_path'])): ?>
                            <img src="<?= h(str_replace('\\\\', '/', $book['cover_path'])); ?>" alt="<?= h($book['title']); ?> cover">
                        <?php endif; ?>
                    </div>
                    <div>
                        <span class="tag"><?= h($book['language']); ?></span>
                        <h3><?= h($book['title']); ?></h3>
                        <?php if (!empty($book['mentor_name'])): ?>
                            <p class="muted-text">Mentor - <?= h($book['mentor_name']); ?></p>
                        <?php endif; ?>
                        <p><?= nl2br(h($book['description'])); ?></p>
                        <p class="muted-text">ဖိုင်အရွယ်အစား - <?= h($book['file_size']); ?> MB</p>
                        <div style="display:flex; gap:0.5rem; flex-wrap:wrap;">
                            <a class="btn" href="actions/download_book.php?id=<?= $book['id']; ?>&csrf=<?= csrf_token(); ?>">ဒေါင်းလုဒ်</a>
                            <?php if ($currentUser): ?>
                                <form method="post" action="actions/favorite_book.php">
                                    <input type="hidden" name="csrf" value="<?= csrf_token(); ?>">
                                    <input type="hidden" name="book_id" value="<?= $book['id']; ?>">
                                    <button class="btn-ghost" type="submit">
                                        <?= in_array($book['id'], $favoriteIds, true) ? 'Favourite မှ ဖယ်' : 'Favourite ထည့်'; ?>
                                    </button>
                                </form>
                            <?php else: ?>
                                <small>Favourite ပြုလုပ်ရန် <a href="login.php"><p class="color: blue;">ဝင်ရောက်ပါ</p></a></small>
                            <?php endif; ?>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>
    <?php if (!$books): ?>
        <p>စာအုပ်များမရှိသေးပါ။ database/schema.sql ကို အသုံးပြု၍ ထည့်ပါ။</p>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/partials/footer.php'; ?>
