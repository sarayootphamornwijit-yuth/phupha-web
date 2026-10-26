<?php
// certificates.php
// ระบบเกียรติบัตรชมรมเพื่อนภูผา
// เงื่อนไข: สมาชิกต้องมีคะแนนจิตอาสาสะสมถึงเกณฑ์ที่กำหนดจึงจะสามารถดาวน์โหลดเกียรติบัตรได้
// ผู้ดูแลระบบสามารถกำหนดและแก้ไขเกณฑ์คะแนนสะสมได้

require_once 'config/database.php';

// ตรวจสอบว่าเข้าสู่ระบบหรือยัง
if (!isLoggedIn()) {
    header("Location: login.php");
    exit;
}

$currentUserId = $_SESSION['user_id'];
$currentUserRole = getCurrentUserRole();

// ดึงข้อมูลสมาชิกและคะแนนจิตอาสาสะสมปัจจุบัน
$userStmt = $conn->prepare("SELECT name, points, role FROM users WHERE user_id = ?");
$userStmt->bind_param("i", $currentUserId);
$userStmt->execute();
$userData = $userStmt->get_result()->fetch_assoc();
$userStmt->close();

$myPoints = (int)($userData['points'] ?? 0);
$myName   = $userData['name'] ?? '';

// จัดการกรณีผู้ดูแลระบบส่งฟอร์มแก้ไขเกณฑ์คะแนนจิตอาสา
$successMessage = '';
if ($currentUserRole === 'admin' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_threshold') {
    verifyCsrfToken();
    $newThreshold = (int)($_POST['point_threshold'] ?? 50);
    setCertificatePointThreshold($newThreshold);
    $successMessage = 'บันทึกการแก้ไขเกณฑ์คะแนนจิตอาสาเรียบร้อยแล้ว';
}

// ดึงเกณฑ์คะแนนจิตอาสาปัจจุบันจากตารางตั้งค่า
$certThreshold = getCertificatePointThreshold();

// ตรวจสอบว่าคะแนนสะสมถึงเกณฑ์หรือยัง
$isQualified = ($myPoints >= $certThreshold);

// ดึงประวัติการดาวน์โหลดเกียรติบัตรของผู้ใช้
$certificateHistory = false;
try {
    $colPk = 'certificate_id';
    $colCheck = $conn->query("SHOW COLUMNS FROM `certificates` LIKE 'certificate_id'");
    if (!$colCheck || $colCheck->num_rows === 0) {
        $colPk = 'id';
    }

    $hasActCol = false;
    $actColCheck = $conn->query("SHOW COLUMNS FROM `certificates` LIKE 'activity_id'");
    if ($actColCheck && $actColCheck->num_rows > 0) {
        $hasActCol = true;
    }

    $selectCols = $hasActCol 
        ? "{$colPk} AS certificate_id, activity_id, title, date_issued" 
        : "{$colPk} AS certificate_id, NULL AS activity_id, title, date_issued";

    $histStmt = $conn->prepare("SELECT {$selectCols} FROM certificates WHERE user_id = ? ORDER BY date_issued DESC");
    if ($histStmt) {
        $histStmt->bind_param("i", $currentUserId);
        $histStmt->execute();
        $certificateHistory = $histStmt->get_result();
        $histStmt->close();
    }
} catch (\Throwable $e) {
    error_log("Error fetching certificate history: " . $e->getMessage());
    $certificateHistory = false;
}

// ดึงกิจกรรมที่สมาชิกคนนี้เข้าร่วมและแต้มที่ได้รับ รวมถึงสถานะการประเมิน
$sqlActivities = "
    SELECT 
        a.activity_id,
        a.title,
        a.start_date,
        a.end_date,
        a.location_name,
        a.points,
        ua.status AS activity_status,
        (SELECT COUNT(*) FROM evaluations e WHERE e.activity_id = a.activity_id AND e.user_id = ua.user_id) AS is_evaluated
    FROM user_activities ua
    JOIN activities a ON ua.activity_id = a.activity_id
    WHERE ua.user_id = ?
    ORDER BY a.start_date DESC
