<?php
// self_checkin.php  к Self Check-in Ѻѡ֡
require_once 'config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();
}

// ǨͺҪԡѡ֡ҷ login 
if (!isLoggedIn()) {
    header("Location: login.php?redirect=self_checkin.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'] ?? null;

// ֧šԨͧѹԴԹ
$activities = [];
$stmt_act = $conn->prepare("SELECT * FROM activities WHERE self_checkin_enabled = 1 AND CURDATE() BETWEEN DATE(start_date) AND DATE(IFNULL(end_date, start_date)) ORDER BY start_date ASC");
$stmt_act->execute();
$res_act = $stmt_act->get_result();
while ($row = $res_act->fetch_assoc()) {
    $activities[] = $row;
}
$stmt_act->close();

// Ѵ POST  Թ
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'self_checkin') {
    $activity_id = (int)($_POST['activity_id'] ?? 0);
    
    // ǨͺҡԨԴԹѹԧ
    $chk_act = $conn->prepare("SELECT points FROM activities WHERE activity_id = ? AND self_checkin_enabled = 1 AND CURDATE() BETWEEN DATE(start_date) AND DATE(IFNULL(end_date, start_date))");
    $chk_act->bind_param("i", $activity_id);
    $chk_act->execute();
    $act_res = $chk_act->get_result();
    $act_data = $act_res->fetch_assoc();
    $chk_act->close();

    if (!$act_data) {
        $_SESSION['alert'] = ['type' => 'error', 'title' => '辺Ԩ', 'text' => 'ԨԴԹѹ'];
        header("Location: self_checkin.php");
        exit;
    }

    // Ǩͺŧ¹
    $stmt_check = $conn->prepare("SELECT user_id, status FROM user_activities WHERE user_id = ? AND activity_id = ?");
    $stmt_check->bind_param("ii", $user_id, $activity_id);
    $stmt_check->execute();
    $res = $stmt_check->get_result();

    if ($res->num_rows === 0) {
        $_SESSION['alert'] = ['type' => 'error', 'title' => '辺', 'text' => 'سѧŧ¹Ԩ'];
    } else {
        $reg_data = $res->fetch_assoc();
        
        if ($reg_data['status'] === 'registered') {
            // ทำการเช็คอิน
            $stmt_upd = $conn->prepare("UPDATE user_activities SET status = 'checked_in', check_in_time = NOW() WHERE user_id = ? AND activity_id = ?");
            $stmt_upd->bind_param("ii", $user_id, $activity_id);
            $stmt_upd->execute();
            $_SESSION['alert'] = ['type' => 'success', 'title' => 'เช็คอินสำเร็จ!', 'text' => 'ระบบบันทึกเวลาเข้าร่วมกิจกรรมเรียบร้อยแล้ว'];
        } else {
            $_SESSION['alert'] = ['type' => 'info', 'title' => 'เช็คอินแล้ว', 'text' => 'คุณได้เช็คอินเข้าร่วมกิจกรรมนี้เรียบร้อยแล้ว'];
        }
    }
    $stmt_check->close();
    header("Location: self_checkin.php");
    exit;
}

// ตรวจสอบสถานะการลงทะเบียนของกิจกรรมที่เปิดเช็คอิน
$user_statuses = [];
if (!empty($activities)) {
    $act_ids = array_column($activities, 'activity_id');
    if (!empty($act_ids)) {
        $placeholders = implode(',', array_fill(0, count($act_ids), '?'));
        
        $stmt_st = $conn->prepare("SELECT activity_id, status FROM user_activities WHERE user_id = ? AND activity_id IN ($placeholders)");
        $types = 'i' . str_repeat('i', count($act_ids));
        $params = array_merge([$user_id], $act_ids);
        $stmt_st->bind_param($types, ...$params);
        $stmt_st->execute();
        $st_res = $stmt_st->get_result();
        while ($row = $st_res->fetch_assoc()) {
            $user_statuses[$row['activity_id']] = $row['status'];
        }
        $stmt_st->close();
    }
}

include 'includes/header.php';
?>

<!-- 觢 Alert  JS -->
<?php if (isset($_SESSION['alert'])): ?>
    <div id="flash-message" data-type="<?= $_SESSION['alert']['type'] ?>" data-title="<?= $_SESSION['alert']['title'] ?>" data-text="<?= $_SESSION['alert']['text'] ?>" class="d-none"></div>
    <?php unset($_SESSION['alert']); ?>
<?php endif; ?>

<div class="container py-4 d-flex justify-center">
    <div class="card" style="max-width: 550px; width: 100%;">
        <div class="text-center mb-4">
            <div class="mx-auto mb-3 d-flex justify-center align-center" style="width: 80px; height: 80px; border-radius: 50%; background: var(--color-primary-pale);">
                <i class="fa-solid fa-hand-pointer text-primary" style="font-size: 36px;"></i>
            </div>
            <h2 class="m-0 text-primary-dark">Self Check-in</h2>
            <p class="text-muted text-sm mt-1">ԹԨµͧ</p>
        </div>

        <?php if (empty($activities)): ?>
            <!-- 辺Ԩ -->
            <div class="text-center p-4" style="background: var(--bg-main); border-radius: var(--border-radius-sm);">
                <i class="fa-solid fa-calendar-xmark fa-3x text-muted mb-3" style="opacity: 0.4;"></i>
                <h3 class="text-md text-muted m-0 mb-2">աԨԴԹ</h3>
                <p class="text-sm text-muted m-0">աԨԴ Self Check-in ѹ</p>
            </div>
        <?php else: ?>
            <!-- ʴ¡áԨ -->
            <?php foreach ($activities as $act): ?>
                <?php 
                $status = $user_statuses[$act['activity_id']] ?? null; 
                ?>
                <div class="mb-4 p-3 rounded" style="border: 1px solid var(--border-color); background: #ffffff;">
                    <div class="mb-3">
                        <h3 class="m-0 text-md font-weight-bold text-primary-dark mb-2">
                            <?= htmlspecialchars($act['title']) ?>
                        </h3>
                        <div class="text-sm text-muted">
                            <div class="mb-1"><?= date('d/m/Y', strtotime($act['start_date'])) ?></div>
                            <div>สถานที่: <?= htmlspecialchars($act['location_name']) ?></div>
                        </div>
                    </div>

                    <?php if ($status === 'checked_in' || $status === 'checked_out'): ?>
                        <!-- เช็คอินแล้ว -->
                        <div class="text-center p-3 rounded" style="background: #E8F5E9; border: 1px dashed #4CAF50;">
                            <i class="fa-solid fa-circle-check text-success" style="font-size: 32px;"></i>
                            <h4 class="text-success m-0 mt-2 mb-1">เช็คอินแล้ว</h4>
                            <p class="text-xs text-muted m-0">ระบบบันทึกการเช็คอินแล้ว (ทำแบบประเมินได้เมื่อสิ้นสุดกิจกรรม)</p>
                        </div>
                    <?php elseif ($status === 'registered'): ?>
                        <!-- Թ -->
                        <form method="POST" action="self_checkin.php">
<?php csrfField(); ?>
                            <input type="hidden" name="action" value="self_checkin">
                            <input type="hidden" name="activity_id" value="<?= $act['activity_id'] ?>">
                            <button type="submit" class="btn btn-success btn-block py-2" style="font-size: 16px;">
                                Թ (Check-in)
                            </button>
                        </form>
                        <p class="text-xs text-muted text-center mt-2 m-0">
                            Ѻ <?= $act['points'] ?> Եҷ
                        </p>
                    <?php else: ?>
                        <!-- ѧŧ¹ -->
                        <div class="text-center p-3 rounded" style="background: #FFF8E1; border: 1px dashed #FFB300;">
                            <i class="fa-solid fa-triangle-exclamation text-warning" style="font-size: 24px;"></i>
                            <p class="text-sm text-warning m-0 mt-2">سѧŧ¹Ԩ</p>
                            <a href="index.php" class="btn btn-outline-primary btn-sm mt-2">ŧ¹˹á</a>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>



<?php include 'includes/footer.php'; ?>
