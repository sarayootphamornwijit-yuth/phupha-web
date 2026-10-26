<?php
// evaluate.php
// ไฟล์นี้ทำหน้าที่แสดงแบบฟอร์มประเมินความพึงพอใจในกิจกรรม
// สมาชิกที่เช็คอินกิจกรรมแล้ว สามารถให้คะแนนดาวและเขียนข้อเสนอแนะได้
// หลังจากประเมินแล้วจะได้รับแต้มจิตอาสา

require_once 'config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();
}


// ส่วนที่ 1 ตรวจสอบสิทธิ์การเข้าถึง
if (!isLoggedIn()) {
    header("Location: login.php");
    exit;
}


// ส่วนที่ 2 รับพารามิเตอร์จาก URL
$currentUserId = $_SESSION['user_id'];
$targetActivityId = 0;
if (isset($_GET['id'])) {
    $targetActivityId = (int)$_GET['id'];
}


// ส่วนที่ 3 ตรวจสอบข้อมูลกิจกรรม
$activityStatement = $conn->prepare("SELECT title, points, start_date, end_date FROM activities WHERE activity_id = ?");
$activityStatement->bind_param("i", $targetActivityId);
$activityStatement->execute();
$activityQueryResult = $activityStatement->get_result();

if ($activityQueryResult->num_rows === 0) {
    // ไม่พบกิจกรรม: redirect กลับหน้าหลัก
    $_SESSION['alert'] = [
        'type'  => 'error',
        'title' => 'ไม่พบข้อมูล',
        'text'  => 'ไม่พบข้อมูลกิจกรรมนี้',
    ];
    header("Location: index.php");
    exit;
}

$activityRecord = $activityQueryResult->fetch_assoc();
$activityStatement->close();

// ส่วนที่ 4 ตรวจสอบว่าผู้ใช้เข้าร่วมและเช็คอินกิจกรรมนี้จริงหรือไม่
$joinCheckStatement = $conn->prepare(
    "SELECT status FROM user_activities WHERE activity_id = ? AND user_id = ?"
);
$joinCheckStatement->bind_param("ii", $targetActivityId, $currentUserId);
$joinCheckStatement->execute();
$joinCheckResult    = $joinCheckStatement->get_result();
$userActivityRecord = null;
if ($joinCheckResult && $joinCheckResult->num_rows > 0) {
    $userActivityRecord = $joinCheckResult->fetch_assoc();
}
$joinCheckStatement->close();

// สมาชิกทั่วไปที่ไม่ได้ลงทะเบียนกิจกรรมนี้ ไม่สามารถประเมินได้
if ($userActivityRecord === null) {
    $_SESSION['alert'] = [
        'type'  => 'warning',
        'title' => 'ปฏิเสธการเข้าถึง',
        'text'  => 'คุณไม่ได้ลงทะเบียนเข้าร่วมกิจกรรมนี้',
    ];
    header("Location: index.php");
    exit;
}

// ต้องมีสถานะเช็คอินเข้าร่วมกิจกรรมแล้วเท่านั้น จึงจะสามารถทำแบบประเมินได้
$userStatus = $userActivityRecord['status'];
if ($userStatus !== 'checked_in' && $userStatus !== 'checked_out') {
    $_SESSION['alert'] = [
        'type'  => 'warning',
        'title' => 'ยังไม่ได้เช็คอิน',
        'text'  => 'คุณต้องเช็คอินเข้าร่วมกิจกรรมก่อน จึงจะสามารถทำแบบประเมินความพึงพอใจได้',
    ];
    header("Location: activity_details.php?id=" . $targetActivityId);
    exit;
}

// ตรวจสอบเวลาสิ้นสุดกิจกรรม: ไม่อนุญาตให้ทำแบบประเมินก่อนสิ้นสุดกิจกรรมตามวันเวลาที่กำหนด
$activityEndDate = $activityRecord['start_date'];
if (!empty($activityRecord['end_date'])) {
    $activityEndDate = $activityRecord['end_date'];
}

$currentTimestamp = time();
$endTimestamp = strtotime($activityEndDate);

// ถ้าเวลายังไม่ถึงเวลาสิ้นสุดกิจกรรม
if ($endTimestamp > $currentTimestamp) {
    $_SESSION['alert'] = [
        'type'  => 'warning',
        'title' => 'กิจกรรมยังไม่สิ้นสุด',
        'text'  => 'แบบประเมินความพึงพอใจจะเปิดให้ทำเมื่อสิ้นสุดกิจกรรมตามวันเวลาที่กำหนดแล้วเท่านั้น',
    ];
    header("Location: activity_details.php?id=" . $targetActivityId);
    exit;
}


// ส่วนที่ 5 ตรวจสอบว่าเคยประเมินกิจกรรมนี้ไปแล้วหรือยัง
$evalCheckStatement = $conn->prepare(
    "SELECT q1, q2, q3, q4, q5, q6, created_at as submitted_at
     FROM evaluations
     WHERE activity_id = ? AND user_id = ?"
);
$evalCheckStatement->bind_param("ii", $targetActivityId, $currentUserId);
$evalCheckStatement->execute();
$evalQueryResult  = $evalCheckStatement->get_result();

