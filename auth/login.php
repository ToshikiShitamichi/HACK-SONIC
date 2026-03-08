<?php
session_start();
require_once __DIR__ . '/../config/db.php';

// ログイン済みならトップへ
if (isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$error = isset($_GET['error']) && $_GET['error'] === '1';
?>
<!doctype html>
<html lang="ja">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>旅 so sweet</title>
    <style>
      *,
      *::before,
      *::after {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
      }

      :root {
        --pink:      #e8405c; /* いちごピンク */
        --pink-lt:   #f47090; /* 明るいピンク */
        --pink-pale: #fce8ec; /* 薄いピンク */
        --pink-deep: #c02848; /* 濃いピンク */
        --cream:     #fff8f4; /* クリーム */
        --white:     #ffffff;
        --dark:      #1e1218;
        --text:      #2a1a20;
        --muted:     #9a7885;
        --border:    #f0d0d8;
      }

      html { scroll-behavior: smooth; }

      body {
        font-family:
          "Helvetica Neue", Arial, "Hiragino Sans", sans-serif;
        background: var(--white);
        color: var(--text);
        -webkit-font-smoothing: antialiased;
        min-height: 100vh;
      }

      /* ============================================================
         上部フレームライン
      ============================================================ */
      .page-frame-top {
        position: fixed;
        top: 0; left: 0; right: 0;
        height: 4px;
        background: linear-gradient(
          90deg,
          var(--pink-pale), var(--pink), var(--pink-lt), var(--pink)
        );
        z-index: 100;
      }

      /* ============================================================
         POSTER SECTION
      ============================================================ */
      .poster {
        position: relative;
        min-height: 68vh;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        overflow: hidden;
        padding: 80px 24px 60px;
        background:
          radial-gradient(ellipse at 50% 0%,   rgba(252, 232, 236, 0.70) 0%, transparent 55%),
          radial-gradient(ellipse at 20% 100%,  rgba(252, 232, 236, 0.35) 0%, transparent 50%),
          var(--white);
      }

      /* イラストの線を濃く（img経由のSVGはCSSセレクタ非対応のためfilterで代用） */
      .poster-illust {
        position: absolute;
        left: 50%;
        top: 50%;
        transform: translateX(-38%) translateY(-50%);
        width: min(68%, 640px);
        height: auto;
        opacity: 0.12;
        pointer-events: none;
        user-select: none;
        filter: contrast(1.6) brightness(0.85);
      }

      /* 小さな装飾ドット */
      .poster::before {
        content: "";
        position: absolute;
        top: 56px; right: 48px;
        width: 6px; height: 6px;
        border-radius: 50%;
        background: var(--pink);
        box-shadow:
          14px 0  0 var(--pink-pale),
          28px 0  0 var(--pink),
          0  14px 0 var(--pink-pale),
          14px 14px 0 var(--pink),
          28px 14px 0 var(--pink-pale);
      }

      /* ============================================================
         鼻歌吹き出し
      ============================================================ */
      .speech-bubble {
        position: absolute;
        top: clamp(56px, 14%, 100px);
        right: clamp(16px, 10%, 80px);
        z-index: 3;
        width: clamp(120px, 18vw, 148px);
        height: clamp(120px, 18vw, 148px);
        max-width: 148px;
        max-height: 148px;
        border-radius: 50%;
        background: #fff8fa;
        border: 1.5px solid var(--pink-pale);
        box-shadow:
          0 4px 20px rgba(232, 64, 92, 0.12),
          inset 0 1px 0 rgba(255, 255, 255, 0.90);
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        text-align: center;
        gap: 4px;
        animation: bubbleFloat 3.2s ease-in-out infinite;
      }

      .speech-bubble p {
        font-size: clamp(12px, 1.6vw, 14px);
        font-weight: 700;
        line-height: 1.55;
        color: var(--pink);
        letter-spacing: 0.3px;
      }

      .speech-bubble .bubble-note {
        font-size: clamp(14px, 1.8vw, 16px);
        color: var(--pink-lt);
      }

      /* 吹き出しの三角ポインタ（ボーダー層） */
      .speech-bubble::before {
        content: "";
        position: absolute;
        bottom: -14px;
        left: 22%;
        width: 0; height: 0;
        border-right: 14px solid transparent;
        border-top: 14px solid var(--pink-pale);
      }

      /* 吹き出しの三角ポインタ（塗り層） */
      .speech-bubble::after {
        content: "";
        position: absolute;
        bottom: -11px;
        left: calc(22% + 1px);
        width: 0; height: 0;
        border-right: 12px solid transparent;
        border-top: 12px solid #fff8fa;
      }

      @keyframes bubbleFloat {
        0%, 100% { transform: translateY(0)    rotate(-1.5deg); }
        50%       { transform: translateY(-10px) rotate(1deg);   }
      }

      /* ============================================================
         ポスター内コンテンツ
      ============================================================ */
      .poster-inner {
        position: relative;
        z-index: 1;
        text-align: center;
        max-width: 860px;
        width: 100%;
      }

      .brand-row {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 20px;
        margin-bottom: 28px;
      }
      .brand-rule {
        flex: 1;
        max-width: 80px;
        height: 1px;
        background: linear-gradient(to right, transparent, var(--border));
      }
      .brand-rule.right {
        background: linear-gradient(to left, transparent, var(--border));
      }
      .brand-label {
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 4px;
        text-transform: uppercase;
        color: var(--pink);
      }

      .poster-title {
        display: flex;
        flex-direction: row;
        align-items: baseline;
        justify-content: center;
        gap: 16px;
        margin-bottom: 32px;
      }

      .title-kanji {
        font-size: clamp(56px, 10vw, 100px);
        font-weight: 900;
        line-height: 0.85;
        color: var(--pink);
        letter-spacing: -6px;
        text-shadow:
          0 2px 0 var(--pink-deep),
          0 6px 32px rgba(232, 64, 92, 0.18);
      }

      .title-sweet {
        font-size: clamp(26px, 5vw, 56px);
        font-weight: 200;
        letter-spacing: clamp(6px, 2vw, 18px);
        color: var(--dark);
        margin-top: 4px;
        transform: translateX(clamp(4px, 1vw, 12px));
      }

      .poster-tagline {
        font-size: 13px;
        color: var(--muted);
        letter-spacing: 3px;
        margin-bottom: 0;
      }

      /* ============================================================
         区切りライン
      ============================================================ */
      .section-divider {
        display: flex;
        align-items: center;
        gap: 0;
      }
      .divider-line {
        flex: 1;
        height: 1px;
        background: var(--border);
      }
      .divider-ornament {
        padding: 0 20px;
        font-size: 16px;
        color: var(--pink-lt);
        line-height: 1;
      }

      /* ============================================================
         LOGIN SECTION
      ============================================================ */
      .login-section {
        background:
          radial-gradient(ellipse at 50% 100%, rgba(252, 232, 236, 0.50) 0%, transparent 60%),
          var(--cream);
        padding: 56px 24px 72px;
      }

      .login-inner {
        max-width: 420px;
        margin: 0 auto;
      }

      .login-eyebrow {
        text-align: center;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 3px;
        text-transform: uppercase;
        color: var(--muted);
        margin-bottom: 32px;
      }

      /* フォーム */
      .login-form {
        display: flex;
        flex-direction: column;
        gap: 20px;
      }

      .field { display: flex; flex-direction: column; gap: 8px; }

      .field-label {
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 1px;
        text-transform: uppercase;
        color: var(--muted);
      }

      .field-input {
        width: 100%;
        padding: 14px 20px;
        border: 1.5px solid var(--border);
        border-radius: 50px;
        background: var(--white);
        font-size: 15px;
        color: var(--text);
        outline: none;
        -webkit-appearance: none;
        appearance: none;
        transition: border-color 0.2s ease, box-shadow 0.2s ease;
      }
      .field-input::placeholder { color: #dbbcc4; }
      .field-input:focus {
        border-color: var(--pink);
        box-shadow: 0 0 0 3px rgba(232, 64, 92, 0.10);
      }
      .field-input.is-error {
        border-color: #ff8080;
      }
      .field-input.is-error:focus {
        border-color: #d94040;
        box-shadow: 0 0 0 3px rgba(217, 64, 64, 0.10);
      }

      /* エラー表示 */
      .form-error {
        font-size: 13px;
        color: #d94040;
        background: #fff0f0;
        border: 1.5px solid #ffc8c8;
        border-radius: 12px;
        padding: 10px 14px;
        margin-bottom: 4px;
        text-align: center;
      }

      /* 旅をはじめる（ログイン） */
      .btn-primary {
        display: block;
        width: 100%;
        padding: 16px 24px;
        margin-top: 10px;
        background: linear-gradient(135deg, var(--pink-lt) 0%, var(--pink) 55%, var(--pink-deep) 100%);
        color: #fff;
        border: none;
        border-radius: 50px;
        font-size: 15px;
        font-weight: 800;
        letter-spacing: 2px;
        text-align: center;
        text-decoration: none;
        cursor: pointer;
        box-shadow:
          0 6px 28px rgba(232, 64, 92, 0.35),
          inset 0 1px 0 rgba(255, 255, 255, 0.25);
        transition: opacity 0.2s ease, transform 0.12s ease, box-shadow 0.2s ease;
      }
      .btn-primary:hover {
        opacity: 0.90;
        transform: translateY(-2px);
        box-shadow:
          0 10px 36px rgba(232, 64, 92, 0.45),
          inset 0 1px 0 rgba(255, 255, 255, 0.25);
      }
      .btn-primary:active {
        transform: translateY(0);
        box-shadow: 0 4px 16px rgba(232, 64, 92, 0.28);
      }

      /* ゲストでログインボタン */
      .btn-guest {
        display: block;
        width: 100%;
        padding: 14px 24px;
        margin-top: 14px;
        background: var(--white);
        color: var(--pink);
        border: 1.5px solid var(--border);
        border-radius: 50px;
        font-size: 14px;
        font-weight: 700;
        letter-spacing: 0.5px;
        text-align: center;
        text-decoration: none;
        cursor: pointer;
        transition: background 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease;
      }
      .btn-guest:hover {
        background: var(--pink-pale);
        border-color: var(--pink-lt);
        box-shadow: 0 2px 12px rgba(232, 64, 92, 0.12);
      }

      /* ============================================================
         ページ下部
      ============================================================ */
      .page-footer {
        text-align: center;
        padding: 20px 24px 32px;
        font-size: 10px;
        letter-spacing: 3px;
        text-transform: uppercase;
        color: var(--border);
        background: var(--cream);
      }

      /* ============================================================
         レスポンシブ
      ============================================================ */
      @media (max-width: 600px) {
        .poster { min-height: 55vh; padding: 72px 20px 48px; }
        .poster-illust {
          left: 50%;
          transform: translateX(-50%) translateY(-50%);
          width: min(85%, 380px);
          opacity: 0.08;
        }
        .speech-bubble {
          top: clamp(48px, 10%, 72px);
          right: clamp(12px, 5%, 24px);
          width: clamp(100px, 26vw, 120px);
          height: clamp(100px, 26vw, 120px);
        }
        .poster::before { right: 24px; }
        .login-section { padding: 40px 20px 56px; }
      }

      @media (max-width: 400px) {
        .title-kanji { letter-spacing: -4px; }
        .speech-bubble { display: none; }
      }
    </style>
  </head>
  <body>

    <!-- 上部フレームライン -->
    <div class="page-frame-top"></div>

    <!-- ============ POSTER ============ -->
    <section class="poster">

      <!-- イラスト（背景として薄く） -->
      <img class="poster-illust" src="../assets/images/hero.svg" alt="" aria-hidden="true" />

      <!-- 鼻歌吹き出し -->
      <div class="speech-bubble" aria-hidden="true">
        <p>Wow oh oh <span class="bubble-note">♪</span></p>
        <p>yeah yeah yeah</p>
      </div>

      <div class="poster-inner">

        <!-- ブランドラベル -->
        <div class="brand-row">
          <div class="brand-rule"></div>
          <p class="brand-label">Sweet Journey</p>
          <div class="brand-rule right"></div>
        </div>

        <!-- 大タイポグラフィ -->
        <h1 class="poster-title">
          <span class="title-kanji">旅</span>
          <span class="title-sweet">so sweet</span>
        </h1>

        <!-- タグライン -->
        <p class="poster-tagline">甘い気分で、旅に出よう。</p>

      </div>
    </section>

    <!-- ============ 区切り ============ -->
    <div class="section-divider">
      <div class="divider-line"></div>
      <div class="divider-ornament">✦</div>
      <div class="divider-line"></div>
    </div>

    <!-- ============ LOGIN ============ -->
    <section class="login-section">
      <div class="login-inner">

        <p class="login-eyebrow">Sign In</p>

        <?php if ($error): ?>
        <p class="form-error">メールアドレスまたはパスワードが正しくありません。</p>
        <?php endif; ?>

        <form class="login-form" action="login_process.php" method="post" novalidate>
          <div class="field">
            <label class="field-label" for="email">メールアドレス</label>
            <input
              class="field-input<?= $error ? ' is-error' : '' ?>" type="email" id="email" name="email"
              placeholder="tavi_so_sweet@example.com"
              autocomplete="email" required
            />
          </div>

          <div class="field">
            <label class="field-label" for="password">パスワード</label>
            <input
              class="field-input<?= $error ? ' is-error' : '' ?>" type="password" id="password" name="password"
              placeholder="••••••••"
              autocomplete="current-password" required
            />
          </div>

          <button class="btn-primary" type="submit">旅をはじめる</button>
        </form>

        <!-- ゲストでログイン -->
        <a class="btn-guest" href="guest_login.php">ゲストでログイン</a>

      </div>
    </section>

    <!-- フッター -->
    <footer class="page-footer">Travel Quest &nbsp;·&nbsp; 旅 so sweet</footer>

  </body>
</html>
