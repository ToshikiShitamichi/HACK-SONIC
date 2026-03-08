<?php
require_once __DIR__ . '/../auth/auth_check.php';
require_once __DIR__ . '/../config/db.php';

$user_id = $_SESSION['user_id'];

$pdo = get_db();

// 受注中クエストを JOIN して取得
$stmt = $pdo->prepare(
    'SELECT
        uq.id        AS user_quest_id,
        uq.status,
        uq.created_at AS accepted_at,
        q.id         AS quest_id,
        q.title,
        q.description,
        q.location,
        q.xp,
        q.difficulty,
        q.image_url
    FROM user_quests uq
    INNER JOIN quests q ON q.id = uq.quest_id
    WHERE uq.user_id = ?
    ORDER BY uq.created_at DESC'
);
$stmt->execute([$user_id]);
$my_quests = $stmt->fetchAll();

// フラッシュメッセージ取得
$flash_success  = $_SESSION['flash_success']  ?? null;
$flash_xp       = $_SESSION['flash_xp']       ?? null;
$flash_levelup  = $_SESSION['flash_levelup']  ?? null;
$flash_info     = $_SESSION['flash_info']     ?? null;
$flash_error    = $_SESSION['flash_error']    ?? null;
unset(
    $_SESSION['flash_success'],
    $_SESSION['flash_xp'],
    $_SESSION['flash_levelup'],
    $_SESSION['flash_info'],
    $_SESSION['flash_error']
);

