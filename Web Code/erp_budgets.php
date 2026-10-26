<?php
// erp_budgets.php
// ไฟล์นี้ทำหน้าที่ในการจัดการบัญชี งบประมาณ รายรับ-รายจ่าย ของแต่ละกิจกรรม
// รองรับการแนบสลิปหรือใบเสร็จรับเงิน และสรุปยอดเงินคงเหลือให้อัตโนมัติ

require_once 'config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();
}


// ส่วนที่ 1 ตรวจสอบสิทธิ์
// ผู้ที่มีสิทธิ์เข้าถึงต้องเป็น admin, committee, หรือ advisor
requireCommitteePermission('budgets');

$currentUserId = $_SESSION['user_id'];

// ตรวจสอบชื่อคอลัมน์ Primary Key ในตาราง budget_transactions (รองรับทั้ง transaction_id และ id)
$budgetPkCol = 'transaction_id';
try {
    $colsResult = $conn->query("SHOW COLUMNS FROM budget_transactions");
    if ($colsResult) {
        $existingCols = [];
        while ($colRow = $colsResult->fetch_assoc()) {
            $existingCols[] = $colRow['Field'];
        }
        if (in_array('transaction_id', $existingCols)) {
            $budgetPkCol = 'transaction_id';
        } elseif (in_array('id', $existingCols)) {
            $budgetPkCol = 'id';
        } elseif (in_array('budget_id', $existingCols)) {
            $budgetPkCol = 'budget_id';
        }
    }
} catch (\Throwable $e) {}


