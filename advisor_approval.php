<?php

require_once 'config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();
}


if (!isLoggedIn() || !in_array(getCurrentUserRole(), ['admin', 'advisor'])) {
    header("Location: index.php");
    exit;
}

$advisorUserId = $_SESSION['user_id'];


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    $targetActivityId = (int)($_POST['activity_id'] ?? 0);
    $requestedAction  = $_POST['action'];

    if ($requestedAction === 'approved') {
        $approveStatement = $conn->prepare(
            "UPDATE activities SET approval_status = 'approved', advisor_id = ?, approved_at = NOW() WHERE activity_id = ?"
        );
        $approveStatement->bind_param("ii", $advisorUserId, $targetActivityId);

        if ($approveStatement->execute()) {
            $_SESSION['alert'] = [
                'type'  => 'success',
                'title' => 'สำเร็จ',
                'text'  => 'อนุมัติโครงการเรียบร้อยแล้ว',
            ];
        } else {
            $_SESSION['alert'] = [
                'type'  => 'error',
                'title' => 'ผิดพลาด',
                'text'  => 'ไม่สามารถทำรายการได้',
            ];
        }
        $approveStatement->close();

    } elseif ($requestedAction === 'rejected') {
        $checkPendingStatement = $conn->prepare("SELECT approval_status FROM activities WHERE activity_id = ?");
        $checkPendingStatement->bind_param("i", $targetActivityId);
        $checkPendingStatement->execute();
        $checkPendingResult = $checkPendingStatement->get_result()->fetch_assoc();
        $checkPendingStatement->close();

        if ($checkPendingResult && $checkPendingResult['approval_status'] === 'pending') {
            
            $rejectStatement = $conn->prepare(
                "UPDATE activities SET approval_status = 'rejected', advisor_id = ?, approved_at = NULL WHERE activity_id = ?"
            );
            $rejectStatement->bind_param("ii", $advisorUserId, $targetActivityId);
            
            if ($rejectStatement->execute()) {
                $_SESSION['alert'] = [
                    'type'  => 'success',
                    'title' => 'สำเร็จ',
                    'text'  => 'ตีกลับโครงการเรียบร้อยแล้ว',
                ];
            } else {
                $_SESSION['alert'] = [
                    'type'  => 'error',
                    'title' => 'ผิดพลาด',
                    'text'  => 'ไม่สามารถทำรายการได้',
                ];
            }
            $rejectStatement->close();
            
        } else {
            $_SESSION['alert'] = [
                'type'  => 'error',
                'title' => 'ผิดพลาด',
                'text'  => 'กิจกรรมนี้ถูกอนุมัติหรือตีกลับไปแล้ว',
            ];
        }
    }


    if ($requestedAction === 'approve_report') {
        $approveReportStatement = $conn->prepare("UPDATE activities SET report_status = 'approved' WHERE activity_id = ?");
        $approveReportStatement->bind_param("i", $targetActivityId);

        if ($approveReportStatement->execute()) {
            $_SESSION['alert'] = [
                'type'  => 'success',
                'title' => 'สำเร็จ',
                'text'  => 'รับทราบและอนุมัติรายงานสรุปโครงการเรียบร้อยแล้ว ปิดโครงการสมบูรณ์',
            ];
        } else {
            $_SESSION['alert'] = [
                'type'  => 'error',
                'title' => 'ผิดพลาด',
                'text'  => 'ไม่สามารถทำรายการได้',
            ];
        }
        $approveReportStatement->close();
    }

    if ($requestedAction === 'reject_report') {
        $rejectReportStatement = $conn->prepare("UPDATE activities SET report_status = 'rejected' WHERE activity_id = ?");
        $rejectReportStatement->bind_param("i", $targetActivityId);

        if ($rejectReportStatement->execute()) {
            $_SESSION['alert'] = [
                'type'  => 'success',
                'title' => 'สำเร็จ',
                'text'  => 'ตีกลับรายงานสรุปโครงการให้คณะกรรมการแก้ไข',
            ];
        } else {
            $_SESSION['alert'] = [
                'type'  => 'error',
                'title' => 'ผิดพลาด',
                'text'  => 'ไม่สามารถทำรายการได้',
            ];
        }
        $rejectReportStatement->close();
    }

    header("Location: advisor_approval.php");
    exit;
}


$activitiesQueryString = "
    SELECT a.*,
           (SELECT SUM(amount) FROM budget_transactions WHERE activity_id = a.activity_id AND transaction_type='expense') as total_expense,
           (SELECT SUM(amount) FROM budget_transactions WHERE activity_id = a.activity_id AND transaction_type='income') as total_income,
           (SELECT COUNT(*) FROM vehicles WHERE activity_id = a.activity_id) as vehicle_count,
           (SELECT COUNT(*) FROM user_activities WHERE activity_id = a.activity_id) as registered_count
    FROM activities a
    ORDER BY FIELD(a.approval_status, 'pending', 'approved', 'rejected'), a.start_date DESC
";

$activitiesResult = $conn->query($activitiesQueryString);

$statisticPendingCount  = 0;
$statisticApprovedCount = 0;
$statisticRejectedCount = 0;
$allProjectList         = [];

