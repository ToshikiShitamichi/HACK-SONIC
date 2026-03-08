<?php
session_start();
require_once __DIR__ . '/../config/db.php';

// POST以外はloginへ
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit;
}

$email    = trim($_POST['email']    ?? '');
$password =      $_POST['password'] ?? '';

// 入力が空なら即失敗
if ($email === '' || $password === '') {
    header('Location: login.php?error=1');
    exit;
}

$pdo  = get_db();
$stmt = $pdo->prepare('SELECT id, password FROM users WHERE email = ? LIMIT 1');
$stmt->execute([$email]);
$user = $stmt->fetch();

if ($user && password_verify($password, $user['password'])) {
    // 認証成功：セッション固定化攻撃対策
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

// 認証失敗
header('Location: login.php?error=1');
exit;