// ส่วนที่ 2 จัดการการเพิ่มข้อมูลรายรับ-รายจ่าย (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_budget') {
    
    // รับค่าจากฟอร์ม
    $activityId      = (int)($_POST['activity_id'] ?? 0);
    $transactionType = trim($_POST['type'] ?? '');
    $categoryName    = trim($_POST['category'] ?? '');
    $transactionAmount = (float)($_POST['amount'] ?? 0);
    $descriptionText = trim($_POST['description'] ?? '');

    // 2.1 ตรวจสอบยอดเงินคงเหลือก่อนบันทึก "รายจ่าย"
    if ($transactionType === 'expense') {
        
        $checkBalanceStatement = $conn->prepare(
            "SELECT 
                SUM(CASE WHEN transaction_type = 'income' THEN amount ELSE 0 END) - 
                SUM(CASE WHEN transaction_type = 'expense' THEN amount ELSE 0 END) as current_balance 
             FROM budget_transactions 
             WHERE activity_id = ?"
        );
        $checkBalanceStatement->bind_param("i", $activityId);
        $checkBalanceStatement->execute();
        
        $balanceResult = $checkBalanceStatement->get_result()->fetch_assoc();
        $currentBalance = (float)($balanceResult['current_balance'] ?? 0);
        
        $checkBalanceStatement->close();

        // ถ้ายอดเงินคงเหลือน้อยกว่ารายจ่ายที่จะเบิก ไม่อนุญาตให้บันทึก
        if ($currentBalance - $transactionAmount < 0) {
            $_SESSION['alert'] = [
                'type'  => 'error',
                'title' => 'ยอดเงินไม่เพียงพอ',
                'text'  => 'ไม่สามารถบันทึกรายจ่ายได้ เนื่องจากยอดเงินคงเหลือไม่เพียงพอ'
            ];
            header("Location: erp_budgets.php?activity_id=" . $activityId);
            exit;
        }
    }

    // 2.2 จัดการการอัปโหลดไฟล์สลิป/ใบเสร็จ (Secure File Upload)
    $receiptImagePathInDB = null;
    
    if (isset($_FILES['receipt_image']) && $_FILES['receipt_image']['error'] === UPLOAD_ERR_OK) {
        $tempFilePath     = $_FILES['receipt_image']['tmp_name'];
        $originalFileName = $_FILES['receipt_image']['name'];

        // ตรวจสอบ MIME Type และนามสกุลไฟล์อย่างปลอดภัย
        $fileInfoHandler = finfo_open(FILEINFO_MIME_TYPE);
        $mimeTypeResult  = finfo_file($fileInfoHandler, $tempFilePath);
        finfo_close($fileInfoHandler);

        $allowedMimeTypes = ['image/jpeg', 'image/png', 'application/pdf'];
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'pdf'];
        $fileExtension = strtolower(pathinfo($originalFileName, PATHINFO_EXTENSION));
        
        if (in_array($mimeTypeResult, $allowedMimeTypes) && in_array($fileExtension, $allowedExtensions)) {
            if ($fileExtension === 'jpeg') $fileExtension = 'jpg';
            $newGeneratedFilename = uniqid('receipt_') . '_' . time() . '.' . $fileExtension;
            $uploadDirectoryPath  = 'uploads/receipts/';

            // สร้างโฟลเดอร์ถ้ายังไม่มี
            if (!is_dir($uploadDirectoryPath)) {
                mkdir($uploadDirectoryPath, 0777, true);
            }

            $finalTargetFile = $uploadDirectoryPath . $newGeneratedFilename;
            
            // ย้ายไฟล์ไปยังตำแหน่งที่กำหนด
            if (move_uploaded_file($tempFilePath, $finalTargetFile)) {
                $receiptImagePathInDB = $finalTargetFile;
            }
        } else {
            $_SESSION['alert'] = [
                'type'  => 'error',
                'title' => 'ไฟล์ไม่ถูกต้อง',
                'text'  => 'รองรับเฉพาะไฟล์รูปภาพ (JPG, PNG) หรือ PDF เท่านั้น'
            ];
            header("Location: erp_budgets.php?activity_id=" . $activityId);
            exit;
        }
    }

    // 2.3 บันทึกข้อมูลลงฐานข้อมูลด้วย Prepared Statement
    $insertBudgetStatement = $conn->prepare(
        "INSERT INTO budget_transactions (
            activity_id, transaction_type, category, amount, description, receipt_file, created_by
        ) VALUES (?, ?, ?, ?, ?, ?, ?)"
    );
    
    $insertBudgetStatement->bind_param(
        "issdssi", 
        $activityId, $transactionType, $categoryName, $transactionAmount, $descriptionText, $receiptImagePathInDB, $currentUserId
    );

    if ($insertBudgetStatement->execute()) {
        $_SESSION['alert'] = [
            'type'  => 'success',
            'title' => 'บันทึกสำเร็จ!',
            'text'  => 'เพิ่มรายการบัญชีเรียบร้อยแล้ว'
        ];
    } else {
        $_SESSION['alert'] = [
            'type'  => 'error',
            'title' => 'เกิดข้อผิดพลาด',
            'text'  => 'ไม่สามารถบันทึกข้อมูลได้'
        ];
    }

    $insertBudgetStatement->close();
    header("Location: erp_budgets.php?activity_id=" . $activityId);
    exit;
}


// ส่วนที่ 3 จัดการการลบรายการ (POST เพื่อความปลอดภัยและป้องกัน CSRF)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_budget' && isset($_POST['delete_id'])) {
    
    $deleteIdParam   = (int)$_POST['delete_id'];
    $activityIdParam = (int)($_POST['activity_id'] ?? 0);

    // ดึงข้อมูลพาธไฟล์เพื่อทำการลบออกจากเซิร์ฟเวอร์
    $selectFileStatement = $conn->prepare("SELECT receipt_file FROM budget_transactions WHERE `$budgetPkCol` = ?");
    $selectFileStatement->bind_param("i", $deleteIdParam);
    $selectFileStatement->execute();
    $fileResult = $selectFileStatement->get_result()->fetch_assoc();

    // ลบไฟล์ใบเสร็จหากมีไฟล์อยู่จริง
    if ($fileResult && !empty($fileResult['receipt_file']) && file_exists($fileResult['receipt_file'])) {
        unlink($fileResult['receipt_file']);
    }
    $selectFileStatement->close();

    // ลบข้อมูลรายการออกจากฐานข้อมูล
    $deleteRecordStatement = $conn->prepare("DELETE FROM budget_transactions WHERE `$budgetPkCol` = ?");
    $deleteRecordStatement->bind_param("i", $deleteIdParam);
    
    if ($deleteRecordStatement->execute()) {
        $_SESSION['alert'] = [
            'type'  => 'success',
            'title' => 'ลบสำเร็จ!',
            'text'  => 'ลบรายการบัญชีและไฟล์แนบเรียบร้อยแล้ว'
        ];
    }
    $deleteRecordStatement->close();

    header("Location: erp_budgets.php?activity_id=" . $activityIdParam);
    exit;
}


