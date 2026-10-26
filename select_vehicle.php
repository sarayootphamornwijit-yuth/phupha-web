<?php
// select_vehicle.php
// ไฟล์สำหรับให้สมาชิกเลือกรูปแบบการเดินทางสำหรับกิจกรรม
// มี 2 รูปแบบให้เลือก:
// 1. เดินทางด้วยตนเอง (บันทึก travel_method = 'own_travel' และ vehicle_id = NULL)
// 2. เดินทางโดยรถของชมรม (บันทึก travel_method = 'club_vehicle' และเก็บ vehicle_id ที่เลือก)

require_once 'config/database.php';

// ตรวจสอบ CSRF Token เมื่อส่งฟอร์มแบบ POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();
}

// ตรวจสอบการเข้าสู่ระบบ
if (!isLoggedIn()) {
    header("Location: login.php");
    exit;
}

$currentUserId = $_SESSION['user_id'];
$activityId = isset($_GET['activity_id']) ? (int)$_GET['activity_id'] : 0;

// ตรวจสอบว่ามีรหัสกิจกรรมถูกต้องหรือไม่
if ($activityId <= 0) {
    header("Location: index.php");
    exit;
}

// ดึงข้อมูลกิจกรรม เช่น ชื่อกิจกรรม วันที่ สถานที่ จุดนัดพบ
$actStmt = $conn->prepare("SELECT activity_id, title, location_name, meeting_point, meeting_time, start_date FROM activities WHERE activity_id = ?");
$actStmt->bind_param("i", $activityId);
$actStmt->execute();
$activity = $actStmt->get_result()->fetch_assoc();
$actStmt->close();

if (!$activity) {
    header("Location: index.php");
    exit;
}

// ตรวจสอบว่าสมาชิกเคยลงทะเบียนกิจกรรมนี้ไว้หรือยัง
$checkRegStmt = $conn->prepare("SELECT travel_method, vehicle_id FROM user_activities WHERE user_id = ? AND activity_id = ?");
$checkRegStmt->bind_param("ii", $currentUserId, $activityId);
$checkRegStmt->execute();
$userRegistration = $checkRegStmt->get_result()->fetch_assoc();
$checkRegStmt->close();

