<?php
session_start();
require_once __DIR__ . '/../config/db.php';

// ログイン済みならトップへ
if (isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}
?>
<!doctype html>
<html lang="ja">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>ゲストログイン | 旅 so sweet</title>
    <link rel="stylesheet" href="../assets/css/login.css" />
  </head>
  <body>
    <div class="layout">
      <!-- 左カラム：ヒーロー画像 -->
      <div class="col-hero">
        <img src="../assets/images/hero.svg" alt="旅のイメージ" class="hero-image" />
      </div>

      <!-- 右カラム -->
      <div class="col-form">
        <div class="form-card">
          <header class="form-header">
            <p class="brand-label">Tavi So Sweet</p>
            <h1 class="title">
              <span class="title-kanji">旅</span>
              <span class="title-roman"> So Sweet</span>
            </h1>
            <p class="subtitle">次の旅は、ちょっと甘くて特別。</p>
          </header>

          <div class="guest-description">
            <p>登録不要で、すぐに旅クエストを体験できます。</p>
          </div>

          <a class="btn-submit" href="guest_login.php">ゲストでログインする</a>

          <p class="form-link">
            アカウントをお持ちの方は<a href="login.php">ログイン</a>
          </p>
        </div>
      </div>
    </div>
  </body>
</html>