// ส่วนที่ 4 ดึงข้อมูลเพื่อใช้ในการแสดงผล (เตรียม Data)
// ดึงรายชื่อกิจกรรมทั้งหมดที่มีสำหรับให้เลือกใน Dropdown
$activitiesListResult = $conn->query("SELECT activity_id, title FROM activities ORDER BY start_date DESC");

// ตรวจสอบว่ามีการเลือกกิจกรรมเพื่อดูบัญชีหรือไม่
$selectedActivityId = (int)($_GET['activity_id'] ?? 0);

$budgetTransactions = [];
$totalIncomeValue   = 0;
$totalExpenseValue  = 0;

// ถ้ามีการเลือกกิจกรรม ให้ดึงข้อมูลประวัติการทำรายการมาแสดง
if ($selectedActivityId > 0) {
    
    $budgetQueryStatement = $conn->prepare(
        "SELECT b.*, u.name as recorder_name 
         FROM budget_transactions b 
         LEFT JOIN users u ON b.created_by = u.user_id 
         WHERE b.activity_id = ? 
         ORDER BY b.created_at ASC"
    );
    $budgetQueryStatement->bind_param("i", $selectedActivityId);
    $budgetQueryStatement->execute();
    $budgetQueryResult = $budgetQueryStatement->get_result();

    while ($budgetRow = $budgetQueryResult->fetch_assoc()) {
        $budgetTransactions[] = $budgetRow;
        
        // คำนวณยอดรวมรายรับรายจ่าย
        if ($budgetRow['transaction_type'] == 'income') {
            $totalIncomeValue += $budgetRow['amount'];
        }
        if ($budgetRow['transaction_type'] == 'expense') {
            $totalExpenseValue += $budgetRow['amount'];
        }
    }
    $budgetQueryStatement->close();
}

// ยอดเงินคงเหลือ
$currentBalanceValue = $totalIncomeValue - $totalExpenseValue;


// ส่วนที่ 5 โหลด Header
include 'includes/header.php';
?>

<!-- แสดง Flash Alert ถ้ามี -->
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

    <h2 class="section-title mb-4">ระบบจัดการงบประมาณและบัญชี</h2>

