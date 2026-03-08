<?php

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

    <div class="tags">
        <select id="region" name="region">
            <option value="">🗾 地方</option>
            <option value="hokkaido">北海道</option>
            <option value="tohoku">東北</option>
            <option value="kanto">関東</option>
            <option value="chubu">中部</option>
            <option value="kansai">関西</option>
            <option value="chugoku">中国</option>
            <option value="shikoku">四国</option>
            <option value="kyushu">九州</option>
        </select>

        <select name="prefecture" id="prefecture">
            <option value="">🔍 都道府県</option>
        </select>

        <select name="category">
            <option value="">🏷️ カテゴリー</option>
            <option value="観光">観光</option>
            <option value="グルメ">グルメ</option>
            <option value="温泉">温泉</option>
            <option value="自然">自然</option>
        </select>

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

    const prefectures = {
        hokkaido: ["北海道"],
        tohoku: ["青森", "岩手", "宮城", "秋田", "山形", "福島"],
        kanto: ["東京", "神奈川", "千葉", "埼玉", "茨城", "栃木", "群馬"],
        chubu: ["新潟", "長野", "山梨", "静岡", "愛知", "岐阜", "富山", "石川", "福井"],
        kansai: ["大阪", "京都", "兵庫", "奈良", "滋賀", "和歌山"],
        chugoku: ["広島", "岡山", "山口", "鳥取", "島根"],
        shikoku: ["香川", "徳島", "愛媛", "高知"],
        kyushu: ["福岡", "佐賀", "長崎", "熊本", "大分", "宮崎", "鹿児島", "沖縄"]
    };

const region = document.getElementById("region");
const pref = document.getElementById("prefecture");

region.onchange = () => {
    const list = prefectures[region.value] || [];
    pref.innerHTML = '<option value="" disabled selected>🔍 都道府県</option>';
    list.forEach(p => {
        const option = document.createElement("option");
        option.value = p;
        option.textContent = p;
        pref.appendChild(option);
    });
};
</script>

</body>
</html>