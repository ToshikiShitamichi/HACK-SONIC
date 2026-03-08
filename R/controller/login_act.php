<?php
// データ受け取り
include('../config/db.php'); 
session_start();

// SQL実行
$sql = 'SELECT * FROM user_table WHERE username=:username';

$username = $_POST['username'];
$password = $_POST['password'];

// DB接続
$pdo = get_db();

$stmt = $pdo->prepare($sql);
$stmt->bindValue(':username', $username, PDO::PARAM_STR);

try {
  $status = $stmt->execute();
} catch (PDOException $e) {
  echo json_encode(["sql error" => "{$e->getMessage()}"]);
  exit();
}
// ユーザ有無で条件分岐

$user = $stmt->fetch(PDO::FETCH_ASSOC);//一つを取り出す

if (!$user || !password_verify($password, $user['password'])) {
    echo "<p>ログイン情報に誤りがあります。</p>";
    echo "<a href = ../views/login.php>ログイン</a>";
    exit();
} else {
    $_SESSION = array();
    $_SESSION['session_id'] =  session_id();
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    header('location:../../index.php');
    exit();
}