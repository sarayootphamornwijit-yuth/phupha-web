<?php
// joined_activities.php
// ไฟล์นี้ทำหน้าที่แสดงกิจกรรมที่ผู้ใช้ลงทะเบียนไว้ทั้งหมด
// ผู้ใช้สามารถดูรายละเอียด, แสดง QR Code สำหรับเช็คอิน, ยกเลิกการลงทะเบียน
// และเลือกรูปแบบการเดินทาง (ยานพาหนะ) ได้จากหน้านี้

require_once 'config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();
}


// ส่วนที่ 1 ตรวจสอบสิทธิ์การเข้าถึง
if (!isLoggedIn()) {
    header("Location: login.php");
    exit;
}


// ส่วนที่ 2 ดึงข้อมูลผู้ใช้ปัจจุบัน
$currentUserId = $_SESSION['user_id'];

$userStatement = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$userStatement->bind_param("i", $currentUserId);
$userStatement->execute();
$userRecord = $userStatement->get_result()->fetch_assoc();
$userStatement->close();


// ส่วนที่ 3 ยกเลิกการลงทะเบียน (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['action'])
    && $_POST['action'] === 'cancel_registration'
) {
    $cancelActivityId = (int)$_POST['activity_id'];

    // ลบการลงทะเบียนเฉพาะสถานะ registered เท่านั้น
    $cancelStatement = $conn->prepare(
        "DELETE FROM user_activities
         WHERE user_id = ? AND activity_id = ? AND status = 'registered'"
    );
    $cancelStatement->bind_param("ii", $currentUserId, $cancelActivityId);

    if ($cancelStatement->execute()) {
        $_SESSION['alert'] = [
            'type'  => 'success',
            'title' => 'ยกเลิกสำเร็จ',
            'text'  => 'คุณยกเลิกการลงทะเบียนกิจกรรมเรียบร้อยแล้ว',
        ];
    }

    $cancelStatement->close();
    header("Location: joined_activities.php");
    exit;
}


// ส่วนที่ 4 เลือกเดินทางเอง (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['action'])
    && $_POST['action'] === 'select_travel_self'
) {
    $travelActivityId     = (int)$_POST['activity_id'];
    $selfTravelMethodName = 'own_travel';

    $selfTravelStatement = $conn->prepare(
        "UPDATE user_activities
         SET travel_method = ?, vehicle_id = NULL
         WHERE user_id = ? AND activity_id = ?"
    );
    $selfTravelStatement->bind_param("sii", $selfTravelMethodName, $currentUserId, $travelActivityId);
    $selfTravelStatement->execute();
    $selfTravelStatement->close();

    $_SESSION['alert'] = [
        'type'  => 'success',
        'title' => 'บันทึกสำเร็จ',
        'text'  => 'เลือกเดินทางเองเรียบร้อยแล้ว',
    ];

    header("Location: joined_activities.php");
    exit;
}


// ส่วนที่ 5 เลือกยานพาหนะ (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['action'])
    && $_POST['action'] === 'select_vehicle'
) {
    $vehicleActivityId = (int)$_POST['activity_id'];
    $selectedVehicleId = (int)$_POST['vehicle_id'];

    // ตรวจสอบว่ายานพาหนะยังมีที่นั่งว่างหรือไม่
    $vehicleCheckStatement = $conn->prepare(
        "SELECT capacity, (SELECT COUNT(*) FROM user_activities WHERE vehicle_id = ?) as booked
         FROM vehicles WHERE vehicle_id = ?"
    );
    $vehicleCheckStatement->bind_param("ii", $selectedVehicleId, $selectedVehicleId);
    $vehicleCheckStatement->execute();
    $vehicleCheckData = $vehicleCheckStatement->get_result()->fetch_assoc();
    $vehicleCheckStatement->close();

    if ($vehicleCheckData && $vehicleCheckData['booked'] < $vehicleCheckData['capacity']) {
        // ที่นั่งว่าง: บันทึกการเลือก
        $vehicleTravelMethodName = 'club_vehicle';

        $selectVehicleStatement = $conn->prepare(
            "UPDATE user_activities
             SET travel_method = ?, vehicle_id = ?
             WHERE user_id = ? AND activity_id = ?"
        );
        $selectVehicleStatement->bind_param("siii", $vehicleTravelMethodName, $selectedVehicleId, $currentUserId, $vehicleActivityId);
        $selectVehicleStatement->execute();
        $selectVehicleStatement->close();

        $_SESSION['alert'] = [
            'type'  => 'success',
            'title' => 'เลือกรถสำเร็จ!',
            'text'  => 'บันทึกการเลือกรถเรียบร้อยแล้ว',
        ];

    } else {
        // ที่นั่งเต็ม
        $_SESSION['alert'] = [
            'type'  => 'error',
            'title' => 'รถเต็มแล้ว',
            'text'  => 'ขออภัย รถคันที่คุณเลือกเต็มแล้ว กรุณาเลือกรถคันอื่น',
        ];
    }

    header("Location: joined_activities.php");
    exit;
}


