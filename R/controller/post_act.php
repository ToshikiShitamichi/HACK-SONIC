   <?php
    include('../../config/db.php'); 
    session_start();
    // check_session_id();

    $text = $_POST['text'];
    $image_url = null;
    $prefecture = $_POST['prefecture'] ?? '';
    $category   = $_POST['category'] ?? '';

if ($text === '' || $prefecture === '' || $category === '') {
    echo json_encode(["error_msg" => "no input"]);
    exit();
}

if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {

    $uploaded_file_name = $_FILES['image']['name'];
    $temp_path  = $_FILES['image']['tmp_name'];

    // サーバー保存先
    $directory_path = __DIR__ . '/../uploads/';

    // ブラウザ表示用URL
    $extension = pathinfo($uploaded_file_name, PATHINFO_EXTENSION);
    $unique_name = date('YmdHis') . md5(session_id()) . "." . $extension;

    // 保存先フルパス
    $filename = $directory_path . $unique_name;

    // ディレクトリ作成（存在しなければ）
    if (!is_dir($directory_path)) {
        mkdir($directory_path, 0777, true);
    }

    // ファイル移動
    if (!move_uploaded_file($temp_path, $filename)) {
        exit('画像保存失敗');
    }

    // DB用URL
    $image_url = './R/uploads/' . $unique_name;
}
    $pdo = get_db();

    $user_id = $_SESSION['user_id'];

    $sql = 'INSERT INTO post_table(user_id, plan_id, message, image_url, prefecture, city, destination, category, created_at) VALUES(:user_id, NULL, :message, :image_url, :prefecture, NULL, NULL, :category, now())';
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->bindValue(':message', $text, PDO::PARAM_STR);
    $stmt->bindValue(':image_url', $image_url, PDO::PARAM_STR);
    $stmt->bindValue(':prefecture', $prefecture, PDO::PARAM_STR);
    $stmt->bindValue(':category', $category, PDO::PARAM_STR);
    try {
    $status = $stmt->execute();
    } catch (PDOException $e) {
    echo json_encode(["sql error" => "{$e->getMessage()}"]);
    exit();
    }

    header("Location:../../index.php");
    exit();