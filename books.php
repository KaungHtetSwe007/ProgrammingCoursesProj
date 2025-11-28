<?php
$pageTitle = 'စာအုပ်ဒေါင်းလုဒ်';
require __DIR__ . '/partials/header.php';

$stmt = $pdo->query('SELECT * FROM books ORDER BY language, title');
$books = $stmt->fetchAll();

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
    <h1>Cover Image + Download Badge ဖြင့် စာအုပ်များ</h1>
    <p class="muted-text">Programming Language အလိုက် စုစည်းထားသော PDF/EPUB များကို ဒေါင်းလုဒ်လုပ်ပြီး Favourite ထားနိုင်ပါသည်။</p>

    <div class="cards">
        <?php foreach ($books as $book): ?>
            <article class="card book-card reveal">
                <div class="book-cover"></div>
                <div>
                    <span class="tag"><?= h($book['language']); ?></span>
                    <h3><?= h($book['title']); ?></h3>
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
                            <small>Favourite ပြုလုပ်ရန် <a href="login.php">ဝင်ရောက်ပါ</a></small>
                        <?php endif; ?>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
        <?php if (!$books): ?>
            <p>စာအုပ်များမရှိသေးပါ။ database/schema.sql ကို အသုံးပြု၍ ထည့်ပါ။</p>
        <?php endif; ?>
    </div>
</section>

<?php require __DIR__ . '/partials/footer.php'; ?>
