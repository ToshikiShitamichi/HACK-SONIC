    <?php
    include('../../config/db.php'); 
    // session_start();
    // check_session_id();

    if (
    !isset($_POST['text']) || $_POST['text'] === ''
    ) {
    echo json_encode(["error_msg" => "no input"]);
    exit();
    }

    $text = $_POST['text'];
    $image_url = null;

    if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {

    $uploaded_file_name = $_FILES['image']['name'];
    $temp_path  = $_FILES['image']['tmp_name'];
    $directory_path = './uploads/';
    // サーバー保存先
    $directory_path=__DIR__.'/../uploads/';
    // ブラウザ表示用URL
    $extension = pathinfo($uploaded_file_name, PATHINFO_EXTENSION);

    $unique_name = date('YmdHis') . md5(session_id()) . "." . $extension;

    $filename = $directory_path . $unique_name;

    if (!is_dir($directory_path)) {
        mkdir($directory_path, 0777, true);
    }

    if (!move_uploaded_file($temp_path, $directory_path.$unique_name)) {
        exit('画像保存失敗');
    }

    $image_url = 'uploads/' . $unique_name;
    }


    $pdo = get_db();

    $user_id = $_SESSION['user_id'];
    $plan_id = $_POST['plan_id'];

    $sql = 'INSERT INTO post_table(user_id, plan_id, message, image_url, prefecture, city, destination, category, created_at) VALUES(:user_id, :plan_id :message, :image_url, :prefecture, :city, :destination, :category, now())';

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->bindValue(':plan_id', $plan_id, PDO::PARAM_INT);
    $stmt->bindValue(':message', $message, PDO::PARAM_STR);
    $stmt->bindValue(':image_url', $image_url, PDO::PARAM_STR);
    $stmt->bindValue(':prefecture', $plan['prefecture'], PDO::PARAM_STR);
    $stmt->bindValue(':city', $plan['city'], PDO::PARAM_STR);
    $stmt->bindValue(':destination', $plan['destination'], PDO::PARAM_STR);
    $stmt->bindValue(':category', $plan['category'], PDO::PARAM_STR);

    try {
    $status = $stmt->execute();
    } catch (PDOException $e) {
    echo json_encode(["sql error" => "{$e->getMessage()}"]);
    exit();
    }


    header("Location:../../index.php");
    exit();