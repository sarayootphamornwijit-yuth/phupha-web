<?php
// activity_details.php
// ไฟล์นี้ทำหน้าที่แสดงรายละเอียดของกิจกรรมแต่ละรายการสำหรับทุกระดับสิทธิ์
// สมาชิก: ดูรายละเอียด, ลงทะเบียน, ประเมินผล, รับเกียรติบัตร และดูรายชื่อเพื่อนที่เข้าร่วม
// คณะกรรมการ: มีเครื่องมืออำนวยความสะดวก เช่น พิมพ์ใบรายชื่อ, สแกน QR, ดูรายงาน ERP
// อาจารย์ที่ปรึกษา: พิจารณาอนุมัติโครงการ และไม่อนุมัติโครงการ พร้อมข้อมูลติดต่อทีมงาน

require_once 'config/database.php';


// ส่วนที่ 1 รับ ID กิจกรรมจาก URL Parameter
$targetActivityId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($targetActivityId <= 0) {
    header("Location: index.php");
    exit;
}

$currentUserRole  = getCurrentUserRole();
$isStaffOrAdvisor = in_array($currentUserRole, ['advisor', 'committee', 'admin']);


// ส่วนที่ 2 การประมวลผลของอาจารย์ที่ปรึกษา (POST: อนุมัติ / ไม่อนุมัติ)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    verifyCsrfToken();
    $actionRequested = $_POST['action'];

    // สิทธิ์เฉพาะอาจารย์ที่ปรึกษาในการอนุมัติหรือไม่อนุมัติโครงการ
    if ($currentUserRole === 'advisor' && in_array($actionRequested, ['approve_activity', 'reject_activity'])) {
        $advisorUserId = getCurrentUserId();

        // ตรวจสอบสถานะปัจจุบันของกิจกรรม
        $checkStmt = $conn->prepare("SELECT approval_status FROM activities WHERE activity_id = ?");
        $checkStmt->bind_param("i", $targetActivityId);
        $checkStmt->execute();
        $currActivity = $checkStmt->get_result()->fetch_assoc();
        $checkStmt->close();

        if ($currActivity && $currActivity['approval_status'] === 'pending') {
            if ($actionRequested === 'approve_activity') {
                $newStatus     = 'approved';
                $approvalQuery = ", approved_at = NOW()";
                $alertMessage  = 'อนุมัติโครงการเรียบร้อยแล้ว';
            } else {
                $newStatus     = 'rejected';
                $approvalQuery = ", approved_at = NULL";
                $alertMessage  = 'ไม่อนุมัติโครงการเรียบร้อยแล้ว';
            }

            $updateStmt = $conn->prepare(
                "UPDATE activities SET approval_status = ?, advisor_id = ? $approvalQuery WHERE activity_id = ?"
            );
            $updateStmt->bind_param("sii", $newStatus, $advisorUserId, $targetActivityId);
            if ($updateStmt->execute()) {
                $_SESSION['alert'] = [
                    'type'  => 'success',
                    'title' => 'สำเร็จ',
                    'text'  => $alertMessage,
                ];
            }
            $updateStmt->close();
        }

        header("Location: activity_details.php?id=" . $targetActivityId);
        exit;
    }
}