if ($activitiesResult) {
    while ($activityRow = $activitiesResult->fetch_assoc()) {
        $allProjectList[] = $activityRow;

        if ($activityRow['approval_status'] == 'pending') {
            $statisticPendingCount++;
        } elseif ($activityRow['approval_status'] == 'approved') {
            $statisticApprovedCount++;
        } elseif ($activityRow['approval_status'] == 'rejected') {
            $statisticRejectedCount++;
        }
    }
}


include 'includes/header.php';
?>


<?php if (isset($_SESSION['alert'])): ?>
    <div id="flash-message"
         data-type="<?= $_SESSION['alert']['type'] ?>"
         data-title="<?= $_SESSION['alert']['title'] ?>"
         data-text="<?= $_SESSION['alert']['text'] ?>"
         class="d-none">
    </div>
    <?php unset($_SESSION['alert']); ?>
<?php endif; ?>

<style>
.card, .btn, .btn-primary, a.btn, a.btn-primary {
    transition: none !important;
    transform: none !important;
    box-shadow: none !important;
    color: #ffffff !important;
    text-decoration: none !important;
}
.btn:hover, .btn-primary:hover, a.btn:hover, a.btn-primary:hover {
    transition: none !important;
    transform: none !important;
    box-shadow: none !important;
    color: #ffffff !important;
    text-decoration: none !important;
}
</style>

<div class="container py-4">

    <h2 class="section-title mb-4">ระบบพิจารณาอนุมัติโครงการ</h2>


    <div class="grid-3 mb-4">
        
        <div class="card" style="background: #ffffff; border: 1px solid var(--border-color); box-shadow: none;">
            <div class="text-sm font-weight-bold mb-1" style="color: #000;">โครงการรออนุมัติ</div>
            <div class="text-xl font-weight-bold" style="color: #000;"><?= $statisticPendingCount ?> โครงการ</div>
        </div>

        <div class="card" style="background: #ffffff; border: 1px solid var(--border-color); box-shadow: none;">
            <div class="text-sm font-weight-bold mb-1" style="color: #000;">อนุมัติแล้ว</div>
            <div class="text-xl font-weight-bold" style="color: #000;"><?= $statisticApprovedCount ?> โครงการ</div>
        </div>

        <div class="card" style="background: #ffffff; border: 1px solid var(--border-color); box-shadow: none;">
            <div class="text-sm font-weight-bold mb-1" style="color: #000;">ไม่อนุมัติ / ยกเลิก</div>
            <div class="text-xl font-weight-bold" style="color: #000;"><?= $statisticRejectedCount ?> โครงการ</div>
        </div>

    </div>


    <?php foreach ($allProjectList as $projectItem): ?>
        <div class="card mb-3" style="background: #ffffff; border: 1px solid var(--border-color); box-shadow: none;">
            <div class="d-flex justify-between align-center flex-wrap gap-2">

                
                <div class="flex-grow-1">
                    <h3 class="m-0 text-md font-weight-bold" style="color: #000;">
                        <?= htmlspecialchars($projectItem['title']) ?>
                    </h3>
                    
                    <div class="text-sm text-muted mt-1">
                        วันที่ <?= date('d/m/Y', strtotime($projectItem['start_date'])) ?> |
                        สถานที่ <?= htmlspecialchars($projectItem['location_name']) ?>
                    </div>

                    
                    <div class="d-flex gap-2 mt-2 align-center text-xs" style="color: #000;">
                        <?php if ($projectItem['approval_status'] == 'pending'): ?>
                            <span>สถานะ: รออนุมัติกิจกรรม</span>
                        <?php elseif ($projectItem['approval_status'] == 'approved'): ?>
                            <span>สถานะ: อนุมัติแล้ว</span>
                        <?php else: ?>
                            <span>สถานะ: ไม่อนุมัติ / ยกเลิก</span>
                        <?php endif; ?>

                        
                        <?php if ($projectItem['report_status'] === 'submitted'): ?>
                            <span>(รอตรวจรายงานสรุป)</span>
                        <?php endif; ?>
                    </div>
                </div>

                
                <div>
                    <?php if ($projectItem['approval_status'] === 'pending'): ?>
                        <a href="activity_details.php?id=<?= $projectItem['activity_id'] ?>"
                           class="btn"
                           style="padding: 8px 16px; background-color: #f59e0b !important; border-color: #f59e0b !important; color: #ffffff !important; box-shadow: none !important; transition: none !important; transform: none !important;">
                            รอการอนุมัติ
                        </a>
                    <?php else: ?>
                        <a href="activity_details.php?id=<?= $projectItem['activity_id'] ?>"
                           class="btn btn-primary"
                           style="padding: 8px 16px; color: #ffffff !important; box-shadow: none !important; transition: none !important; transform: none !important;">
                            ดูรายละเอียดกิจกรรม
                        </a>
                    <?php endif; ?>
                </div>

            </div>
        </div>
    <?php endforeach; ?>

    
    <?php if (empty($allProjectList)): ?>
        <div class="card p-4 text-center text-muted" style="border: 1px dashed var(--border-color);">
            <p class="m-0">ไม่มีโครงการในระบบ</p>
        </div>
    <?php endif; ?>

</div>





<script src="assets/js/advisor_approval.js"></script>

<?php include 'includes/footer.php'; ?>
