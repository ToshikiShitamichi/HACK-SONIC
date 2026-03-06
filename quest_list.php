<?php
require_once __DIR__ . '/db.php';

// ログインチェック（セッション実装後に有効化）
// session_start();
// if (!isset($_SESSION['user_id'])) {
//     header('Location: login.php');
//     exit;
// }
// $user_id = $_SESSION['user_id'];

// クエスト一覧を取得
$pdo    = get_db();
$stmt   = $pdo->query('SELECT * FROM quests ORDER BY difficulty DESC, xp DESC');
$quests = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>クエスト一覧 | HACK SONIC</title>
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
            gap: 12px;
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

        /* ========== HERO AREA ========== */
        .hero {
            position: relative;
            text-align: center;
            padding: 64px 24px 56px;
            overflow: hidden;
            background: linear-gradient(180deg, #0d0d2b 0%, #0f0f1a 100%);
        }

        /* 背景の星パターン */
        .hero::before {
            content: '';
            position: absolute;
            inset: 0;
            background-image:
                radial-gradient(circle, rgba(160, 160, 255, 0.18) 1px, transparent 1px),
                radial-gradient(circle, rgba(255, 255, 255, 0.07) 1px, transparent 1px);
            background-size: 60px 60px, 28px 28px;
            background-position: 0 0, 14px 14px;
            animation: starDrift 24s linear infinite;
            pointer-events: none;
        }
        @keyframes starDrift {
            from { transform: translateY(0); }
            to   { transform: translateY(28px); }
        }

        /* 下部のグロウライン */
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
            font-size: clamp(26px, 5vw, 52px);
            font-weight: 900;
            letter-spacing: 6px;
            text-transform: uppercase;
            background: linear-gradient(135deg, #ffffff 0%, #c0c0ff 35%, #6060ff 65%, #ffffff 100%);
            background-size: 200% auto;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            animation: titleShine 4s linear infinite;
            margin-bottom: 18px;
        }
        @keyframes titleShine {
            0%   { background-position: 0% center; }
            100% { background-position: 200% center; }
        }

        .hero-subtitle {
            position: relative;
            font-size: 14px;
            color: #7070aa;
            letter-spacing: 4px;
        }
        .hero-subtitle::before,
        .hero-subtitle::after {
            content: '━━━';
            color: #4a4aff;
            margin: 0 14px;
            opacity: 0.7;
        }

        /* ========== メインコンテンツ ========== */
        main {
            max-width: 960px;
            margin: 0 auto;
            padding: 32px 16px;
        }

        .page-title {
            font-size: 18px;
            color: #a0a0ff;
            margin-bottom: 24px;
            padding-bottom: 10px;
            border-bottom: 1px solid #2a2a4a;
        }

        /* ========== クエストカード ========== */
        .quest-list {
            display: flex;
            flex-direction: column;
            gap: 16px;
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

        /* ホバー時の内側グラデーション */
        .quest-card::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(74, 74, 255, 0.06) 0%, transparent 55%);
            opacity: 0;
            transition: opacity 0.28s ease;
            pointer-events: none;
            border-radius: 12px;
        }

        /* カードホバー：浮き上がり */
        .quest-card:hover {
            transform: translateY(-7px);
            border-color: #4a4aff;
            box-shadow:
                0 14px 40px rgba(74, 74, 255, 0.28),
                0 0 0 1px rgba(74, 74, 255, 0.18);
        }
        .quest-card:hover::before {
            opacity: 1;
        }

        /* ========== Quest Accepted オーバーレイ ========== */
        .accepted-overlay {
            position: absolute;
            inset: 0;
            border-radius: 12px;
            background: rgba(8, 8, 24, 0.93);
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
        .accepted-overlay.show {
            opacity: 1;
        }

        .accepted-check {
            font-size: 52px;
            line-height: 1;
            color: #4cff88;
            filter: drop-shadow(0 0 12px rgba(76, 255, 136, 0.6));
            animation: checkBounce 0.55s cubic-bezier(0.34, 1.56, 0.64, 1) both;
        }
        @keyframes checkBounce {
            0%   { transform: scale(0) rotate(-20deg); opacity: 0; }
            100% { transform: scale(1) rotate(0deg);   opacity: 1; }
        }

        .accepted-label {
            font-size: 20px;
            font-weight: 900;
            letter-spacing: 3px;
            color: #ffffff;
            text-transform: uppercase;
            animation: fadeUp 0.4s ease 0.15s both;
        }

        .accepted-xp {
            font-size: 30px;
            font-weight: 900;
            color: #ffd700;
            text-shadow: 0 0 20px rgba(255, 215, 0, 0.7);
            animation: fadeUp 0.4s ease 0.28s both;
        }

        @keyframes fadeUp {
            0%   { transform: translateY(10px); opacity: 0; }
            100% { transform: translateY(0);    opacity: 1; }
        }

        /* ========== XP フロートポップアップ ========== */
        .xp-popup {
            position: fixed;
            pointer-events: none;
            font-size: 30px;
            font-weight: 900;
            color: #ffd700;
            text-shadow:
                0 0 16px rgba(255, 215, 0, 0.9),
                0 0 40px rgba(255, 215, 0, 0.4);
            z-index: 9999;
            animation: xpFloat 1.7s cubic-bezier(0.22, 1, 0.36, 1) forwards;
            white-space: nowrap;
            transform-origin: center bottom;
        }
        @keyframes xpFloat {
            0%   { transform: translateY(0)    scale(0.4); opacity: 0; }
            18%  { transform: translateY(-22px) scale(1.3); opacity: 1; }
            55%  { transform: translateY(-70px) scale(1);   opacity: 1; }
            100% { transform: translateY(-130px) scale(0.7); opacity: 0; }
        }

        /* ========== クエスト画像 ========== */
        .quest-image {
            width: 100px;
            height: 100px;
            border-radius: 8px;
            object-fit: cover;
            flex-shrink: 0;
            background: #2a2a3e;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
        }

        /* ========== クエスト情報 ========== */
        .quest-info {
            flex: 1;
        }

        .quest-header {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 8px;
        }

        .quest-title {
            font-size: 17px;
            font-weight: bold;
            color: #ffffff;
        }

        /* difficulty バッジ */
        .badge-difficulty {
            font-size: 11px;
            font-weight: bold;
            padding: 2px 10px;
            border-radius: 20px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .badge-difficulty.easy   { background: #1a3a1a; color: #4caf50; border: 1px solid #4caf50; }
        .badge-difficulty.normal { background: #3a2a00; color: #ffb300; border: 1px solid #ffb300; }
        .badge-difficulty.hard   { background: #3a1a1a; color: #f44336; border: 1px solid #f44336; }

        /* XP バッジ */
        .badge-xp {
            font-size: 12px;
            font-weight: bold;
            padding: 2px 10px;
            border-radius: 20px;
            background: #1a1a3a;
            color: #a0a0ff;
            border: 1px solid #4a4aff;
        }

        /* 場所 */
        .quest-location {
            font-size: 13px;
            color: #888;
            margin-bottom: 8px;
        }
        .quest-location::before {
            content: '📍 ';
        }

        /* 説明文 */
        .quest-description {
            font-size: 14px;
            color: #aaa;
            line-height: 1.6;
            margin-bottom: 14px;
        }

        /* ========== ゲーム風受注ボタン ========== */
        .btn-accept {
            position: relative;
            overflow: hidden;
            background: linear-gradient(135deg, #2828bb 0%, #5533ee 48%, #4a4aff 100%);
            color: #fff;
            border: 1px solid #7070ff;
            padding: 10px 28px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: bold;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            cursor: pointer;
            box-shadow:
                0 0 14px rgba(74, 74, 255, 0.45),
                inset 0 1px 0 rgba(255, 255, 255, 0.18);
            transition: box-shadow 0.2s ease, transform 0.12s ease;
        }

        /* シマー（光の流れ） */
        .btn-accept::before {
            content: '';
            position: absolute;
            top: 0;
            left: -80%;
            width: 55%;
            height: 100%;
            background: linear-gradient(
                90deg,
                transparent,
                rgba(255, 255, 255, 0.32),
                transparent
            );
            transform: skewX(-18deg);
            animation: shimmer 3.5s ease infinite;
        }
        @keyframes shimmer {
            0%, 100% { left: -80%; }
            45%       { left: 130%; }
        }

        .btn-accept:hover {
            transform: translateY(-2px);
            box-shadow:
                0 0 28px rgba(100, 100, 255, 0.75),
                0 0 10px rgba(160, 160, 255, 0.45),
                inset 0 1px 0 rgba(255, 255, 255, 0.22);
        }
        .btn-accept:active {
            transform: translateY(1px);
            box-shadow: 0 0 10px rgba(74, 74, 255, 0.4);
        }

        /* ========== クエスト0件 ========== */
        .no-quests {
            text-align: center;
            color: #555;
            padding: 60px 0;
            font-size: 16px;
        }
    </style>
</head>
<body>

<header>
    <div>
        <h1>⚔ HACK SONIC</h1>
        <div class="subtitle">クエスト一覧</div>
    </div>
</header>

<!-- HERO AREA -->
<section class="hero">
    <h2 class="hero-title">Travel Quest Board</h2>
    <p class="hero-subtitle">旅に出れば、誰でも勇者になる</p>
</section>

<main>
    <div class="page-title">受注可能なクエスト（<?= count($quests) ?>件）</div>

    <div class="quest-list">
        <?php if (empty($quests)): ?>
            <div class="no-quests">クエストが存在しません</div>

        <?php else: ?>
            <?php foreach ($quests as $quest): ?>
            <div class="quest-card">

                <!-- Quest Accepted オーバーレイ -->
                <div class="accepted-overlay">
                    <div class="accepted-check">✓</div>
                    <div class="accepted-label">Quest Accepted!</div>
                    <div class="accepted-xp">+<?= (int)$quest['xp'] ?> XP</div>
                </div>

                <!-- 画像 -->
                <div class="quest-image">
                    <?php if (!empty($quest['image_url'])): ?>
                        <img
                            src="<?= htmlspecialchars($quest['image_url'], ENT_QUOTES) ?>"
                            alt="<?= htmlspecialchars($quest['title'], ENT_QUOTES) ?>"
                            style="width:100%;height:100%;object-fit:cover;border-radius:8px;"
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
                        <span class="badge-xp">
                            +<?= (int)$quest['xp'] ?> XP
                        </span>
                    </div>

                    <?php if (!empty($quest['location'])): ?>
                    <div class="quest-location">
                        <?= htmlspecialchars($quest['location'], ENT_QUOTES) ?>
                    </div>
                    <?php endif; ?>

                    <div class="quest-description">
                        <?= htmlspecialchars($quest['description'], ENT_QUOTES) ?>
                    </div>

                    <!-- 受注ボタン -->
                    <form method="post" action="accept_quest.php">
                        <input type="hidden" name="quest_id" value="<?= (int)$quest['id'] ?>">
                        <button
                            type="button"
                            class="btn-accept"
                            data-xp="<?= (int)$quest['xp'] ?>"
                            onclick="acceptQuest(this)"
                        >クエスト受注</button>
                    </form>
                </div>

            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</main>

<script>
function acceptQuest(btn) {
    var card    = btn.closest('.quest-card');
    var overlay = card.querySelector('.accepted-overlay');
    var xp      = btn.dataset.xp;

    // カードのオーバーレイを表示
    overlay.classList.add('show');

    // XP ポップアップをボタン位置から飛ばす
    spawnXpPopup(btn, '+' + xp + ' XP');

    // 2秒後にフォームを送信
    setTimeout(function () {
        btn.closest('form').submit();
    }, 2000);
}

function spawnXpPopup(btn, text) {
    var rect  = btn.getBoundingClientRect();
    var popup = document.createElement('div');
    popup.className   = 'xp-popup';
    popup.textContent = text;
    popup.style.left  = (rect.left + rect.width / 2 - 60) + 'px';
    popup.style.top   = (rect.top + window.scrollY - 10) + 'px';
    document.body.appendChild(popup);
    popup.addEventListener('animationend', function () {
        popup.remove();
    });
}
</script>

</body>
</html>
