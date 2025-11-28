<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';

$pageTitle = $pageTitle ?? APP_TITLE;
$currentUser = current_user($pdo);
$flashSuccess = get_flash('success');
$flashError = get_flash('error');
?>
<!DOCTYPE html>
<html lang="my">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($pageTitle); ?> | <?= APP_TITLE; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Padauk:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css?v=2">
</head>
<body data-theme="dark">
<div class="app-gradient"></div>
<div class="app-grid"></div>
<div class="site-shell">
    <header class="site-header">
        <a class="brand" href="index.php">
            <span class="brand-badge">Code</span>
            <div class="brand-meta">
                <strong><?= APP_TITLE; ?></strong>
                <small>နည်းပညာသင်တန်းများ</small>
            </div>
        </a>
        <nav class="main-nav">
            <div class="nav-links">
                <a href="index.php"<?= basename($_SERVER['PHP_SELF']) === 'index.php' ? ' class="active"' : ''; ?>>မူလ</a>
                <a href="courses.php"<?= basename($_SERVER['PHP_SELF']) === 'courses.php' ? ' class="active"' : ''; ?>>သင်တန်းများ</a>
                <a href="books.php"<?= basename($_SERVER['PHP_SELF']) === 'books.php' ? ' class="active"' : ''; ?>>စာအုပ်များ</a>
                <a href="instructors.php"<?= basename($_SERVER['PHP_SELF']) === 'instructors.php' ? ' class="active"' : ''; ?>>ဆရာများ</a>
                <?php if ($currentUser): ?>
                    <a href="dashboard.php"<?= basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? ' class="active"' : ''; ?>>ကျွန်ုပ်၏ဘုတ်</a>
                    <?php if (is_admin($pdo)): ?>
                        <a href="admin.php"<?= basename($_SERVER['PHP_SELF']) === 'admin.php' ? ' class="active"' : ''; ?>>အက်ဒ်မင်</a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            <div class="nav-actions">
                <button class="theme-toggle" type="button" id="themeToggle" aria-label="Toggle theme">
                    <span class="sun">☀</span><span class="moon">🌙</span>
                </button>
                <?php if ($currentUser): ?>
                    <a class="avatar-chip" href="dashboard.php">
                        <img src="<?= avatar_url($currentUser); ?>" alt="<?= h($currentUser['name']); ?>">
                        <span><?= h($currentUser['name']); ?></span>
                    </a>
                    <a href="logout.php" class="btn-ghost btn-small">ထွက်မည်</a>
                <?php else: ?>
                    <a href="register.php" class="btn btn-small">စာရင်းသွင်း</a>
                    <a href="login.php" class="btn-ghost btn-small">လော့ဂ်အင်</a>
                <?php endif; ?>
            </div>
        </nav>
    </header>

    <?php if ($flashSuccess): ?>
        <div class="flash flash-success"><?= h($flashSuccess); ?></div>
    <?php endif; ?>

    <?php if ($flashError): ?>
        <div class="flash flash-error"><?= h($flashError); ?></div>
    <?php endif; ?>

    <main class="site-main">
