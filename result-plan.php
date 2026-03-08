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
    <title>旅行プラン提案結果 | 旅 so sweet</title>
    <link rel="stylesheet" href="./assets/css/common.css">
    <style>
        main {
            max-width: 1200px;
        }

        /* ===== プランレイアウト ===== */

        .plans-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            align-items: start;
        }

        /* ===== プランカード ===== */

        .plan-column {
            background: var(--white);
            border: 1.5px solid var(--border);
            border-radius: 20px;
            padding: 24px;
            box-shadow: 0 4px 16px rgba(232, 64, 92, 0.06);
            transition: transform 0.25s ease, box-shadow 0.25s ease, border-color 0.25s ease;
        }

        .plan-column:hover {
            transform: translateY(-4px);
            border-color: var(--pink-lt);
            box-shadow: 0 12px 32px rgba(232, 64, 92, 0.12);
        }

        .plan-column h2 {
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 3px;
            text-transform: uppercase;
            color: var(--pink);
            margin-bottom: 12px;
        }

        .plan-column h3 {
            font-size: 18px;
            font-weight: 800;
            color: var(--text);
            margin-bottom: 12px;
        }

        .plan-column p {
            font-size: 14px;
            color: var(--muted);
            line-height: 1.7;
            margin-bottom: 12px;
        }

        .plan-column h4 {
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: var(--muted);
            margin: 24px 0 12px;
            padding: 10px 16px;
            background: var(--pink-pale);
            border-left: 4px solid var(--pink);
            border-radius: 0 10px 10px 0;
        }

        /* ===== スポットカード ===== */

        .spot-card {
            border: 1.5px solid var(--border);
            padding: 16px;
            margin-top: 12px;
            background: var(--cream);
            border-radius: 14px;
        }

        .spot-card > div:first-child {
            font-size: 15px;
            font-weight: 700;
            color: var(--text);
        }

        /* ===== 画像 ===== */

        .spot-image-wrap {
            width: 100%;
            height: 180px;
            margin-top: 12px;
            overflow: hidden;
            background: var(--pink-pale);
            border-radius: 12px;
        }

        .spot-image-wrap img {
            width: 100%;
            height: 100%;
            display: block;
            object-fit: cover;
        }

        /* ===== メタ情報 ===== */

        .spot-meta {
            margin-top: 10px;
            font-size: 13px;
            color: var(--muted);
            line-height: 1.6;
        }

        .spot-meta div {
            margin-top: 6px;
        }

        .spot-meta a {
            color: var(--pink);
            font-weight: 600;
        }

        /* ===== クエスト番号 ===== */

        .quest-number {
            display: inline-block;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 1px;
            padding: 4px 10px;
            background: var(--pink);
            color: var(--white);
            border-radius: 50px;
            margin-bottom: 10px;
        }

        /* ===== 確定ボタン ===== */

        .confirm-box {
            margin-top: 24px;
            padding-top: 20px;
            border-top: 1px solid var(--border);
        }

        .confirm-button {
            display: block;
            width: 100%;
            padding: 14px 20px;
            border: none;
            background: linear-gradient(135deg, var(--pink-lt) 0%, var(--pink) 55%, var(--pink-deep) 100%);
            color: var(--white);
            font-size: 14px;
            font-weight: 800;
            letter-spacing: 1px;
            border-radius: 50px;
            cursor: pointer;
            box-shadow: 0 6px 20px rgba(232, 64, 92, 0.32);
            transition: opacity 0.2s ease, transform 0.12s ease, box-shadow 0.2s ease;
        }

        .confirm-button:hover {
            opacity: 0.92;
            transform: translateY(-2px);
            box-shadow: 0 10px 28px rgba(232, 64, 92, 0.42);
        }

        .error-box {
            background: #fff0f0;
            border: 1.5px solid #ffc8c8;
            color: #d94040;
            padding: 20px;
            border-radius: 14px;
            margin-bottom: 24px;
        }

        .error-box h2 {
            font-size: 16px;
            margin-bottom: 10px;
        }

        .back-link {
            display: inline-block;
            margin-top: 24px;
            color: var(--pink);
            font-weight: 700;
        }

        @media (max-width: 1024px) {
            .plans-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>

<div class="page-frame-top"></div>

<header>
    <div class="header-brand">
        <span class="brand-kanji">旅</span>
        <span class="brand-roman">so sweet</span>
    </div>
    <nav class="header-nav">
        <a href="./index.php">ホーム</a>
        <a href="./quests/quest_list.php">旅クエスト</a>
        <a href="./auth/logout.php">ログアウト</a>
    </nav>
</header>

<section class="hero">
    <p class="hero-eyebrow">Travel Plan Results</p>
    <h1 class="hero-title">旅行<em>プラン</em>提案結果</h1>
    <p class="hero-subtitle">AIが生成した旅行プラン候補</p>
</section>

<main>

        <!-- <section>
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
        </section> -->

        <?php if ($pageError !== null): ?>
            <div class="error-box">
                <h2>エラー</h2>
                <p><?= h($pageError) ?></p>

                <?php if (is_array($apiResponse) && !empty($apiResponse['errors']) && is_array($apiResponse['errors'])): ?>
                    <ul>
                        <?php foreach ($apiResponse['errors'] as $error): ?>
                            <li><?= h($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <a href="./input-plan.html" class="btn-secondary">入力フォームに戻る</a>
            </div>
        <?php else: ?>
            <div class="page-section-title">プラン候補（<?= count($plans) ?>件）</div>

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
                                    <h4>ゴール地点</h4>
                                    <div class="spot-card">
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
                                            <div class="spot-image-wrap">
                                                <img src="<?= h($plan['goal']['image']) ?>" alt="<?= h($plan['goal']['name'] ?? 'ゴール地点') ?>">
                                            </div>
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
                                                <div class="spot-image-wrap">
                                                    <img src="<?= h($quest['image']) ?>" alt="<?= h($quest['place'] ?? $quest['title'] ?? 'クエスト') ?>">
                                                </div>
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
                    <div class="card-static text-center" style="padding: 60px 20px;">
                        <p class="text-muted" style="font-size: 15px;">プラン候補を取得できませんでした。</p>
                        <a href="./input-plan.html" class="btn-secondary mt-4">入力フォームに戻る</a>
                    </div>
                <?php endif; ?>
        <?php endif; ?>

        <div class="text-center mt-6">
            <a href="./input-plan.html" class="back-link">入力フォームに戻る</a>
        </div>
</main>

<footer class="page-footer">Travel Quest · 旅 so sweet</footer>

</body>

</html>
