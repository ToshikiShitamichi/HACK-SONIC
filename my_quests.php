<?php
session_start();
require_once __DIR__ . '/db.php';

$user_id = 1; // 固定（セッション実装後に差し替え）

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
    <title>マイクエスト | HACK SONIC</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Helvetica Neue', Arial, sans-serif;
            background: #0f0f1a;
            color: #e0e0e0;
            min-height: 100vh;
        }

        /* ========== ヘッダー ========== */
        header {
            background: #1a1a2e;
            border-bottom: 2px solid #4a4aff;
            padding: 16px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        header h1 {
            font-size: 22px;
            color: #a0a0ff;
            letter-spacing: 2px;
        }
        header .subtitle {
            font-size: 13px;
            color: #666;
            margin-top: 2px;
        }
        .header-nav a {
            color: #6060cc;
            text-decoration: none;
            font-size: 13px;
            letter-spacing: 1px;
            transition: color 0.2s;
        }
        .header-nav a:hover {
            color: #a0a0ff;
        }

        /* ========== HERO AREA ========== */
        .hero {
            position: relative;
            text-align: center;
            padding: 52px 24px 44px;
            overflow: hidden;
            background: linear-gradient(180deg, #0d0d2b 0%, #0f0f1a 100%);
        }
        .hero::before {
            content: '';
            position: absolute;
            inset: 0;
            background-image:
                radial-gradient(circle, rgba(160, 160, 255, 0.16) 1px, transparent 1px),
                radial-gradient(circle, rgba(255, 255, 255, 0.06) 1px, transparent 1px);
            background-size: 60px 60px, 28px 28px;
            background-position: 0 0, 14px 14px;
            animation: starDrift 24s linear infinite;
            pointer-events: none;
        }
        @keyframes starDrift {
            from { transform: translateY(0); }
            to   { transform: translateY(28px); }
        }
        .hero::after {
            content: '';
            position: absolute;
            bottom: 0; left: 0; right: 0;
            height: 2px;
            background: linear-gradient(90deg, transparent, #4a4aff, #a0a0ff, #4a4aff, transparent);
            animation: glowPulse 3s ease-in-out infinite;
            pointer-events: none;
        }
        @keyframes glowPulse {
            0%, 100% { opacity: 0.4; }
            50%      { opacity: 1; }
        }

        .hero-title {
            position: relative;
            font-size: clamp(24px, 4.5vw, 44px);
            font-weight: 900;
            letter-spacing: 6px;
            text-transform: uppercase;
            background: linear-gradient(135deg, #ffffff 0%, #c0c0ff 35%, #6060ff 65%, #ffffff 100%);
            background-size: 200% auto;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            animation: titleShine 4s linear infinite;
            margin-bottom: 12px;
        }
        @keyframes titleShine {
            0%   { background-position: 0% center; }
            100% { background-position: 200% center; }
        }

        .hero-desc {
            position: relative;
            font-size: 13px;
            color: #6868a8;
            letter-spacing: 2px;
            line-height: 1.7;
        }
        .hero-desc .ai-tag {
            display: inline-block;
            background: rgba(74, 74, 255, 0.15);
            border: 1px solid rgba(74, 74, 255, 0.4);
            color: #9090ff;
            font-size: 11px;
            padding: 1px 8px;
            border-radius: 3px;
            letter-spacing: 1px;
            margin-bottom: 8px;
            text-transform: uppercase;
        }

        /* ========== スタッツバー ========== */
        .stats-bar {
            max-width: 960px;
            margin: 28px auto 0;
            padding: 0 16px;
            display: flex;
            gap: 12px;
        }
        .stat-chip {
            background: #1a1a2e;
            border: 1px solid #2a2a4a;
            border-radius: 8px;
            padding: 10px 20px;
            font-size: 13px;
            color: #888;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .stat-chip .num {
            font-size: 20px;
            font-weight: 900;
            color: #a0a0ff;
        }
        .stat-chip.completed .num { color: #4cff88; }

        /* ========== メイン ========== */
        main {
            max-width: 960px;
            margin: 0 auto;
            padding: 24px 16px 48px;
        }

        /* ========== フラッシュメッセージ ========== */
        .flash {
            padding: 12px 18px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: bold;
            letter-spacing: 0.5px;
            margin-bottom: 24px;
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
            background: rgba(76, 255, 136, 0.1);
            border: 1px solid rgba(76, 255, 136, 0.4);
            color: #4cff88;
        }
        .flash.info {
            background: rgba(160, 160, 255, 0.1);
            border: 1px solid rgba(74, 74, 255, 0.4);
            color: #a0a0ff;
        }
        .flash.error {
            background: rgba(244, 67, 54, 0.1);
            border: 1px solid rgba(244, 67, 54, 0.4);
            color: #f44336;
        }
        .flash.levelup {
            background: rgba(255, 215, 0, 0.1);
            border: 1px solid rgba(255, 215, 0, 0.5);
            color: #ffd700;
            text-shadow: 0 0 12px rgba(255, 215, 0, 0.4);
        }
        .flash-xp {
            margin-left: 10px;
            font-size: 16px;
            color: #ffd700;
            text-shadow: 0 0 10px rgba(255, 215, 0, 0.6);
        }

        /* ========== セクション見出し ========== */
        .section-title {
            font-size: 13px;
            font-weight: bold;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: #555;
            margin-bottom: 14px;
            padding-bottom: 8px;
            border-bottom: 1px solid #1e1e3a;
        }
        .section-title span {
            color: #4a4aff;
            margin-right: 6px;
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
            background: #1a1a2e;
            border: 1px solid #2a2a4a;
            border-radius: 12px;
            padding: 20px;
            display: flex;
            gap: 20px;
            overflow: hidden;
            transition: transform 0.28s ease, border-color 0.28s ease, box-shadow 0.28s ease;
        }
        .quest-card::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(74, 74, 255, 0.05) 0%, transparent 55%);
            opacity: 0;
            transition: opacity 0.28s ease;
            pointer-events: none;
            border-radius: 12px;
        }
        .quest-card:hover {
            transform: translateY(-5px);
            border-color: #4a4aff;
            box-shadow: 0 12px 36px rgba(74, 74, 255, 0.24), 0 0 0 1px rgba(74, 74, 255, 0.15);
        }
        .quest-card:hover::before {
            opacity: 1;
        }

        /* completed カードはトーンを落とす */
        .quest-card.completed {
            opacity: 0.55;
            filter: grayscale(0.3);
        }
        .quest-card.completed:hover {
            transform: none;
            box-shadow: none;
            border-color: #2a2a4a;
        }

        /* 完了リボン */
        .ribbon-completed {
            position: absolute;
            top: 12px; right: -28px;
            background: #4cff88;
            color: #0a1a0a;
            font-size: 10px;
            font-weight: 900;
            letter-spacing: 1px;
            padding: 4px 36px;
            transform: rotate(35deg);
        }

        /* ========== クエスト画像 ========== */
        .quest-image {
            width: 90px;
            height: 90px;
            border-radius: 8px;
            flex-shrink: 0;
            background: #2a2a3e;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 30px;
            overflow: hidden;
        }
        .quest-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 8px;
        }

        /* ========== クエスト情報 ========== */
        .quest-info {
            flex: 1;
            min-width: 0;
        }

        .quest-header {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            margin-bottom: 6px;
        }

        .quest-title {
            font-size: 16px;
            font-weight: bold;
            color: #ffffff;
        }

        .badge-difficulty {
            font-size: 10px;
            font-weight: bold;
            padding: 2px 9px;
            border-radius: 20px;
            text-transform: uppercase;
            letter-spacing: 1px;
            flex-shrink: 0;
        }
        .badge-difficulty.easy   { background: #1a3a1a; color: #4caf50; border: 1px solid #4caf50; }
        .badge-difficulty.normal { background: #3a2a00; color: #ffb300; border: 1px solid #ffb300; }
        .badge-difficulty.hard   { background: #3a1a1a; color: #f44336; border: 1px solid #f44336; }

        .badge-xp {
            font-size: 11px;
            font-weight: bold;
            padding: 2px 9px;
            border-radius: 20px;
            background: #1a1a3a;
            color: #a0a0ff;
            border: 1px solid #4a4aff;
            flex-shrink: 0;
        }

        .quest-location {
            font-size: 12px;
            color: #777;
            margin-bottom: 6px;
        }
        .quest-location::before { content: '📍 '; }

        .quest-description {
            font-size: 13px;
            color: #999;
            line-height: 1.6;
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
            background: linear-gradient(135deg, #1a4a1a 0%, #2a7a2a 48%, #1e6a1e 100%);
            color: #4cff88;
            border: 1px solid #4caf50;
            padding: 9px 26px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: bold;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            cursor: pointer;
            box-shadow:
                0 0 12px rgba(76, 175, 80, 0.35),
                inset 0 1px 0 rgba(255, 255, 255, 0.1);
            transition: box-shadow 0.2s ease, transform 0.12s ease;
        }
        .btn-complete::before {
            content: '';
            position: absolute;
            top: 0; left: -80%;
            width: 55%; height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transform: skewX(-18deg);
            animation: shimmer 4s ease infinite;
        }
        @keyframes shimmer {
            0%, 100% { left: -80%; }
            40%       { left: 130%; }
        }
        .btn-complete:hover {
            transform: translateY(-2px);
            box-shadow:
                0 0 24px rgba(76, 255, 136, 0.55),
                0 0 8px  rgba(76, 175, 80, 0.4),
                inset 0 1px 0 rgba(255, 255, 255, 0.15);
        }
        .btn-complete:active {
            transform: translateY(1px);
            box-shadow: 0 0 8px rgba(76, 175, 80, 0.3);
        }

        /* ========== 空状態 ========== */
        .no-quests {
            text-align: center;
            padding: 60px 0;
        }
        .no-quests .icon {
            font-size: 52px;
            margin-bottom: 16px;
            opacity: 0.4;
        }
        .no-quests p {
            color: #444;
            font-size: 15px;
            margin-bottom: 20px;
        }

        /* ========== クエストボードへ戻るリンク ========== */
        .btn-link {
            display: inline-block;
            background: transparent;
            color: #4a4aff;
            border: 1px solid #4a4aff;
            padding: 10px 28px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: bold;
            letter-spacing: 1px;
            text-transform: uppercase;
            text-decoration: none;
            transition: background 0.2s, color 0.2s, box-shadow 0.2s;
        }
        .btn-link:hover {
            background: rgba(74, 74, 255, 0.12);
            box-shadow: 0 0 16px rgba(74, 74, 255, 0.3);
        }

        /* ========== XP フロートポップアップ ========== */
        .xp-popup {
            position: fixed;
            pointer-events: none;
            font-size: 28px;
            font-weight: 900;
            color: #4cff88;
            text-shadow: 0 0 16px rgba(76, 255, 136, 0.8), 0 0 40px rgba(76, 255, 136, 0.4);
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

        /* 完了オーバーレイ */
        .complete-overlay {
            position: absolute;
            inset: 0;
            border-radius: 12px;
            background: rgba(8, 24, 8, 0.93);
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
            filter: drop-shadow(0 0 12px rgba(76, 255, 136, 0.7));
        }
        .complete-overlay .ov-label {
            font-size: 18px;
            font-weight: 900;
            letter-spacing: 3px;
            color: #fff;
            text-transform: uppercase;
            animation: fadeUp 0.4s ease 0.15s both;
        }
        .complete-overlay .ov-xp {
            font-size: 28px;
            font-weight: 900;
            color: #4cff88;
            text-shadow: 0 0 18px rgba(76, 255, 136, 0.7);
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
    </style>
</head>
<body>

<header>
    <div>
        <h1>⚔ HACK SONIC</h1>
        <div class="subtitle">マイクエスト</div>
    </div>
    <nav class="header-nav">
        <a href="quest_list.php">▶ クエストボードへ</a>
    </nav>
</header>

<!-- HERO AREA -->
<section class="hero">
    <h2 class="hero-title">My Quests</h2>
    <div class="hero-desc">
        <div class="ai-tag">AI Generated Plan</div><br>
        AIが提案した旅行プランをもとに生成された、あなたの冒険一覧です
    </div>
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
            <a href="quest_list.php" class="btn-link">クエストボードへ</a>
        </div>

    <?php else: ?>
        <!-- 進行中クエスト -->
        <?php $pending = array_filter($my_quests, fn($q) => $q['status'] === 'pending'); ?>
        <?php if ($pending): ?>
        <div class="section-title"><span>▶</span> 進行中のクエスト</div>
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
        <div class="section-title"><span>✓</span> 達成済みクエスト</div>
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
