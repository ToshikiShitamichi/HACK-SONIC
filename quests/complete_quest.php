<?php
require_once __DIR__ . '/../auth/auth_check.php';
require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: my_quests.php');
    exit;
}

$quest_id = isset($_POST['quest_id']) ? (int)$_POST['quest_id'] : 0;
$user_id  = $_SESSION['user_id'];

if ($quest_id <= 0) {
    header('Location: my_quests.php');
    exit;
}

// ==========================================
// レベル計算
// XP テーブル: Lv.N になるのに必要な累計 XP = N*(N-1)*100
//   Lv1:     0 XP〜
//   Lv2:   200 XP〜
//   Lv3:   600 XP〜
//   Lv4:  1200 XP〜
//   Lv5:  2000 XP〜 ...
// ==========================================
function calc_level(int $total_xp): int {
    $level = (int)floor((1 + sqrt(1 + $total_xp / 50)) / 2);
    return max(1, min($level, 99));
}

// ==========================================
// レベル称号
// ==========================================
function level_title(int $level): string {
    return match(true) {
        $level >= 50 => '伝説の旅人',
        $level >= 40 => '大賢者',
        $level >= 30 => '英雄',
        $level >= 20 => '冒険者マスター',
        $level >= 15 => '歴戦の旅人',
        $level >= 10 => '熟練の冒険者',
        $level >=  7 => '一人前の旅人',
        $level >=  5 => '見習い冒険者',
        $level >=  3 => '旅の始まり',
        default      => '新米旅人',
    };
}

$pdo = get_db();

try {
    $pdo->beginTransaction();

    // ① user_quests を completed に更新
    //    pending のものだけ対象（二重達成を防ぐ）
    $stmt = $pdo->prepare(
        'UPDATE user_quests
         SET status = "completed", completed_at = NOW()
         WHERE user_id = ? AND quest_id = ? AND status = "pending"'
    );
    $stmt->execute([$user_id, $quest_id]);

    if ($stmt->rowCount() === 0) {
        // すでに達成済み or 受注していない
        $pdo->rollBack();
        $_SESSION['flash_info'] = 'このクエストはすでに達成済みです。';
        header('Location: my_quests.php');
        exit;
    }

    // ② quests から xp を取得
    $stmt = $pdo->prepare('SELECT xp FROM quests WHERE id = ?');
    $stmt->execute([$quest_id]);
    $quest = $stmt->fetch();

    if (!$quest) {
        $pdo->rollBack();
        $_SESSION['flash_error'] = 'クエストデータが見つかりません。';
        header('Location: my_quests.php');
        exit;
    }

    $xp_gain = (int)$quest['xp'];

    // ③ total_xp を加算し、レベル・称号を更新
    $stmt2 = $pdo->prepare('SELECT total_xp FROM user_levels WHERE user_id = ?');
    $stmt2->execute([$user_id]);
    $row = $stmt2->fetch();
    $current_xp   = $row ? (int)$row['total_xp'] : 0;
    $new_total_xp = $current_xp + $xp_gain;
    $new_level    = calc_level($new_total_xp);
    $new_title    = level_title($new_level);
    $old_level    = calc_level($current_xp);

    $stmt = $pdo->prepare(
        'INSERT INTO user_levels (user_id, total_xp, level, title)
         VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
             total_xp = total_xp + VALUES(total_xp),
             level    = VALUES(level),
             title    = VALUES(title)'
    );
    $stmt->execute([$user_id, $xp_gain, $new_level, $new_title]);

    $pdo->commit();

    // セッションにフラッシュメッセージをセット
    $_SESSION['flash_success'] = 'クエスト達成！';
    $_SESSION['flash_xp']      = '+' . $xp_gain . ' XP';

    if ($new_level > $old_level) {
        $_SESSION['flash_levelup'] = 'Level Up! Lv.' . $old_level . ' → Lv.' . $new_level . '「' . $new_title . '」';
    }

} catch (PDOException $e) {
    $pdo->rollBack();
    $_SESSION['flash_error'] = 'エラーが発生しました。もう一度お試しください。';
}

header('Location: my_quests.php');
exit;