// ส่วนที่ 3 ดึงข้อมูลกิจกรรมจากฐานข้อมูล
try {
    if ($isStaffOrAdvisor) {
        // ผู้ดูแลระบบ คณะกรรมการ และอาจารย์ที่ปรึกษา สามารถดูได้ทุกสถานะ (pending, approved, rejected)
        $activityStatement = $conn->prepare(
            "SELECT a.*,
                 (SELECT COUNT(*) FROM user_activities WHERE activity_id = a.activity_id) as current_participants
             FROM activities a
             WHERE a.activity_id = ?"
        );
        $activityStatement->bind_param("i", $targetActivityId);
    } else {
        // บุคคลทั่วไป และสมาชิกทั่วไป ดูได้เฉพาะกิจกรรมที่ได้รับการอนุมัติแล้วเท่านั้น
        $activityStatement = $conn->prepare(
            "SELECT a.*,
                 (SELECT COUNT(*) FROM user_activities WHERE activity_id = a.activity_id) as current_participants
             FROM activities a
             WHERE a.activity_id = ? AND a.approval_status = 'approved'"
        );
        $activityStatement->bind_param("i", $targetActivityId);
    }

    $activityStatement->execute();
    $activityResult = $activityStatement->get_result();
    $activityRecord = $activityResult ? $activityResult->fetch_assoc() : null;
    $activityStatement->close();

    // ถ้าไม่พบกิจกรรม ให้กลับหน้าหลัก
    if (!$activityRecord) {
        header("Location: index.php");
        exit;
    }


    // ส่วนที่ 4 ตรวจสอบสถานะกิจกรรมและผู้ใช้
    $activityEndDate = !empty($activityRecord['end_date']) ? $activityRecord['end_date'] : $activityRecord['start_date'];
    $isActivityPast  = (strtotime($activityEndDate) <= time());
    $isActivityEnded = $isActivityPast;
    $isUserLoggedIn  = isLoggedIn();
    $canRegister     = canRegisterActivity();

    $hasUserJoined      = false;
    $userActivityStatus = null;
    $hasUserEvaluated   = false;

    if ($isUserLoggedIn) {
        $loggedInUserId = $_SESSION['user_id'];

        // ตรวจสอบว่าผู้ใช้ปัจจุบันลงทะเบียนกิจกรรมนี้หรือไม่
        $joinCheckStatement = $conn->prepare(
            "SELECT status FROM user_activities WHERE user_id = ? AND activity_id = ?"
        );
        $joinCheckStatement->bind_param("ii", $loggedInUserId, $targetActivityId);
        $joinCheckStatement->execute();
        $joinResult = $joinCheckStatement->get_result();
        if ($joinResult && $joinResult->num_rows > 0) {
            $hasUserJoined      = true;
            $userActivityStatus = $joinResult->fetch_assoc()['status'];
        }
        $joinCheckStatement->close();

        // ตรวจสอบว่าเคยส่งแบบประเมินกิจกรรมนี้แล้วหรือไม่
        try {
            $evalCheck = $conn->prepare("SELECT 1 FROM evaluations WHERE user_id = ? AND activity_id = ? LIMIT 1");
            if ($evalCheck) {
                $evalCheck->bind_param("ii", $loggedInUserId, $targetActivityId);
                $evalCheck->execute();
                $evalResult = $evalCheck->get_result();
                $hasUserEvaluated = ($evalResult && $evalResult->num_rows > 0);
                $evalCheck->close();
            }
        } catch (\Throwable $e) {
            $hasUserEvaluated = false;
        }
    }

    // ดึงข้อมูลผู้ติดต่อโครงการ (สำหรับอาจารย์ที่ปรึกษา)
    $contactPersonsList = [];
    if ($currentUserRole === 'advisor') {
        if (!empty($activityRecord['venue_contact_user_id'])) {
            $prStatement = $conn->prepare(
                "SELECT name, phone, student_id, role, committee_position FROM users WHERE user_id = ?"
            );
            $prStatement->bind_param("i", $activityRecord['venue_contact_user_id']);
            $prStatement->execute();
            if ($prRow = $prStatement->get_result()->fetch_assoc()) {
                $contactPersonsList[] = $prRow;
            }
            $prStatement->close();
        } else {
            $prResult = $conn->query("SELECT name, phone, student_id, role, committee_position FROM users WHERE committee_position = 'pr'");
            if ($prResult) {
                while ($prRow = $prResult->fetch_assoc()) {
                    $contactPersonsList[] = $prRow;
                }
            }
            if (empty($contactPersonsList)) {
                $adminResult = $conn->query("SELECT name, phone, student_id, role, committee_position FROM users WHERE role = 'admin' LIMIT 1");
                if ($adminResult && $adminRow = $adminResult->fetch_assoc()) {
                    $contactPersonsList[] = $adminRow;
                }
            }
        }
    }

    // ดึงรายชื่อผู้เข้าร่วมกิจกรรมทั้งหมด (สำหรับผู้ใช้งานที่ล็อกอินแล้ว)
    $participantsList = [];
    if ($isUserLoggedIn) {
        $partStmt = $conn->prepare(
            "SELECT ua.status, ua.created_at,
                    u.user_id, u.name, u.student_id,
                    f.faculty_name, m.major_name
             FROM user_activities ua
             JOIN users u ON ua.user_id = u.user_id
             LEFT JOIN faculties f ON u.faculty_id = f.faculty_id
             LEFT JOIN majors m ON u.major_id = m.major_id
             WHERE ua.activity_id = ?
             ORDER BY ua.created_at ASC"
        );
        if ($partStmt) {
            $partStmt->bind_param("i", $targetActivityId);
            $partStmt->execute();
            $partResult = $partStmt->get_result();
            if ($partResult) {
                $participantsList = $partResult->fetch_all(MYSQLI_ASSOC);
            }
            $partStmt->close();
        }
    }

} catch (\Throwable $e) {
    error_log("Activity Details Error: " . $e->getMessage());
    $_SESSION['alert'] = [
        'type'  => 'error',
        'title' => 'เกิดข้อผิดพลาด',
        'text'  => 'ไม่สามารถโหลดข้อมูลกิจกรรมได้ในขณะนี้',
    ];
    header('Location: index.php');
    exit;
}


