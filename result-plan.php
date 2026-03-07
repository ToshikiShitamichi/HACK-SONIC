<?php
declare(strict_types=1);

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function formatYen($value): string
{
    if ($value === '' || $value === null || !is_numeric((string)$value)) {
        return '未指定';
    }
    return number_format((int)$value) . '円';
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
        <p><a href="./input-plan.html">入力フォームに戻る</a></p>
    </body>
    </html>
    <?php
    exit;
}

$input = [
    'departurePrefecture' => trim((string)($_POST['departurePrefecture'] ?? '')),
    'departureCity' => trim((string)($_POST['departureCity'] ?? '')),
    'destinationPrefecture' => trim((string)($_POST['destinationPrefecture'] ?? '')),
    'destinationCity' => trim((string)($_POST['destinationCity'] ?? '')),
    'destinationKeyword' => trim((string)($_POST['destinationKeyword'] ?? '')),
    'departureDate' => trim((string)($_POST['departureDate'] ?? '')),
    'duration' => trim((string)($_POST['duration'] ?? '')),
    'people' => trim((string)($_POST['people'] ?? '')),
    'budgetMin' => trim((string)($_POST['budgetMin'] ?? '')),
    'budgetMax' => trim((string)($_POST['budgetMax'] ?? '')),
];

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$baseDir = rtrim(str_replace('\\', '/', dirname($_SERVER['PHP_SELF'])), '/');
$apiUrl = $scheme . '://' . $_SERVER['HTTP_HOST'] . $baseDir . '/api/generate-plan.php';

$ch = curl_init($apiUrl);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json'
    ],
    CURLOPT_POSTFIELDS => json_encode($input, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    CURLOPT_TIMEOUT => 120,
]);

$responseBody = curl_exec($ch);
$curlError = curl_error($ch);
$httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$apiResponse = null;
$pageError = null;

