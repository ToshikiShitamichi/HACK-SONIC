<?php
header('Content-Type: application/json; charset=utf-8');

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../config/db.php';

try {

    $pdo = get_db();

    if (!isset($_GET['name']) || $_GET['name'] === '') {
        echo json_encode(["image" => null]);
        exit;
    }

    $name = trim($_GET['name']);

    //
    // ① DBキャッシュ確認
    //

    $stmt = $pdo->prepare("
        SELECT image_url
        FROM place_images
        WHERE place_name = ?
        LIMIT 1
    ");

    $stmt->execute([$name]);

    $cached = $stmt->fetch();

    if ($cached) {

        echo json_encode([
            "image" => $cached["image_url"],
            "cached" => true
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }

    //
    // ② Wikipedia API取得
    //

    $encoded = urlencode($name);

    $url = "https://ja.wikipedia.org/api/rest_v1/page/summary/" . $encoded;

    $options = [
        "http" => [
            "method" => "GET",
            "header" => "User-Agent: TravelQuizApp/1.0\r\n",
            "timeout" => 5
        ]
    ];

    $context = stream_context_create($options);

    $response = @file_get_contents($url, false, $context);

    if ($response === false) {
        echo json_encode(["image" => null]);
        exit;
    }

    $data = json_decode($response, true);

    $image = $data['thumbnail']['source'] ?? null;

    //
    // ③ DB保存（キャッシュ）
    //

    if ($image) {

        $stmt = $pdo->prepare("
            INSERT INTO place_images (place_name, image_url)
            VALUES (?, ?)
        ");

        $stmt->execute([$name, $image]);
    }

    echo json_encode([
        "image" => $image,
        "cached" => false
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {

    http_response_code(500);

    echo json_encode([
        "error" => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}