// จัดการการส่งฟอร์มเลือกรูปแบบการเดินทาง (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_travel') {
    $travelType = trim($_POST['travel_type'] ?? '');

    // กรณีที่ 1: สมาชิกเลือกเดินทางด้วยตนเอง
    if ($travelType === 'own_travel') {
        $travelMethod = 'own_travel';
        $chosenVehicleId = null;

        // ตรวจสอบว่ามีข้อมูลการลงทะเบียนเดิมอยู่แล้วหรือไม่
        if ($userRegistration) {
            // ถ้ามีแล้ว ให้อัปเดตข้อมูล
            $updateStmt = $conn->prepare("UPDATE user_activities SET travel_method = ?, vehicle_id = NULL WHERE user_id = ? AND activity_id = ?");
            $updateStmt->bind_param("sii", $travelMethod, $currentUserId, $activityId);
            $updateStmt->execute();
            $updateStmt->close();
        } else {
            // ถ้ายังไม่มี ให้เพิ่มข้อมูลการลงทะเบียนใหม่
            $insertStmt = $conn->prepare("INSERT INTO user_activities (user_id, activity_id, status, travel_method, vehicle_id) VALUES (?, ?, 'registered', ?, NULL)");
            $insertStmt->bind_param("iis", $currentUserId, $activityId, $travelMethod);
            $insertStmt->execute();
            $insertStmt->close();
        }

        $_SESSION['alert'] = [
            'type'  => 'success',
            'title' => 'บันทึกสำเร็จ',
            'text'  => 'คุณเลือกเดินทางด้วยตนเองเรียบร้อยแล้ว'
        ];
        header("Location: joined_activities.php");
        exit;
    }

    // กรณีที่ 2: สมาชิกเลือกเดินทางโดยรถของชมรม
    elseif ($travelType === 'club_vehicle') {
        $selectedVehicleId = (int)($_POST['vehicle_id'] ?? 0);

        // ตรวจสอบว่าได้เลือกรถหรือไม่
        if ($selectedVehicleId <= 0) {
            $_SESSION['alert'] = [
                'type'  => 'error',
                'title' => 'กรุณาเลือกรถ',
                'text'  => 'กรุณาเลือกรถของชมรมที่ต้องการโดยสาร'
            ];
            header("Location: select_vehicle.php?activity_id=" . $activityId);
            exit;
        }

        // ตรวจสอบข้อมูลรถคันที่เลือกและคำนวณที่นั่งว่าง
        $vCheckStmt = $conn->prepare("
            SELECT capacity, 
                   (SELECT COUNT(*) FROM user_activities WHERE vehicle_id = ? AND user_id != ?) AS booked 
            FROM vehicles 
            WHERE vehicle_id = ? AND activity_id = ?
        ");
        $vCheckStmt->bind_param("iiii", $selectedVehicleId, $currentUserId, $selectedVehicleId, $activityId);
        $vCheckStmt->execute();
        $vCheckData = $vCheckStmt->get_result()->fetch_assoc();
        $vCheckStmt->close();

        // ตรวจสอบว่ารถยังมีที่นั่งว่างหรือไม่
        if ($vCheckData && $vCheckData['booked'] < $vCheckData['capacity']) {
            $travelMethod = 'club_vehicle';

            // ถ้ามีประวัติการลงทะเบียนเดิม ให้อัปเดต
            if ($userRegistration) {
                $updateStmt = $conn->prepare("UPDATE user_activities SET travel_method = ?, vehicle_id = ? WHERE user_id = ? AND activity_id = ?");
                $updateStmt->bind_param("siii", $travelMethod, $selectedVehicleId, $currentUserId, $activityId);
                $updateStmt->execute();
                $updateStmt->close();
            } else {
                // ถ้ายังไม่มี ให้บันทึกใหม่
                $insertStmt = $conn->prepare("INSERT INTO user_activities (user_id, activity_id, status, travel_method, vehicle_id) VALUES (?, ?, 'registered', ?, ?)");
                $insertStmt->bind_param("iisi", $currentUserId, $activityId, $travelMethod, $selectedVehicleId);
                $insertStmt->execute();
                $insertStmt->close();
            }

            $_SESSION['alert'] = [
                'type'  => 'success',
                'title' => 'บันทึกสำเร็จ',
                'text'  => 'คุณเลือกเดินทางโดยรถของชมรมเรียบร้อยแล้ว'
            ];
            header("Location: joined_activities.php");
            exit;
        } else {
            // กรณีรถเต็มแล้ว
            $_SESSION['alert'] = [
                'type'  => 'error',
                'title' => 'รถเต็มแล้ว',
                'text'  => 'ขออภัย รถคันที่คุณเลือกมีที่นั่งเต็มแล้ว กรุณาเลือกรถคันอื่น'
            ];
            header("Location: select_vehicle.php?activity_id=" . $activityId);
            exit;
        }
    }
}

