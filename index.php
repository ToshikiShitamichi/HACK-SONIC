<?php
require_once __DIR__ . '/auth/auth_check.php';

$user_id = $_SESSION['user_id'] ?? null;
$pdo = get_db();

// フィルター
$filter = $_GET['filter'] ?? '';
$prefecture = $_GET['destination'] ?? ''; // フォームのnameが destination なのでこれで受ける
$category   = $_GET['category'] ?? '';

// SQL文
$sql = "
SELECT 
    p.id,
    p.user_id,
    p.message,
    p.image_url,
    p.created_at,
    p.plan_id,
    p.prefecture,
    p.city,
    p.destination,
    p.category,
    u.name AS username,
    COUNT(l.id) AS like_count,
    COALESCE(SUM(CASE WHEN l.user_id = :user_id THEN 1 ELSE 0 END), 0) AS liked
FROM post_table p
JOIN users u ON p.user_id = u.id
LEFT JOIN like_table l ON p.id = l.post_id
WHERE 1
";

if ($prefecture !== '') {
    $sql .= " AND p.prefecture = :prefecture ";
}

if ($category !== '') {
    $sql .= " AND p.category = :category ";
}

$sql .= "
GROUP BY 
    p.id,
    p.user_id,
    p.message,
    p.image_url,
    p.created_at,
    p.plan_id,
    p.prefecture,
    p.city,
    p.destination,
    p.category,
    u.name
";

if ($filter === 'liked') {
    $sql .= " HAVING COALESCE(SUM(CASE WHEN l.user_id = :having_user_id THEN 1 ELSE 0 END), 0) > 0 ";
}

$sql .= " ORDER BY p.created_at DESC ";

$stmt = $pdo->prepare($sql);
$stmt->bindValue(':user_id', (int)$user_id, PDO::PARAM_INT);

if ($filter === 'liked') {
    $stmt->bindValue(':having_user_id', (int)$user_id, PDO::PARAM_INT);
}
if ($prefecture !== '') {
    $stmt->bindValue(':prefecture', $prefecture, PDO::PARAM_STR);
}
if ($category !== '') {
    $stmt->bindValue(':category', $category, PDO::PARAM_STR);
}

try {
    $stmt->execute();
} catch (PDOException $e) {
    echo '<pre>';
    print_r(["sql error" => $e->getMessage()]);
    echo '</pre>';
    exit();
}

$result = $stmt->fetchAll(PDO::FETCH_ASSOC);
$output = "";

// 投稿カード作成
foreach ($result as $record) {
    $deleteBtn = '';
    if ((int)$record['user_id'] === (int)$user_id) {
        $deleteBtn = "<a class='deleteBtn' href='./controller/delete.php?id={$record['id']}' onclick='return confirm(\"本当に削除しますか？\")'>削除</a>";
    }

    $likedClass = !empty($record['liked']) ? 'liked' : '';

    $imageHtml = '';
    if (!empty($record['image_url'])) {
        $imageHtml = '<img src="' . htmlspecialchars($record['image_url'], ENT_QUOTES, 'UTF-8') . '" alt="">';
    }

    $output .= '
<div class="postCard">
    <div class="postHeader">
        <span class="user">
            <i class="fa-solid fa-user"></i>
            <span class="name">' . htmlspecialchars($record['username'], ENT_QUOTES, 'UTF-8') . '</span>
        </span>
        <span class="time">投稿: ' . htmlspecialchars($record['created_at'], ENT_QUOTES, 'UTF-8') . '</span>
        <a class="likeBtn ' . $likedClass . '" 
           href="./R/controller/like_create.php?user_id=' . (int)$user_id . '&post_id=' . (int)$record['id'] . '">
           ♥<span class="likeCount">' . (int)$record['like_count'] . '</span>
        </a>
        ' . $deleteBtn . '
    </div>
    <div class="postBody">
        <div class="tags">
            <span class="tag">📍' . htmlspecialchars($record['prefecture'] ?? '', ENT_QUOTES, 'UTF-8') . '</span>
            <span class="tag">🏙' . htmlspecialchars($record['city'] ?? '', ENT_QUOTES, 'UTF-8') . '</span>
            <span class="tag">📌' . htmlspecialchars($record['destination'] ?? '', ENT_QUOTES, 'UTF-8') . '</span>
            <span class="tag">🏷' . htmlspecialchars($record['category'] ?? '', ENT_QUOTES, 'UTF-8') . '</span>
        </div>
        <p>' . nl2br(htmlspecialchars($record['message'], ENT_QUOTES, 'UTF-8')) . '</p>
        ' . $imageHtml . '
    </div>
</div>';
}
?>

