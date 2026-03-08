<?php
//プランテーブルの目的地を取得
include('./R/config/db.php'); 

$plan_id = $_GET['plan_id'] ?? null;

if (!$plan_id) {
    echo "プランが指定されていません。";
    exit();
}

// DB接続
$pdo = get_db();

$sql = "SELECT * FROM plan_table WHERE id = :plan_id";

$stmt = $pdo->prepare($sql);
$stmt->bindValue(':plan_id', $plan_id, PDO::PARAM_INT);
$stmt->execute();

$plan = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$plan) {
    echo "プランが存在しません。";
    exit();
}

?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>R_Post</title>
<style>
  body{
      margin:0;
      font-family:sans-serif;
      background:#f5f5f5;
  }

  header{
      background:white;
      padding:10px 20px;
      border-bottom:1px solid #ddd;
      text-align:center;
  }

  h2{
      margin:0;
  }

  .postCard{
      background:white;
      max-width:600px;
      width:95%;
      margin:20px auto;
      border-radius:10px;
      padding:15px;
      box-shadow:0 2px 6px rgba(0,0,0,0.1);
  }

  .tags{
    display:flex;
    flex-wrap:wrap;
    gap:6px;
    margin-bottom:12px;
  }

  .tag{
    background:#f1f3f5;
    padding:4px 10px;
    border-radius:20px;
    font-size:12px;
    color:#555;
  }

  #text{
      width:100%;
      height:150px;
      padding:10px;
      border:1px solid #ddd;
      border-radius:8px;
      resize:none;
      font-size:14px;
      margin-bottom:10px;
  }

  input[type="file"]{
      display:block;
      margin-bottom:15px;
  }

  #btn{
      display:flex;
      justify-content:space-between;
      gap:10px;
  }

  #btn button{
      flex:1;
      padding:10px;
      font-size:16px;
      border:none;
      border-radius:8px;
      cursor:pointer;
      color:white;
  }

  #cancel{
      background:#aaa;
  }

  #post{
      background:#4CAF50;
  }

  /* スマホ用微調整 */
  @media screen and (max-width: 400px){
      #text{
          height:120px;
      }
      #btn button{
          font-size:14px;
          padding:8px;
      }
  }
</style>
</head>
<body>

<header>
  <h2>新規投稿</h2>
</header>

<div class="postCard">
  <form action="../controller/post_act.php" method="POST" enctype="multipart/form-data">

  <input type="hidden" name="plan_id" value="<?= $plan_id ?>">

    <div class="tags">
        <span class="tag">📍<?= htmlspecialchars($plan["prefecture"]) ?></span>
        <span class="tag">🏙<?= htmlspecialchars($plan["city"]) ?></span>
        <span class="tag">📌<?= htmlspecialchars($plan["destination"]) ?></span>
        <span class="tag">🏷<?= htmlspecialchars($plan["category"]) ?></span>
    </div>
      <textarea name="text" id="text" placeholder="どんな旅だった？"></textarea>
      <input type="file" name="image" accept="image/*">

      <div id="btn">
          <button id="cancel" type="button">キャンセル</button>
          <button id="post" type="submit">投稿する</button>
      </div>
  </form>
</div>

<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.4.1/jquery.min.js"></script>
<script>
    $('#cancel').on('click', function(){
        window.location.href = '../../index.php';
    });
</script>

</body>
</html>