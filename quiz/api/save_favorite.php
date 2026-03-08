<?php
session_start();

header('Content-Type: application/json; charset=utf-8');

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../config/db.php';

try {

    $pdo = get_db();

    // -----------------------
    // ログイン確認
    // -----------------------

    $user_id = $_SESSION['user_id'] ?? null;

    if (!$user_id) {
        echo json_encode([
            "success" => false,
            "error" => "ログインしていません"
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // -----------------------
    // POST取得
    // -----------------------

    $quiz_id = isset($_POST['quiz_id']) ? intval($_POST['quiz_id']) : 0;

    if ($quiz_id <= 0) {
        echo json_encode([
            "success" => false,
            "error" => "無効なクイズID"
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // -----------------------
    // 重複チェック
    // -----------------------

    $stmt = $pdo->prepare("
        SELECT 1
        FROM favorite
        WHERE user_id = ?
        AND quiz_id = ?
        LIMIT 1
    ");

    $stmt->execute([$user_id, $quiz_id]);

    if ($stmt->fetch()) {

        echo json_encode([
            "success" => true,
            "message" => "すでに保存済み"
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }

    // -----------------------
    // お気に入り登録
    // -----------------------

    $stmt = $pdo->prepare("
        INSERT INTO favorite (user_id, quiz_id)
        VALUES (?, ?)
    ");

    $stmt->execute([$user_id, $quiz_id]);

    echo json_encode([
        "success" => true,
        "message" => "お気に入りに登録しました"
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "error" => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}