<?php

header('Content-Type: application/json');

$quiz_id = $_GET['quiz_id'] ?? null;

if(!$quiz_id){
    echo json_encode(["error"=>"quiz_id required"]);
    exit;
}

$pdo = new PDO(
    "mysql:host=localhost;dbname=travel_quiz;charset=utf8",
    "root",
    ""
);

$sql = "
SELECT t.name
FROM quiz_tags qt
JOIN tags t ON qt.tag_id = t.id
WHERE qt.quiz_id = ?
";

$stmt = $pdo->prepare($sql);
$stmt->execute([$quiz_id]);

$tags = $stmt->fetchAll(PDO::FETCH_COLUMN);

echo json_encode([
    "tags"=>$tags
]);