// ส่วนที่ 6 ดึงรายการกิจกรรมทั้งหมด
$activitiesStatement = $conn->prepare(
    "SELECT
         a.activity_id as activity_id,
         a.title,
         a.description,
         a.start_date,
         a.end_date,
         a.location_name,
         a.meeting_point,
         a.meeting_time,
         a.facebook_link,
         ua.status,
         ua.travel_method,
         ua.vehicle_id,
         (SELECT COUNT(*) FROM evaluations WHERE user_id = ua.user_id AND activity_id = a.activity_id) as is_evaluated,
         a.points
     FROM user_activities ua
     JOIN activities a ON ua.activity_id = a.activity_id
     WHERE ua.user_id = ?
     ORDER BY a.start_date DESC"
);
$activitiesStatement->bind_param("i", $currentUserId);
$activitiesStatement->execute();
$activitiesResult = $activitiesStatement->get_result();
$activitiesStatement->close();


// ส่วนที่ 7 โหลด Header
include 'includes/header.php';
?>

<!-- Flash Alert -->
<?php if (isset($_SESSION['alert'])): ?>
    <div id="flash-message"
         data-type="<?= $_SESSION['alert']['type'] ?>"
         data-title="<?= $_SESSION['alert']['title'] ?>"
         data-text="<?= $_SESSION['alert']['text'] ?>"
         class="d-none">
    </div>
    <?php unset($_SESSION['alert']); ?>
<?php endif; ?>


