<?php
// 各ページの先頭で require_once して使う認証チェック共通ファイル
// 例: require_once __DIR__ . '/../auth/auth_check.php';

require_once __DIR__ . '/../config/db.php';

session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/auth/login.php');
    exit;
}
