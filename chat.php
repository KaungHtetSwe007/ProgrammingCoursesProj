<?php
require __DIR__ . '/includes/functions.php';
$user = require_login($pdo);
$pageTitle = 'Community Chat';
require __DIR__ . '/partials/header.php';

$courseId = (int)($_GET['course_id'] ?? 0);
$courseStmt = $pdo->prepare('SELECT id, title FROM courses WHERE id = ?');
$courseStmt->execute([$courseId]);
$course = $courseStmt->fetch();

if (!$course) {
    set_flash('error', 'သင်တန်းမရှိပါ။');
    redirect('dashboard.php');
}

if (!ensure_course_access($pdo, $courseId)) {
    set_flash('error', 'ဤ Chat ကို ဝင်ရောက်ခွင့်မရှိပါ။');
    redirect('dashboard.php');
}

$chatStmt = $pdo->prepare('
    SELECT m.*, u.name, u.avatar_path,
           (SELECT COUNT(*) FROM chat_message_likes l WHERE l.message_id = m.id) AS likes_count,
           EXISTS (
               SELECT 1 FROM chat_message_likes l2 WHERE l2.message_id = m.id AND l2.user_id = :current_user
           ) AS liked_by_me
    FROM chat_messages m
    JOIN users u ON m.user_id = u.id
    WHERE m.course_id = :course_id
    ORDER BY m.created_at DESC
    LIMIT 50
');
$chatStmt->execute([
    ':course_id' => $courseId,
    ':current_user' => $user['id']
]);
$messages = $chatStmt->fetchAll();

$repliesByMessage = [];
if ($messages) {
    $ids = array_column($messages, 'id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $replyStmt = $pdo->prepare("
        SELECT r.*, u.name, u.avatar_path
        FROM chat_replies r
        JOIN users u ON u.id = r.user_id
        WHERE r.message_id IN ($placeholders)
        ORDER BY r.created_at ASC
    ");
    $replyStmt->execute($ids);
    foreach ($replyStmt->fetchAll() as $reply) {
        $repliesByMessage[$reply['message_id']][] = $reply;
    }
}
?>

<section class="section">
    <h1><?= h($course['title']); ?> · Chat Community</h1>
    <div class="box">
        <div class="chat-window">
            <?php foreach ($messages as $message): ?>
                <div class="chat-bubble">
                    <div style="display:flex; gap:0.75rem; align-items:flex-start;">
                        <img src="<?= avatar_url(['avatar_path' => $message['avatar_path'] ?? null]); ?>" alt="<?= h($message['name']); ?>" style="width:44px;height:44px;border-radius:50%;object-fit:cover;">
                        <div style="flex:1;">
                            <strong><?= h($message['name']); ?> · <?= date('m/d H:i', strtotime($message['created_at'])); ?></strong>
                            <p><?= nl2br(h($message['message'])); ?></p>
                            <div style="display:flex; gap:0.5rem; align-items:center; flex-wrap:wrap;">
                                <form method="post" action="actions/like_chat.php" style="display:inline-flex; gap:0.35rem; align-items:center;">
                                    <input type="hidden" name="csrf" value="<?= csrf_token(); ?>">
                                    <input type="hidden" name="message_id" value="<?= $message['id']; ?>">
                                    <button class="btn-ghost btn-small" type="submit"><?= $message['liked_by_me'] ? 'Unlike' : 'Like'; ?></button>
                                </form>
                                <small class="muted-text"><?= (int)$message['likes_count']; ?> Likes</small>
                            </div>
                            <div class="chat-replies">
                                <?php foreach ($repliesByMessage[$message['id']] ?? [] as $reply): ?>
                                    <div class="chat-reply">
                                        <img src="<?= avatar_url(['avatar_path' => $reply['avatar_path'] ?? null]); ?>" alt="<?= h($reply['name']); ?>">
                                        <div>
                                            <strong><?= h($reply['name']); ?></strong>
                                            <p><?= nl2br(h($reply['reply_text'])); ?></p>
                                            <small class="muted-text"><?= date('m/d H:i', strtotime($reply['created_at'])); ?></small>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                <form method="post" action="actions/reply_chat.php" class="reply-form">
                                    <input type="hidden" name="csrf" value="<?= csrf_token(); ?>">
                                    <input type="hidden" name="course_id" value="<?= $course['id']; ?>">
                                    <input type="hidden" name="message_id" value="<?= $message['id']; ?>">
                                    <div class="form-group" style="margin-bottom:0.5rem;">
                                        <label style="font-weight:600;">Reply</label>
                                        <textarea name="reply_text" rows="2" required></textarea>
                                    </div>
                                    <button class="btn-small btn" type="submit">ပြန်ကြားမည်</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php if (!$messages): ?>
                <p>စတင်မက်ဆေ့ခ်ျ တင်ပေးပါ။</p>
            <?php endif; ?>
        </div>
        <form method="post" action="actions/post_chat.php">
            <input type="hidden" name="csrf" value="<?= csrf_token(); ?>">
            <input type="hidden" name="course_id" value="<?= $course['id']; ?>">
            <div class="form-group">
                <label>သင့်မက်ဆေ့ခ်ျ</label>
                <textarea name="message" rows="3" required></textarea>
            </div>
            <button class="btn" type="submit">ပိုစ့်မည်</button>
        </form>
    </div>
</section>

<?php require __DIR__ . '/partials/footer.php'; ?>
