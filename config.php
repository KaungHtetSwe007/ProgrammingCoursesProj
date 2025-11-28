<?php
declare(strict_types=1);

date_default_timezone_set('Asia/Yangon');

$dbHost = getenv('DB_HOST') ?: 'localhost';
$dbName = getenv('DB_NAME') ?: 'programming_courses';
$dbUser = getenv('DB_USER') ?: 'root';
$dbPass = getenv('DB_PASS') ?: '';

$dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $dbHost, $dbName);
$pdoOptions = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $dbUser, $dbPass, $pdoOptions);
} catch (PDOException $exception) {
    exit('ဒေတာဘေ့စ်ချိတ်ဆက်ရာတွင် ပြဿနာရှိနေပါသည်။ ဆာဗာကို စစ်ဆေးပါ။');
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

const APP_TITLE = 'ပရိုဂရမ်မင်း သင်တန်းဇုံ';
const APP_BRAND_COLOR = '#6C4EFE';
$basePath = getenv('APP_BASE_PATH') ?: '';
define('APP_BASE_PATH', rtrim($basePath, '/'));