$pending_count   = count(array_filter($my_quests, fn($q) => $q['status'] === 'pending'));
$completed_count = count(array_filter($my_quests, fn($q) => $q['status'] === 'completed'));
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>マイクエスト | 旅 so sweet</title>
    <style>
        *, *::before, *::after {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --pink:      #e8405c;
            --pink-lt:   #f47090;
            --pink-pale: #fce8ec;
            --pink-deep: #c02848;
            --cream:     #fff8f4;
            --white:     #ffffff;
            --dark:      #1e1218;
            --text:      #2a1a20;
            --muted:     #9a7885;
            --border:    #f0d0d8;
        }

        body {
            font-family: 'Helvetica Neue', Arial, 'Hiragino Sans', sans-serif;
            background: var(--cream);
            color: var(--text);
            min-height: 100vh;
            -webkit-font-smoothing: antialiased;
        }

        /* ========== 上部フレームライン ========== */
        .page-frame-top {
            position: fixed;
            top: 0; left: 0; right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--pink-pale), var(--pink), var(--pink-lt), var(--pink));
            z-index: 100;
        }

        /* ========== ヘッダー ========== */
        header {
            background: var(--white);
            border-bottom: 1px solid var(--border);
            padding: 14px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 4px;
            z-index: 99;
            box-shadow: 0 2px 12px rgba(232, 64, 92, 0.06);
        }

        .header-brand {
            display: flex;
            align-items: baseline;
            gap: 10px;
        }
        .header-brand .brand-kanji {
            font-size: 22px;
            font-weight: 900;
            color: var(--pink);
            letter-spacing: -1px;
        }
        .header-brand .brand-roman {
            font-size: 14px;
            font-weight: 300;
            color: var(--muted);
            letter-spacing: 3px;
        }

        .header-nav {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .header-nav a {
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 1px;
            text-decoration: none;
            color: var(--muted);
            transition: color 0.2s;
        }
        .header-nav a:hover { color: var(--pink); }

        /* ========== HERO AREA ========== */
        .hero {
            position: relative;
            text-align: center;
            padding: 48px 24px 40px;
            overflow: hidden;
            background:
                radial-gradient(ellipse at 50% 0%, rgba(252, 232, 236, 0.80) 0%, transparent 60%),
                var(--white);
            border-bottom: 1px solid var(--border);
        }

        .hero::before {
            content: '';
            position: absolute;
            top: 28px; right: 56px;
            width: 5px; height: 5px;
            border-radius: 50%;
            background: var(--pink);
            box-shadow:
                14px 0  0 var(--pink-pale),
                28px 0  0 var(--pink),
                0  12px 0 var(--pink-pale),
                14px 12px 0 var(--pink);
            pointer-events: none;
        }

        .hero-eyebrow {
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 4px;
            text-transform: uppercase;
            color: var(--pink);
            margin-bottom: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
        }
        .hero-eyebrow::before,
        .hero-eyebrow::after {
            content: '';
            width: 36px;
            height: 1px;
            background: var(--border);
        }

        .hero-title {
            font-size: clamp(24px, 4.5vw, 44px);
            font-weight: 900;
            color: var(--text);
            margin-bottom: 10px;
            letter-spacing: -1px;
        }
        .hero-title em {
            color: var(--pink);
            font-style: normal;
        }

        .hero-desc {
            font-size: 12px;
            color: var(--muted);
            letter-spacing: 2px;
        }

        /* ========== スタッツバー ========== */
        .stats-bar {
            max-width: 900px;
            margin: 24px auto 0;
            padding: 0 16px;
            display: flex;
            gap: 12px;
        }

        .stat-chip {
            background: var(--white);
            border: 1.5px solid var(--border);
            border-radius: 14px;
            padding: 10px 20px;
            font-size: 12px;
            color: var(--muted);
            display: flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 2px 8px rgba(232, 64, 92, 0.05);
        }
        .stat-chip .num {
            font-size: 20px;
            font-weight: 900;
            color: var(--pink);
        }
        .stat-chip.completed .num { color: #388e3c; }

        /* ========== メイン ========== */
        main {
            max-width: 900px;
            margin: 0 auto;
            padding: 24px 16px 64px;
        }

        /* ========== フラッシュメッセージ ========== */
        .flash {
            padding: 12px 18px;
            border-radius: 14px;
            font-size: 14px;
            font-weight: 700;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: flashSlide 0.4s ease;
        }
        @keyframes flashSlide {
            from { transform: translateY(-8px); opacity: 0; }
            to   { transform: translateY(0);    opacity: 1; }
        }
        .flash.success {
            background: var(--pink-pale);
            border: 1.5px solid var(--border);
            color: var(--pink-deep);
        }
        .flash.info {
            background: #f0f4ff;
            border: 1.5px solid #c8d4f0;
            color: #4060c0;
        }
        .flash.error {
            background: #fff0f0;
            border: 1.5px solid #ffc8c8;
            color: #d94040;
        }
        .flash.levelup {
            background: #fffbe6;
            border: 1.5px solid #ffe082;
            color: #b07800;
        }
        .flash-xp {
            margin-left: 6px;
            font-size: 15px;
            color: var(--pink);
        }

        /* ========== セクション見出し ========== */
        .section-title {
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 3px;
            text-transform: uppercase;
            color: var(--muted);
            margin-bottom: 14px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .section-title::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--border);
        }
        .section-title .section-icon {
            color: var(--pink);
        }

        /* ========== クエストカード ========== */
        .quest-list {
            display: flex;
            flex-direction: column;
            gap: 14px;
            margin-bottom: 36px;
        }

        .quest-card {
            position: relative;
            background: var(--white);
            border: 1.5px solid var(--border);
            border-radius: 20px;
            padding: 20px;
            display: flex;
            gap: 20px;
            overflow: hidden;
            transition: transform 0.25s ease, border-color 0.25s ease, box-shadow 0.25s ease;
            box-shadow: 0 4px 16px rgba(232, 64, 92, 0.06);
        }

        .quest-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--pink-pale), var(--pink-lt));
            opacity: 0;
            transition: opacity 0.25s ease;
            border-radius: 20px 20px 0 0;
        }

        .quest-card:hover {
            transform: translateY(-4px);
            border-color: var(--pink-lt);
            box-shadow: 0 10px 28px rgba(232, 64, 92, 0.12);
        }
        .quest-card:hover::before { opacity: 1; }

        /* completed カード */
        .quest-card.completed {
            opacity: 0.60;
        }
        .quest-card.completed:hover {
            transform: none;
            box-shadow: 0 4px 16px rgba(232, 64, 92, 0.06);
            border-color: var(--border);
        }
        .quest-card.completed::before { display: none; }

        /* 完了リボン */
        .ribbon-completed {
            position: absolute;
            top: 14px; right: -26px;
            background: var(--pink-pale);
            color: var(--pink);
            font-size: 10px;
            font-weight: 900;
            letter-spacing: 1px;
            padding: 4px 36px;
            transform: rotate(35deg);
            border-top: 1px solid var(--border);
            border-bottom: 1px solid var(--border);
        }

        /* ========== クエスト画像 ========== */
        .quest-image {
            width: 88px;
            height: 88px;
            border-radius: 14px;
            flex-shrink: 0;
            background: var(--pink-pale);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            overflow: hidden;
        }
        .quest-image img {
            width: 100%; height: 100%;
            object-fit: cover;
            border-radius: 14px;
        }

        /* ========== クエスト情報 ========== */
        .quest-info { flex: 1; min-width: 0; }

        .quest-header {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            margin-bottom: 6px;
        }

        .quest-title {
            font-size: 15px;
            font-weight: 800;
            color: var(--text);
        }

        .badge-difficulty {
            font-size: 10px;
            font-weight: 700;
            padding: 3px 10px;
            border-radius: 50px;
            letter-spacing: 1px;
            flex-shrink: 0;
        }
        .badge-difficulty.easy {
            background: #e8f5e9;
            color: #388e3c;
            border: 1px solid #a5d6a7;
        }
        .badge-difficulty.normal {
            background: #fff8e1;
            color: #f57f17;
            border: 1px solid #ffe082;
        }
        .badge-difficulty.hard {
            background: var(--pink-pale);
            color: var(--pink-deep);
            border: 1px solid var(--pink-lt);
        }

        .badge-xp {
            font-size: 11px;
            font-weight: 700;
            padding: 3px 10px;
            border-radius: 50px;
            background: var(--pink-pale);
            color: var(--pink);
            border: 1px solid var(--border);
            flex-shrink: 0;
        }

        .quest-location {
            font-size: 12px;
            color: var(--muted);
            margin-bottom: 6px;
        }
        .quest-location::before { content: '📍 '; }

        .quest-description {
            font-size: 13px;
            color: var(--muted);
            line-height: 1.65;
            margin-bottom: 14px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        /* ========== 達成ボタン ========== */
        .btn-complete {
            position: relative;
            overflow: hidden;
            background: linear-gradient(135deg, var(--pink-lt) 0%, var(--pink) 55%, var(--pink-deep) 100%);
            color: #fff;
            border: none;
            padding: 9px 24px;
            border-radius: 50px;
            font-size: 13px;
            font-weight: 800;
            letter-spacing: 1.5px;
            cursor: pointer;
            box-shadow: 0 6px 20px rgba(232, 64, 92, 0.28), inset 0 1px 0 rgba(255, 255, 255, 0.25);
            transition: opacity 0.2s ease, transform 0.12s ease, box-shadow 0.2s ease;
        }
        .btn-complete::before {
            content: '';
            position: absolute;
            top: 0; left: -80%;
            width: 55%; height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.28), transparent);
            transform: skewX(-18deg);
            animation: shimmer 4s ease infinite;
        }
        @keyframes shimmer {
            0%, 100% { left: -80%; }
            40%       { left: 130%; }
        }
        .btn-complete:hover {
            opacity: 0.92;
            transform: translateY(-2px);
            box-shadow: 0 10px 28px rgba(232, 64, 92, 0.40), inset 0 1px 0 rgba(255, 255, 255, 0.25);
        }
        .btn-complete:active {
            transform: translateY(0);
            box-shadow: 0 4px 12px rgba(232, 64, 92, 0.24);
        }

        /* ========== 空状態 ========== */
        .no-quests {
            text-align: center;
            padding: 60px 0;
        }
        .no-quests .icon {
            font-size: 52px;
            margin-bottom: 16px;
            opacity: 0.5;
        }
        .no-quests p {
            color: var(--muted);
            font-size: 15px;
            margin-bottom: 24px;
        }

        .btn-link {
            display: inline-block;
            background: transparent;
            color: var(--pink);
            border: 1.5px solid var(--border);
            padding: 10px 28px;
            border-radius: 50px;
            font-size: 13px;
            font-weight: 700;
            letter-spacing: 1px;
            text-decoration: none;
            transition: background 0.2s, border-color 0.2s, box-shadow 0.2s;
        }
        .btn-link:hover {
            background: var(--pink-pale);
            border-color: var(--pink-lt);
            box-shadow: 0 4px 16px rgba(232, 64, 92, 0.12);
        }

        /* ========== XP フロートポップアップ ========== */
        .xp-popup {
            position: fixed;
            pointer-events: none;
            font-size: 26px;
            font-weight: 900;
            color: var(--pink);
            text-shadow: 0 0 16px rgba(232, 64, 92, 0.5), 0 0 40px rgba(232, 64, 92, 0.25);
            z-index: 9999;
            animation: xpFloat 1.7s cubic-bezier(0.22, 1, 0.36, 1) forwards;
            white-space: nowrap;
        }
        @keyframes xpFloat {
            0%   { transform: translateY(0)     scale(0.4); opacity: 0; }
            18%  { transform: translateY(-22px)  scale(1.3); opacity: 1; }
            55%  { transform: translateY(-70px)  scale(1);   opacity: 1; }
            100% { transform: translateY(-130px) scale(0.7); opacity: 0; }
        }

        /* ========== 達成オーバーレイ ========== */
        .complete-overlay {
            position: absolute;
            inset: 0;
            border-radius: 20px;
            background: rgba(255, 248, 250, 0.96);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 6px;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.3s ease;
            z-index: 10;
        }
        .complete-overlay.show { opacity: 1; }
        .complete-overlay .ov-icon {
            font-size: 50px;
            animation: checkBounce 0.55s cubic-bezier(0.34, 1.56, 0.64, 1) both;
        }
        .complete-overlay .ov-label {
            font-size: 18px;
            font-weight: 900;
            letter-spacing: 3px;
            color: var(--pink);
            animation: fadeUp 0.4s ease 0.15s both;
        }
        .complete-overlay .ov-xp {
            font-size: 26px;
            font-weight: 900;
            color: var(--pink-deep);
            animation: fadeUp 0.4s ease 0.28s both;
        }
        @keyframes checkBounce {
            0%   { transform: scale(0) rotate(-20deg); opacity: 0; }
            100% { transform: scale(1) rotate(0deg);   opacity: 1; }
        }
        @keyframes fadeUp {
            0%   { transform: translateY(10px); opacity: 0; }
            100% { transform: translateY(0);    opacity: 1; }
        }

        /* ========== フッター ========== */
        .page-footer {
            text-align: center;
            padding: 20px 24px 32px;
            font-size: 10px;
            letter-spacing: 3px;
            text-transform: uppercase;
            color: var(--border);
        }

        /* ========== レスポンシブ ========== */
        @media (max-width: 600px) {
            .stats-bar { flex-wrap: wrap; }
            .quest-card { flex-direction: column; gap: 14px; }
            .quest-image { width: 64px; height: 64px; }
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
        <a href="../index.php">旅のホームへ</a>
        <a href="quest_list.php">クエスト一覧</a>
        <a href="../auth/logout.php">ログアウト</a>
    </nav>
</header>

<!-- HERO AREA -->
<section class="hero">
    <p class="hero-eyebrow">My Quests</p>
    <h1 class="hero-title">わたしの<em>旅クエスト</em></h1>
    <p class="hero-desc">受注した旅のクエスト一覧</p>
</section>

<!-- スタッツ -->
<div class="stats-bar">
    <div class="stat-chip">
        <span class="num"><?= count($my_quests) ?></span> 受注合計
    </div>
    <div class="stat-chip">
        <span class="num"><?= $pending_count ?></span> 進行中
    </div>
    <div class="stat-chip completed">
        <span class="num"><?= $completed_count ?></span> 達成済み
    </div>
</div>

<main>
    <!-- フラッシュメッセージ -->
    <?php if ($flash_success): ?>
        <div class="flash success">
            ✓ <?= htmlspecialchars($flash_success, ENT_QUOTES) ?>
            <?php if ($flash_xp): ?>
                <span class="flash-xp"><?= htmlspecialchars($flash_xp, ENT_QUOTES) ?></span>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    <?php if ($flash_levelup): ?>
        <div class="flash levelup">
            ⬆ <?= htmlspecialchars($flash_levelup, ENT_QUOTES) ?>
        </div>
    <?php endif; ?>
    <?php if ($flash_info): ?>
        <div class="flash info">ℹ <?= htmlspecialchars($flash_info, ENT_QUOTES) ?></div>
    <?php endif; ?>
    <?php if ($flash_error): ?>
        <div class="flash error">✕ <?= htmlspecialchars($flash_error, ENT_QUOTES) ?></div>
    <?php endif; ?>

    <?php if (empty($my_quests)): ?>
        <div class="no-quests">
            <div class="icon">🗺️</div>
            <p>まだクエストを受注していません</p>
            <a href="quest_list.php" class="btn-link">クエスト一覧へ</a>
        </div>

    <?php else: ?>
        <!-- 進行中クエスト -->
        <?php $pending = array_filter($my_quests, fn($q) => $q['status'] === 'pending'); ?>
        <?php if ($pending): ?>
        <div class="section-title"><span class="section-icon">✦</span> 進行中のクエスト</div>
        <div class="quest-list">
            <?php foreach ($pending as $quest): ?>
            <div class="quest-card" id="card-<?= (int)$quest['user_quest_id'] ?>">

                <!-- 達成オーバーレイ -->
                <div class="complete-overlay">
                    <div class="ov-icon">🏆</div>
                    <div class="ov-label">Quest Complete!</div>
                    <div class="ov-xp">+<?= (int)$quest['xp'] ?> XP</div>
                </div>

                <!-- 画像 -->
                <div class="quest-image">
                    <?php if (!empty($quest['image_url'])): ?>
                        <img
                            src="<?= htmlspecialchars($quest['image_url'], ENT_QUOTES) ?>"
                            alt="<?= htmlspecialchars($quest['title'], ENT_QUOTES) ?>"
                        >
                    <?php else: ?>
                        🗺️
                    <?php endif; ?>
                </div>

                <!-- 情報 -->
                <div class="quest-info">
                    <div class="quest-header">
                        <span class="quest-title">
                            <?= htmlspecialchars($quest['title'], ENT_QUOTES) ?>
                        </span>
                        <span class="badge-difficulty <?= htmlspecialchars($quest['difficulty'], ENT_QUOTES) ?>">
                            <?= htmlspecialchars($quest['difficulty'], ENT_QUOTES) ?>
                        </span>
                        <span class="badge-xp">+<?= (int)$quest['xp'] ?> XP</span>
                    </div>

                    <?php if (!empty($quest['location'])): ?>
                    <div class="quest-location">
                        <?= htmlspecialchars($quest['location'], ENT_QUOTES) ?>
                    </div>
                    <?php endif; ?>

                    <div class="quest-description">
                        <?= htmlspecialchars($quest['description'], ENT_QUOTES) ?>
                    </div>

                    <!-- 達成ボタン -->
                    <form method="post" action="complete_quest.php">
                        <input type="hidden" name="quest_id" value="<?= (int)$quest['quest_id'] ?>">
                        <button
                            type="button"
                            class="btn-complete"
                            data-xp="<?= (int)$quest['xp'] ?>"
                            data-card="card-<?= (int)$quest['user_quest_id'] ?>"
                            onclick="completeQuest(this)"
                        >達成する</button>
                    </form>
                </div>

            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- 達成済みクエスト -->
        <?php $completed = array_filter($my_quests, fn($q) => $q['status'] === 'completed'); ?>
        <?php if ($completed): ?>
        <div class="section-title"><span class="section-icon">✓</span> 達成済みクエスト</div>
        <div class="quest-list">
            <?php foreach ($completed as $quest): ?>
            <div class="quest-card completed">
                <div class="ribbon-completed">DONE</div>

                <div class="quest-image">
                    <?php if (!empty($quest['image_url'])): ?>
                        <img
                            src="<?= htmlspecialchars($quest['image_url'], ENT_QUOTES) ?>"
                            alt="<?= htmlspecialchars($quest['title'], ENT_QUOTES) ?>"
                        >
                    <?php else: ?>
                        🗺️
                    <?php endif; ?>
                </div>

                <div class="quest-info">
                    <div class="quest-header">
                        <span class="quest-title">
                            <?= htmlspecialchars($quest['title'], ENT_QUOTES) ?>
                        </span>
                        <span class="badge-difficulty <?= htmlspecialchars($quest['difficulty'], ENT_QUOTES) ?>">
                            <?= htmlspecialchars($quest['difficulty'], ENT_QUOTES) ?>
                        </span>
                        <span class="badge-xp">+<?= (int)$quest['xp'] ?> XP</span>
                    </div>

                    <?php if (!empty($quest['location'])): ?>
                    <div class="quest-location">
                        <?= htmlspecialchars($quest['location'], ENT_QUOTES) ?>
                    </div>
                    <?php endif; ?>

                    <div class="quest-description">
                        <?= htmlspecialchars($quest['description'], ENT_QUOTES) ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

    <?php endif; ?>
</main>

<footer class="page-footer">Travel Quest &nbsp;·&nbsp; 旅 so sweet</footer>

<script>
function completeQuest(btn) {
    var card    = document.getElementById(btn.dataset.card);
    var overlay = card.querySelector('.complete-overlay');
    var xp      = btn.dataset.xp;

    overlay.classList.add('show');
    spawnXpPopup(btn, '+' + xp + ' XP');

    setTimeout(function () {
        btn.closest('form').submit();
    }, 2200);
}

function spawnXpPopup(btn, text) {
    var rect  = btn.getBoundingClientRect();
    var popup = document.createElement('div');
    popup.className   = 'xp-popup';
    popup.textContent = text;
    popup.style.left  = (rect.left + rect.width / 2 - 50) + 'px';
    popup.style.top   = (rect.top + window.scrollY - 10) + 'px';
    document.body.appendChild(popup);
    popup.addEventListener('animationend', function () { popup.remove(); });
}
</script>

</body>
</html>
