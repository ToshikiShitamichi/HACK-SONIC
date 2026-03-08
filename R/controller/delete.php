<?php
session_start();

$id = $_GET['id'];
$user_id = $_SESSION['user_id'];

include('../../config/db.php'); 
$pdo = get_db();

$sql = 'DELETE FROM post_table WHERE id=:id AND user_id=:user_id';

$stmt = $pdo->prepare($sql);
$stmt->bindValue(':id', $id, PDO::PARAM_STR);
$stmt->bindValue(':user_id', $user_id, PDO::PARAM_STR);

try {
  $status = $stmt->execute();
} catch (PDOException $e) {
  echo json_encode(["sql error" => "{$e->getMessage()}"]);
  exit();
}

header("Location:../../index.php");
exit();
?>