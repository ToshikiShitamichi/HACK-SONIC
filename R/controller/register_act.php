<?php
include('../../config/db.php'); 

$name = $_POST["name"];
$username = $_POST["username"];
$password = $_POST["password"];

$pdo = get_db();

$sql = 'SELECT COUNT(*) FROM user_table WHERE username=:username';

$stmt = $pdo->prepare($sql);
$stmt->bindValue(':username', $username, PDO::PARAM_STR);

try {
  $status = $stmt->execute();
} catch (PDOException $e) {
  echo json_encode(["sql error" => "{$e->getMessage()}"]);
  exit();
}

if ($stmt->fetchColumn() > 0) {
  echo '<p>すでに登録されているユーザです．</p>';
  echo '<a href="../views/login.php">login</a>';
  exit();
}

$password_hash = password_hash($password, PASSWORD_DEFAULT);

$sql = 'INSERT INTO user_table(id, name, username, password, created_at) VALUES(NULL, :name, :username, :password, now())';

$stmt = $pdo->prepare($sql);
$stmt->bindValue(':name', $name, PDO::PARAM_STR);
$stmt->bindValue(':username', $username, PDO::PARAM_STR);
$stmt->bindValue(':password', $password_hash, PDO::PARAM_STR);

try {
  $status = $stmt->execute();
} catch (PDOException $e) {
  echo json_encode(["sql error" => "{$e->getMessage()}"]);
  exit();
}

header("Location:../../index.php");
exit();
?>