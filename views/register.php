<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>R</title>
</head>
<body>
    <form action="../controller/register_act.php" method="POST">
        <fieldset>
            <legend>ユーザー登録</legend>
            <div>
                name: <input type="text" name="name" placeholder="姓 名">
            </div>
            <div>
                username: <input type="text" name="username" placeholder="example@example.com">
            </div>
            <div>
                password: <input type="text" name="password" placeholder="password">
            </div>
            <button>登録</button>
        </fieldset>
    </form>
</body>
</html>