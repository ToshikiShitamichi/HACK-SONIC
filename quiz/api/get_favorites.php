<?php
session_start();
header('Content-Type: application/json');

ini_set('display_errors', 1);
error_reporting(E_ALL);

// 暫定ユーザーID
$_SESSION['user_id'] = 1;  
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    echo json_encode(['error' => 'ユーザーIDが見つかりません']);
    exit;
}

// DB接続
$host = "localhost";
$db   = "travel_quiz";
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

// フィルタ
$prefecture_id = isset($_GET['prefecture_id']) ? intval($_GET['prefecture_id']) : 0;
$genre_id = isset($_GET['genre_id']) ? intval($_GET['genre_id']) : 0;

try {
    // 基本 SQL
    $sql = "
        SELECT DISTINCT f.quiz_id, q.question, q.explanation, q.prefecture_id
        FROM favorite f
        JOIN quiz q ON f.quiz_id = q.id
        LEFT JOIN quiz_tags qt ON q.id = qt.quiz_id
        LEFT JOIN tags t ON qt.tag_id = t.id
        LEFT JOIN categories c ON t.category_id = c.id
        WHERE f.user_id = ?
    ";
    $params = [$user_id];

    // 都道府県フィルタ
    if ($prefecture_id > 0) {
        $sql .= " AND q.prefecture_id = ?";
        $params[] = $prefecture_id;
    }

    // ジャンルフィルタ
    if ($genre_id > 0) {
        $sql .= " AND c.id = ?";
        $params[] = $genre_id;
    }

    $sql .= " ORDER BY f.created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $favorites = $stmt->fetchAll();

    // 各クイズの正解を取得
    foreach ($favorites as &$fav) {
        $stmt2 = $pdo->prepare("SELECT choice_text FROM quiz_choices WHERE quiz_id = ? AND is_correct = 1");
        $stmt2->execute([$fav['quiz_id']]);
        $correct = $stmt2->fetch();
        $fav['correct_answer'] = $correct ? $correct['choice_text'] : null;
    }

    echo json_encode(['success' => true, 'favorites' => $favorites], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    echo json_encode(['error' => 'SQLエラー: ' . $e->getMessage()]);
    exit;
}