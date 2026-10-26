<?php
// erp_vehicles.php
// ไฟล์นี้จัดการเกี่ยวกับระบบยานพาหนะ การนัดหมาย แผนการเดินทาง และคำนวณที่นั่งให้เพียงพอ

require_once 'config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();
}


// ส่วนที่ 1 ตรวจสอบสิทธิ์
requireCommitteePermission('vehicles');


// ส่วนที่ 2 จัดการรับค่าจาก POST (Form Submissions)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    $actionRequested = $_POST['action'];

    // 2.1 อัปเดตข้อมูลสถานที่และจุดนัดพบ (Logistics)
    if ($actionRequested === 'update_logistics') {
        try {
            $activityId     = (int)($_POST['activity_id'] ?? 0);
            $meetingPoint   = trim($_POST['meeting_point'] ?? '');
            $surveyDetails  = trim($_POST['survey_details'] ?? '');

            $updateLogisticsStatement = $conn->prepare(
                "UPDATE activities SET meeting_point = ?, survey_details = ? WHERE activity_id = ?"
            );
            $updateLogisticsStatement->bind_param("ssi", $meetingPoint, $surveyDetails, $activityId);

            if ($updateLogisticsStatement->execute()) {
                $_SESSION['alert'] = [
                    'type'  => 'success',
                    'title' => 'อัปเดตสำเร็จ!',
                    'text'  => 'บันทึกแผนการเดินทางเรียบร้อยแล้ว'
                ];
            } else {
                $_SESSION['alert'] = [
                    'type'  => 'error',
                    'title' => 'เกิดข้อผิดพลาด',
                    'text'  => 'ไม่สามารถอัปเดตข้อมูลได้'
                ];
            }
            
            $updateLogisticsStatement->close();
        } catch (\Throwable $e) {
            $_SESSION['alert'] = [
                'type'  => 'error',
                'title' => 'เกิดข้อผิดพลาด',
                'text'  => $e->getMessage()
            ];
        }
        header("Location: erp_vehicles.php?activity_id=" . ($activityId ?? 0));
        exit;
    }

    // 2.2 เพิ่มข้อมูลยานพาหนะเข้าสู่ระบบ (Add Vehicle)
    elseif ($actionRequested === 'add_vehicle') {
        try {
            $activityId    = (int)($_POST['activity_id'] ?? $_GET['activity_id'] ?? 0);
            $vehicleType   = trim($_POST['vehicle_type'] ?? '');
            $licensePlate  = trim($_POST['license_plate'] ?? '');
            $driverName    = trim($_POST['driver_name'] ?? '');
            $driverPhone   = trim($_POST['driver_phone'] ?? '');
            $seatCapacity  = (int)($_POST['capacity'] ?? 0);

            if ($activityId <= 0) {
                $_SESSION['alert'] = [
                    'type'  => 'error',
                    'title' => 'เกิดข้อผิดพลาด',
                    'text'  => 'ไม่พบรหัสกิจกรรม กรุณาเลือกกิจกรรมก่อนเพิ่มยานพาหนะ'
                ];
                header("Location: erp_vehicles.php");
                exit;
            }

            // ตรวจสอบว่ามีรถป้ายทะเบียนนี้ในกิจกรรมนี้แล้วหรือไม่ (ห้ามซ้ำกันใน 1 กิจกรรม)
            $checkDupStatement = $conn->prepare(
                "SELECT 1 FROM vehicles 
                 WHERE activity_id = ? AND REPLACE(license_plate, ' ', '') = REPLACE(?, ' ', '')"
            );
            $checkDupStatement->bind_param("is", $activityId, $licensePlate);
            $checkDupStatement->execute();
            $checkDupResult = $checkDupStatement->get_result();
            if ($checkDupResult && $checkDupResult->num_rows > 0) {
                $checkDupStatement->close();
                $_SESSION['alert'] = [
                    'type'  => 'warning',
                    'title' => 'ข้อมูลซ้ำ',
                    'text'  => 'รถป้ายทะเบียนนี้ (' . htmlspecialchars($licensePlate) . ') มีอยู่ในกิจกรรมนี้แล้ว ไม่สามารถเพิ่มซ้ำได้'
                ];
                header("Location: erp_vehicles.php?activity_id=" . $activityId);
                exit;
            }
            $checkDupStatement->close();

            $addVehicleStatement = $conn->prepare(
                "INSERT INTO vehicles (
                    activity_id, vehicle_type, license_plate, driver_name, driver_phone, capacity
                ) VALUES (?, ?, ?, ?, ?, ?)"
            );
            $addVehicleStatement->bind_param(
                "issssi", 
                $activityId, $vehicleType, $licensePlate, $driverName, $driverPhone, $seatCapacity
            );

            if ($addVehicleStatement->execute()) {
                $_SESSION['alert'] = [
                    'type'  => 'success',
                    'title' => 'เพิ่มรถสำเร็จ!',
                    'text'  => 'บันทึกข้อมูลยานพาหนะเข้าสู่ระบบแล้ว'
                ];
            } else {
                $_SESSION['alert'] = [
                    'type'  => 'error',
                    'title' => 'เกิดข้อผิดพลาด',
                    'text'  => 'ไม่สามารถบันทึกข้อมูลรถได้: ' . $conn->error
                ];
            }
            
            $addVehicleStatement->close();
        } catch (\Throwable $e) {
            $_SESSION['alert'] = [
                'type'  => 'error',
                'title' => 'เกิดข้อผิดพลาดในการเพิ่มรถ',
                'text'  => $e->getMessage()
            ];
        }
        header("Location: erp_vehicles.php?activity_id=" . $activityId);
        exit;
    }
}


