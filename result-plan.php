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

function difficultyLabel(string $difficulty): string
{
    switch ($difficulty) {
        case 'easy':
            return 'やさしい';
        case 'normal':
            return 'ふつう';
        case 'hard':
            return 'むずかしい';
        default:
            return $difficulty;
    }
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
    CURLOPT_TIMEOUT => 180,
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

$plans = [];
if (is_array($apiResponse) && !empty($apiResponse['data']['plans']) && is_array($apiResponse['data']['plans'])) {
    $plans = $apiResponse['data']['plans'];
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
            margin: 0;
            padding: 16px;
            background: #f7f7f7;
            font-family: sans-serif;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .error {
            border: 1px solid #cc0000;
            background: #fff;
            padding: 12px;
        }

        .plans-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            align-items: start;
        }

        .plan-column {
            border: 1px solid #cccccc;
            background: #fff;
            padding: 16px;
            box-sizing: border-box;
        }

        .spot-card {
            border: 1px solid #dddddd;
            padding: 12px;
            margin-top: 12px;
            background: #fafafa;
        }

        .spot-card img {
            max-width: 100%;
            height: auto;
            margin-top: 8px;
            display: block;
        }

        .spot-meta {
            margin-top: 8px;
        }

        .spot-meta div {
            margin-top: 4px;
        }

        .quest-number {
            display: inline-block;
            font-weight: bold;
            margin-bottom: 8px;
        }

        .confirm-box {
            margin-top: 20px;
            padding-top: 16px;
            border-top: 1px solid #ddd;
        }

        .confirm-button {
            display: inline-block;
            width: 100%;
            padding: 12px 16px;
            border: none;
            background: #222;
            color: #fff;
            font-size: 16px;
            cursor: pointer;
        }

        .confirm-button:hover {
            opacity: 0.9;
        }

        pre {
            white-space: pre-wrap;
            word-break: break-word;
            overflow-x: auto;
        }

        @media (max-width: 1024px) {
            .plans-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <h1>旅行プラン提案結果</h1>

        <section>
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
                <dd><?= h($apiResponse['user_context']['interest_category'] ?? 'すべて') ?></dd>
            </dl>
        </section>

        <?php if ($pageError !== null): ?>
            <section class="error">
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
            </section>
        <?php else: ?>
            <section>
                <h2>AIが生成した旅行プラン候補</h2>

                <?php if (!empty($plans)): ?>
                    <div class="plans-grid">
                        <?php foreach ($plans as $index => $plan): ?>
                            <section class="plan-column">
                                <h2>プラン<?= $index + 1 ?></h2>

                                <h3><?= h($plan['plan_title'] ?? 'タイトル未設定') ?></h3>

                                <?php if (!empty($plan['concept'])): ?>
                                    <p><strong>コンセプト:</strong> <?= nl2br(h($plan['concept'])) ?></p>
                                <?php endif; ?>

                                <?php if (!empty($plan['summary'])): ?>
                                    <p><strong>概要:</strong> <?= nl2br(h($plan['summary'])) ?></p>
                                <?php endif; ?>

                                <?php if (!empty($plan['budget_estimate'])): ?>
                                    <p>
                                        <strong>予算目安:</strong>
                                        <?= h(formatYen($plan['budget_estimate']['min'] ?? '')) ?>
                                        〜
                                        <?= h(formatYen($plan['budget_estimate']['max'] ?? '')) ?>
                                    </p>
                                <?php endif; ?>

                                <?php if (!empty($plan['goal']) && is_array($plan['goal'])): ?>
                                    <div class="spot-card">
                                        <h4>ゴール地点</h4>
                                        <div><strong><?= h($plan['goal']['name'] ?? '') ?></strong></div>

                                        <?php if (!empty($plan['goal']['description'])): ?>
                                            <div class="spot-meta"><?= nl2br(h($plan['goal']['description'])) ?></div>
                                        <?php endif; ?>

                                        <div class="spot-meta">
                                            <?php if (!empty($plan['goal']['area'])): ?>
                                                <div><strong>住所・エリア:</strong> <?= h($plan['goal']['area']) ?></div>
                                            <?php endif; ?>

                                            <?php if (!empty($plan['goal']['google_maps_url'])): ?>
                                                <div>
                                                    <a href="<?= h($plan['goal']['google_maps_url']) ?>" target="_blank" rel="noopener noreferrer">Googleマップで見る</a>
                                                </div>
                                            <?php endif; ?>

                                            <?php if (!empty($plan['goal']['official_url'])): ?>
                                                <div>
                                                    <a href="<?= h($plan['goal']['official_url']) ?>" target="_blank" rel="noopener noreferrer">公式サイトを見る</a>
                                                </div>
                                            <?php endif; ?>
                                        </div>

                                        <?php if (!empty($plan['goal']['image'])): ?>
                                            <img src="<?= h($plan['goal']['image']) ?>" alt="<?= h($plan['goal']['name'] ?? 'ゴール地点') ?>">
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($plan['quests']) && is_array($plan['quests'])): ?>
                                    <h4>クエスト</h4>

                                    <?php foreach ($plan['quests'] as $qIndex => $quest): ?>
                                        <div class="spot-card">
                                            <div class="quest-number">クエスト<?= $qIndex + 1 ?></div>
                                            <div><strong><?= h($quest['title'] ?? 'クエスト') ?></strong></div>

                                            <?php if (!empty($quest['place'])): ?>
                                                <div><strong>場所:</strong> <?= h($quest['place']) ?></div>
                                            <?php endif; ?>

                                            <?php if (!empty($quest['description'])): ?>
                                                <div class="spot-meta"><?= nl2br(h($quest['description'])) ?></div>
                                            <?php endif; ?>

                                            <div class="spot-meta">
                                                <?php if (!empty($quest['area'])): ?>
                                                    <div><strong>住所・エリア:</strong> <?= h($quest['area']) ?></div>
                                                <?php endif; ?>

                                                <?php if (!empty($quest['stay_minutes'])): ?>
                                                    <div><strong>滞在目安:</strong> <?= h((string)$quest['stay_minutes']) ?>分</div>
                                                <?php endif; ?>

                                                <?php if (!empty($quest['estimated_cost'])): ?>
                                                    <div><strong>費用目安:</strong> <?= h($quest['estimated_cost']) ?></div>
                                                <?php endif; ?>

                                                <?php if (!empty($quest['difficulty'])): ?>
                                                    <div><strong>難易度:</strong> <?= h(difficultyLabel((string)$quest['difficulty'])) ?></div>
                                                <?php endif; ?>

                                                <?php if (!empty($quest['exp'])): ?>
                                                    <div><strong>EXP:</strong> <?= h((string)$quest['exp']) ?></div>
                                                <?php endif; ?>

                                                <?php if (!empty($quest['google_maps_url'])): ?>
                                                    <div>
                                                        <a href="<?= h($quest['google_maps_url']) ?>" target="_blank" rel="noopener noreferrer">Googleマップで見る</a>
                                                    </div>
                                                <?php endif; ?>

                                                <?php if (!empty($quest['official_url'])): ?>
                                                    <div>
                                                        <a href="<?= h($quest['official_url']) ?>" target="_blank" rel="noopener noreferrer">公式サイトを見る</a>
                                                    </div>
                                                <?php endif; ?>
                                            </div>

                                            <?php if (!empty($quest['image'])): ?>
                                                <img src="<?= h($quest['image']) ?>" alt="<?= h($quest['place'] ?? $quest['title'] ?? 'クエスト') ?>">
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>

                                <?php if (!empty($plan['notes']) && is_array($plan['notes'])): ?>
                                    <div>
                                        <h4>メモ</h4>
                                        <ul>
                                            <?php foreach ($plan['notes'] as $note): ?>
                                                <li><?= h($note) ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                <?php endif; ?>

                                <div class="confirm-box">
                                    <form action="./confirm-plan.php" method="post">
                                        <input type="hidden" name="selectedPlan" value="<?= h(base64_encode(json_encode($plan, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))) ?>">
                                        <input type="hidden" name="inputData" value="<?= h(base64_encode(json_encode($input, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))) ?>">
                                        <button type="submit" class="confirm-button">このプランを確定</button>
                                    </form>
                                </div>
                            </section>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p>プラン候補を取得できませんでした。</p>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <p><a href="./input-plan.html">入力フォームに戻る</a></p>
    </div>
</body>

</html>