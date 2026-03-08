<?php

header('Content-Type: application/json; charset=utf-8');

if (!isset($_GET['name'])) {
    echo json_encode(["image"=>null]);
    exit;
}

$name = $_GET['name'];

$pdo = new PDO(
    "mysql:host=localhost;dbname=travel_quiz;charset=utf8",
    "root",
    ""
);

//
// ① DBキャッシュ確認
//

$stmt = $pdo->prepare("SELECT image_url FROM place_images WHERE place_name = ?");
$stmt->execute([$name]);
$cached = $stmt->fetch(PDO::FETCH_ASSOC);

if ($cached) {

    echo json_encode([
        "image"=>$cached["image_url"],
        "cached"=>true
    ]);

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
        "header" => "User-Agent: TravelQuizApp/1.0\r\n"
    ]
];

$context = stream_context_create($options);

$response = @file_get_contents($url, false, $context);

if ($response === FALSE) {
    echo json_encode(["image"=>null]);
    exit;
}

$data = json_decode($response, true);

$image = $data['thumbnail']['source'] ?? null;

//
// ③ DB保存
//

if ($image) {

    $stmt = $pdo->prepare("
        INSERT INTO place_images (place_name,image_url)
        VALUES (?,?)
    ");

    $stmt->execute([$name,$image]);

}

echo json_encode([
    "image"=>$image,
    "cached"=>false
]);