$hasAlreadyEvaluated = false;
$existingEvalData = null;
if ($evalQueryResult && $evalQueryResult->num_rows > 0) {
    $hasAlreadyEvaluated = true;
    $existingEvalData = $evalQueryResult->fetch_assoc();
}

$evalCheckStatement->close();


// ส่วนที่ 6 บันทึกผลการประเมิน (เมื่อผู้ใช้กดปุ่มส่ง POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'submit_eval') {

        // ตรวจสอบว่าเคยประเมินแล้วหรือยัง (ป้องกันการส่งซ้ำ)
        if ($hasAlreadyEvaluated === true) {
            $_SESSION['alert'] = [
                'type'  => 'info',
                'title' => 'ประเมินแล้ว',
                'text'  => 'คุณได้ประเมินกิจกรรมนี้ไปแล้วครับ',
            ];
            header("Location: activity_details.php?id=" . $targetActivityId);
            exit;
        }

        // รับค่าคะแนนแบบประเมิน q1 ถึง q6 จากฟอร์ม
        $q1 = 0;
        if (isset($_POST['q1'])) {
            $q1 = (int)$_POST['q1'];
        }

        $q2 = 0;
        if (isset($_POST['q2'])) {
            $q2 = (int)$_POST['q2'];
        }

        $q3 = 0;
        if (isset($_POST['q3'])) {
            $q3 = (int)$_POST['q3'];
        }

        $q4 = 0;
        if (isset($_POST['q4'])) {
            $q4 = (int)$_POST['q4'];
        }

        $q5 = 0;
        if (isset($_POST['q5'])) {
            $q5 = (int)$_POST['q5'];
        }

        $q6 = 0;
        if (isset($_POST['q6'])) {
            $q6 = (int)$_POST['q6'];
        }

        // ตรวจสอบความถูกต้อง: คะแนนแต่ละข้อต้องอยู่ระหว่าง 1 ถึง 5 (เกณฑ์ประเมินมาตรฐาน 5 ระดับ Likert Scale)
        $isValidScore = true;
        if ($q1 < 1 || $q1 > 5) {
            $isValidScore = false;
        }
        if ($q2 < 1 || $q2 > 5) {
            $isValidScore = false;
        }
        if ($q3 < 1 || $q3 > 5) {
            $isValidScore = false;
        }
        if ($q4 < 1 || $q4 > 5) {
            $isValidScore = false;
        }
        if ($q5 < 1 || $q5 > 5) {
            $isValidScore = false;
        }
        if ($q6 < 1 || $q6 > 5) {
            $isValidScore = false;
        }

        // ถ้าคะแนนถูกต้องครบทั้ง 6 ข้อ ให้บันทึกลงฐานข้อมูล
        if ($isValidScore === true) {
            $insertEvalStatement = $conn->prepare(
                "INSERT INTO evaluations (activity_id, user_id, q1, q2, q3, q4, q5, q6) VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $insertEvalStatement->bind_param("iiiiiiii", $targetActivityId, $currentUserId, $q1, $q2, $q3, $q4, $q5, $q6);

            if ($insertEvalStatement->execute()) {
                // ดึงคะแนนแต้มจิตอาสาของกิจกรรมนี้
                $actPoints = 0;
                if (isset($activityRecord['points'])) {
                    $actPoints = (int)$activityRecord['points'];
                }

                // เพิ่มแต้มสะสมจิตอาสาให้สมาชิก
                if ($actPoints > 0) {
                    $addPtsStmt = $conn->prepare("UPDATE users SET points = points + ? WHERE user_id = ? AND user_type = 'student'");
                    $addPtsStmt->bind_param("ii", $actPoints, $currentUserId);
                    $addPtsStmt->execute();
                    $addPtsStmt->close();
                }

                $_SESSION['alert'] = [
                    'type'  => 'success',
                    'title' => 'บันทึกสำเร็จ!',
                    'text'  => 'ทำแบบประเมินเรียบร้อยแล้ว คุณได้รับ ' . $actPoints . ' แต้มจิตอาสา',
                ];
            }

            $insertEvalStatement->close();
        }

        header("Location: activity_details.php?id=" . $targetActivityId);
        exit;
    }
}


// ส่วนที่ 7 โหลด Header
include 'includes/header.php';
?>

<!-- โหลด CSS สำหรับระบบดาว Rating -->
<link rel="stylesheet" href="assets/css/evaluate.css">

