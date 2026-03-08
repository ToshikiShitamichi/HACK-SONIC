<?php
header("Content-Type: application/json; charset=utf-8");

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../config/db.php';

try {

    $pdo = get_db();

    // -----------------------
    // GETパラメータ取得
    // -----------------------

    $region_id = isset($_GET["region_id"]) ? intval($_GET["region_id"]) : null;
    $prefecture_id = isset($_GET["prefecture_id"]) ? intval($_GET["prefecture_id"]) : null;
    $genre_ids = $_GET["genre"] ?? [];
    $exclude = $_GET["exclude"] ?? [];

    if (!is_array($genre_ids)) $genre_ids = $genre_ids ? [$genre_ids] : [];
    if (!is_array($exclude)) $exclude = $exclude ? [$exclude] : [];

    $params = [];
    $where = [];

    // -----------------------
    // 地域条件
    // -----------------------

    if ($region_id) {
        $where[] = "q.region_id = ?";
        $params[] = $region_id;
    }

    // -----------------------
    // 都道府県条件
    // -----------------------

    if ($prefecture_id) {
        $where[] = "q.prefecture_id = ?";
        $params[] = $prefecture_id;
    }

    // -----------------------
    // ジャンル条件
    // -----------------------

    if (!empty($genre_ids)) {

        $placeholders = implode(",", array_fill(0, count($genre_ids), "?"));

        $where[] = "q.id IN (
            SELECT DISTINCT qt.quiz_id
            FROM quiz_tags qt
            JOIN tags t ON qt.tag_id = t.id
            WHERE t.category_id IN ($placeholders)
        )";

        foreach ($genre_ids as $g) {
            $params[] = intval($g);
        }
    }

    // -----------------------
    // 出題済み除外
    // -----------------------

    if (!empty($exclude)) {

        $placeholders = implode(",", array_fill(0, count($exclude), "?"));

        $where[] = "q.id NOT IN ($placeholders)";

        foreach ($exclude as $e) {
            $params[] = intval($e);
        }
    }

    // -----------------------
    // WHERE生成
    // -----------------------

    $whereSql = "";

    if (!empty($where)) {
        $whereSql = "WHERE " . implode(" AND ", $where);
    }

    // -----------------------
    // クイズID取得
    // -----------------------

    $sql = "SELECT q.id FROM quiz q $whereSql";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $allIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (!$allIds) {
        echo json_encode([
            "success" => false,
            "error" => "クイズなし"
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // -----------------------
    // ランダム1問
    // -----------------------

    $randId = $allIds[array_rand($allIds)];

    // -----------------------
    // クイズ取得
    // -----------------------

    $stmt = $pdo->prepare("
        SELECT id, question, explanation, difficulty
        FROM quiz
        WHERE id = ?
    ");

    $stmt->execute([$randId]);

    $quiz = $stmt->fetch();

    if (!$quiz) {
        echo json_encode([
            "success" => false,
            "error" => "クイズ取得失敗"
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // -----------------------
    // 選択肢
    // -----------------------

    $stmt = $pdo->prepare("
        SELECT choice_text, is_correct
        FROM quiz_choices
        WHERE quiz_id = ?
    ");

    $stmt->execute([$quiz["id"]]);

    $choices = $stmt->fetchAll();

    // 選択肢シャッフル
    shuffle($choices);

    $quiz["choices"] = $choices;

    echo json_encode([
        "success" => true,
        "quiz" => $quiz
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "error" => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}