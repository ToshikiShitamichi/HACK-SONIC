<?php
header('Content-Type: application/json');

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

// 都道府県取得
$pref_stmt = $pdo->query("SELECT id, name FROM prefectures ORDER BY id ASC");
$prefectures = $pref_stmt->fetchAll();

// ジャンル取得
$genre_stmt = $pdo->query("SELECT id, name FROM categories ORDER BY id ASC");
$genres = $genre_stmt->fetchAll();

echo json_encode([
    'success' => true,
    'prefectures' => $prefectures,
    'genres' => $genres
]);