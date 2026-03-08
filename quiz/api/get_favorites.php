<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../config/db.php';

try {

    $pdo = get_db();

    // セッションユーザーID
    $user_id = $_SESSION['user_id'] ?? null;

    if (!$user_id) {
        echo json_encode([
            'success' => false,
            'error' => 'ログインしていません'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // フィルタ
    $prefecture_id = isset($_GET['prefecture_id']) ? intval($_GET['prefecture_id']) : 0;
    $genre_id = isset($_GET['genre_id']) ? intval($_GET['genre_id']) : 0;

    // 基本SQL
    $sql = "
        SELECT DISTINCT 
            f.quiz_id,
            q.question,
            q.explanation,
            q.prefecture_id
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

    // 正解取得
    $stmt_correct = $pdo->prepare("
        SELECT choice_text
        FROM quiz_choices
        WHERE quiz_id = ?
        AND is_correct = 1
        LIMIT 1
    ");

    foreach ($favorites as &$fav) {

        $stmt_correct->execute([$fav['quiz_id']]);
        $correct = $stmt_correct->fetch();

        $fav['correct_answer'] = $correct['choice_text'] ?? null;
    }

    echo json_encode([
        'success' => true,
        'favorites' => $favorites
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}