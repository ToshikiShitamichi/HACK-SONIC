<?php
session_start();
header('Content-Type: application/json');

// 暫定：ユーザーID固定
$_SESSION['user_id'] = 1;  

// DB接続設定
$host = "localhost";
$db   = "travel_quiz"; // DB名
$user = "root";
$pass = "";
$charset = "utf8mb4";

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    echo json_encode(['error' => 'DB接続失敗: ' . $e->getMessage()]);
    exit;
}

// ログインユーザー確認（暫定でuser_id=1がセット済み）
$user_id = $_SESSION['user_id'];

// POSTデータ取得
$quiz_id = isset($_POST['quiz_id']) ? intval($_POST['quiz_id']) : 0;
if ($quiz_id <= 0) {
    echo json_encode(['error' => '無効なクイズID']);
    exit;
}

// 重複チェック
$stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM favorite WHERE user_id = ? AND quiz_id = ?");
$stmt->execute([$user_id, $quiz_id]);
$row = $stmt->fetch();

if ($row['cnt'] > 0) {
    echo json_encode(['success' => true, 'message' => 'すでに保存済み']);
    exit;
}

// お気に入り登録
$stmt = $pdo->prepare("INSERT INTO favorite (user_id, quiz_id) VALUES (?, ?)");
if ($stmt->execute([$user_id, $quiz_id])) {
    echo json_encode(['success' => true, 'message' => 'お気に入りに登録しました']);
} else {
    echo json_encode(['error' => '保存に失敗しました']);
}