// ส่วนที่ 3 จัดการลบยานพาหนะผ่าน POST Request (แก้ไขจาก GET เพื่อความปลอดภัย)
// เดิมใช้ GET ซึ่งอันตราย ตอนนี้เปลี่ยนเป็น POST พร้อมตรวจสอบ CSRF token
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_vehicle') {
    try {
        $deleteVehicleId  = (int)($_POST['delete_vehicle_id'] ?? 0);
        $returnActivityId = (int)($_POST['activity_id'] ?? 0);

        if ($deleteVehicleId > 0) {
            $deleteStatement = $conn->prepare("DELETE FROM vehicles WHERE vehicle_id = ?");
            $deleteStatement->bind_param("i", $deleteVehicleId);

            if ($deleteStatement->execute()) {
                $_SESSION['alert'] = [
                    'type'  => 'success',
                    'title' => 'ลบสำเร็จ!',
                    'text'  => 'ลบข้อมูลยานพาหนะเรียบร้อยแล้ว'
                ];
            } else {
                $_SESSION['alert'] = [
                    'type'  => 'error',
                    'title' => 'เกิดข้อผิดพลาด',
                    'text'  => 'ไม่สามารถลบยานพาหนะได้ กรุณาลองใหม่อีกครั้ง'
                ];
            }

            $deleteStatement->close();
        }
    } catch (\Throwable $e) {
        $_SESSION['alert'] = [
            'type'  => 'error',
            'title' => 'เกิดข้อผิดพลาด',
            'text'  => $e->getMessage()
        ];
    }

    header("Location: erp_vehicles.php?activity_id=" . ($returnActivityId ?? 0));
    exit;
}