<div class="container py-4 d-flex justify-center">
    <div class="card p-4" style="max-width: 600px; width: 100%;">

        <!-- ส่วนหัวของหน้า -->
        <div class="text-center mb-4">
            <div class="bg-blue-light text-primary rounded-circle d-flex justify-center align-center mx-auto mb-3"
                 style="width: 60px; height: 60px;">
                <i class="fa-solid fa-clipboard-list fa-2x"></i>
            </div>
            <h2 class="m-0 text-main">แบบประเมินความพึงพอใจ</h2>
            <p class="text-muted text-sm mt-1">โครงการ <?= htmlspecialchars($activityRecord['title']) ?></p>
        </div>

        <!-- แสดงผลประเมินเดิม (ถ้าเคยประเมินแล้ว) -->
        <?php if ($hasAlreadyEvaluated): ?>

            <div class="bg-main border-light rounded p-4 text-center">
                <h3 class="m-0 text-md text-main mb-3">คุณได้ทำแบบประเมินนี้แล้ว</h3>

                <!-- แสดงคะแนนที่ให้ (คะแนนเต็ม 5.00 คะแนน) -->
                <div class="mb-3 text-left">
                    <p class="text-sm mb-1">1. การประชาสัมพันธ์กิจกรรม <strong class="text-primary"><?= $existingEvalData['q1'] ?? '-' ?></strong> จาก 5 คะแนน</p>
                    <p class="text-sm mb-1">2. สถานที่และการจัดการ <strong class="text-primary"><?= $existingEvalData['q2'] ?? '-' ?></strong> จาก 5 คะแนน</p>
                    <p class="text-sm mb-1">3. ความเหมาะสมของเวลา <strong class="text-primary"><?= $existingEvalData['q3'] ?? '-' ?></strong> จาก 5 คะแนน</p>
                    <p class="text-sm mb-1">4. การดำเนินกิจกรรมและเนื้อหา <strong class="text-primary"><?= $existingEvalData['q4'] ?? '-' ?></strong> จาก 5 คะแนน</p>
                    <p class="text-sm mb-1">5. สวัสดิการอาหารและเครื่องดื่ม <strong class="text-primary"><?= $existingEvalData['q5'] ?? '-' ?></strong> จาก 5 คะแนน</p>
                    <p class="text-sm mb-1">6. ความพึงพอใจในภาพรวม <strong class="text-primary"><?= $existingEvalData['q6'] ?? '-' ?></strong> จาก 5 คะแนน</p>
                </div>

                <div class="text-xs text-muted mt-3">
                    ทำแบบประเมินเมื่อ <?= date('d/m/Y H:i', strtotime($existingEvalData['submitted_at'])) ?>
                </div>

                <div class="d-flex justify-center gap-2 mt-4 flex-wrap">
                    <a href="activity_details.php?id=<?= $targetActivityId ?>" class="btn btn-secondary">กลับหน้ารายละเอียดกิจกรรม</a>
                </div>
            </div>

        <?php else: ?>

            <!-- ฟอร์มประเมินความพึงพอใจ (เกณฑ์ 5 ระดับสากล) -->
            <form method="POST" action="evaluate.php?id=<?= $targetActivityId ?>">
                <?php csrfField(); ?>
                <input type="hidden" name="action" value="submit_eval">

                <!-- ส่วนคำถาม 6 ด้าน -->
                <div class="mb-4 text-left">
                    <div class="alert alert-light text-sm mb-3">
                        <strong>เกณฑ์การให้คะแนน (คะแนนเต็ม 5 คะแนน)</strong><br>
                        5 = ดีมาก, 4 = ดี, 3 = ปานกลาง, 2 = น้อย, 1 = น้อยที่สุด
                    </div>

                    <?php
                    // รายการข้อคำถามแบบประเมิน 6 ด้าน (แสดงผลปกติ ชิดกันปกติ ไม่มี :, /, |)
                    $questions = [
                        'q1' => '1. การประชาสัมพันธ์กิจกรรม',
                        'q2' => '2. สถานที่และการจัดการ',
                        'q3' => '3. ความเหมาะสมของเวลา',
                        'q4' => '4. การดำเนินกิจกรรมและเนื้อหา',
                        'q5' => '5. สวัสดิการอาหารและเครื่องดื่ม',
                        'q6' => '6. ความพึงพอใจในภาพรวม'
                    ];
                    
                    foreach ($questions as $qKey => $qLabel):
                    ?>
                        <div class="mb-3 p-3 bg-surface border-light rounded">
                            <p class="text-main font-weight-bold mb-2"><?= $qLabel ?> <span class="text-danger">*</span></p>
                            <!-- ปุ่มตัวเลือกคะแนน 1 ถึง 5 (กว้าง 20% รองรับ 5 ตัวเลือกพอดี) -->
                            <div class="d-flex justify-between" style="max-width: 400px; margin: 0 auto;">
                                <?php for ($i = 5; $i >= 1; $i--): ?>
                                    <label class="d-flex flex-column align-center cursor-pointer" style="width: 20%;">
                                        <input type="radio" name="<?= $qKey ?>" value="<?= $i ?>" required>
                                        <span class="mt-1 text-sm"><?= $i ?></span>
                                    </label>
                                <?php endfor; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- ปุ่ม Submit -->
                <button type="submit" class="btn btn-success btn-block py-2 text-md">
                    ส่งแบบประเมินความพึงพอใจ
                </button>
            </form>

        <?php endif; ?>

    </div>
</div>

<?php include 'includes/footer.php'; ?>