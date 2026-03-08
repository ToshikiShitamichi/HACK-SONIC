<?php
session_start();
require_once __DIR__ . '/../config/db.php';

// ランダムなゲストユーザー情報を生成
$suffix   = bin2hex(random_bytes(6));          // 12文字のランダム16進数
$name     = 'guest_' . $suffix;
$email    = 'guest_' . $suffix . '@guest.local';
$password = bin2hex(random_bytes(16));         // パスワードは内部のみで使用
$hash     = password_hash($password, PASSWORD_DEFAULT);

$pdo = get_db();

// ゲストユーザーを作成
$stmt = $pdo->prepare('INSERT INTO users (name, email, password) VALUES (?, ?, ?)');
$stmt->execute([$name, $email, $hash]);
$user_id = (int) $pdo->lastInsertId();

// user_levels の初期データを作成
$stmt = $pdo->prepare(
    'INSERT INTO user_levels (user_id, total_xp, level, title) VALUES (?, 0, 1, ?)'
);
$stmt->execute([$user_id, '新米旅人']);

// セッション固定化攻撃対策
session_regenerate_id(true);
$_SESSION['user_id'] = $user_id;

header('Location: ../index.php');
exit;
