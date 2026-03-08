<?php
include('../functions.php');
session_start();

// ログインしていなければ login.php にリダイレクト
if (!isset($_SESSION['user_id'])) {
    header('Location: ../views/login.php');
    exit();
}

$user_id = $_GET['user_id'];
$post_id = $_GET['post_id'];

$pdo = connect_to_db_pre();

// すでにいいねしているか確認
$sql = 'SELECT * FROM like_table WHERE user_id=:user_id AND post_id=:post_id';
$stmt = $pdo->prepare($sql);
$stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
$stmt->bindValue(':post_id', $post_id, PDO::PARAM_INT);
$stmt->execute();
$like = $stmt->fetch();

if ($like) {
    $sql = 'DELETE FROM like_table WHERE user_id=:user_id AND post_id=:post_id';
    $likedNow = 0;
} else {
    $sql = 'INSERT INTO like_table (user_id, post_id, created_at) VALUES (:user_id, :post_id, now())';
    $likedNow = 1;
}

$stmt = $pdo->prepare($sql);
$stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
$stmt->bindValue(':post_id', $post_id, PDO::PARAM_INT);
$stmt->execute();

// 最新のいいね数を取得
$sql = 'SELECT COUNT(*) FROM like_table WHERE post_id=:post_id';
$stmt = $pdo->prepare($sql);
$stmt->bindValue(':post_id', $post_id, PDO::PARAM_INT);
$stmt->execute();
$like_count = $stmt->fetchColumn();

// リダイレクトでページ更新
header("Location: ../index.php");
exit();