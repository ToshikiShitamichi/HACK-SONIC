<?php
header('Content-Type: application/json; charset=utf-8');

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../config/db.php';

try {

    $pdo = get_db();

    // 都道府県
    $stmt_pref = $pdo->query("
        SELECT id, name
        FROM prefectures
        ORDER BY id ASC
    ");

    $prefectures = $stmt_pref->fetchAll();

    // ジャンル
    $stmt_genre = $pdo->query("
        SELECT id, name
        FROM categories
        ORDER BY id ASC
    ");

    $genres = $stmt_genre->fetchAll();

    echo json_encode([
        'success' => true,
        'prefectures' => $prefectures,
        'genres' => $genres
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}