// ส่วนที่ 5 คำนวณและจัดรูปแบบการแสดงผลวันที่-เวลา
$startTimestamp = strtotime($activityRecord['start_date']);
$endTimestamp   = !empty($activityRecord['end_date'])
                ? strtotime($activityRecord['end_date'])
                : $startTimestamp;

$startDateString = date('Y-m-d', $startTimestamp);
$endDateString   = date('Y-m-d', $endTimestamp);
$startTimeString = date('H:i', $startTimestamp);
$endTimeString   = date('H:i', $endTimestamp);

$displayDateText = '';
$displayTimeText = '';

$thaiMonthNames = [
    '', 'มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน',
    'พฤษภาคม', 'มิถุนายน', 'กรกฎาคม', 'สิงหาคม',
    'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม',
];

if ($startDateString === $endDateString) {
    // กรณีกิจกรรม 1 วัน
    $dayNumber   = date('d', $startTimestamp);
    $monthNumber = date('m', $startTimestamp);
    $yearBE      = date('Y', $startTimestamp) + 543;
    $displayDateText = "วันที่ {$dayNumber}/{$monthNumber}/{$yearBE}";

    if ($startTimeString !== $endTimeString && $endTimeString !== '00:00') {
        $displayTimeText = "เวลา {$startTimeString} น. - {$endTimeString} น.";
    } else {
        $displayTimeText = "เวลา {$startTimeString} น.";
    }
} else {
    // กรณีกิจกรรมหลายวัน
    $startDay    = date('j', $startTimestamp);
    $endDay      = date('j', $endTimestamp);
    $startMonth  = (int)date('n', $startTimestamp);
    $endMonth    = (int)date('n', $endTimestamp);
    $startYearBE = date('Y', $startTimestamp) + 543;
    $endYearBE   = date('Y', $endTimestamp) + 543;

    if ($startMonth === $endMonth && $startYearBE === $endYearBE) {
        $displayDateText = "วันที่ {$startDay}-{$endDay} {$thaiMonthNames[$startMonth]} {$startYearBE}";
    } elseif ($startYearBE === $endYearBE) {
        $displayDateText = "วันที่ {$startDay} {$thaiMonthNames[$startMonth]} - {$endDay} {$thaiMonthNames[$endMonth]} {$startYearBE}";
    } else {
        $displayDateText = "วันที่ {$startDay} {$thaiMonthNames[$startMonth]} {$startYearBE} - {$endDay} {$thaiMonthNames[$endMonth]} {$endYearBE}";
    }

    $displayTimeText = "เวลา {$startTimeString} น. - {$endTimeString} น.";
}


// ส่วนที่ 6 โหลด Header
include 'includes/header.php';
?>

<!-- แจ้งเตือน Flash Alert ถ้ามี -->
<?php if (isset($_SESSION['alert'])): ?>
    <div id="flash-message"
         data-type="<?= $_SESSION['alert']['type'] ?>"
         data-title="<?= $_SESSION['alert']['title'] ?>"
         data-text="<?= $_SESSION['alert']['text'] ?>"
         class="d-none">
    </div>
    <?php unset($_SESSION['alert']); ?>
<?php endif; ?>

