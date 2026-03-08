<?php
require_once __DIR__ . '/auth/auth_check.php';
// include('./config/db.php');
// session_start();
$user_id = $_SESSION['user_id'] ?? null;
$pdo = get_db();

// フィルター
$filter = $_GET['filter'] ?? '';
$prefecture = $_GET['destination'] ?? '';
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
    u.name AS username,
    pl.prefecture,
    pl.city,
    pl.destination,
    pl.category,
    COUNT(l.id) AS like_count,
    SUM(l.user_id = :user_id) AS liked
FROM post_table p
JOIN user_table u ON p.user_id = u.id
LEFT JOIN plan_table pl ON p.plan_id = pl.id
LEFT JOIN like_table l ON p.id = l.post_id
WHERE 1
";

// 都道府県・カテゴリの絞り込み
if ($prefecture !== '') {
    $sql .= " AND pl.prefecture = :prefecture ";
}
if ($category !== '') {
    $sql .= " AND pl.category = :category ";
}

$sql .= " GROUP BY p.id ";

// いいねフィルターは HAVING で指定
if ($filter === 'liked') {
    $sql .= " HAVING SUM(l.user_id = :user_id) > 0 ";
}

$sql .= " ORDER BY p.created_at DESC ";

$stmt = $pdo->prepare($sql);
$stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
if ($prefecture !== '') {
    $stmt->bindValue(':prefecture', $prefecture, PDO::PARAM_STR);
}
if ($category !== '') {
    $stmt->bindValue(':category', $category, PDO::PARAM_STR);
}

try {
    $stmt->execute();
} catch (PDOException $e) {
    echo json_encode(["sql error" => $e->getMessage()]);
    exit();
}

$result = $stmt->fetchAll(PDO::FETCH_ASSOC);
$output = "";

// 投稿カード作成
foreach ($result as $record) {
    $deleteBtn = '';
    if ($record['user_id'] == $user_id) {
        $deleteBtn = "<a class='deleteBtn' href='./controller/delete.php?id={$record['id']}' onclick='return confirm(\"本当に削除しますか？\")'>削除</a>";
    }

    $likedClass = $record['liked'] ? 'liked' : '';
    $plan_url = $record['plan_id'] ? "plan_detail.php?id={$record['plan_id']}" : "#";

    $output .= '
<div class="postCard">
    <div class="postHeader">
        <span class="user">
            <i class="fa-solid fa-user"></i>
            <span class="name">' . htmlspecialchars($record['username']) . '</span>
        </span>
        <span class="time">投稿: ' . $record['created_at'] . '</span>
        <a class="likeBtn ' . $likedClass . '" 
           href="./R/controller/like_create.php?user_id=' . $user_id . '&post_id=' . $record['id'] . '">
           ♥<span class="likeCount">' . $record['like_count'] . '</span>
        </a>
        ' . $deleteBtn . '
    </div>
    <div class="postBody">
        <div class="tags">
            <span class="tag">📍' . $record['prefecture'] . '</span>
            <span class="tag">🏙' . $record['city'] . '</span>
            <span class="tag">📌' . $record['destination'] . '</span>
            <span class="tag">🏷' . $record['category'] . '</span>
        </div>
        <p>' . htmlspecialchars($record['message']) . '</p>
        <img src="' . $record['image_url'] . '" alt="">
        <a href="' . $plan_url . '" class="planLink">プランを見る</a>
    </div>
</div>';
}
?>

<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="./index.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <title>R_Home</title>
</head>

<body>
    <header>
        <h1>"旅" so sweet</h1>
        <div class="headerLinks">
            <!-- <a href="./auth/register.php">ユーザー登録</a>
            <a href="./auth/login.php">ログイン</a> -->
            <a href="./auth/logout.php">ログアウト</a>
        </div>
    </header>
    <main>
        <div id="quizArea">
            <?= include('./quiz/quiz-top-component.php'); ?>
            <!-- <h2>クイズ</h2>
            <div>
                <p>日本一GEEKな場所はどこでしょう</p>
                <input type="text" placeholder="回答を入力">
            </div>
            <button>回答</button> -->
        </div>

        <div id="linkArea">
            <a href="./input-plan.html">旅プラン</a>
            <a href="./quests/my_quests.php">旅クエスト</a>
        </div>

        <div id="timeLine">
            <h2>R</h2>
            <div class="filterBar">
                <a href="index.php?filter=">全件表示</a>
                <a href="index.php?filter=liked">❤️ いいね</a>
                <button id="filterBtn">🔍 検索</button>
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
        const btn = document.getElementById("filterBtn");
        const area = document.getElementById("filterArea");
        btn.onclick = () => area.classList.toggle("hidden");

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
            list.forEach(p => pref.innerHTML += `<option value="${p}">${p}</option>`);
        };
    </script>
</body>

</html>