if ($responseBody === false) {
    $pageError = 'APIの呼び出しに失敗しました: ' . $curlError;
} else {
    $apiResponse = json_decode($responseBody, true);

    if (!is_array($apiResponse)) {
        $pageError = 'APIレスポンスのJSON解析に失敗しました。';
    } elseif ($httpCode < 200 || $httpCode >= 300 || empty($apiResponse['success'])) {
        $pageError = $apiResponse['error'] ?? 'プラン生成に失敗しました。';
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>旅行プラン提案結果</title>
    <style>
        body {
            font-family: sans-serif;
            line-height: 1.7;
            margin: 0;
            padding: 24px;
            background: #f7f7fb;
            color: #222;
        }
        .container {
            max-width: 1100px;
            margin: 0 auto;
        }
        .box {
            background: #fff;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,.06);
        }
        .error {
            border-left: 6px solid #d33;
            background: #fff4f4;
        }
        .input-summary dl {
            display: grid;
            grid-template-columns: 220px 1fr;
            gap: 8px 12px;
        }
        .input-summary dt {
            font-weight: bold;
        }
        .plan-card {
            border: 1px solid #ddd;
            border-radius: 12px;
            padding: 18px;
            margin-top: 18px;
        }
        .goal {
            background: #f1f8ff;
            border-radius: 10px;
            padding: 14px;
            margin: 14px 0;
        }
        .quest {
            border: 1px solid #e5e5e5;
            border-radius: 10px;
            padding: 14px;
            margin: 12px 0;
            background: #fcfcfc;
        }
        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 12px;
            margin-right: 8px;
            background: #eee;
        }
        .easy { background: #e8f7e8; }
        .normal { background: #fff2cc; }
        .hard { background: #ffd9d9; }
        ul {
            padding-left: 1.2em;
        }
        pre {
            white-space: pre-wrap;
            word-break: break-word;
            background: #111;
            color: #f5f5f5;
            padding: 14px;
            border-radius: 8px;
            overflow-x: auto;
        }
        a {
            color: #0b57d0;
        }
    </style>
</head>
<body>
<div class="container">
    <h1>旅行プラン提案結果</h1>

    <div class="box input-summary">
        <h2>入力内容</h2>
        <dl>
            <dt>出発地（都道府県）</dt>
            <dd><?= h($input['departurePrefecture']) ?></dd>

            <dt>出発地（市区町村）</dt>
            <dd><?= h($input['departureCity']) ?: '未入力' ?></dd>

            <dt>旅行先（都道府県）</dt>
            <dd><?= h($input['destinationPrefecture']) ?></dd>

            <dt>旅行先（市区町村）</dt>
            <dd><?= h($input['destinationCity']) ?: '未入力' ?></dd>

            <dt>旅行先キーワード</dt>
            <dd><?= h($input['destinationKeyword']) ?: '未入力' ?></dd>

            <dt>出発日</dt>
            <dd><?= h($input['departureDate']) ?></dd>

            <dt>期間</dt>
            <dd><?= h($input['duration']) ?></dd>

            <dt>人数</dt>
            <dd><?= h($input['people']) ?></dd>

            <dt>予算（下限）</dt>
            <dd><?= h(formatYen($input['budgetMin'])) ?></dd>

            <dt>予算（上限）</dt>
            <dd><?= h(formatYen($input['budgetMax'])) ?></dd>

            <dt>気になるカテゴリ</dt>
            <dd><?= h($apiResponse['user_context']['interest_category'] ?? 'グルメ') ?></dd>
        </dl>
    </div>

    <?php if ($pageError !== null): ?>
        <div class="box error">
            <h2>エラー</h2>
            <p><?= h($pageError) ?></p>

            <?php if (is_array($apiResponse) && !empty($apiResponse['errors']) && is_array($apiResponse['errors'])): ?>
                <ul>
                    <?php foreach ($apiResponse['errors'] as $error): ?>
                        <li><?= h($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <p><a href="./input-plan.html">入力フォームに戻る</a></p>
        </div>
    <?php else: ?>
        <div class="box">
            <h2>AIが生成した旅行プラン候補</h2>

            <?php
            $plans = $apiResponse['data']['plans'] ?? [];
            foreach ($plans as $index => $plan):
            ?>
                <section class="plan-card">
                    <h3>候補<?= h((string)($index + 1)) ?>：<?= h($plan['plan_title'] ?? '無題プラン') ?></h3>

                    <p><strong>コンセプト：</strong><?= h($plan['concept'] ?? '') ?></p>
                    <p><strong>概要：</strong><?= h($plan['summary'] ?? '') ?></p>

                    <p>
                        <strong>予算目安：</strong>
                        <?= h(formatYen($plan['budget_estimate']['min'] ?? '')) ?>
                        〜
                        <?= h(formatYen($plan['budget_estimate']['max'] ?? '')) ?>
                    </p>

                    <div class="goal">
                        <h4>ゴール：<?= h($plan['goal']['name'] ?? '') ?></h4>
                        <p><strong>場所：</strong><?= h($plan['goal']['area'] ?? '') ?></p>
                        <p><?= h($plan['goal']['description'] ?? '') ?></p>
                        <p><strong>画像：</strong><?= h($plan['goal']['image'] ?? 'NULL') ?></p>
                    </div>

                    <h4>クエスト一覧</h4>
                    <?php if (!empty($plan['quests']) && is_array($plan['quests'])): ?>
                        <?php foreach ($plan['quests'] as $quest): ?>
                            <?php
                            $difficulty = (string)($quest['difficulty'] ?? '');
                            $difficultyClass = in_array($difficulty, ['easy', 'normal', 'hard'], true) ? $difficulty : '';
                            ?>
                            <div class="quest">
                                <h5><?= h($quest['title'] ?? '無題クエスト') ?></h5>
                                <p><?= h($quest['description'] ?? '') ?></p>
                                <p>
                                    <span class="badge <?= h($difficultyClass) ?>">難易度: <?= h($difficulty ?: '-') ?></span>
                                    <span class="badge">獲得経験値: <?= h((string)($quest['exp'] ?? '-')) ?></span>
                                </p>
                                <p><strong>場所：</strong><?= h($quest['place'] ?? '') ?></p>
                                <p><strong>画像：</strong><?= h($quest['image'] ?? 'NULL') ?></p>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p>クエスト情報はありません。</p>
                    <?php endif; ?>

                    <h4>注意点</h4>
                    <?php if (!empty($plan['notes']) && is_array($plan['notes'])): ?>
                        <ul>
                            <?php foreach ($plan['notes'] as $note): ?>
                                <li><?= h($note) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p>特記事項なし</p>
                    <?php endif; ?>
                </section>
            <?php endforeach; ?>
        </div>

        <div class="box">
            <h2>デバッグ用: APIレスポンス</h2>
            <pre><?= h(json_encode($apiResponse, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></pre>
        </div>
    <?php endif; ?>

    <p><a href="./input-plan.html">入力フォームに戻る</a></p>
</div>
</body>
</html>