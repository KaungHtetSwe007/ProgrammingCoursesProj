<?php
$pageTitle = 'ဆရာများ';
require __DIR__ . '/partials/header.php';

$stmt = $pdo->query('
    SELECT i.*, u.name AS user_name, u.avatar_path,
           (SELECT COUNT(*) FROM instructor_likes WHERE instructor_id = i.id) AS likes
    FROM instructors i
    LEFT JOIN users u ON i.user_id = u.id
    ORDER BY likes DESC, i.display_name
');
$instructors = $stmt->fetchAll();
$currentUser = current_user($pdo);
?>

<section class="section">
    <h1>သင်တန်းဆရာများ</h1>
    <p>တစ်ဦးချင်းစီ၏ ပရိုဖိုင်၊ ရည်ရွယ်ချက်များနှင့် အတွေ့အကြုံများကို စုံစမ်းကြည့်ရှုနိုင်ပါသည်။</p>

    <div class="cards">
        <?php foreach ($instructors as $instructor): ?>
            <article class="card reveal">
                <?php
                $photo = $instructor['avatar_path'] ?: ($instructor['photo_url'] ?? '');
                ?>
                <?php if (!empty($photo)): ?>
                    <img src="<?= h($photo); ?>" alt="<?= h($instructor['display_name']); ?>" style="width:100%;max-width:200px;border-radius:1rem;object-fit:cover;">
                <?php endif; ?>
                <?php $isOwnProfile = $currentUser && (int) $instructor['user_id'] === (int) $currentUser['id']; ?>
                <span class="tag"><?= h($instructor['primary_language']); ?></span>
                <h3><?= h($instructor['display_name']); ?></h3>
                <p><?= h($instructor['title']); ?></p>
                <p><?= nl2br(h($instructor['bio'])); ?></p>
                <p>နှစ်စဉ်ဝင်ငွေ ထောက်ထားမှု - <?= format_currency((int)$instructor['annual_income']); ?></p>
                <p>Like ထားသူများ - <?= h($instructor['likes']); ?> ဦး</p>
                <?php if ($currentUser && !$isOwnProfile): ?>
                    <form method="post" action="actions/like_instructor.php">
                        <input type="hidden" name="csrf" value="<?= csrf_token(); ?>">
                        <input type="hidden" name="instructor_id" value="<?= $instructor['id']; ?>">
                        <button class="btn-ghost" type="submit">Like ပေးမည်</button>
                    </form>
                <?php elseif ($isOwnProfile): ?>
                    <small class="muted-text">မိမိပရိုဖိုင်ထက် အခြားဆရာများအား လိုက်ကို မျှဝေပါ။</small>
                <?php else: ?>
                    <small>Like မလုပ်ခင် <a href="login.php">ဝင်ရောက်ပါ</a></small>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
        <?php if (!$instructors): ?>
            <p>ဆရာများကို database/schema.sql ဖြင့် ထည့်ပေးပါ။</p>
        <?php endif; ?>
    </div>
</section>

<?php require __DIR__ . '/partials/footer.php'; ?>