<!-- ส่วนเลือกกิจกรรม (Dropdown) -->
    <div class="card mb-4">
        <form method="GET" action="erp_budgets.php" class="d-flex align-end gap-3 flex-wrap">
            <div class="flex-grow-1">
                <label class="form-label text-main">เลือกกิจกรรมที่ต้องการจัดการบัญชี:</label>
                <select name="activity_id" class="form-control" required>
                    <option value="">-- กรุณาเลือกกิจกรรม --</option>
                    <?php while ($activityItem = $activitiesListResult->fetch_assoc()): ?>
                        <?php $isSelected = ($selectedActivityId == $activityItem['activity_id']) ? 'selected' : ''; ?>
                        <option value="<?= $activityItem['activity_id'] ?>" <?= $isSelected ?>>
                            <?= htmlspecialchars($activityItem['title']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="fa-solid fa-magnifying-glass me-1"></i> เรียกดูข้อมูล
            </button>
        </form>
    </div>

    <!-- แสดงผลเมื่อมีการเลือกกิจกรรมแล้ว -->
    <?php if ($selectedActivityId > 0): ?>
        
<!-- Dashboard สรุปยอดเงิน (Cards) -->
        <div class="grid-3 mb-4">
            <div class="card bg-main">
                <div class="text-sm font-weight-bold text-dark mb-1">
                    รายรับรวม (Income)
                </div>
                <div class="text-xl font-weight-bold text-dark">
                    ฿<?= number_format($totalIncomeValue, 2) ?>
                </div>
            </div>
            
            <div class="card bg-main">
                <div class="text-sm font-weight-bold text-dark mb-1">
                    รายจ่ายรวม (Expense)
                </div>
                <div class="text-xl font-weight-bold text-dark">
                    ฿<?= number_format($totalExpenseValue, 2) ?>
                </div>
            </div>
            
            <div class="card bg-main">
                <div class="text-sm font-weight-bold text-dark mb-1">
                    งบคงเหลือ (Balance)
                </div>
                <?php $balanceTextColorClass = $currentBalanceValue >= 0 ? 'text-dark' : 'text-danger'; ?>
                <div class="text-xl font-weight-bold <?= $balanceTextColorClass ?>">
                    ฿<?= number_format($currentBalanceValue, 2) ?>
                </div>
            </div>
            
        </div>

<!-- ส่วนจัดการข้อมูล แบ่งเป็น Grid 2 คอลัมน์ -->
        <div class="grid-layout" style="grid-template-columns: 1fr 2fr; gap: var(--space-20);">

<!-- ฝั่งซ้าย: ฟอร์มเพิ่มรายการบัญชี -->
            <div class="card h-100">
                <h3 class="text-md m-0 mb-3 pb-3 border-bottom">เพิ่มรายการบัญชีใหม่</h3>
                
                <form method="POST" action="erp_budgets.php" enctype="multipart/form-data">
<?php csrfField(); ?>
                    <input type="hidden" name="action" value="add_budget">
                    <input type="hidden" name="activity_id" value="<?= $selectedActivityId ?>">

                    <div class="form-group mb-3">
                        <label class="form-label">ประเภทรายการ <span class="text-danger">*</span></label>
                        <select name="type" class="form-control" required>
                            <option value="income">รายรับ (+)</option>
                            <option value="expense">รายจ่าย (-)</option>
                        </select>
                    </div>

                    <div class="form-group mb-3">
                        <label class="form-label">หมวดหมู่ / ชื่อรายการ <span class="text-danger">*</span></label>
                        <input type="text" name="category" placeholder="เช่น งบมหาวิทยาลัย, ค่าอาหารค่าย" class="form-control" required>
                    </div>

                    <div class="form-group mb-3">
                        <label class="form-label">จำนวนเงิน (บาท) <span class="text-danger">*</span></label>
                        <input type="number" name="amount" step="0.01" min="0" class="form-control" required>
                    </div>

                    <div class="form-group mb-3">
                        <label class="form-label">แนบใบเสร็จ / สลิปโอนเงิน (ถ้ามี)</label>
                        <input type="file" name="receipt_image" id="receipt_image" accept="image/jpeg, image/png, application/pdf" class="form-control text-sm">
                        <div class="text-xs text-muted mt-1">รองรับไฟล์ JPG, PNG, PDF (ขนาดไม่เกิน 5MB)</div>
                    </div>

                    <div class="form-group mb-4">
                        <label class="form-label">รายละเอียดเพิ่มเติม</label>
                        <textarea name="description" rows="2" class="form-control"></textarea>
                    </div>

                    <button type="submit" class="btn btn-success btn-block">
                        <i class="fa-solid fa-floppy-disk me-1"></i> บันทึกรายการ
                    </button>
                </form>
            </div>

<!-- ฝั่งขวา: ตารางประวัติการทำรายการ -->
            <div class="card h-100">
                <h3 class="text-md m-0 mb-3 pb-3 border-bottom">ประวัติการทำรายการบัญชี</h3>

                <?php if (count($budgetTransactions) > 0): ?>
                    <div class="table-responsive">
                        <table class="table w-100 text-sm">
                            
                            <thead class="bg-main text-muted">
                                <tr>
                                    <th class="p-2 text-left">วันที่/เวลา</th>
                                    <th class="p-2 text-left">หมวดหมู่รายการ</th>
                                    <th class="p-2 text-right">รายรับ (+)</th>
                                    <th class="p-2 text-right">รายจ่าย (-)</th>
                                    <th class="p-2 text-center">หลักฐาน</th>
                                    <th class="p-2 text-center" style="width: 60px;">ลบ</th>
                                </tr>
                            </thead>
                            
                            <tbody>
                                <?php foreach ($budgetTransactions as $transaction): ?>
                                    <tr class="border-bottom">
                                        
                                        <!-- วันที่และเวลา -->
                                        <td class="p-2 text-muted text-xs">
                                            <?= date('d/m/Y H:i', strtotime($transaction['created_at'])) ?>
                                        </td>
                                        
                                        <!-- รายการและรายละเอียด -->
                                        <td class="p-2">
                                            <strong class="text-main"><?= htmlspecialchars($transaction['category']) ?></strong>
                                            <?php if (!empty($transaction['description'])): ?>
                                                <div class="text-xs text-muted mt-1">
                                                    <?= htmlspecialchars($transaction['description']) ?>
                                                </div>
                                            <?php endif; ?>
                                            <div class="text-xs text-muted mt-1">
                                                <i class="fa-solid fa-user-pen"></i> ผู้บันทึก: <?= htmlspecialchars($transaction['recorder_name'] ?? 'ระบบ') ?>
                                            </div>
                                        </td>
                                        
                                        <!-- ยอดรายรับ -->
                                        <td class="p-2 text-right font-weight-bold text-success">
                                            <?= $transaction['transaction_type'] == 'income' ? '+' . number_format($transaction['amount'], 2) : '-' ?>
                                        </td>
                                        
                                        <!-- ยอดรายจ่าย -->
                                        <td class="p-2 text-right font-weight-bold text-danger">
                                            <?= $transaction['transaction_type'] == 'expense' ? '-' . number_format($transaction['amount'], 2) : '-' ?>
                                        </td>
                                        
                                        <!-- ลิงก์ดูไฟล์แนบ -->
                                        <td class="p-2 text-center">
                                            <?php if (!empty($transaction['receipt_file'])): ?>
                                                <a href="<?= htmlspecialchars($transaction['receipt_file']) ?>" target="_blank" class="badge badge-primary text-decoration-none" title="คลิกเพื่อดูไฟล์หลักฐาน">
                                                    <i class="fa-solid fa-file-invoice"></i> ดูไฟล์
                                                </a>
                                            <?php else: ?>
                                                <span class="text-muted text-xs">-</span>
                                            <?php endif; ?>
                                        </td>
                                        
                                        <!-- ปุ่มลบรายการ (ใช้ฟอร์ม POST พร้อม CSRF Token) -->
                                        <td class="p-2 text-center">
                                            <?php $transId = $transaction['transaction_id'] ?? $transaction['id'] ?? $transaction['budget_id'] ?? 0; ?>
                                            <form method="POST" action="erp_budgets.php" class="m-0 p-0 d-inline" onsubmit="return confirm('ยืนยันการลบรายการบัญชี?\nยอดเงินจะถูกคำนวณใหม่ และไฟล์แนบจะถูกลบออกจากระบบ คุณไม่สามารถกู้คืนได้!');">
                                                <?php csrfField(); ?>
                                                <input type="hidden" name="action" value="delete_budget">
                                                <input type="hidden" name="delete_id" value="<?= (int)$transId ?>">
                                                <input type="hidden" name="activity_id" value="<?= (int)$selectedActivityId ?>">
                                                <button type="submit" class="btn-icon text-danger" title="ลบรายการนี้" style="background: none; border: none; cursor: pointer;">
                                                    <i class="fa-solid fa-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                        
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center p-4 border-dashed mt-3" style="color: #000;">
                        <p class="m-0" style="color: #000;">ยังไม่มีการบันทึกรายรับ-รายจ่ายสำหรับกิจกรรมนี้</p>
                    </div>
                <?php endif; ?>
            </div>
            
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>