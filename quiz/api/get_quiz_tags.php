<?php
header('Content-Type: application/json; charset=utf-8');

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../config/db.php';

try {

    $quiz_id = isset($_GET['quiz_id']) ? intval($_GET['quiz_id']) : 0;

    if ($quiz_id <= 0) {
        echo json_encode([
            "success" => false,
            "error" => "quiz_id required"
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $pdo = get_db();

    $sql = "
        SELECT t.name
        FROM quiz_tags qt
        JOIN tags t ON qt.tag_id = t.id
        WHERE qt.quiz_id = ?
        ORDER BY t.id ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$quiz_id]);

    $tags = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo json_encode([
        "success" => true,
        "tags" => $tags
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "error" => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}