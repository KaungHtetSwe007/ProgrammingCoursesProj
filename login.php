<?php
require __DIR__ . '/includes/functions.php';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf($_POST['csrf'] ?? '');

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        $errors[] = 'လော့ဂ်အင်မအောင်မြင်ပါ။ Email သို့မဟုတ် စကားဝှက်အမှားဖြစ်နိုင်ပါသည်။';
    } else {
        $_SESSION['user_id'] = $user['id'];
        log_activity($pdo, (int) $user['id'], 'Login', 'အကောင့်ဝင်ခြင်း', $user['role'] ?? null);
        set_flash('success', 'ပလက်ဖောင်းသို့ ကြိုဆိုပါတယ်။');
        redirect('dashboard.php');
    }
}

$pageTitle = 'လော့ဂ်အင်';
require __DIR__ . '/partials/header.php';
?>

<section class="section">
    <div class="box" style="max-width:420px;margin:0 auto;">
        <h2>လော့ဂ်အင်ဝင်ပါ</h2>
        <?php if ($errors): ?>
            <div class="flash flash-error"><?= h(implode(' / ', $errors)); ?></div>
        <?php endif; ?>
        <form method="post">
            <input type="hidden" name="csrf" value="<?= csrf_token(); ?>">
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" required value="<?= h($_POST['email'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label>စကားဝှက်</label>
                <input type="password" name="password" required>
            </div>
            <button class="btn" type="submit">ဝင်ရောက်မည်</button>
            <p>အကောင့်မရှိသေးပါက <a href="register.php">အသစ်ဖွင့်ပါ</a></p>
        </form>
    </div>
</section>

<?php require __DIR__ . '/partials/footer.php'; ?>
