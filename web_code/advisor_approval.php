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
.advisor-action-btn {
    padding: 6px 14px;
    font-size: 0.85rem;
    font-weight: 600;
    border-radius: 6px;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    cursor: pointer;
    text-decoration: none;
    border: none;
}
.advisor-action-btn.btn-approve {
    background-color: #16a34a;
    color: #ffffff;
}
.advisor-action-btn.btn-approve:hover {
    background-color: #15803d;
}
.advisor-action-btn.btn-reject {
    background-color: #dc2626;
    color: #ffffff;
}
.advisor-action-btn.btn-reject:hover {
    background-color: #b91c1c;
}
.advisor-action-btn.btn-view {
    background-color: #f1f5f9;
    color: #334155;
    border: 1px solid #cbd5e1;
}
.advisor-action-btn.btn-view:hover {
    background-color: #e2e8f0;
}
</style>

<div class="container py-4">

    <h2 class="section-title mb-4 text-left">ระบบพิจารณาอนุมัติโครงการ</h2>

    <div class="grid-3 mb-4">
        <div class="card p-3 text-left" style="background: #ffffff; border: 1px solid var(--border-color); box-shadow: none;">
            <div class="text-sm font-weight-bold mb-1" style="color: #64748b;">โครงการรออนุมัติ</div>
            <div class="text-xl font-weight-bold" style="color: #d97706;"><?= $statisticPendingCount ?> โครงการ</div>
        </div>

        <div class="card p-3 text-left" style="background: #ffffff; border: 1px solid var(--border-color); box-shadow: none;">
            <div class="text-sm font-weight-bold mb-1" style="color: #64748b;">อนุมัติแล้ว</div>
            <div class="text-xl font-weight-bold" style="color: #16a34a;"><?= $statisticApprovedCount ?> โครงการ</div>
        </div>

        <div class="card p-3 text-left" style="background: #ffffff; border: 1px solid var(--border-color); box-shadow: none;">
            <div class="text-sm font-weight-bold mb-1" style="color: #64748b;">ไม่อนุมัติ / ยกเลิก</div>
            <div class="text-xl font-weight-bold" style="color: #dc2626;"><?= $statisticRejectedCount ?> โครงการ</div>
        </div>
    </div>

    <?php foreach ($allProjectList as $projectItem): ?>
        <div class="card mb-3 p-4 text-left" style="background: #ffffff; border: 1px solid var(--border-color); box-shadow: none;">
            <div class="d-flex justify-between align-center flex-wrap gap-3">

                <div class="flex-grow-1 text-left">
                    <h3 class="m-0 text-md font-weight-bold text-left" style="color: #0f172a;">
                        <?= htmlspecialchars($projectItem['title']) ?>
                    </h3>
                    
                    <div class="text-sm text-muted mt-1 text-left">
                        วันที่ <?= date('d/m/Y', strtotime($projectItem['start_date'])) ?> |
                        สถานที่: <?= htmlspecialchars($projectItem['location_name']) ?>
                    </div>

                    <div class="d-flex gap-2 mt-2 align-center text-xs text-left" style="color: #334155;">
                        <?php if ($projectItem['approval_status'] == 'pending'): ?>
                            <span class="badge badge-warning text-xs">รออนุมัติกิจกรรม</span>
                        <?php elseif ($projectItem['approval_status'] == 'approved'): ?>
                            <span class="badge badge-success text-xs">อนุมัติแล้ว</span>
                        <?php else: ?>
                            <span class="badge badge-danger text-xs">ไม่อนุมัติ / ยกเลิก</span>
                        <?php endif; ?>

                        <?php if ($projectItem['report_status'] === 'submitted'): ?>
                            <span class="badge badge-warning text-xs">(รอตรวจรายงานสรุป)</span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="d-flex gap-2 flex-wrap align-center">
                    <?php if ($projectItem['approval_status'] === 'pending'): ?>
                        <button type="button" class="advisor-action-btn btn-approve btn-action-approve" data-id="<?= $projectItem['activity_id'] ?>" data-action="approved">
                            <i class="fa-solid fa-check"></i> อนุมัติ
                        </button>
                        <button type="button" class="advisor-action-btn btn-reject btn-action-approve" data-id="<?= $projectItem['activity_id'] ?>" data-action="rejected">
                            <i class="fa-solid fa-xmark"></i> ไม่อนุมัติ
                        </button>
                    <?php endif; ?>
                    <a href="activity_details.php?id=<?= $projectItem['activity_id'] ?>" class="advisor-action-btn btn-view">
                        <i class="fa-solid fa-eye"></i> ดูรายละเอียด
                    </a>
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
