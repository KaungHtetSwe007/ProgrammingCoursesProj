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

    $attachStmt = $pdo->prepare("
        SELECT *
        FROM chat_attachments
        WHERE message_id IN ($placeholders)
        ORDER BY created_at ASC
    ");
    $attachStmt->execute($ids);
    $attachmentsByMessage = [];
    $attachmentsByReply = [];
    foreach ($attachStmt->fetchAll() as $attachment) {
        if (!empty($attachment['reply_id'])) {
            $attachmentsByReply[$attachment['reply_id']][] = $attachment;
        } else {
            $attachmentsByMessage[$attachment['message_id']][] = $attachment;
        }
    }
} else {
    $attachmentsByMessage = $attachmentsByReply = [];
}
?>

<section class="section">
    <h1><?= h($course['title']); ?> · Chat Community</h1>
    <div class="box chat-shell">
        <div class="chat-window">
            <?php foreach ($messages as $message): ?>
                <article class="chat-bubble" id="message-<?= $message['id']; ?>">
                    <div class="chat-head">
                        <img class="chat-avatar" src="<?= avatar_url(['avatar_path' => $message['avatar_path'] ?? null]); ?>" alt="<?= h($message['name']); ?>">
                        <div class="chat-head-meta">
                            <div class="chat-meta-line">
                                <strong><?= h($message['name']); ?></strong>
                                <span class="muted-text"><?= date('m/d H:i', strtotime($message['created_at'])); ?></span>
                            </div>
                            <p class="chat-text"><?= nl2br(h($message['message'])); ?></p>
                        </div>
                    </div>

                    <?php if (!empty($attachmentsByMessage[$message['id']])): ?>
                        <div class="chat-attachments">
                            <?php foreach ($attachmentsByMessage[$message['id']] as $attachment): ?>
                                <?php
                                $isImage = strpos($attachment['mime_type'], 'image/') === 0;
                                $sizeLabel = round(((int)$attachment['size_bytes']) / 1024, 1) . ' KB';
                                ?>
                                <div class="chat-attachment">
                                    <?php if ($isImage): ?>
                                        <a href="<?= h($attachment['path']); ?>" target="_blank">
                                            <img src="<?= h($attachment['path']); ?>" alt="<?= h($attachment['original_name']); ?>">
                                        </a>
                                    <?php else: ?>
                                        <div class="attachment-file">
                                            <svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M16.5 6.5v9a4.5 4.5 0 0 1-9 0v-10a3 3 0 1 1 6 0v9a1.5 1.5 0 1 1-3 0V7h-1.5v7.5a3 3 0 1 0 6 0v-9a4.5 4.5 0 0 0-9 0v10a6 6 0 0 0 12 0v-10z"/></svg>
                                            <a href="<?= h($attachment['path']); ?>" target="_blank"><?= h($attachment['original_name']); ?></a>
                                        </div>
                                    <?php endif; ?>
                                    <small class="muted-text"><?= h($attachment['mime_type']); ?> · <?= $sizeLabel; ?></small>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <div class="chat-actions">
                        <form method="post" action="actions/like_chat.php" class="like-form">
                            <input type="hidden" name="csrf" value="<?= csrf_token(); ?>">
                            <input type="hidden" name="message_id" value="<?= $message['id']; ?>">
                            <button class="heart-button<?= $message['liked_by_me'] ? ' is-active' : ''; ?>" type="submit" aria-label="<?= $message['liked_by_me'] ? 'Unlike' : 'Like'; ?>">
                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21s-6.2-3.8-9-8.1C1.1 11.2 1 9.2 1.9 7.7 3 5.8 5 5 6.8 5c1.9 0 3.3 1.2 4.2 2.4.9-1.2 2.3-2.4 4.2-2.4C17 5 19 5.8 20.1 7.7c.9 1.5.8 3.5-.1 5.2C18.2 17.2 12 21 12 21z" /></svg>
                            </button>
                            <span class="like-count"><?= (int)$message['likes_count']; ?></span>
                        </form>
                        <button class="reply-toggle" type="button" data-target="reply-form-<?= $message['id']; ?>">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M10 9V5l-7 7l7 7v-4h4a7 7 0 0 0 7-7a1 1 0 0 0-1-1h-2a5 5 0 0 1-5 5z"/></svg>
                            Reply
                        </button>
                    </div>

                    <div class="chat-replies">
                        <?php foreach ($repliesByMessage[$message['id']] ?? [] as $reply): ?>
                            <div class="chat-reply">
                                <img src="<?= avatar_url(['avatar_path' => $reply['avatar_path'] ?? null]); ?>" alt="<?= h($reply['name']); ?>">
                                <div>
                                    <div class="chat-meta-line">
                                        <strong><?= h($reply['name']); ?></strong>
                                        <span class="muted-text"><?= date('m/d H:i', strtotime($reply['created_at'])); ?></span>
                                    </div>
                                    <p class="chat-text"><?= nl2br(h($reply['reply_text'])); ?></p>
                                    <?php if (!empty($attachmentsByReply[$reply['id']] ?? [])): ?>
                                        <div class="chat-attachments">
                                            <?php foreach ($attachmentsByReply[$reply['id']] as $attachment): ?>
                                                <?php
                                                $isImage = strpos($attachment['mime_type'], 'image/') === 0;
                                                $sizeLabel = round(((int)$attachment['size_bytes']) / 1024, 1) . ' KB';
                                                ?>
                                                <div class="chat-attachment">
                                                    <?php if ($isImage): ?>
                                                        <a href="<?= h($attachment['path']); ?>" target="_blank">
                                                            <img src="<?= h($attachment['path']); ?>" alt="<?= h($attachment['original_name']); ?>">
                                                        </a>
                                                    <?php else: ?>
                                                        <div class="attachment-file">
                                                            <svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M16.5 6.5v9a4.5 4.5 0 0 1-9 0v-10a3 3 0 1 1 6 0v9a1.5 1.5 0 1 1-3 0V7h-1.5v7.5a3 3 0 1 0 6 0v-9a4.5 4.5 0 0 0-9 0v10a6 6 0 0 0 12 0v-10z"/></svg>
                                                            <a href="<?= h($attachment['path']); ?>" target="_blank"><?= h($attachment['original_name']); ?></a>
                                                        </div>
                                                    <?php endif; ?>
                                                    <small class="muted-text"><?= h($attachment['mime_type']); ?> · <?= $sizeLabel; ?></small>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <form method="post" action="actions/reply_chat.php" class="reply-form is-hidden" id="reply-form-<?= $message['id']; ?>" enctype="multipart/form-data">
                            <input type="hidden" name="csrf" value="<?= csrf_token(); ?>">
                            <input type="hidden" name="course_id" value="<?= $course['id']; ?>">
                            <input type="hidden" name="message_id" value="<?= $message['id']; ?>">
                            <div class="form-group">
                                <label class="sr-only" for="reply-text-<?= $message['id']; ?>">Reply</label>
                                <textarea id="reply-text-<?= $message['id']; ?>" name="reply_text" rows="2" required placeholder="Reply to <?= h($message['name']); ?>"></textarea>
                            </div>
                            <div class="form-group attach-control">
                                <label class="attach-label">
                                    <input class="sr-only attachment-input" type="file" name="attachment" accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.zip,.txt,.doc,.docx,.ppt,.pptx,.csv,.mp4">
                                    <span class="attach-icon" aria-hidden="true">
                                        <svg viewBox="0 0 24 24"><path fill="currentColor" d="M16.5 6.5v9a4.5 4.5 0 0 1-9 0v-10a3 3 0 1 1 6 0v9a1.5 1.5 0 1 1-3 0V7h-1.5v7.5a3 3 0 1 0 6 0v-9a4.5 4.5 0 0 0-9 0v10a6 6 0 0 0 12 0v-10z"/></svg>
                                    </span>
                                    <span class="attach-text">Add attachment</span>
                                </label>
                                <small class="muted-text">Max 8MB · Image/Doc/Zip/MP4</small>
                            </div>
                            <button class="btn-small btn" type="submit">ပြန်ကြားမည်</button>
                        </form>
                    </div>
                </article>
            <?php endforeach; ?>
            <?php if (!$messages): ?>
                <p>စတင်မက်ဆေ့ခ်ျ တင်ပေးပါ။</p>
            <?php endif; ?>
        </div>

        <div class="chat-compose">
            <h3>သင့်မက်ဆေ့ခ်ျ</h3>
            <form method="post" action="actions/post_chat.php" enctype="multipart/form-data">
                <input type="hidden" name="csrf" value="<?= csrf_token(); ?>">
                <input type="hidden" name="course_id" value="<?= $course['id']; ?>">
                <div class="form-group">
                    <label class="sr-only" for="message">မက်ဆေ့ခ်ျ</label>
                    <textarea id="message" name="message" rows="3" required placeholder="Share an update, question or link"></textarea>
                </div>
                <div class="compose-actions">
                    <label class="attach-label">
                        <input class="sr-only attachment-input" type="file" name="attachment" accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.zip,.txt,.doc,.docx,.ppt,.pptx,.csv,.mp4">
                        <span class="attach-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24"><path fill="currentColor" d="M16.5 6.5v9a4.5 4.5 0 0 1-9 0v-10a3 3 0 1 1 6 0v9a1.5 1.5 0 1 1-3 0V7h-1.5v7.5a3 3 0 1 0 6 0v-9a4.5 4.5 0 0 0-9 0v10a6 6 0 0 0 12 0v-10z"/></svg>
                        </span>
                        <span class="attach-text">Add attachment</span>
                    </label>
                    <small class="muted-text">Max 8MB · Image/Doc/Zip/MP4</small>
                    <button class="btn" type="submit">ပိုစ့်မည်</button>
                </div>
            </form>
        </div>
    </div>
</section>

<?php require __DIR__ . '/partials/footer.php'; ?>
