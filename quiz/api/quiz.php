<?php
require "db.php";
header("Content-Type: application/json");

// エラー表示
error_reporting(E_ALL);
ini_set('display_errors', 1);

// -----------------------
// GETパラメータ取得
// -----------------------
$region_id = $_GET["region_id"] ?? null;
$prefecture_id = $_GET["prefecture_id"] ?? null;
$genre_ids = $_GET["genre"] ?? []; // categories.id の配列
$exclude = $_GET["exclude"] ?? []; // 出題済みID

// 配列に統一
if (!is_array($genre_ids)) $genre_ids = $genre_ids ? [$genre_ids] : [];
if (!is_array($exclude)) $exclude = $exclude ? [$exclude] : [];


$params = [];
$where = [];

// -----------------------
// 地域・都道府県条件
// -----------------------
if ($region_id) {
    $where[] = "q.region_id = ?";
    $params[] = $region_id;
}

if ($prefecture_id) {
    $where[] = "q.prefecture_id = ?";
    $params[] = $prefecture_id;
}

// -----------------------
// ジャンル条件（tags 経由、ORで一致）
// -----------------------
if (!empty($genre_ids)) {
    $placeholders = implode(",", array_fill(0, count($genre_ids), "?"));
    $where[] = "q.id IN (
        SELECT DISTINCT qt.quiz_id
        FROM quiz_tags qt
        JOIN tags t ON qt.tag_id = t.id
        WHERE t.category_id IN ($placeholders)
    )";
    $params = array_merge($params, $genre_ids);
}

// -----------------------
// 出題済み除外
// -----------------------
if (!empty($exclude)) {
    $placeholders = implode(",", array_fill(0, count($exclude), "?"));
    $where[] = "q.id NOT IN ($placeholders)";
    $params = array_merge($params, $exclude);
}

// -----------------------
// WHERE文生成
// -----------------------
$whereSql = "";
if (!empty($where)) {
    $whereSql = "WHERE " . implode(" AND ", $where);
}

// -----------------------
// クイズID取得（ランダム）
// -----------------------
$sql = "SELECT q.id FROM quiz q $whereSql";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$allIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

if (empty($allIds)) {
    echo json_encode(["error" => "クイズなし"]);
    exit;
}

// ランダムに1件選択
$randId = $allIds[array_rand($allIds)];

// -----------------------
// クイズ本体取得
// -----------------------
$sql = "SELECT id, question, explanation, difficulty FROM quiz WHERE id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$randId]);
$quiz = $stmt->fetch(PDO::FETCH_ASSOC);

// -----------------------
// 選択肢取得
// -----------------------
$sql = "SELECT choice_text, is_correct FROM quiz_choices WHERE quiz_id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$quiz["id"]]);
$quiz["choices"] = $stmt->fetchAll(PDO::FETCH_ASSOC);

// JSON出力
echo json_encode($quiz, JSON_UNESCAPED_UNICODE);