<?php
require_once __DIR__ . '/../auth/auth_check.php';
require_once __DIR__ . '/../config/db.php';

$user_id = $_SESSION['user_id'];

// クエスト一覧をログインユーザーの受注状況と一緒に取得
$pdo  = get_db();
$stmt = $pdo->prepare(
    'SELECT q.*,
            uq.status AS my_status
     FROM quests q
     LEFT JOIN user_quests uq ON uq.quest_id = q.id AND uq.user_id = ?
     ORDER BY q.difficulty DESC, q.xp DESC'
);
$stmt->execute([$user_id]);
$quests = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>クエスト一覧 | 旅 so sweet</title>
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

        .header-nav .nav-active {
            color: var(--pink);
        }

        /* ========== HERO AREA ========== */
        .hero {
            position: relative;
            text-align: center;
            padding: 56px 24px 48px;
            overflow: hidden;
            background:
                radial-gradient(ellipse at 50% 0%, rgba(252, 232, 236, 0.80) 0%, transparent 60%),
                var(--white);
            border-bottom: 1px solid var(--border);
        }

        /* 装飾ドット */
        .hero::before {
            content: '';
            position: absolute;
            top: 32px; right: 56px;
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
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
        }
        .hero-eyebrow::before,
        .hero-eyebrow::after {
            content: '';
            width: 40px;
            height: 1px;
            background: var(--border);
        }

        .hero-title {
            font-size: clamp(28px, 5vw, 52px);
            font-weight: 900;
            color: var(--text);
            margin-bottom: 12px;
            letter-spacing: -1px;
        }
        .hero-title em {
            color: var(--pink);
            font-style: normal;
        }

        .hero-subtitle {
            font-size: 13px;
            color: var(--muted);
            letter-spacing: 3px;
        }

        /* ========== メインコンテンツ ========== */
        main {
            max-width: 900px;
            margin: 0 auto;
            padding: 32px 16px 64px;
        }

        .page-section-title {
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 3px;
            text-transform: uppercase;
            color: var(--muted);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .page-section-title::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--border);
        }

        /* ========== クエストカード ========== */
        .quest-list {
            display: flex;
            flex-direction: column;
            gap: 16px;
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
            transform: translateY(-5px);
            border-color: var(--pink-lt);
            box-shadow: 0 12px 36px rgba(232, 64, 92, 0.14);
        }
        .quest-card:hover::before {
            opacity: 1;
        }

        /* ========== Quest Accepted オーバーレイ ========== */
        .accepted-overlay {
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
        .accepted-overlay.show { opacity: 1; }

        .accepted-check {
            font-size: 52px;
            line-height: 1;
            color: var(--pink);
            filter: drop-shadow(0 0 12px rgba(232, 64, 92, 0.4));
            animation: checkBounce 0.55s cubic-bezier(0.34, 1.56, 0.64, 1) both;
        }
        @keyframes checkBounce {
            0%   { transform: scale(0) rotate(-20deg); opacity: 0; }
            100% { transform: scale(1) rotate(0deg);   opacity: 1; }
        }

        .accepted-label {
            font-size: 18px;
            font-weight: 900;
            letter-spacing: 3px;
            color: var(--pink);
            animation: fadeUp 0.4s ease 0.15s both;
        }

        .accepted-xp {
            font-size: 28px;
            font-weight: 900;
            color: var(--pink-deep);
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
            font-size: 28px;
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

        /* ========== クエスト画像 ========== */
        .quest-image {
            width: 96px;
            height: 96px;
            border-radius: 14px;
            flex-shrink: 0;
            background: var(--pink-pale);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
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
            font-size: 16px;
            font-weight: 800;
            color: var(--text);
        }

        /* difficulty バッジ */
        .badge-difficulty {
            font-size: 10px;
            font-weight: 700;
            padding: 3px 10px;
            border-radius: 50px;
            letter-spacing: 1px;
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

        /* XP バッジ */
        .badge-xp {
            font-size: 11px;
            font-weight: 700;
            padding: 3px 10px;
            border-radius: 50px;
            background: var(--pink-pale);
            color: var(--pink);
            border: 1px solid var(--border);
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
        }

        /* ========== 受注ボタン ========== */
        .btn-accept {
            position: relative;
            overflow: hidden;
            background: linear-gradient(135deg, var(--pink-lt) 0%, var(--pink) 55%, var(--pink-deep) 100%);
            color: #fff;
            border: none;
            padding: 10px 26px;
            border-radius: 50px;
            font-size: 13px;
            font-weight: 800;
            letter-spacing: 1.5px;
            cursor: pointer;
            box-shadow: 0 6px 20px rgba(232, 64, 92, 0.32), inset 0 1px 0 rgba(255, 255, 255, 0.25);
            transition: opacity 0.2s ease, transform 0.12s ease, box-shadow 0.2s ease;
        }
        .btn-accept::before {
            content: '';
            position: absolute;
            top: 0; left: -80%;
            width: 55%; height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.28), transparent);
            transform: skewX(-18deg);
            animation: shimmer 3.5s ease infinite;
        }
        @keyframes shimmer {
            0%, 100% { left: -80%; }
            45%       { left: 130%; }
        }
        .btn-accept:hover {
            opacity: 0.92;
            transform: translateY(-2px);
            box-shadow: 0 10px 28px rgba(232, 64, 92, 0.42), inset 0 1px 0 rgba(255, 255, 255, 0.25);
        }
        .btn-accept:active {
            transform: translateY(0);
            box-shadow: 0 4px 12px rgba(232, 64, 92, 0.28);
        }

        /* ========== 受注済み・完了済みカード ========== */
        .quest-card.is-accepted {
            opacity: 0.75;
            border-color: #f0d0d8;
        }
        .quest-card.is-accepted:hover {
            transform: none;
            box-shadow: 0 4px 16px rgba(232, 64, 92, 0.06);
            border-color: #f0d0d8;
        }
        .quest-card.is-accepted::before { display: none; }

        .quest-card.is-done {
            opacity: 0.50;
            filter: saturate(0.5);
        }
        .quest-card.is-done:hover {
            transform: none;
            box-shadow: 0 4px 16px rgba(232, 64, 92, 0.06);
            border-color: #f0d0d8;
        }
        .quest-card.is-done::before { display: none; }

        /* ステータスバッジ */
        .badge-status-accepted {
            font-size: 10px;
            font-weight: 700;
            padding: 3px 12px;
            border-radius: 50px;
            background: var(--pink-pale);
            color: var(--pink);
            border: 1px solid var(--pink-lt);
            letter-spacing: 0.5px;
        }
        .badge-status-done {
            font-size: 10px;
            font-weight: 700;
            padding: 3px 12px;
            border-radius: 50px;
            background: #e8f5e9;
            color: #388e3c;
            border: 1px solid #a5d6a7;
            letter-spacing: 0.5px;
        }

        /* 無効ボタン */
        .btn-disabled {
            display: inline-block;
            padding: 10px 26px;
            border-radius: 50px;
            font-size: 13px;
            font-weight: 700;
            letter-spacing: 1px;
            cursor: default;
            border: 1.5px solid var(--border);
            background: transparent;
            color: var(--muted);
        }

        /* ========== クエスト0件 ========== */
        .no-quests {
            text-align: center;
            color: var(--muted);
            padding: 60px 0;
            font-size: 15px;
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
        <a href="my_quests.php">マイクエスト</a>
        <a href="../auth/logout.php">ログアウト</a>
    </nav>
</header>

<!-- HERO AREA -->
<section class="hero">
    <p class="hero-eyebrow">Travel Quest Board</p>
    <h1 class="hero-title">旅の<em>クエスト</em>一覧</h1>
    <p class="hero-subtitle">旅に出れば、誰でも旅人になる</p>
</section>

<main>
    <div class="page-section-title">受注可能なクエスト（<?= count($quests) ?>件）</div>

    <div class="quest-list">
        <?php if (empty($quests)): ?>
            <div class="no-quests">クエストが存在しません</div>

        <?php else: ?>
            <?php foreach ($quests as $quest): ?>
            <?php
                $my_status = $quest['my_status'] ?? null;
                $card_class = '';
                if ($my_status === 'pending')   $card_class = ' is-accepted';
                if ($my_status === 'completed') $card_class = ' is-done';
            ?>
            <div class="quest-card<?= $card_class ?>">

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
                        <?php if ($my_status === 'pending'): ?>
                            <span class="badge-status-accepted">受注済み</span>
                        <?php elseif ($my_status === 'completed'): ?>
                            <span class="badge-status-done">完了済み</span>
                        <?php endif; ?>
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
                    <?php if ($my_status === null): ?>
                    <form method="post" action="accept_quest.php">
                        <input type="hidden" name="quest_id" value="<?= (int)$quest['id'] ?>">
                        <button
                            type="button"
                            class="btn-accept"
                            data-xp="<?= (int)$quest['xp'] ?>"
                            onclick="acceptQuest(this)"
                        >クエスト受注</button>
                    </form>
                    <?php elseif ($my_status === 'pending'): ?>
                    <span class="btn-disabled">受注済み</span>
                    <?php else: ?>
                    <span class="btn-disabled">完了済み</span>
                    <?php endif; ?>
                </div>

            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</main>

<footer class="page-footer">Travel Quest &nbsp;·&nbsp; 旅 so sweet</footer>

<script>
function acceptQuest(btn) {
    var card    = btn.closest('.quest-card');
    var overlay = card.querySelector('.accepted-overlay');
    var xp      = btn.dataset.xp;

    overlay.classList.add('show');
    spawnXpPopup(btn, '+' + xp + ' XP');

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
    popup.addEventListener('animationend', function () { popup.remove(); });
}
</script>

</body>
</html>