// ส่วนที่ 4 ดึงข้อมูลเพื่อใช้แสดงผล (Display Data)
// 4.0 ตรวจสอบและสร้างตาราง vehicles และคอลัมน์ที่เกี่ยวข้องโดยอัตโนมัติหากยังไม่มี
try {
    $conn->query("
        CREATE TABLE IF NOT EXISTS vehicles (
            vehicle_id INT AUTO_INCREMENT PRIMARY KEY,
            activity_id INT NOT NULL,
            vehicle_type VARCHAR(100) NOT NULL,
            license_plate VARCHAR(50) NOT NULL,
            driver_name VARCHAR(100) NOT NULL,
            driver_phone VARCHAR(50) DEFAULT NULL,
            capacity INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY idx_activity (activity_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
} catch (\Throwable $e) {}

try {
    $conn->query("ALTER TABLE activities ADD COLUMN meeting_point VARCHAR(255) DEFAULT NULL");
} catch (\Throwable $e) {}
try {
    $conn->query("ALTER TABLE activities ADD COLUMN survey_details TEXT DEFAULT NULL");
} catch (\Throwable $e) {}
try {
    $conn->query("ALTER TABLE user_activities ADD COLUMN travel_method VARCHAR(50) DEFAULT NULL");
} catch (\Throwable $e) {}
try {
    $conn->query("ALTER TABLE user_activities ADD COLUMN vehicle_id INT DEFAULT NULL");
} catch (\Throwable $e) {}

// 4.1 ดึงรายชื่อกิจกรรมทั้งหมดสำหรับทำ Dropdown (แสดงแบบเรียงวันที่)
$activitiesQueryList = null;
try {
    $activitiesQueryList = $conn->query("SELECT activity_id, title FROM activities ORDER BY start_date DESC");
} catch (\Throwable $e) {
    // กรณีมีปัญหาในการ query
}

// รับค่าไอดีกิจกรรมที่เลือกเพื่อกรองดูข้อมูล
$selectedActivityId = (int)($_GET['activity_id'] ?? 0);

$activityDetailRecord = null;
$vehiclesDataList     = [];
$totalSeatsCapacity   = 0;
$totalRegisteredUsers = 0;

if ($selectedActivityId > 0) {
    // 4.2 ดึงข้อมูลกิจกรรมและรายละเอียดแผนการเดินทาง
    try {
        $activityDetailStatement = $conn->prepare("SELECT * FROM activities WHERE activity_id = ?");
        if ($activityDetailStatement) {
            $activityDetailStatement->bind_param("i", $selectedActivityId);
            $activityDetailStatement->execute();
            $activityDetailResult = $activityDetailStatement->get_result();
            
            if ($activityDetailResult && $activityDetailResult->num_rows > 0) {
                $activityDetailRecord = $activityDetailResult->fetch_assoc();
            }
            $activityDetailStatement->close();
        }
    } catch (\Throwable $e) {
        $activityDetailRecord = null;
    }

    // 4.3 ดึงข้อมูลขบวนรถทั้งหมดของกิจกรรมนี้ พร้อมหาผลรวมจำนวนที่นั่ง
    $vehiclesDataList = [];
    $totalSeatsCapacity = 0;
    try {
        $vRes = $conn->query("SELECT * FROM vehicles WHERE activity_id = " . (int)$selectedActivityId . " ORDER BY 1 ASC");
        if ($vRes) {
            while ($vehicleRow = $vRes->fetch_assoc()) {
                if (!isset($vehicleRow['vehicle_id']) && isset($vehicleRow['id'])) {
                    $vehicleRow['vehicle_id'] = $vehicleRow['id'];
                }
                $vehiclesDataList[] = $vehicleRow;
                $totalSeatsCapacity += (int)($vehicleRow['capacity'] ?? 0);
            }
            $vRes->free();
        }
    } catch (\Throwable $e) {
        $vehiclesDataList = [];
    }

    // 4.4 นับจำนวนคนทั้งหมดที่ลงทะเบียนร่วมกิจกรรมนี้
    try {
        $registeredCountStatement = $conn->prepare("SELECT COUNT(*) as total_users FROM user_activities WHERE activity_id = ?");
        if ($registeredCountStatement) {
            $registeredCountStatement->bind_param("i", $selectedActivityId);
            $registeredCountStatement->execute();
            $regResult = $registeredCountStatement->get_result();
            if ($regResult) {
                $regRow = $regResult->fetch_assoc();
                $totalRegisteredUsers = $regRow ? (int)$regRow['total_users'] : 0;
            }
            $registeredCountStatement->close();
        }
    } catch (\Throwable $e) {
        $totalRegisteredUsers = 0;
    }
}


// ส่วนที่ 5 โหลด Header
include 'includes/header.php';
?>

<!-- แจ้งเตือนสถานะต่างๆ ผ่าน ID flash-message -->
<?php if (isset($_SESSION['alert'])): ?>
    <div id="flash-message"
         data-type="<?= $_SESSION['alert']['type'] ?>"
         data-title="<?= $_SESSION['alert']['title'] ?>"
         data-text="<?= $_SESSION['alert']['text'] ?>"
         class="d-none">
    </div>
    <?php unset($_SESSION['alert']); ?>
<?php endif; ?>

<div class="container py-4">

    <!-- ส่วนหัวหน้าจอ -->
    <h2 class="section-title mb-4">ระบบจัดการสถานที่และยานพาหนะ</h2>

<!-- ส่วนเลือกกิจกรรม (Filter) -->
    <div class="card mb-4">
        <form method="GET" action="erp_vehicles.php" class="d-flex align-end gap-3 flex-wrap">
            <div class="flex-grow-1">
                <label class="form-label text-main">เลือกกิจกรรมที่ต้องการจัดการยานพาหนะ:</label>
                <select name="activity_id" class="form-control" required>
                    <option value="">-- กรุณาเลือกกิจกรรม --</option>
                    
                    <?php if ($activitiesQueryList && $activitiesQueryList->num_rows > 0): ?>
                        <?php while ($activityItem = $activitiesQueryList->fetch_assoc()): ?>
                            <?php $isSelected = ($selectedActivityId == $activityItem['activity_id']) ? 'selected' : ''; ?>
                            
                            <option value="<?= $activityItem['activity_id'] ?>" <?= $isSelected ?>>
                                <?= htmlspecialchars($activityItem['title']) ?>
                            </option>
                        <?php endwhile; ?>
                    <?php endif; ?>
                    
                </select>
            </div>
            <button type="submit" class="btn btn-primary">
                เรียกดูข้อมูล
            </button>
        </form>
    </div>


    <?php if ($activityDetailRecord !== null): ?>
        
<!-- Dashboard สรุปที่นั่ง (Overview) -->
        <div class="grid-3 mb-4">
            
            <div class="card bg-main">
                <div class="text-sm font-weight-bold mb-1" style="color: #000;">
                    จำนวนผู้เข้าร่วมทั้งหมด
                </div>
                <div class="text-xl font-weight-bold" style="color: #000;">
                    <?= $totalRegisteredUsers ?> <span class="text-sm font-normal" style="color: #000;">คน</span>
                </div>
            </div>
            
            <div class="card bg-main">
                <div class="text-sm font-weight-bold mb-1" style="color: #000;">
                    จำนวนที่นั่งบนรถรวมทั้งหมด
                </div>
                <div class="text-xl font-weight-bold" style="color: #000;">
                    <?= $totalSeatsCapacity ?> <span class="text-sm font-normal" style="color: #000;">ที่นั่ง</span>
                </div>
            </div>

            <?php 
                $isSeatsEnough = ($totalSeatsCapacity >= $totalRegisteredUsers);
            ?>
            <div class="card bg-main">
                <div class="text-sm font-weight-bold mb-1" style="color: #000;">
                    สถานะความเพียงพอของที่นั่ง
                </div>
                <div class="text-xl font-weight-bold" style="color: #000;">
                    <?php if ($isSeatsEnough): ?>
                        เพียงพอต่อการเดินทาง
                    <?php else: ?>
                        ขาดอีก <?= ($totalRegisteredUsers - $totalSeatsCapacity) ?> ที่นั่ง
                    <?php endif; ?>
                </div>
            </div>
            
        </div>


<!-- แบ่ง Layout สำหรับแก้ไขข้อมูลและแสดงรถ -->
        <div class="grid-layout" style="grid-template-columns: 1fr 2fr; gap: var(--space-20);">

            <!-- คอลัมน์ซ้าย: ฟอร์มจัดการต่างๆ -->
            <div class="d-flex flex-column gap-4">
                
                <!-- ฟอร์ม: แผนการเดินทางและจุดนัดพบ -->
                <div class="card">
                    <h3 class="text-md m-0 mb-3 pb-3 border-bottom text-main" style="color: #000;">
                        แผนการเดินทางและจุดนัดพบ
                    </h3>
                    
                    <form method="POST" action="erp_vehicles.php">
<?php csrfField(); ?>
                        <input type="hidden" name="action" value="update_logistics">
                        <input type="hidden" name="activity_id" value="<?= $selectedActivityId ?>">

                        <div class="form-group mb-3">
                            <label class="form-label" style="color: #000;">สถานที่จุดนัดพบ</label>
                            <input type="text" name="meeting_point" value="<?= htmlspecialchars($activityDetailRecord['meeting_point'] ?? '') ?>" placeholder="เช่น ลานอนุสาวรีย์..." onfocus="this.dataset.ph=this.placeholder; this.placeholder=''" onblur="this.placeholder=this.dataset.ph || ''" class="form-control" required>
                        </div>

                        <div class="form-group mb-4">
                            <label class="form-label" style="color: #000;">สรุปการสำรวจเส้นทาง สภาพถนนและคำแนะนำเพิ่มเติม</label>
                            <textarea name="survey_details" rows="3" class="form-control" onfocus="this.dataset.ph=this.placeholder; this.placeholder=''" onblur="this.placeholder=this.dataset.ph || ''"><?= htmlspecialchars($activityDetailRecord['survey_details'] ?? '') ?></textarea>
                        </div>

                        <button type="submit" class="btn btn-primary btn-block">บันทึกแผนการเดินทาง</button>
                    </form>
                </div>

                <!-- ฟอร์ม: เพิ่มยานพาหนะใหม่ -->
                <div class="card">
                    <h3 class="text-md m-0 mb-3 pb-3 border-bottom text-main" style="color: #000;">
                        เพิ่มยานพาหนะใหม่เข้าขบวน
                    </h3>
                    
                    <form method="POST" action="erp_vehicles.php">
<?php csrfField(); ?>
                        <input type="hidden" name="action" value="add_vehicle">
                        <input type="hidden" name="activity_id" value="<?= $selectedActivityId ?>">

                        <div class="form-group mb-3">
                            <label class="form-label" style="color: #000;">ประเภทยานพาหนะ <span class="text-danger">*</span></label>
                            <select name="vehicle_type" class="form-control" required>
                                <option value="รถตู้โดยสาร">รถตู้โดยสาร</option>
                                <option value="รถกระบะ (บรรทุกคน/ของ)">รถกระบะ (บรรทุกคน/ของ)</option>
                                <option value="รถยนต์ส่วนตัว (เก๋ง)">รถยนต์ส่วนตัว (เก๋ง)</option>
                                <option value="รถบัสมหาวิทยาลัย">รถบัสมหาวิทยาลัย</option>
                            </select>
                        </div>

                        <div class="d-flex gap-2 mb-3">
                            <div class="form-group flex-grow-1">
                                <label class="form-label" style="color: #000;">ป้ายทะเบียน <span class="text-danger">*</span></label>
                                <input type="text" name="license_plate" placeholder="เช่น กข 1234" onfocus="this.dataset.ph=this.placeholder; this.placeholder=''" onblur="this.placeholder=this.dataset.ph || ''" class="form-control" required>
                            </div>
                            <div class="form-group flex-grow-1">
                                <label class="form-label" style="color: #000;">จำนวนที่นั่ง <span class="text-danger">*</span></label>
                                <input type="number" name="capacity" min="1" placeholder="ความจุต่อคัน" onfocus="this.dataset.ph=this.placeholder; this.placeholder=''" onblur="this.placeholder=this.dataset.ph || ''" class="form-control" required>
                            </div>
                        </div>

                        <div class="form-group mb-3">
                            <label class="form-label" style="color: #000;">ชื่อคนขับ หรือ เจ้าของรถ <span class="text-danger">*</span></label>
                            <input type="text" name="driver_name" placeholder="ชื่อเล่น หรือ ชื่อจริง" onfocus="this.dataset.ph=this.placeholder; this.placeholder=''" onblur="this.placeholder=this.dataset.ph || ''" class="form-control" required>
                        </div>

                        <div class="form-group mb-4">
                            <label class="form-label" style="color: #000;">เบอร์โทรศัพท์ติดต่อคนขับ</label>
                            <input type="text" name="driver_phone" placeholder="08X-XXX-XXXX" onfocus="this.dataset.ph=this.placeholder; this.placeholder=''" onblur="this.placeholder=this.dataset.ph || ''" class="form-control">
                        </div>

                        <button type="submit" class="btn btn-success btn-block">
                            บันทึกยานพาหนะคันใหม่
                        </button>
                    </form>
                </div>
                
            </div>


            <!-- คอลัมน์ขวา: รายชื่อรถทั้งหมดที่ถูกเพิ่มในกิจกรรมนี้ -->
            <div class="card h-100">
                <h3 class="text-md m-0 mb-3 pb-3 border-bottom text-main" style="color: #000;">
                    ขบวนรถสำหรับกิจกรรมนี้
                </h3>

                <?php if (count($vehiclesDataList) > 0): ?>
                    
                    <div class="d-flex flex-column gap-3 mt-3">
                        <?php foreach ($vehiclesDataList as $indexValue => $vehicleData): ?>
                            
                            <div class="bg-main border-light rounded p-3 d-flex justify-between align-center gap-3">
                                
                                <div class="flex-grow-1">
                                    <div class="d-flex align-center gap-2 mb-2">
                                        <span style="color: #000; font-weight: bold; font-size: 13px;">คันที่ <?= $indexValue + 1 ?></span>
                                        <h4 class="m-0 text-sm" style="color: #000; font-weight: bold;"><?= htmlspecialchars($vehicleData['vehicle_type']) ?></h4>
                                    </div>
                                    
                                    <div class="grid-2 gap-2 text-xs mt-2" style="color: #000;">
                                        <div><strong>ทะเบียน:</strong> <?= htmlspecialchars($vehicleData['license_plate']) ?></div>
                                        <div><strong>ความจุ:</strong> <?= $vehicleData['capacity'] ?> ที่นั่ง</div>
                                        <div><strong>คนขับ:</strong> <?= htmlspecialchars($vehicleData['driver_name']) ?></div>
                                        <div><strong>ติดต่อ:</strong> <?= htmlspecialchars($vehicleData['driver_phone'] ?: '- ไม่มีข้อมูล -') ?></div>
                                    </div>
                                </div>
                                
                                <div class="text-right pl-3" style="border-left: 1px dashed #cbd5e1;">
                                    <?php
                                    // สร้าง CSRF token เพื่อความปลอดภัย
                                    $csrfTokenForDelete = htmlspecialchars(generateCsrfToken(), ENT_QUOTES, 'UTF-8');
                                    ?>
                                    <!-- ฟอร์มสำหรับลบรถ เปลี่ยนจาก GET เป็น POST เพื่อความปลอดภัย -->
                                    <form method="POST" action="erp_vehicles.php" class="m-0 p-0" id="delete-form-<?= $vehicleData['vehicle_id'] ?>">
                                        <input type="hidden" name="csrf_token" value="<?= $csrfTokenForDelete ?>">
                                        <input type="hidden" name="action" value="delete_vehicle">
                                        <input type="hidden" name="delete_vehicle_id" value="<?= $vehicleData['vehicle_id'] ?>">
                                        <input type="hidden" name="activity_id" value="<?= $selectedActivityId ?>">
                                        <button type="button"
                                                class="btn btn-danger text-xs btn-delete-vehicle"
                                                data-form-id="delete-form-<?= $vehicleData['vehicle_id'] ?>"
                                                title="ลบรถคันนี้"
                                                style="box-shadow: none !important; transform: none !important;">
                                            ลบ
                                        </button>
                                    </form>
                                </div>
                                
                            </div>
                            
                        <?php endforeach; ?>
                    </div>
                    
                <?php else: ?>
                    
                    <div class="text-center p-4 border-dashed mt-3" style="color: #000;">
                        <p class="m-0" style="color: #000;">ยังไม่มีการเพิ่มยานพาหนะสำหรับกิจกรรมนี้<br>กรุณาเพิ่มรถในฟอร์มด้านซ้ายมือ</p>
                    </div>
                    
                <?php endif; ?>
                
            </div>

        </div>
    <?php endif; ?>
</div>

<style>
.form-control:focus::placeholder, textarea.form-control:focus::placeholder {
    color: transparent !important;
}
</style>

<script>
    document.addEventListener("DOMContentLoaded", () => {
        // แจ้งเตือนก่อนทำการลบรถออกจากระบบด้วย Native Confirm
        const deleteVehicleButtons = document.querySelectorAll('.btn-delete-vehicle');
        deleteVehicleButtons.forEach(function(button) {
            button.addEventListener('click', function() {
                var formId = this.getAttribute('data-form-id');
                var deleteForm = document.getElementById(formId);
                if (confirm('ยืนยันการลบรถคันนี้\nคุณต้องการลบยานพาหนะนี้ออกจากขบวนรถใช่หรือไม่ (ไม่สามารถกู้คืนได้)')) {
                    deleteForm.submit();
                }
            });
        });
    });
</script>

<?php include 'includes/footer.php'; ?>