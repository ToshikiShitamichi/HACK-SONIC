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

        $goalQuestId = null;

        // ----------------------------
        // 1. ゴールを quests に保存
        // ----------------------------
        if (!empty($plan['goal']) && is_array($plan['goal'])) {
            $goalTitle = trim((string)($plan['goal']['name'] ?? ''));
            if ($goalTitle === '') {
                $goalTitle = 'ゴール';
            }

            $goalDescription = trim((string)($plan['goal']['description'] ?? ''));
            $goalLocation    = trim((string)($plan['goal']['area'] ?? ''));
            $goalImageUrl    = trim((string)($plan['goal']['image'] ?? ''));

            $stmtQuest->execute([
                ':title'       => $goalTitle,
                ':description' => $goalDescription !== '' ? $goalDescription : null,
                ':xp'          => 0,
                ':difficulty'  => 'normal',
                ':location'    => $goalLocation !== '' ? $goalLocation : null,
                ':image_url'   => $goalImageUrl !== '' ? $goalImageUrl : null,
            ]);

            $goalQuestId = (int)$pdo->lastInsertId();
        }

        // ----------------------------
        // 2. クエストを quests に保存
        // ----------------------------
        if (!empty($plan['quests']) && is_array($plan['quests'])) {
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
            }
        }

        // ----------------------------
        // 3. user_quests には goal のみ保存
        // ----------------------------
        if ($userId !== null && $goalQuestId !== null) {
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

            $stmtUserQuest->execute([
                ':user_id'  => (int)$userId,
                ':quest_id' => $goalQuestId,
            ]);
        }

        $_SESSION['saved_plan_hashes'][] = $planHash;
        $saveMessage = 'ゴールとクエスト情報を保存しました。';
    } else {
        $saveMessage = 'このプラン情報はすでに保存済みです。';
    }

    $pdo->commit();
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $saveError = '保存に失敗しました: ' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>確定した旅行プラン | 旅 so sweet</title>
    <link rel="stylesheet" href="./assets/css/common.css">
    <style>
        .plan-card {
            background: var(--white);
            border: 1.5px solid var(--border);
            border-radius: 20px;
            padding: 28px;
            box-shadow: 0 4px 16px rgba(232, 64, 92, 0.06);
        }

        .plan-card h2 {
            font-size: 20px;
            font-weight: 800;
            color: var(--text);
            margin-bottom: 16px;
        }

        .plan-card p {
            font-size: 14px;
            color: var(--muted);
            line-height: 1.7;
            margin-bottom: 12px;
        }

        .section-heading {
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: var(--muted);
            margin: 28px 0 16px;
            padding: 10px 16px;
            background: var(--pink-pale);
            border-left: 4px solid var(--pink);
            border-radius: 0 10px 10px 0;
        }

        .spot-card {
            border: 1.5px solid var(--border);
            padding: 18px;
            margin-top: 14px;
            background: var(--cream);
            border-radius: 14px;
        }

        .spot-card h3 {
            font-size: 14px;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 8px;
        }

        .spot-card > div:first-child strong {
            font-size: 15px;
            font-weight: 700;
            color: var(--text);
        }

        .spot-card img {
            width: 100%;
            max-width: 360px;
            height: 200px;
            object-fit: cover;
            margin-top: 12px;
            display: block;
            border-radius: 12px;
            border: 1px solid var(--border);
        }

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

        .button-row {
            margin-top: 32px;
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .message-box {
            margin-bottom: 20px;
            padding: 14px 18px;
            border-radius: 14px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .message-success {
            background: var(--pink-pale);
            border: 1.5px solid var(--border);
            color: var(--pink-deep);
        }

        .message-error {
            background: #fff0f0;
            border: 1.5px solid #ffc8c8;
            color: #d94040;
        }

        .notes-list {
            margin-top: 12px;
            padding-left: 20px;
        }

        .notes-list li {
            font-size: 13px;
            color: var(--muted);
            margin-bottom: 6px;
            line-height: 1.6;
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
    <p class="hero-eyebrow">Plan Confirmed</p>
    <h1 class="hero-title">旅行<em>プラン</em>確定</h1>
    <p class="hero-subtitle">クエストとして保存されました</p>
</section>

<main>
        <?php if ($saveMessage !== ''): ?>
            <div class="message-box message-success">&#10003; <?= h($saveMessage) ?></div>
        <?php endif; ?>

        <?php if ($saveError !== ''): ?>
            <div class="message-box message-error">&#10007; <?= h($saveError) ?></div>
        <?php endif; ?>

        <section class="plan-card">
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
                <h4 class="section-heading">ゴール地点</h4>
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
                        <img src="<?= h($plan['goal']['image']) ?>" alt="<?= h($plan['goal']['name'] ?? 'ゴール地点') ?>">
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($plan['quests']) && is_array($plan['quests'])): ?>
                <h4 class="section-heading">クエスト</h4>

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
                <h4 class="section-heading">メモ</h4>
                <ul class="notes-list">
                    <?php foreach ($plan['notes'] as $note): ?>
                        <li><?= h($note) ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <div class="button-row">
            <a href="./quests/quest_list.php" class="btn-primary">クエスト一覧にすすむ</a>
            <a href="./index.php" class="btn-secondary">ホームに戻る</a>
        </div>
</main>

<footer class="page-footer">Travel Quest · 旅 so sweet</footer>

</body>

</html>
