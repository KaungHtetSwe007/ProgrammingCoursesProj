<?php
require __DIR__ . '/includes/functions.php';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf($_POST['csrf'] ?? '');

    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($name === '') {
        $errors[] = 'အမည် ထည့်ရန်လိုအပ်ပါသည်။';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'တရားဝင် Email လိုအပ်ပါသည်။';
    }

    if (strlen($password) < 6) {
        $errors[] = 'စကားဝှက်သည် အနည်းဆုံး စာလုံး ၆ လုံး ရှိရပါမည်။';
    }

    if (!$errors) {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$email]);

        if ($stmt->fetch()) {
            $errors[] = 'ဤ Email ဖြင့်စာရင်းသွင်းပြီးသား ဖြစ်နေပါသည်။';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $insert = $pdo->prepare('INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)');
            $insert->execute([$name, $email, $hash, 'student']);
            set_flash('success', 'အကောင့်ဖွင့်ပြီးပါပြီ။ ယခုလော့ဂ်အင် ဝင်နိုင်ပါပြီ။');
            redirect('login.php');
        }
    }
}

$pageTitle = 'စာရင်းသွင်း';
require __DIR__ . '/partials/header.php';
?>

<section class="section">
    <div class="box" style="max-width:480px;margin:0 auto;">
        <h2>အသုံးပြုသူစာရင်းသွင်းခြင်း</h2>
        <?php if ($errors): ?>
            <div class="flash flash-error">
                <?= h(implode(' / ', $errors)); ?>
            </div>
        <?php endif; ?>
        <form method="post">
            <input type="hidden" name="csrf" value="<?= csrf_token(); ?>">
            <div class="form-group">
                <label>အမည်</label>
                <input type="text" name="name" required value="<?= h($_POST['name'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" required value="<?= h($_POST['email'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label>စကားဝှက်</label>
                <input type="password" name="password" required>
            </div>
            <button class="btn" type="submit">စာရင်းသွင်းမည်</button>
            <p>ရှိပြီးသား အကောင့်? <a href="login.php">လော့ဂ်အင်ဝင်</a></p>
        </form>
    </div>
</section>

<?php require __DIR__ . '/partials/footer.php'; ?>
