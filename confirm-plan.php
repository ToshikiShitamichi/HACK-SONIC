<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/config/db.php';

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

function normalizeDifficulty(?string $difficulty): string
{
    $difficulty = strtolower(trim((string)$difficulty));

    if (in_array($difficulty, ['easy', 'normal', 'hard'], true)) {
        return $difficulty;
    }

    return 'normal';
}

function normalizeXp($xp): int
{
    if ($xp === null || $xp === '' || !is_numeric((string)$xp)) {
        return 0;
    }
    return (int)$xp;
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

$selectedPlanEncoded = (string)($_POST['selectedPlan'] ?? '');
$inputDataEncoded    = (string)($_POST['inputData'] ?? '');

$plan  = json_decode(base64_decode($selectedPlanEncoded), true);
$input = json_decode(base64_decode($inputDataEncoded), true);

if (!is_array($plan) || !is_array($input)) {
    http_response_code(400);
?>
    <!DOCTYPE html>
    <html lang="ja">

    <head>
        <meta charset="UTF-8">
        <title>エラー</title>
    </head>

    <body>
        <h1>エラー</h1>
        <p>確定プランのデータを取得できませんでした。</p>
        <p><a href="./input-plan.html">入力フォームに戻る</a></p>
    </body>

    </html>
<?php
    exit;
}

$userId = $_SESSION['user_id'] ?? null;
$saveMessage = '';
$saveError = '';

// 二重登録防止用
$planHash = hash('sha256', $selectedPlanEncoded);

try {
    $pdo = get_db();
    $pdo->beginTransaction();

    // 同じ確定POSTの二重保存を防ぐため、セッションに保存
    if (!isset($_SESSION['saved_plan_hashes'])) {
        $_SESSION['saved_plan_hashes'] = [];
    }

    if (!in_array($planHash, $_SESSION['saved_plan_hashes'], true)) {
        $savedQuestIds = [];

        if (!empty($plan['quests']) && is_array($plan['quests'])) {
            $insertQuestSql = "
                INSERT INTO quests (
                    title,
                    description,
                    xp,
                    difficulty,
                    location,
                    image_url,
                    created_at,
                    updated_at
                ) VALUES (
                    :title,
                    :description,
                    :xp,
                    :difficulty,
                    :location,
                    :image_url,
                    NOW(),
                    NOW()
                )
            ";
            $stmtQuest = $pdo->prepare($insertQuestSql);

            foreach ($plan['quests'] as $quest) {
                if (!is_array($quest)) {
                    continue;
                }

                $title = trim((string)($quest['title'] ?? ''));
                if ($title === '') {
                    $title = 'クエスト';
                }

                $description = trim((string)($quest['description'] ?? ''));
                $xp          = normalizeXp($quest['exp'] ?? 0);
                $difficulty  = normalizeDifficulty($quest['difficulty'] ?? 'normal');

                // place を優先、なければ area を保存
                $location = trim((string)($quest['place'] ?? ''));
                if ($location === '') {
                    $location = trim((string)($quest['area'] ?? ''));
                }

                $imageUrl = trim((string)($quest['image'] ?? ''));

                $stmtQuest->execute([
                    ':title'       => $title,
                    ':description' => $description !== '' ? $description : null,
                    ':xp'          => $xp,
                    ':difficulty'  => $difficulty,
                    ':location'    => $location !== '' ? $location : null,
                    ':image_url'   => $imageUrl !== '' ? $imageUrl : null,
                ]);

                $savedQuestIds[] = (int)$pdo->lastInsertId();
            }
        }

        // user_quests に紐づけ保存（ログイン時のみ）
        if ($userId !== null && !empty($savedQuestIds)) {
            $insertUserQuestSql = "
                INSERT INTO user_quests (
                    user_id,
                    quest_id
                ) VALUES (
                    :user_id,
                    :quest_id
                )
            ";
            $stmtUserQuest = $pdo->prepare($insertUserQuestSql);

            foreach ($savedQuestIds as $questId) {
                $stmtUserQuest->execute([
                    ':user_id'  => (int)$userId,
                    ':quest_id' => $questId,
                ]);
            }
        }

        $_SESSION['saved_plan_hashes'][] = $planHash;
        $saveMessage = 'クエスト情報を保存しました。';
    } else {
        $saveMessage = 'このプランのクエスト情報はすでに保存済みです。';
    }

    $pdo->commit();
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $saveError = 'クエスト情報の保存に失敗しました: ' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>確定した旅行プラン</title>
    <style>
        body {
            margin: 0;
            padding: 16px;
            background: #f7f7f7;
            font-family: sans-serif;
        }

        .container {
            max-width: 900px;
            margin: 0 auto;
        }

        .card {
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
            width: 100%;
            max-width: 320px;
            height: 200px;
            object-fit: cover;
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

        .button-row {
            margin-top: 24px;
        }

        .back-button {
            display: inline-block;
            padding: 10px 16px;
            background: #222;
            color: #fff;
            text-decoration: none;
            margin-right: 8px;
        }

        .sub-box {
            margin-top: 16px;
            background: #fff;
            border: 1px solid #ddd;
            padding: 16px;
        }

        .message-box {
            margin-bottom: 16px;
            padding: 12px 16px;
            border-radius: 6px;
        }

        .message-success {
            background: #ecfdf3;
            border: 1px solid #86efac;
            color: #166534;
        }

        .message-error {
            background: #fef2f2;
            border: 1px solid #fca5a5;
            color: #991b1b;
        }
    </style>
</head>

<body>
    <div class="container">
        <h1>確定した旅行プラン</h1>

        <?php if ($saveMessage !== ''): ?>
            <div class="message-box message-success"><?= h($saveMessage) ?></div>
        <?php endif; ?>

        <?php if ($saveError !== ''): ?>
            <div class="message-box message-error"><?= h($saveError) ?></div>
        <?php endif; ?>

        <section class="card">
            <h2><?= h($plan['plan_title'] ?? 'タイトル未設定') ?></h2>

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
                    <h3>ゴール地点</h3>
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
                <h3>クエスト</h3>

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
                    <h3>メモ</h3>
                    <ul>
                        <?php foreach ($plan['notes'] as $note): ?>
                            <li><?= h($note) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </section>

        <div class="button-row">
            <a href="./input-plan.html" class="back-button">入力フォームに戻る</a>
            <a href="./R/views/post.php" class="back-button">SNSに投稿</a>
        </div>
    </div>
</body>

</html>