// ดึงรายการรถทั้งหมดที่สังกัดกิจกรรมนี้
$vehiclesList = [];
$vQuery = $conn->prepare("
    SELECT v.vehicle_id, v.vehicle_type, v.license_plate, v.driver_name, v.driver_phone, v.capacity,
           (SELECT COUNT(*) FROM user_activities ua WHERE ua.vehicle_id = v.vehicle_id) AS booked
    FROM vehicles v
    WHERE v.activity_id = ?
    ORDER BY v.vehicle_id ASC
");
$vQuery->bind_param("i", $activityId);
$vQuery->execute();
$vResult = $vQuery->get_result();
while ($row = $vResult->fetch_assoc()) {
    $vehiclesList[] = $row;
}
$vQuery->close();

include 'includes/header.php';
?>

<div class="container py-4" style="max-width: 800px;">

    <!-- ปุ่มย้อนกลับ -->
    <div class="mb-3">
        <a href="joined_activities.php" class="btn btn-surface text-sm">ย้อนกลับ</a>
    </div>

    <!-- ส่วนหัวหน้าจอ -->
    <h2 class="section-title mb-2">เลือกรูปแบบการเดินทาง</h2>
    <p class="text-muted text-sm mb-4">กรุณาเลือกวิธีการเดินทางเพื่อเข้าร่วมกิจกรรม</p>

    <!-- กล่องข้อมูลกิจกรรมและจุดนัดหมาย -->
    <div class="card p-3 mb-4" style="background: #f8fafc; border-left: 4px solid var(--color-primary);">
        <h3 class="text-md m-0 mb-2" style="font-weight: 600; color: #0f172a;">
            <?= htmlspecialchars($activity['title']) ?>
        </h3>
        <div class="text-sm text-muted mb-1">
            <strong>สถานที่จัด:</strong> <?= htmlspecialchars($activity['location_name'] ?: 'ไม่ระบุ') ?>
        </div>
        <div class="text-sm text-muted mb-1">
            <strong>วันที่จัด:</strong> <?= date('d/m/Y', strtotime($activity['start_date'])) ?>
        </div>
        <?php if (!empty($activity['meeting_point'])): ?>
            <div class="text-sm text-muted mb-1">
                <strong>จุดนัดพบขึ้นรถ:</strong> <?= htmlspecialchars($activity['meeting_point']) ?>
            </div>
        <?php endif; ?>
        <?php if (!empty($activity['meeting_time'])): ?>
            <div class="text-sm text-muted">
                <strong>เวลานัดหมายขึ้นรถ:</strong> <?= date('d/m/Y H:i', strtotime($activity['meeting_time'])) ?> น.
            </div>
        <?php endif; ?>
    </div>

    <!-- ตัวเลือกรูปแบบการเดินทาง 2 แบบชัดเจน -->
    <div class="grid-2 gap-4 mb-4">

        <!-- ตัวเลือกที่ 1: เดินทางด้วยตนเอง -->
        <div class="card p-4 d-flex flex-column justify-between" style="border: 2px solid <?= ($userRegistration && $userRegistration['travel_method'] === 'own_travel') ? 'var(--color-primary)' : '#e2e8f0' ?>;">
            <div>
                <h4 class="text-md m-0 mb-2" style="font-weight: 600;">1. เดินทางด้วยตนเอง</h4>
                <p class="text-sm text-muted mb-4" style="line-height: 1.6;">
                    สมาชิกเดินทางไปยังสถานที่จัดกิจกรรมด้วยตนเอง เช่น รถจักรยานยนต์ส่วนตัว หรือรถยนต์ส่วนตัว โดยไม่ใช้บริการรถของชมรม
                </p>
                <?php if ($userRegistration && $userRegistration['travel_method'] === 'own_travel'): ?>
                    <div class="badge mb-3" style="background: #dcfce7; color: #166534; padding: 5px 10px;">
                        คุณเลือกรูปแบบนี้อยู่
                    </div>
                <?php endif; ?>
            </div>

            <form method="POST" action="select_vehicle.php?activity_id=<?= $activityId ?>" class="m-0">
                <?php csrfField(); ?>
                <input type="hidden" name="action" value="save_travel">
                <input type="hidden" name="travel_type" value="own_travel">
                <button type="submit" class="btn btn-surface btn-block" style="border: 1px solid #94a3b8;">
                    ยืนยันเดินทางด้วยตนเอง
                </button>
            </form>
        </div>

        <!-- ข้อมูลภาพรวมตัวเลือกที่ 2: รถของชมรม -->
        <div class="card p-4 d-flex flex-column justify-between" style="border: 2px solid <?= ($userRegistration && $userRegistration['travel_method'] === 'club_vehicle') ? 'var(--color-primary)' : '#e2e8f0' ?>;">
            <div>
                <h4 class="text-md m-0 mb-2" style="font-weight: 600;">2. เดินทางโดยรถของชมรม</h4>
                <p class="text-sm text-muted mb-3" style="line-height: 1.6;">
                    สมาชิกเดินทางไปพร้อมกับคณะ โดยขึ้นรถที่ทางชมรมจัดเตรียมไว้ตามจุดนัดหมาย
                </p>
                <div class="text-sm text-muted">
                    มีรถให้บริการทั้งหมด: <strong><?= count($vehiclesList) ?></strong> คัน
                </div>
            </div>
            <div class="pt-3">
                <span class="text-xs text-muted">* เลือกคันรถที่ต้องการด้านล่าง</span>
            </div>
        </div>

    </div>

    <!-- รายการรถของชมรมสำหรับกิจกรรมนี้ -->
    <h3 class="text-md m-0 mb-3" style="font-weight: 600;">รายการรถของชมรมสำหรับกิจกรรมนี้</h3>

    <?php if (!empty($vehiclesList)): ?>
        <div class="grid-2 gap-3">
            <?php foreach ($vehiclesList as $v): ?>
                <?php
                // คำนวณที่นั่งว่างคงเหลือ
                $capacity  = (int)$v['capacity'];
                $booked    = (int)$v['booked'];
                $available = $capacity - $booked;
                $isFull    = ($available <= 0);

                // ตรวจสอบว่าสมาชิกคนนี้เลือกรถคันนี้อยู่หรือไม่
                $isMyVehicle = ($userRegistration && (int)$userRegistration['vehicle_id'] === (int)$v['vehicle_id']);
                ?>
                <div class="card p-3 d-flex flex-column justify-between" style="<?= $isFull ? 'background: #f8fafc; border-color: #cbd5e1;' : '' ?> <?= $isMyVehicle ? 'border: 2px solid #16a34a;' : '' ?>">
                    <div>
                        <div class="d-flex justify-between align-center mb-2">
                            <h4 class="text-md m-0 font-weight-bold" style="color: #0f172a;">
                                <?= htmlspecialchars($v['vehicle_type']) ?>
                            </h4>
                            <?php if ($isFull): ?>
                                <span class="badge" style="background: #fee2e2; color: #991b1b; padding: 3px 8px; font-size: 12px;">
                                    (เต็มแล้ว)
                                </span>
                            <?php else: ?>
                                <span class="badge" style="background: #dcfce7; color: #166534; padding: 3px 8px; font-size: 12px;">
                                    ว่าง <?= $available ?> ที่นั่ง
                                </span>
                            <?php endif; ?>
                        </div>

                        <ul class="text-sm text-muted m-0 p-0 mb-3" style="list-style: none; line-height: 1.8;">
                            <li><strong>ทะเบียน:</strong> <?= htmlspecialchars($v['license_plate']) ?></li>
                            <li><strong>คนขับ:</strong> <?= htmlspecialchars($v['driver_name']) ?></li>
                            <li><strong>เบอร์โทร:</strong> <?= htmlspecialchars($v['driver_phone'] ?: 'ไม่ระบุ') ?></li>
                            <li><strong>ความจุทั้งหมด:</strong> <?= $capacity ?> ที่นั่ง (จองแล้ว <?= $booked ?> ที่นั่ง)</li>
                        </ul>

                        <?php if ($isMyVehicle): ?>
                            <div class="badge mb-3" style="background: #dcfce7; color: #166534; padding: 4px 8px; font-size: 12px;">
                                คุณเลือกรถคันนี้อยู่
                            </div>
                        <?php endif; ?>
                    </div>

                    <div>
                        <?php if ($isFull && !$isMyVehicle): ?>
                            <!-- ถ้ารถเต็มแล้ว ให้แสดงข้อความและห้ามเลือก -->
                            <button type="button" class="btn btn-surface btn-block text-muted" disabled style="cursor: not-allowed;">
                                (เต็มแล้ว)
                            </button>
                        <?php else: ?>
                            <!-- รถยังว่าง หรือเป็นคันเดิมที่เคยเลือก -->
                            <form method="POST" action="select_vehicle.php?activity_id=<?= $activityId ?>" class="m-0">
                                <?php csrfField(); ?>
                                <input type="hidden" name="action" value="save_travel">
                                <input type="hidden" name="travel_type" value="club_vehicle">
                                <input type="hidden" name="vehicle_id" value="<?= $v['vehicle_id'] ?>">
                                <button type="submit" class="btn btn-primary btn-block">
                                    <?= $isMyVehicle ? 'ยืนยันคันเดิม' : 'เลือกรถคันนี้' ?>
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="card p-4 text-center text-muted">
            <p class="m-0">ยังไม่มีข้อมูลรถที่ผูกกับกิจกรรมนี้</p>
        </div>
    <?php endif; ?>

</div>

<?php include 'includes/footer.php'; ?>