<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="./assets/css/common.css">
    <link rel="stylesheet" href="./index.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <title>ホーム | 旅 so sweet</title>
</head>

<body>

    <div class="page-frame-top"></div>

    <header>
        <div class="header-brand">
            <span class="brand-kanji">旅</span>
            <span class="brand-roman">so sweet</span>
        </div>
        <nav class="header-nav">
            <a href="./quests/quest_list.php">旅クエスト</a>
            <a href="./quests/my_quests.php">マイクエスト</a>
            <a href="./auth/logout.php">ログアウト</a>
        </nav>
    </header>

    <main>
        <div id="quizArea">
            <?php include('./quiz/quiz-top-component.php'); ?>
        </div>

        <div id="linkArea">
            <a href="./input-plan.html">旅プラン生成</a>
            <a href="./quests/quest_list.php">旅クエスト</a>
            <a href="./R/views/post.php">投稿</a>
        </div>

        <div id="timeLine">
            <h2>R</h2>
            <div class="filterBar">
                <a href="index.php?filter=">全件表示</a>
                <a href="index.php?filter=liked">❤️ いいね</a>
            </div>

            <div id="filterArea" class="hidden">
                <form method="GET">
                    <select id="region" name="region">
                        <option value="">🗾 地方</option>
                        <option value="hokkaido">北海道</option>
                        <option value="tohoku">東北</option>
                        <option value="kanto">関東</option>
                        <option value="chubu">中部</option>
                        <option value="kansai">関西</option>
                        <option value="chugoku">中国</option>
                        <option value="shikoku">四国</option>
                        <option value="kyushu">九州</option>
                    </select>

                    <select name="destination" id="prefecture">
                        <option value="">🔍 都道府県</option>
                    </select>

                    <select name="category">
                        <option value="">🏷️ カテゴリー</option>
                        <option value="観光">観光</option>
                        <option value="グルメ">グルメ</option>
                        <option value="温泉">温泉</option>
                        <option value="自然">自然</option>
                    </select>

                    <button type="submit">検索</button>
                </form>
            </div>

            <?= $output ?>
        </div>
    </main>

    <script>
        const region = document.getElementById("region");
        const pref = document.getElementById("prefecture");

        const prefectures = {
            hokkaido: ["北海道"],
            tohoku: ["青森", "岩手", "宮城", "秋田", "山形", "福島"],
            kanto: ["東京", "神奈川", "千葉", "埼玉", "茨城", "栃木", "群馬"],
            chubu: ["新潟", "長野", "山梨", "静岡", "愛知", "岐阜", "富山", "石川", "福井"],
            kansai: ["大阪", "京都", "兵庫", "奈良", "滋賀", "和歌山"],
            chugoku: ["広島", "岡山", "山口", "鳥取", "島根"],
            shikoku: ["香川", "徳島", "愛媛", "高知"],
            kyushu: ["福岡", "佐賀", "長崎", "熊本", "大分", "宮崎", "鹿児島", "沖縄"]
        };

        region.onchange = () => {
            const list = prefectures[region.value];
            pref.innerHTML = "<option value=''>都道府県</option>";
            if (!list) return;
            list.forEach(p => {
                pref.innerHTML += `<option value="${p}">${p}</option>`;
            });
        };
    </script>

    <footer class="page-footer">Travel Quest · 旅 so sweet</footer>

</body>

</html>