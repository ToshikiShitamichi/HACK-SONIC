<?php
require_once __DIR__ . '/../auth/auth_check.php';
require_once __DIR__ . '/../config/db.php';

// POST 以外は弾く
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: quest_list.php');
    exit;
}

$quest_id = isset($_POST['quest_id']) ? (int)$_POST['quest_id'] : 0;
$user_id  = $_SESSION['user_id'];

if ($quest_id <= 0) {
    header('Location: quest_list.php');
    exit;
}

$pdo = get_db();

// quest が存在するか確認
$stmt = $pdo->prepare('SELECT id FROM quests WHERE id = ?');
$stmt->execute([$quest_id]);
if (!$stmt->fetch()) {
    $_SESSION['flash_error'] = 'クエストが見つかりません。';
    header('Location: quest_list.php');
    exit;
}

// INSERT IGNORE で重複受注を防ぐ（UNIQUE KEY uq_user_quest が保護）
$stmt = $pdo->prepare(
    'INSERT IGNORE INTO user_quests (user_id, quest_id, status) VALUES (?, ?, ?)'
);
$stmt->execute([$user_id, $quest_id, 'pending']);

if ($stmt->rowCount() > 0) {
    $_SESSION['flash_success'] = 'クエストを受注しました！';
} else {
    $_SESSION['flash_info'] = 'すでにそのクエストは受注済みです。';
}

header('Location: my_quests.php');
exit;
