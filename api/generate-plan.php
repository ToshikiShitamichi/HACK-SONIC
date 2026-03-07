<?php
header('Content-Type: text/html; charset=UTF-8');

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
?>
    <!DOCTYPE html>
    <html lang="ja">

    <head>
        <meta charset="UTF-8">
        <title>エラー</title>
    </head>

    <body>
        <h1>エラー</h1>
        <p>このページにはPOSTでアクセスしてください。</p>
        <p><a href="../index.html">入力フォームに戻る</a></p>
    </body>

    </html>
<?php
    exit;
}

$departurePrefecture = $_POST['departurePrefecture'] ?? '';
$destinationPrefecture = $_POST['destinationPrefecture'] ?? '';
$departureDate = $_POST['departureDate'] ?? '';
$duration = $_POST['duration'] ?? '';
$people = $_POST['people'] ?? '';
$budgetMin = $_POST['budgetMin'] ?? '';
$budgetMax = $_POST['budgetMax'] ?? '';

function formatYen($value): string
{
    if ($value === '' || !is_numeric($value)) {
        return '';
    }

    return number_format((int)$value) . '円';
}
?>
<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>送信内容確認</title>
</head>

<body>
    <h1>送信内容確認</h1>

    <p>入力フォームから送信された内容を表示しています。</p>

    <dl>
        <dt>出発地（都道府県）</dt>
        <dd><?= h($departurePrefecture) ?></dd>

        <dt>旅行先（都道府県）</dt>
        <dd><?= h($destinationPrefecture) ?></dd>

        <dt>出発日</dt>
        <dd><?= h($departureDate) ?></dd>

        <dt>期間</dt>
        <dd><?= h($duration) ?></dd>

        <dt>人数</dt>
        <dd><?= h($people) ?></dd>

        <dt>予算（下限）</dt>
        <dd><?= h(formatYen($budgetMin)) ?></dd>

        <dt>予算（上限）</dt>
        <dd><?= h(formatYen($budgetMax)) ?></dd>
    </dl>

    <hr>

    <h2>デバッグ用の生データ</h2>
    <pre><?php print_r($_POST); ?></pre>

    <p><a href="../index.html">入力フォームに戻る</a></p>
</body>

</html>