<div class="container py-4" style="max-width: 900px;">

    <h3 class="section-title mb-3">กิจกรรมที่เข้าร่วม</h3>

    <?php if ($activitiesResult->num_rows > 0): ?>

        <div class="d-flex flex-column gap-3">
            <?php while ($activityItem = $activitiesResult->fetch_assoc()): ?>

                <?php
                // ตรวจสอบว่าเป็นกิจกรรมที่ผ่านมาแล้วหรือยัง
                $actEndDate = !empty($activityItem['end_date']) ? $activityItem['end_date'] : $activityItem['start_date'];
                $isActivityPast = (strtotime($actEndDate) <= time());

                // กำหนดสีเส้นซ้ายของการ์ดตามสถานะ
                if ($activityItem['status'] == 'checked_in') {
                    $cardBorderColor = 'var(--color-success)';
                } else {
                    $cardBorderColor = 'var(--color-primary)';
                }
                ?>

                <div class="card" style="border: 1px solid var(--border-color); box-shadow: none;">

                    <!-- ข้อมูลกิจกรรม -->
                    <h4 class="m-0 text-md text-main mb-2" style="color: #000; font-weight: bold;">
                        <?= htmlspecialchars($activityItem['title']) ?>
                    </h4>
                    <div class="text-sm mb-1" style="color: #000;">
                        วันที่ <?= date('d/m/Y', strtotime($activityItem['start_date'])) ?> สถานที่: <?= htmlspecialchars($activityItem['location_name']) ?>
                    </div>
                    <div class="text-sm mb-1" style="color: #000;">
                        จุดนัดพบ <?= htmlspecialchars(!empty($activityItem['meeting_point']) ? $activityItem['meeting_point'] : '-') ?>
                    </div>
                    <div class="text-sm mb-3" style="color: #000;">
                        เวลานัดพบ <?= !empty($activityItem['meeting_time']) ? date('H:i', strtotime($activityItem['meeting_time'])) . ' น.' : '-' ?>
                    </div>

                    <!-- ปุ่ม 3 ปุ่ม ชิดซ้าย เรียงจากซ้ายไปขวา ไม่มีเอฟเฟกต์ hover แสงเงา -->
                    <div class="d-flex flex-wrap align-center gap-2 mb-3">
                        <a href="activity_details.php?id=<?= $activityItem['activity_id'] ?>" 
                           class="btn btn-surface border-light text-sm" 
                           style="color: #000; box-shadow: none !important; transform: none !important;">
                            รายละเอียด
                        </a>

                        <?php if ($activityItem['status'] == 'checked_in' || $activityItem['status'] == 'checked_out'): ?>
                            <?php if ($activityItem['is_evaluated']): ?>
                                <span class="text-sm px-2 py-1" style="color: #166534; font-weight: 600;">
                                    ✓ ทำแบบประเมินแล้ว (+<?= $activityItem['points'] ?> แต้ม)
                                </span>
                            <?php elseif ($isActivityPast): ?>
                                <a href="evaluate.php?id=<?= $activityItem['activity_id'] ?>" 
                                   class="btn btn-warning text-sm" 
                                   style="box-shadow: none !important; transform: none !important;">
                                    ทำแบบประเมินเพื่อรับคะแนน
                                </a>
                            <?php else: ?>
                                <span class="text-sm px-2 py-1" style="color: #92400e; background: #fef3c7; border-radius: 4px; font-weight: 500;">
                                    เช็คอินแล้ว (รอสิ้นสุดกิจกรรมเพื่อประเมิน)
                                </span>
                            <?php endif; ?>

                        <?php elseif ($activityItem['status'] == 'registered' && !$isActivityPast): ?>
                            <button type="button" 
                                    class="btn btn-primary text-sm btn-show-qr" 
                                    data-code="checkin-<?= $currentUserId ?>-<?= $activityItem['activity_id'] ?>-<?= generateQrToken($currentUserId, $activityItem['activity_id']) ?>"
                                    data-title="<?= htmlspecialchars($activityItem['title']) ?>"
                                    data-username="<?= htmlspecialchars($_SESSION['name'] ?? 'สมาชิก') ?>"
                                    style="box-shadow: none !important; transform: none !important;">
                                คิวอาร์โค้ดเช็คอิน
                            </button>
                            <form method="POST" action="joined_activities.php" onsubmit="return confirm('ยืนยันการยกเลิกลงทะเบียนกิจกรรม?');" class="m-0 p-0 d-inline">
                                <?php csrfField(); ?>
                                <input type="hidden" name="action" value="cancel_registration">
                                <input type="hidden" name="activity_id" value="<?= $activityItem['activity_id'] ?>">
                                <button type="submit" 
                                        class="btn btn-danger text-sm"
                                        style="box-shadow: none !important; transform: none !important;">
                                    ยกเลิกลงทะเบียน
                                </button>
                            </form>

                        <?php elseif ($isActivityPast && $activityItem['status'] == 'registered'): ?>
                            <span class="text-sm px-2 py-1 font-weight-bold" style="color: #dc2626;">
                                <i class="fa-solid fa-circle-exclamation mr-1"></i> กิจกรรมนี้สิ้นสุดแล้ว คุณไม่ได้เช็คอินเข้าร่วมกิจกรรม
                            </span>
                        <?php else: ?>
                            <span class="text-sm px-2 py-1" style="color: #000;">
                                สิ้นสุดกิจกรรม
                            </span>
                        <?php endif; ?>
                    </div>

                    <!-- ส่วนเลือกรูปแบบการเดินทาง -->
                    <?php if (!$isActivityPast && $activityItem['status'] === 'registered'): ?>

                        <div class="border-top pt-3">

                            <div class="text-sm font-weight-bold mb-2" style="color: #000;">
                                รูปแบบการเดินทาง
                                <?php
                                $displayTravelText = 'ยังไม่ได้เลือก';
                                if ($activityItem['travel_method'] === 'own_travel' || $activityItem['travel_method'] === 'เดินทางเอง') {
                                    $displayTravelText = 'เดินทางด้วยตนเอง';
                                } elseif ($activityItem['travel_method'] === 'club_vehicle' || !empty($activityItem['vehicle_id']) || $activityItem['travel_method'] === 'ไปกับรถที่จัดหาให้' || $activityItem['travel_method'] === 'ไปด้วยรถที่จัดให้') {
                                    $displayTravelText = 'เดินทางโดยรถของชมรม';
                                } elseif (!empty($activityItem['travel_method'])) {
                                    $displayTravelText = $activityItem['travel_method'];
                                }
                                ?>
                                <span style="color: #000; font-weight: normal;">
                                    <?= htmlspecialchars($displayTravelText) ?>
                                </span>
                            </div>

                            <?php
                            // ดึงรายการยานพาหนะของกิจกรรมนี้ (ที่คณะกรรมการได้กรอกไว้)
                            $hasAvailableVehicles = false;
                            $vehicleList = [];
                            try {
                                $actId = (int)$activityItem['activity_id'];
                                $vQuery = $conn->query("
                                    SELECT v.*, 
                                    (SELECT COUNT(*) FROM user_activities ua WHERE ua.vehicle_id = COALESCE(v.vehicle_id, v.id, 0)) as booked
                                    FROM vehicles v 
                                    WHERE v.activity_id = {$actId}
                                    ORDER BY 1 ASC
                                ");
                                if ($vQuery) {
                                    while ($vRow = $vQuery->fetch_assoc()) {
                                        if (!isset($vRow['vehicle_id']) && isset($vRow['id'])) {
                                            $vRow['vehicle_id'] = $vRow['id'];
                                        }
                                        $vehicleList[] = $vRow;
                                    }
                                    $vQuery->free();
                                }
                            } catch (\Throwable $e) {
                                try {
                                    $vQuery = $conn->query("SELECT * FROM vehicles WHERE activity_id = " . (int)$activityItem['activity_id'] . " ORDER BY 1 ASC");
                                    if ($vQuery) {
                                        while ($vRow = $vQuery->fetch_assoc()) {
                                            if (!isset($vRow['vehicle_id']) && isset($vRow['id'])) {
                                                $vRow['vehicle_id'] = $vRow['id'];
                                            }
                                            $vRow['booked'] = 0;
                                            $vehicleList[] = $vRow;
                                        }
                                        $vQuery->free();
                                    }
                                } catch (\Throwable $e2) {
                                    $vehicleList = [];
                                }
                            }
                            $hasAvailableVehicles = !empty($vehicleList);

                            // ข้อมูลรถที่เลือกปัจจุบัน
                            $selectedVehicleData = null;
                            if (!empty($activityItem['vehicle_id'])) {
                                try {
                                    $sVehId = (int)$activityItem['vehicle_id'];
                                    $sRes = $conn->query("
                                        SELECT v.*, 
                                        (SELECT COUNT(*) FROM user_activities ua WHERE ua.vehicle_id = {$sVehId}) as booked
                                        FROM vehicles v 
                                        WHERE v.vehicle_id = {$sVehId} OR v.id = {$sVehId}
                                        LIMIT 1
                                    ");
                                    if ($sRes && $sRes->num_rows > 0) {
                                        $selectedVehicleData = $sRes->fetch_assoc();
                                        $sRes->free();
                                    }
                                } catch (\Throwable $e) {
                                    $selectedVehicleData = null;
                                }
                            }
                            ?>

                            <?php if ($selectedVehicleData): ?>
                                <!-- กรณีเลือกรถของคณะกรรมการไว้แล้ว -->
                                <div class="p-3 rounded mb-2"
                                     style="background: #fff; border: 1px solid var(--border-color); box-shadow: none;">
                                    <div class="text-sm font-weight-bold mb-1" style="color: #000;">
                                        รถที่เลือก: <?= htmlspecialchars($selectedVehicleData['vehicle_type']) ?>
                                    </div>
                                    <div class="text-xs" style="color: #000;">
                                        <strong>คนขับ:</strong> <?= htmlspecialchars($selectedVehicleData['driver_name']) ?> <?= !empty($selectedVehicleData['driver_phone']) ? '('.htmlspecialchars($selectedVehicleData['driver_phone']).')' : '' ?> |
                                        <strong>ทะเบียน:</strong> <?= htmlspecialchars($selectedVehicleData['license_plate'] ?? '-') ?> |
                                        <strong>จำนวน:</strong>
                                        <span class="font-weight-bold">
                                            <?= $selectedVehicleData['booked'] ?>/<?= $selectedVehicleData['capacity'] ?>
                                        </span>
                                    </div>
                                </div>

                                <div class="d-flex flex-wrap gap-2 mb-2">
                                    <form method="POST" action="joined_activities.php" class="m-0 p-0 d-inline">
                                        <?php csrfField(); ?>
                                        <input type="hidden" name="action" value="select_travel_self">
                                        <input type="hidden" name="activity_id" value="<?= $activityItem['activity_id'] ?>">
                                        <button type="submit" class="btn btn-surface border-light text-sm" style="color: #000; box-shadow: none !important; transform: none !important;">
                                            เปลี่ยนเป็นเดินทางเอง
                                        </button>
                                    </form>

                                    <?php if ($hasAvailableVehicles): ?>
                                        <button type="button"
                                                class="btn btn-surface border-light text-sm btn-toggle-vehicles"
                                                data-target="vehicles-<?= $activityItem['activity_id'] ?>"
                                                style="color: #000; box-shadow: none !important; transform: none !important;">
                                            เปลี่ยนเลือกรถคันอื่น
                                        </button>
                                    <?php endif; ?>
                                </div>

                            <?php elseif ($activityItem['travel_method'] === 'เดินทางเอง'): ?>
                                <!-- กรณีเลือกเดินทางเองไว้แล้ว -->
                                <div class="d-flex flex-wrap gap-2 mb-2">
                                    <button type="button" class="btn btn-surface border-light text-sm cursor-default" disabled style="color: #000; box-shadow: none !important; transform: none !important; opacity: 1;">
                                        เดินทางเอง (เลือกแล้ว)
                                    </button>

                                    <?php if ($hasAvailableVehicles): ?>
                                        <button type="button"
                                                class="btn btn-primary text-sm btn-toggle-vehicles"
                                                data-target="vehicles-<?= $activityItem['activity_id'] ?>"
                                                style="box-shadow: none !important; transform: none !important;">
                                            เลือกรถที่คณะกรรมการจัดหาให้
                                        </button>
                                    <?php endif; ?>
                                </div>

                            <?php else: ?>
                                <!-- กรณียังไม่ได้เลือกรูปแบบการเดินทาง -->
                                <div class="d-flex flex-wrap gap-2 mb-2">
                                    <form method="POST" action="joined_activities.php" class="m-0 p-0 d-inline">
                                        <?php csrfField(); ?>
                                        <input type="hidden" name="action" value="select_travel_self">
                                        <input type="hidden" name="activity_id" value="<?= $activityItem['activity_id'] ?>">
                                        <button type="submit" class="btn btn-surface border-light text-sm" style="color: #000; box-shadow: none !important; transform: none !important;">
                                            เดินทางเอง
                                        </button>
                                    </form>

                                    <?php if ($hasAvailableVehicles): ?>
                                        <button type="button"
                                                class="btn btn-primary text-sm btn-toggle-vehicles"
                                                data-target="vehicles-<?= $activityItem['activity_id'] ?>"
                                                style="box-shadow: none !important; transform: none !important;">
                                            เลือกรถที่คณะกรรมการจัดหาให้
                                        </button>
                                    <?php endif; ?>
                                </div>

                                <?php if (!$hasAvailableVehicles): ?>
                                    <div class="text-xs text-muted mb-2" style="color: #666;">
                                        (ยังไม่มีข้อมูลยานพาหนะจากคณะกรรมการสำหรับกิจกรรมนี้ สามารถเลือกเดินทางเองได้)
                                    </div>
                                <?php endif; ?>

                            <?php endif; ?>

                            <!-- รายการยานพาหนะที่คณะกรรมการกรอกไว้สำหรับกิจกรรมนี้ -->
                            <?php if ($hasAvailableVehicles): ?>
                                <div id="vehicles-<?= $activityItem['activity_id'] ?>" class="<?= (empty($activityItem['travel_method']) && $hasAvailableVehicles) ? 'mt-2' : 'd-none mt-2' ?>">
                                    <div class="text-sm font-weight-bold mb-2" style="color: #000;">ยานพาหนะที่คณะกรรมการจัดหาให้:</div>
                                    <div class="d-flex flex-column gap-2">

                                        <?php
                                        foreach ($vehicleList as $vehicleItem):
                                            $seatsAvailable     = (int)$vehicleItem['capacity'] - (int)$vehicleItem['booked'];
                                            $isVehicleFull      = ($seatsAvailable <= 0);
                                            $isMyCurrentVehicle = ($activityItem['vehicle_id'] == $vehicleItem['vehicle_id']);
                                        ?>

                                            <div class="d-flex align-center justify-between p-3 rounded"
                                                 style="background: #fff; border: 1px solid var(--border-color); box-shadow: none;">

                                                <div class="flex-grow-1">
                                                    <div class="text-sm font-weight-bold" style="color: #000;">
                                                        <?= htmlspecialchars($vehicleItem['vehicle_type']) ?>
                                                    </div>
                                                    <div class="text-xs" style="color: #000;">
                                                        คนขับ: <?= htmlspecialchars($vehicleItem['driver_name']) ?> <?= !empty($vehicleItem['driver_phone']) ? '('.htmlspecialchars($vehicleItem['driver_phone']).')' : '' ?> |
                                                        ทะเบียน: <?= htmlspecialchars($vehicleItem['license_plate'] ?? '-') ?>
                                                    </div>
                                                    <div class="text-xs mt-1" style="color: #000;">
                                                        <strong>จำนวน: </strong>
                                                        <span class="font-weight-bold" style="font-size: 13px; color: #000;">
                                                            <?= $vehicleItem['booked'] ?>/<?= $vehicleItem['capacity'] ?>
                                                        </span>
                                                        <?php if ($isVehicleFull): ?>
                                                            <span style="color: #000; font-size: 12px; margin-left: 4px;">เต็มแล้ว</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>

                                                <div>
                                                    <?php if ($isMyCurrentVehicle): ?>
                                                        <span style="color: #000; font-size: 12px; font-weight: bold;">เลือกแล้ว</span>
                                                    <?php elseif ($isVehicleFull): ?>
                                                        <button class="btn btn-surface border-light text-xs cursor-default" disabled style="color: #000; box-shadow: none !important; transform: none !important;">เต็มแล้ว</button>
                                                    <?php else: ?>
                                                        <form method="POST" action="joined_activities.php" class="m-0 p-0">
                                                            <?php csrfField(); ?>
                                                            <input type="hidden" name="action" value="select_vehicle">
                                                            <input type="hidden" name="activity_id" value="<?= $activityItem['activity_id'] ?>">
                                                            <input type="hidden" name="vehicle_id" value="<?= $vehicleItem['vehicle_id'] ?>">
                                                            <button type="submit"
                                                                    class="btn btn-primary text-xs"
                                                                    onclick="return confirm('ยืนยันการเลือกรถคันนี้?');"
                                                                    style="box-shadow: none !important; transform: none !important;">
                                                                เลือกรถคันนี้
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                </div>

                                            </div>

                                        <?php endforeach; ?>

                                    </div>
                                </div>
                            <?php endif; ?>

                        </div>

                    <?php else: ?>

                        <div class="text-xs text-muted border-top pt-2">
                            <strong>รูปแบบการเดินทาง</strong> 
                            <?php
                            if (!empty($activityItem['travel_method'])) {
                                echo htmlspecialchars($activityItem['travel_method']);
                            } else {
                                echo 'ไม่ได้ระบุ';
                            }
                            ?>
                        </div>

                    <?php endif; ?>

                </div>

            <?php endwhile; ?>
        </div>

    <?php else: ?>

        <div class="card p-4 text-center text-muted border-dashed" style="border: 1px dashed var(--text-light); box-shadow: none;">
            <p class="m-0">คุณยังไม่มีประวัติการลงทะเบียนกิจกรรม</p>
        </div>

    <?php endif; ?>

</div>

<!-- Modal แสดง QR Code เช็คอิน (ไม่ถูกบล็อกโดย Popup Blocker และไม่มีวันหมดอายุ) -->
<div id="qrCodeModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.65); z-index:99999; justify-content:center; align-items:center; padding:15px; box-sizing:border-box;">
    <div style="background:#fff; width:100%; max-width:400px; border-radius:16px; overflow:hidden; box-shadow:0 20px 25px -5px rgba(0,0,0,0.3);">
        <div style="background:var(--color-primary, #15803d); color:#fff; padding:14px 18px; display:flex; justify-content:space-between; align-items:center;">
            <div style="font-weight:700; font-size:1rem; display:flex; align-items:center; gap:8px;">
                <i class="fa-solid fa-qrcode"></i> คิวอาร์โค้ดเช็คอินกิจกรรม
            </div>
            <button type="button" id="closeQrModalBtn" style="background:transparent; border:none; color:#fff; font-size:1.5rem; cursor:pointer; line-height:1;">&times;</button>
        </div>
        
        <div style="padding:20px; text-align:center;">
            <div id="modalActivityTitle" style="font-weight:700; font-size:1.05rem; color:#0f172a; margin-bottom:4px; line-height:1.4;">ชื่อกิจกรรม</div>
            <div id="modalUserName" style="font-size:0.875rem; color:#64748b; margin-bottom:14px;">ผู้เข้าร่วม: -</div>

            <style>
                #modalQrBox {
                    background: #ffffff;
                    border: 2px dashed #94a3b8;
                    border-radius: 16px;
                    width: 200px;
                    height: 200px;
                    padding: 20px;
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    margin: 0 auto 16px auto;
                    position: relative;
                    box-sizing: border-box;
                    overflow: hidden;
                }
                #modalQrCanvasContainer {
                    width: 160px !important;
                    height: 160px !important;
                    display: flex !important;
                    justify-content: center !important;
                    align-items: center !important;
                    position: relative !important;
                    overflow: hidden !important;
                }
                #modalQrCanvasContainer canvas,
                #modalQrCanvasContainer img {
                    width: 160px !important;
                    height: 160px !important;
                    max-width: 160px !important;
                    max-height: 160px !important;
                    display: block !important;
                    margin: 0 auto !important;
                    border-radius: 8px !important;
                    box-sizing: border-box !important;
                }
                /* ป้องกัน qrcode.js แสดงผลทั้ง canvas และ img ซ้ำซ้อน 2 รูป */
                #modalQrCanvasContainer canvas + img {
                    display: none !important;
                }
                #modalQrCanvasContainer img + canvas {
                    display: none !important;
                }
            </style>

            <div id="modalQrBox">
                <div id="modalQrCanvasContainer"></div>
                <div id="modalQrLoading" style="display:none; position:absolute; top:0; left:0; width:100%; height:100%; background:rgba(255,255,255,0.95); border-radius:14px; flex-direction:column; justify-content:center; align-items:center; gap:8px;">
                    <i class="fa-solid fa-spinner fa-spin" style="font-size:2rem; color:var(--color-primary, #15803d);"></i>
                    <span style="font-size:0.85rem; color:#64748b; font-weight:500;">กำลังโหลด QR Code...</span>
                </div>
            </div>

            <div style="background:#f1f5f9; border-radius:8px; padding:8px 12px; margin-bottom:16px; display:flex; justify-content:space-between; align-items:center; gap:8px; text-align:left;">
                <div style="overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:240px;">
                    <span style="font-size:0.75rem; color:#64748b; display:block;">รหัสเช็คอิน:</span>
                    <code id="modalQrCodeText" style="font-size:0.85rem; color:#1e293b; font-weight:700;">-</code>
                </div>
                <button type="button" id="copyCodeBtn" class="btn btn-surface text-xs py-1 px-2" style="white-space:nowrap;">
                    <i class="fa-regular fa-copy"></i> คัดลอก
                </button>
            </div>

            <button type="button" id="closeQrModalBottomBtn" class="btn btn-secondary btn-block" style="padding:8px 16px; font-size:0.9rem;">
                ปิดหน้าต่าง
            </button>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script src="assets/js/profile.js?v=<?= time() ?>"></script>
<script>
// Toggle รายการรถ
document.querySelectorAll('.btn-toggle-vehicles').forEach(function(toggleButton) {
    toggleButton.addEventListener('click', function() {
        var targetElementId = this.dataset.target;
        var targetElement   = document.getElementById(targetElementId);
        if (targetElement) {
            targetElement.classList.toggle('d-none');
            if (!targetElement.classList.contains('d-none')) {
                targetElement.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
        }
    });
});
</script>

<?php include 'includes/footer.php'; ?>