";
$stmtAct = $conn->prepare($sqlActivities);
$stmtAct->bind_param("i", $currentUserId);
$stmtAct->execute();
$myActivities = $stmtAct->get_result();
$stmtAct->close();

include 'includes/header.php';
?>

<div class="container py-4" style="max-width: 850px;">

    <!-- ส่วนหัวหน้าจอ -->
    <div class="text-center mb-4">
        <h2 class="section-title m-0">ระบบเกียรติบัตรจิตอาสา</h2>
        <p class="text-muted text-sm mt-2">
            สะสมคะแนนจิตอาสาจากการเข้าร่วมกิจกรรมและทำแบบประเมิน เพื่อรับเกียรติบัตรเมื่อมีคะแนนสะสมครบตามเกณฑ์
        </p>
    </div>

    <!-- ส่วนสำหรับผู้ดูแลระบบ: ปรับเกณฑ์คะแนนจิตอาสา -->
    <?php if ($currentUserRole === 'admin'): ?>
        <?php if ($successMessage !== ''): ?>
            <div class="alert alert-success text-center mb-3">
                <?= htmlspecialchars($successMessage) ?>
            </div>
        <?php endif; ?>

        <div class="card p-3 mb-4" style="background: #f8fafc; border: 1px solid #cbd5e1;">
            <h4 class="text-md m-0 mb-1" style="font-weight: 600;">ตั้งค่าเกณฑ์คะแนนสำหรับผู้ดูแลระบบ</h4>
            <p class="text-sm text-muted mb-2">กำหนดคะแนนจิตอาสาสะสมขั้นต่ำที่สมาชิกต้องมีเพื่อรับเกียรติบัตรเชิดชูเกียรติรวม</p>
            <form action="certificates.php" method="POST" class="d-flex align-center gap-2 flex-wrap">
                <?php csrfField(); ?>
                <input type="hidden" name="action" value="update_threshold">
                <label for="point_threshold" class="text-sm">เกณฑ์คะแนนสะสมขั้นต่ำ:</label>
                <input type="number" name="point_threshold" id="point_threshold" class="form-control" value="<?= $certThreshold ?>" min="1" style="max-width: 120px;" required>
                <span class="text-sm">คะแนน</span>
                <button type="submit" class="btn btn-primary">บันทึกเกณฑ์คะแนน</button>
            </form>
        </div>
    <?php endif; ?>

    <!-- กล่องสรุปคะแนนและสิทธิ์รับเกียรติบัตรสะสมแต้ม -->
    <div class="card p-4 mb-4">
        <h3 class="text-md m-0 mb-3 pb-2 border-bottom" style="font-weight: 600;">
            สถานะคะแนนจิตอาสาสะสมของคุณ
        </h3>

        <div class="grid-2 gap-3 mb-4 text-center">
            <div class="p-3 bg-main rounded border">
                <div class="text-sm text-muted">คะแนนจิตอาสาสะสมของคุณ</div>
                <div class="text-xl font-weight-bold mt-1" style="color: var(--color-primary); font-size: 28px;">
                    <?= number_format($myPoints) ?> คะแนน
                </div>
            </div>
            <div class="p-3 bg-main rounded border">
                <div class="text-sm text-muted">เกณฑ์คะแนนสะสมที่กำหนด</div>
                <div class="text-xl font-weight-bold mt-1" style="color: #334155; font-size: 28px;">
                    <?= number_format($certThreshold) ?> คะแนน
                </div>
            </div>
        </div>

        <!-- การตรวจสอบสิทธิ์การรับเกียรติบัตรคะแนนสะสม -->
        <div class="p-3 rounded text-center" style="background: #f1f5f9;">
            <?php 
            $hasIssuedCert = ($certificateHistory && $certificateHistory->num_rows > 0);
            ?>
            <?php if ($hasIssuedCert): ?>
                <!-- กรณีได้รับเกียรติบัตรไปแล้ว (จำกัดสิทธิ์ 1 คนต่อ 1 ใบ) -->
                <div class="mb-3">
                    <span class="badge" style="background: #e0f2fe; color: #0369a1; padding: 6px 14px; font-size: 14px; font-weight: 600;">
                        <i class="fa-solid fa-circle-check mr-1"></i> คุณได้รับเกียรติบัตรเชิดชูเกียรติแล้ว (รับสิทธิ์แล้ว 1 ครั้ง)
                    </span>
                </div>
                <p class="text-sm text-muted mb-3">
                    คุณได้สะสมคะแนนครบตามเกณฑ์และได้รับเกียรติบัตรเชิดชูเกียรติเรียบร้อยแล้ว การทำกิจกรรมอื่นๆ เพิ่มเติมหลังจากนี้จะได้รับเฉพาะแต้มจิตอาสาสะสมในโปรไฟล์
                </p>
                <div>
                    <a href="generate_certificate.php" target="_blank" class="btn btn-outline-primary" style="padding: 8px 24px; font-size: 15px; text-decoration: none;">
                        <i class="fa-solid fa-print mr-1"></i> ดู/พิมพ์เกียรติบัตรฉบับเดิมของคุณ (PDF)
                    </a>
                </div>
            <?php elseif ($isQualified): ?>
                <!-- กรณีคะแนนสะสมถึงเกณฑ์ที่กำหนดแล้ว และยังไม่เคยรับ -->
                <div class="mb-3">
                    <span class="badge" style="background: #dcfce7; color: #166534; padding: 6px 14px; font-size: 14px; font-weight: 600;">
                        คะแนนสะสมของคุณผ่านเกณฑ์เชิดชูเกียรติแล้ว
                    </span>
                </div>
                <p class="text-sm text-muted mb-3">
                    คุณมีสิทธิ์รับเกียรติบัตรเชิดชูเกียรติผู้มีจิตอาสาของชมรมเพื่อนภูผา สามารถคลิกปุ่มเพื่อดาวน์โหลดเอกสาร PDF ได้ทันที
                </p>
                <div>
                    <a href="generate_certificate.php" target="_blank" class="btn btn-primary" style="padding: 8px 24px; font-size: 15px;">
                        ดาวน์โหลดเกียรติบัตรสะสมแต้ม (PDF)
                    </a>
                </div>
            <?php else: ?>
                <!-- กรณีคะแนนสะสมยังไม่ถึงเกณฑ์ -->
                <div class="text-sm" style="color: #b91c1c; font-weight: 500;">
                    ต้องมีคะแนนจิตอาสาสะสมครบตามเกณฑ์ที่กำหนด จึงจะสามารถดาวน์โหลดเกียรติบัตรได้
                </div>
                <div class="text-xs text-muted mt-2">
                    คุณยังขาดอีก <?= number_format($certThreshold - $myPoints) ?> คะแนน จึงจะผ่านเกณฑ์การรับเกียรติบัตร (เข้าร่วมกิจกรรมและทำแบบประเมินเพื่อสะสมแต้มเพิ่มได้จากรายการด้านล่าง)
                </div>
            <?php endif; ?>
        </div>

    </div>

    <!-- ประวัติการเข้าร่วมกิจกรรมและคะแนนจิตอาสาที่ได้รับ -->
    <div class="card p-4 mb-4">
        <h3 class="text-md m-0 mb-3 pb-2 border-bottom" style="font-weight: 600;">
            ประวัติกิจกรรมและคะแนนจิตอาสาที่ได้รับ
        </h3>

        <?php if ($myActivities && $myActivities->num_rows > 0): ?>
            <div class="table-responsive">
                <table class="table w-100 text-sm">
                    <thead>
                        <tr style="background: #f8fafc; color: #475569;">
                            <th class="p-2 text-left">ชื่อกิจกรรม</th>
                            <th class="p-2 text-center" style="width: 110px;">วันที่จัด</th>
                            <th class="p-2 text-center" style="width: 100px;">การเช็คอิน</th>
                            <th class="p-2 text-center" style="width: 200px;">การทำแบบประเมิน</th>
                            <th class="p-2 text-right" style="width: 90px;">คะแนนที่ได้</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($act = $myActivities->fetch_assoc()): 
                            $isCheckedIn = in_array($act['activity_status'], ['checked_in', 'checked_out'], true);
                            $isEvaluated = ((int)$act['is_evaluated'] > 0);
                            $actEndDate  = !empty($act['end_date']) ? $act['end_date'] : $act['start_date'];
                            $isEnded     = (!empty($actEndDate) && strtotime($actEndDate) <= time());
                        ?>
                            <tr class="border-bottom">
                                <td class="p-2">
                                    <div class="font-weight-medium" style="color: #0f172a;">
                                        <?= htmlspecialchars($act['title']) ?>
                                    </div>
                                    <div class="text-muted text-xs mt-1">
                                        สถานที่: <?= htmlspecialchars($act['location_name'] ?: 'ไม่ระบุ') ?>
                                    </div>
                                </td>
                                <td class="p-2 text-center text-muted">
                                    <?= date('d/m/Y', strtotime($act['start_date'])) ?>
                                </td>
                                <td class="p-2 text-center">
                                    <?php if ($isCheckedIn): ?>
                                        <span class="badge" style="background: #dcfce7; color: #166534; padding: 4px 8px;">
                                            เช็คอินแล้ว
                                        </span>
                                    <?php else: ?>
                                        <span class="badge" style="background: #f1f5f9; color: #64748b; padding: 4px 8px;">
                                            ยังไม่เช็คอิน
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="p-2 text-center">
                                    <?php if ($isCheckedIn && $isEvaluated): ?>
                                        <span class="badge" style="background: #dcfce7; color: #166534; padding: 4px 10px; font-size: 12px;">
                                            ทำแบบประเมินแล้ว
                                        </span>
                                    <?php elseif ($isCheckedIn && !$isEvaluated): ?>
                                        <?php if ($isEnded): ?>
                                            <a href="evaluate.php?id=<?= $act['activity_id'] ?>" 
                                               class="btn btn-sm btn-warning" 
                                               style="padding: 4px 10px; font-size: 12px; text-decoration: none;">
                                                ทำแบบประเมินเพื่อรับคะแนน
                                            </a>
                                        <?php else: ?>
                                            <span class="badge" style="background: #fef3c7; color: #92400e; padding: 4px 8px; font-size: 11px;">
                                                รอสิ้นสุดกิจกรรมเพื่อประเมิน
                                            </span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-xs text-muted">
                                            ต้องเช็คอินหน้างานก่อน
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="p-2 text-right font-weight-bold" style="color: var(--color-primary);">
                                    <?= (int)$act['points'] ?> แต้ม
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center py-4 text-muted text-sm">
                ยังไม่มีประวัติการเข้าร่วมกิจกรรม
            </div>
        <?php endif; ?>
    </div>

    <!-- ประวัติการออกเกียรติบัตรในระบบ -->
    <?php if ($certificateHistory && $certificateHistory->num_rows > 0): ?>
        <div class="card p-4">
            <h3 class="text-md m-0 mb-3 pb-2 border-bottom" style="font-weight: 600;">
                เกียรติบัตร
            </h3>
            <div class="table-responsive">
                <table class="table w-100 text-sm">
                    <thead>
                        <tr style="background: #f8fafc; color: #475569;">
                            <th class="p-2 text-left">รายการเกียรติบัตร</th>
                            <th class="p-2 text-center" style="width: 170px;">วันที่ออกเอกสาร</th>
                            <th class="p-2 text-center" style="width: 100px;">การจัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($cert = $certificateHistory->fetch_assoc()): 
                            $viewCertUrl = "generate_certificate.php";
                        ?>
                            <tr class="border-bottom">
                                <td class="p-2 font-weight-medium">
                                    <?= htmlspecialchars($cert['title'] ?: 'เกียรติบัตรเชิดชูเกียรติผู้มีจิตอาสา') ?>
                                </td>
                                <td class="p-2 text-center text-muted">
                                    <?= date('d/m/Y', strtotime($cert['date_issued'])) ?>
                                </td>
                                <td class="p-2 text-center">
                                    <a href="<?= $viewCertUrl ?>" target="_blank" class="btn btn-sm btn-outline-primary" title="พิมพ์/ดู" style="padding: 4px 12px; font-size: 13px; text-decoration: none;">
                                        <i class="fa-solid fa-print"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

</div>

<?php include 'includes/footer.php'; ?>