<div class="container py-4" style="max-width: 860px;">

    <!-- ลิงก์ย้อนกลับตามบทบาทของผู้ใช้งาน -->
    <div class="mb-3 hide-on-desktop">
        <?php if ($currentUserRole === 'advisor'): ?>
            <a href="advisor_approval.php" class="text-muted text-sm text-decoration-none">
                <i class="fa-solid fa-arrow-left"></i> กลับไปหน้ารายการอนุมัติ
            </a>
        <?php elseif ($currentUserRole === 'committee'): ?>
            <a href="erp_activities.php" class="text-muted text-sm text-decoration-none">
                <i class="fa-solid fa-arrow-left"></i> กลับไปหน้ารายการกิจกรรม
            </a>
        <?php else: ?>
            <a href="index.php" class="text-muted text-sm text-decoration-none">
                <i class="fa-solid fa-arrow-left"></i> กลับไปหน้าแรก
            </a>
        <?php endif; ?>
    </div>

    <!-- แถบเครื่องมือสำหรับอาจารย์ที่ปรึกษา (เฉพาะ Role: advisor) -->
    <?php if ($currentUserRole === 'advisor'): ?>
        <div class="card mb-4" style="background: var(--bg-surface); border: 1px solid var(--border-color);">
            <div class="d-flex justify-between align-center flex-wrap gap-2 mb-3">
                <div class="font-weight-bold text-md text-main">
                    การพิจารณาของอาจารย์ที่ปรึกษา
                </div>
                <div>
                    <?php if ($activityRecord['approval_status'] === 'pending'): ?>
                        <span class="badge badge-warning text-xs px-2 py-1">รอการพิจารณาอนุมัติ</span>
                    <?php elseif ($activityRecord['approval_status'] === 'approved'): ?>
                        <span class="badge badge-success text-xs px-2 py-1"><i class="fa-solid fa-check mr-1"></i> โครงการนี้ได้รับการอนุมัติแล้ว</span>
                    <?php elseif ($activityRecord['approval_status'] === 'rejected'): ?>
                        <span class="badge badge-danger text-xs px-2 py-1"><i class="fa-solid fa-xmark mr-1"></i> โครงการนี้ไม่อนุมัติ</span>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($activityRecord['approval_status'] === 'pending'): ?>
                <div class="d-flex gap-2 flex-wrap mb-3">
                    <form method="POST" class="m-0" onsubmit="return confirm('ยืนยันอนุมัติโครงการนี้หรือไม่?');">
                        <?php csrfField(); ?>
                        <input type="hidden" name="action" value="approve_activity">
                        <button type="submit" class="btn btn-success text-sm py-2 px-3" style="font-weight: 600;">
                            <i class="fa-solid fa-check mr-1"></i> อนุมัติโครงการ
                        </button>
                    </form>

                    <form method="POST" class="m-0" onsubmit="return confirm('ยืนยันไม่อนุมัติโครงการนี้หรือไม่?');">
                        <?php csrfField(); ?>
                        <input type="hidden" name="action" value="reject_activity">
                        <button type="submit" class="btn btn-danger text-sm py-2 px-3" style="font-weight: 600;">
                            <i class="fa-solid fa-xmark mr-1"></i> ไม่อนุมัติ
                        </button>
                    </form>
                </div>
            <?php endif; ?>

            <?php if (!empty($contactPersonsList)): ?>
                <div class="p-3 bg-surface rounded border text-sm" style="line-height: 1.6;">
                    <p class="mb-2 text-muted">
                        หากอาจารย์ที่ปรึกษามีข้อสงสัย หรือต้องการให้คณะกรรมการแก้ไขข้อมูล/เอกสาร
                        สามารถติดต่อฝ่ายประชาสัมพันธ์หรือผู้ประสานงานโครงการได้โดยตรงครับ
                    </p>

                    <?php foreach ($contactPersonsList as $contactData): ?>
                        <div class="mt-2 p-3 rounded" style="background: var(--bg-main);">
                            <div class="font-weight-bold text-main mb-1">
                                <?= htmlspecialchars($contactData['name']) ?>

                                <?php
                                if (!empty($activityRecord['venue_contact_user_id']) && $activityRecord['venue_contact_user_id'] != 0) {
                                    $contactRoleText = 'ผู้ประสานงานโครงการ';
                                } elseif ($contactData['committee_position'] === 'pr') {
                                    $contactRoleText = 'ฝ่ายประชาสัมพันธ์';
                                } else {
                                    $contactRoleText = 'ผู้ดูแลระบบ';
                                }
                                ?>
                                <span class="badge badge-surface text-xs ml-1">
                                    <?= $contactRoleText ?>
                                </span>
                            </div>

                            <?php if (!empty($contactData['phone'])): ?>
                                <div class="text-muted mb-1">
                                    เบอร์โทรศัพท์: <?= htmlspecialchars($contactData['phone']) ?>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($contactData['student_id'])): ?>
                                <div class="text-xs text-muted"><?= htmlspecialchars($contactData['student_id']) ?></div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- แถบเครื่องมืออำนวยความสะดวกสำหรับคณะกรรมการ (เฉพาะ Role: committee) -->
    <?php if ($currentUserRole === 'committee'): ?>
        <div class="card mb-4" style="background: var(--bg-surface); border: 1px solid var(--border-color);">
            <div class="d-flex justify-between align-center flex-wrap gap-2">
                <div class="font-weight-bold text-sm text-main">
                    <i class="fa-solid fa-toolbox mr-1 text-primary"></i> เครื่องมือจัดการกิจกรรมสำหรับคณะกรรมการ
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <a href="qr_scanner.php?activity_id=<?= $targetActivityId ?>" class="btn btn-primary text-xs py-1 px-3" style="display: inline-flex; align-items: center; gap: 6px;">
                        <i class="fa-solid fa-qrcode"></i> สแกน QR เช็คอิน
                    </a>
                    <a href="erp_reports.php?activity_id=<?= $targetActivityId ?>" class="btn btn-surface text-xs py-1 px-3" style="display: inline-flex; align-items: center; gap: 6px; border: 1px solid var(--border-color);">
                        <i class="fa-solid fa-chart-pie"></i> รายงานสรุป ERP
                    </a>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- การ์ดรายละเอียดกิจกรรม -->
    <div class="card p-0 overflow-hidden mb-4">

        <!-- รูปปกกิจกรรม (แสดงเฉพาะถ้ามี) -->
        <?php if (!empty($activityRecord['cover_image'])): ?>
            <div style="height: 350px; background: var(--bg-main);">
                <img src="<?= htmlspecialchars($activityRecord['cover_image']) ?>"
                     alt="รูปปกกิจกรรม"
                     style="width: 100%; height: 100%; object-fit: cover;"
                     onerror="this.onerror=null; this.parentElement.style.display='none';">
            </div>
        <?php endif; ?>

        <div class="p-4">

            <!-- สถานะกิจกรรม (กรณีดูโดยกรรมการ/อาจารย์/แอดมิน) -->
            <?php if ($isStaffOrAdvisor && $activityRecord['approval_status'] !== 'approved'): ?>
                <div class="mb-3">
                    <?php if ($activityRecord['approval_status'] === 'pending'): ?>
                        <span class="badge badge-warning text-xs px-2 py-1"><i class="fa-solid fa-clock mr-1"></i> โครงการนี้อยู่ระหว่างรออาจารย์อนุมัติ</span>
                    <?php elseif ($activityRecord['approval_status'] === 'rejected'): ?>
                        <span class="badge badge-danger text-xs px-2 py-1"><i class="fa-solid fa-circle-xmark mr-1"></i> โครงการนี้ไม่อนุมัติ</span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- ชื่อกิจกรรม -->
            <h1 class="text-2xl text-main m-0 mb-3 font-weight-bold">
                <?= htmlspecialchars($activityRecord['title']) ?>
            </h1>

            <!-- รายละเอียดกิจกรรม -->
            <?php if (!empty($activityRecord['description']) && trim($activityRecord['description']) !== ''): ?>
                <div class="mb-4">
                    <p class="text-muted text-md m-0" style="line-height: 1.8; white-space: pre-wrap;"><?= htmlspecialchars($activityRecord['description']) ?></p>
                </div>
            <?php endif; ?>

            <hr class="border-light mb-4">

            <!-- ข้อมูลกิจกรรมที่สำคัญ -->
            <div class="mb-4 d-flex flex-column gap-3">
                
                <!-- สถานที่ -->
                <div>
                    <strong class="text-main font-weight-bold mr-2">สถานที่</strong>
                    <span class="text-muted text-md"><?= htmlspecialchars($activityRecord['location_name']) ?></span>
                </div>

                <!-- วันที่และเวลา -->
                <div>
                    <strong class="text-main font-weight-bold mr-2">วันที่และเวลา</strong>
                    <span class="text-muted text-md"><?= $displayDateText ?> <?= $displayTimeText ?></span>
                </div>

                <!-- ข้อมูลสำหรับผู้ล็อกอินเท่านั้น -->
                <?php if ($canRegister || $hasUserJoined || $isStaffOrAdvisor): ?>
                    <!-- จุดนัดพบ -->
                    <div>
                        <strong class="text-main font-weight-bold mr-2">จุดนัดพบ</strong>
                        <span class="text-muted text-md">
                            <?php 
                                if (!empty($activityRecord['meeting_point'])) {
                                    echo htmlspecialchars($activityRecord['meeting_point']);
                                    if (!empty($activityRecord['meeting_time'])) {
                                        echo ' (เวลา ' . date('H:i', strtotime($activityRecord['meeting_time'])) . ' น.)';
                                    }
                                } else {
                                    echo 'รอประกาศ';
                                }
                            ?>
                        </span>
                        <?php if (!empty($activityRecord['survey_details'])): ?>
                            <div class="text-muted text-sm mt-1">
                                <?= nl2br(htmlspecialchars($activityRecord['survey_details'])) ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- จำนวนผู้เข้าร่วมกิจกรรม -->
                    <div>
                        <strong class="text-main font-weight-bold mr-2">จำนวนผู้เข้าร่วมกิจกรรม</strong>
                        <span class="text-muted text-md">
                            <?= $activityRecord['current_participants'] ?> / 
                            <?php
                            if ($activityRecord['max_participants'] > 0) {
                                echo $activityRecord['max_participants'] . ' คน';
                            } else {
                                echo 'ไม่จำกัดจำนวน';
                            }
                            ?>
                        </span>
                    </div>
                <?php elseif (!isLoggedIn()): ?>
                    <div class="text-muted text-sm mb-4">
                        <i class="fa-solid fa-circle-info"></i> ข้อมูลจุดนัดพบและจำนวนผู้เข้าร่วมจะแสดงสำหรับสมาชิกที่เข้าสู่ระบบแล้วเท่านั้น
                    </div>
                    
                    <?php if (strtotime($activityRecord['start_date']) <= time()): ?>
                        <button class="btn btn-block text-md py-2 mb-4 cursor-default"
                                style="background: #6c757d; border-color: #6c757d; color: #fff; justify-content: center;"
                                disabled>
                            เริ่มกิจกรรมแล้ว (ปิดรับสมัคร)
                        </button>
                    <?php else: ?>
                        <a href="login.php?redirect=activity_details.php?id=<?= $activityRecord['activity_id'] ?>"
                           class="btn btn-primary btn-block text-md py-2 mb-4"
                           style="justify-content: center; text-decoration: none;">
                            กรุณาสมัครสมาชิกและเข้าสู่ระบบเพื่อลงทะเบียน
                        </a>
                    <?php endif; ?>
                <?php endif; ?>
                
            </div>

            <!-- ปุ่มลงทะเบียน / ประเมิน / เกียรติบัตร / Facebook Link -->
            <?php if ($activityRecord['approval_status'] === 'approved' && ($isActivityPast || $canRegister || $hasUserJoined)): ?>
                <div class="d-flex flex-column gap-2 mt-2">

                    <?php if ($isUserLoggedIn && $hasUserJoined): ?>
                        <!-- ผู้ใช้ลงทะเบียนเข้าร่วมกิจกรรมนี้ -->

                        <?php if ($userActivityStatus === 'checked_in' || $userActivityStatus === 'checked_out'): ?>
                            <!-- สมาชิกเช็คอินเข้าร่วมกิจกรรมแล้ว -->

                            <?php if ($hasUserEvaluated): ?>
                                <!-- ประเมินแล้ว: บันทึกแต้มจิตอาสาแล้ว -->
                                <div class="alert alert-success text-center text-sm py-2 mb-2" style="background: #dcfce7; color: #166534; border: 1px solid #bbf7d0;">
                                    <i class="fa-solid fa-circle-check mr-1"></i> คุณทำแบบประเมินกิจกรรมนี้แล้ว (+<?= (int)$activityRecord['points'] ?> แต้มจิตอาสา)
                                </div>
                                <a href="evaluate.php?id=<?= $targetActivityId ?>" 
                                   class="text-sm text-center text-muted" 
                                   style="text-decoration: underline;">
                                    ดูผลการประเมินความพึงพอใจของคุณ
                                </a>
                            <?php else: ?>
                                <!-- ยังไม่ได้ประเมิน -->
                                <?php if ($isActivityEnded): ?>
                                    <a href="evaluate.php?id=<?= $targetActivityId ?>"
                                       class="btn btn-warning text-md py-2 w-100 mb-2"
                                       style="justify-content: center; text-decoration: none; font-weight: 600;">
                                        <i class="fa-solid fa-clipboard-check mr-1"></i> ทำแบบประเมินเพื่อรับคะแนนจิตอาสา
                                    </a>
                                <?php else: ?>
                                    <div class="alert alert-info text-center text-sm py-2 mb-2">
                                        <i class="fa-solid fa-circle-check mr-1"></i> เช็คอินแล้ว (รอทำแบบประเมินหลังสิ้นสุดกิจกรรม)
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>

                        <?php elseif ($userActivityStatus === 'registered'): ?>
                            <!-- ยังไม่ได้เช็คอิน -->
                            <?php if (!$isActivityPast): ?>
                                <a href="joined_activities.php"
                                   class="btn btn-primary text-md py-2 w-100 mb-2"
                                   style="justify-content: center; text-decoration: none; box-shadow: none;">
                                    กิจกรรมที่เข้าร่วม / เลือกการเดินทาง
                                </a>
                            <?php else: ?>
                                <div class="alert text-center text-sm py-2 mb-2 font-weight-bold" style="background: #fef2f2; color: #dc2626; border: 1px solid #fecaca;">
                                    <i class="fa-solid fa-circle-exclamation mr-1"></i> กิจกรรมนี้สิ้นสุดแล้ว คุณไม่ได้เช็คอินเข้าร่วมกิจกรรม
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>

                    <?php elseif ($isActivityPast): ?>

                        <!-- กิจกรรมสิ้นสุดแล้ว และไม่ได้เข้าร่วม: แสดงลิงก์ Facebook (ถ้ามี) -->
                        <?php if (!empty($activityRecord['facebook_link'])): ?>
                            <a href="<?= htmlspecialchars($activityRecord['facebook_link']) ?>"
                                target="_blank"
                                class="btn btn-block text-white text-md py-2"
                                style="background: #1976D2; justify-content: center;">
                                ดูเพิ่มเติมบน Facebook
                            </a>
                        <?php endif; ?>

                    <?php else: ?>

                        <!-- กิจกรรมที่ยังไม่สิ้นสุด และยังไม่ได้ลงทะเบียน -->
                        <?php if ($isUserLoggedIn && canRegisterActivity()): ?>
                            <?php $isActivityStarted = (strtotime($activityRecord['start_date']) <= time()); ?>

                            <?php if ($isActivityStarted): ?>
                                <!-- เริ่มกิจกรรมแล้ว ปิดรับสมัคร -->
                                <button class="btn btn-block py-2 text-md cursor-default"
                                        style="justify-content: center; background: #6c757d; border-color: #6c757d; color: #fff;"
                                        disabled>
                                    เริ่มกิจกรรมแล้ว (ปิดรับสมัคร)
                                </button>
                            <?php elseif ($activityRecord['max_participants'] > 0
                                       && $activityRecord['current_participants'] >= $activityRecord['max_participants']): ?>
                                <!-- ผู้ลงทะเบียนเต็มแล้ว -->
                                <button class="btn btn-danger btn-block py-2 text-md cursor-default"
                                        style="justify-content: center;"
                                        disabled>
                                    ผู้ลงทะเบียนเต็มจำนวนแล้ว
                                </button>
                            <?php else: ?>
                                <!-- ยังมีที่ว่าง: แสดงปุ่มลงทะเบียน -->
                                <form method="POST" action="index.php" class="m-0 w-100">
                                    <?php csrfField(); ?>
                                    <input type="hidden" name="action" value="register">
                                    <input type="hidden" name="activity_id" value="<?= $activityRecord['activity_id'] ?>">
                                    <button type="submit"
                                            class="btn btn-primary btn-block py-2 text-md"
                                            style="justify-content: center;">
                                        ลงทะเบียนเข้าร่วมกิจกรรม
                                    </button>
                                </form>
                            <?php endif; ?>

                        <?php endif; ?>

                    <?php endif; ?>

                    <?php if ($isActivityPast && !empty($activityRecord['facebook_link']) && $hasUserJoined): ?>
                        <a href="<?= htmlspecialchars($activityRecord['facebook_link']) ?>"
                           target="_blank"
                           class="btn btn-block text-white text-sm py-1 mt-1"
                           style="background: #1976D2; justify-content: center;">
                            ดูภาพกิจกรรมบน Facebook
                        </a>
                    <?php endif; ?>

                </div>
            <?php endif; ?>

        </div>
    </div>

    <!-- ส่วนที่ 7 รายชื่อผู้เข้าร่วมกิจกรรม (สำหรับผู้ใช้งานที่ล็อกอินแล้วทุกคน) -->
    <?php if ($isUserLoggedIn): ?>
        <div class="card mb-4">
            <div class="d-flex justify-between align-center flex-wrap gap-2 mb-3 pb-2 border-bottom">
                <h3 class="text-md m-0 font-weight-bold text-main">
                    รายชื่อผู้เข้าร่วมกิจกรรม (<?= count($participantsList) ?> คน)
                </h3>
                <span class="text-xs text-muted">
                    <?php
                    if ($activityRecord['max_participants'] > 0) {
                        echo 'รับจำนวนจำกัด ' . $activityRecord['max_participants'] . ' คน';
                    } else {
                        echo 'ไม่จำกัดจำนวนคน';
                    }
                    ?>
                </span>
            </div>

            <?php if (!empty($participantsList)): ?>
                <div class="table-responsive">
                    <table class="table w-100 text-sm mb-0" style="min-width: 580px;">
                        <thead class="bg-main text-muted">
                            <tr>
                                <th style="width: 50px; text-align: center;">ลำดับ</th>
                                <th style="width: 120px; text-align: center;">รหัสนักศึกษา</th>
                                <th style="text-align: left;">ชื่อ - นามสกุล</th>
                                <th style="text-align: left;">คณะ / สาขาวิชา</th>
                                <th style="width: 130px; text-align: center;">สถานะ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($participantsList as $idx => $participant): ?>
                                <tr>
                                    <td style="text-align: center; color: var(--text-muted);"><?= $idx + 1 ?></td>
                                    <td style="text-align: center; font-family: monospace; font-size: 13px; color: var(--text-muted);">
                                        <?= !empty($participant['student_id']) ? htmlspecialchars($participant['student_id']) : '-' ?>
                                    </td>
                                    <td style="text-align: left;">
                                        <div class="font-weight-bold text-main">
                                            <?= htmlspecialchars($participant['name']) ?>
                                        </div>
                                    </td>
                                    <td style="text-align: left;">
                                        <?php if (!empty($participant['faculty_name'])): ?>
                                            <div class="text-xs text-main"><?= htmlspecialchars($participant['faculty_name']) ?></div>
                                        <?php endif; ?>
                                        <?php if (!empty($participant['major_name'])): ?>
                                            <div class="text-xs text-muted"><?= htmlspecialchars($participant['major_name']) ?></div>
                                        <?php endif; ?>
                                        <?php if (empty($participant['faculty_name']) && empty($participant['major_name'])): ?>
                                            <span class="text-xs text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align: center;">
                                        <span class="badge badge-success text-xs">
                                            ลงทะเบียนแล้ว
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-4 text-muted text-sm">
                    ยังไม่มีผู้ลงทะเบียนเข้าร่วมกิจกรรมนี้
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

</div>

<script src="assets/js/profile.js"></script>

<?php include 'includes/footer.php'; ?>