<?php
// import_quiz.php
// quiz_data.json を読み込み、DBに一括挿入するスクリプト

$dsn = 'mysql:host=localhost;dbname=travel_quiz;charset=utf8mb4';
$user = 'root';
$pass = ''; // XAMPPの場合は空か、設定に応じて変更

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
} catch (PDOException $e) {
    die("DB接続失敗: " . $e->getMessage());
}

// JSON読み込み
$jsonFile = 'quiz_data.json';
if (!file_exists($jsonFile)) {
    die("quiz_data.json が見つかりません");
}

// true を指定して連想配列として取得
$quizData = json_decode(file_get_contents($jsonFile), true);
if (!$quizData) {
    die("JSON読み込み失敗");
}

// タグキャッシュ（name+category => id）
$tagCache = [];

foreach ($quizData as $quiz) {
    // region_id取得
    $stmt = $pdo->prepare("SELECT id FROM regions WHERE name = ?");
    $stmt->execute([$quiz['region']]);
    $region_id = $stmt->fetchColumn();
    if (!$region_id) die("region が見つかりません: " . $quiz['region']);

    // prefecture_id取得
    $stmt = $pdo->prepare("SELECT id FROM prefectures WHERE name = ?");
    $stmt->execute([$quiz['prefecture']]);
    $prefecture_id = $stmt->fetchColumn();
    if (!$prefecture_id) die("prefecture が見つかりません: " . $quiz['prefecture']);

    // quiz挿入
    $stmt = $pdo->prepare("INSERT INTO quiz (question, explanation, difficulty, region_id, prefecture_id) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([
        $quiz['question'],
        $quiz['explanation'],
        $quiz['difficulty'],
        $region_id,
        $prefecture_id
    ]);
    $quiz_id = $pdo->lastInsertId();

    // quiz_choices挿入
    foreach ($quiz['choices'] as $choice) {
        $stmt = $pdo->prepare("INSERT INTO quiz_choices (quiz_id, choice_text, is_correct) VALUES (?, ?, ?)");
        $stmt->execute([
            $quiz_id,
            $choice['choice_text'],
            $choice['is_correct']
        ]);
    }

    // tags挿入
    foreach ($quiz['tags'] as $tag) {
        $tagName = $tag['name'];
        $categoryName = $tag['category'];

        // キャッシュキーを「name|category」にする
        $cacheKey = $tagName . '|' . $categoryName;

        if (isset($tagCache[$cacheKey])) {
            $tag_id = $tagCache[$cacheKey];
        } else {
            // category_id取得
            $stmt = $pdo->prepare("SELECT id FROM categories WHERE name = ?");
            $stmt->execute([$categoryName]);
            $category_id = $stmt->fetchColumn();
            if (!$category_id) die("category が見つかりません: " . $categoryName);

            // タグ存在チェック
            $stmt = $pdo->prepare("SELECT id FROM tags WHERE name = ? AND category_id = ?");
            $stmt->execute([$tagName, $category_id]);
            $tag_id = $stmt->fetchColumn();

            if (!$tag_id) {
                // 新規タグ追加
                $stmt = $pdo->prepare("INSERT INTO tags (category_id, name) VALUES (?, ?)");
                $stmt->execute([$category_id, $tagName]);
                $tag_id = $pdo->lastInsertId();
            }

            // キャッシュに保存
            $tagCache[$cacheKey] = $tag_id;
        }

        // quiz_tagsに紐付け
        $stmt = $pdo->prepare("INSERT INTO quiz_tags (quiz_id, tag_id) VALUES (?, ?)");
        $stmt->execute([$quiz_id, $tag_id]);
    }
}

echo "インポート完了！ " . count($quizData) . " 件の問題を追加しました。\n";