<?php
require_once 'config/database.php';

/* ตรวจสอบสิทธิ์เฉพาะคณะกรรมการ */
requireCommitteePermission('qr_scanner');

/* รับค่ารหัส QR Code ที่สแกนได้ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['qr_data'])) {
    $qr_data = trim($_POST['qr_data'] ?? '');

    $u_id = 0;
    $a_id = 0;
    $token = '';

    /* แยกข้อความเพื่อหารหัสสมาชิก ($u_id) และรหัสกิจกรรม ($a_id) */
    if (preg_match('/checkin-(\d+)-(\d+)(?:-([a-zA-Z0-9]+))?/i', $qr_data, $matches)) {
        $u_id = (int)$matches[1];
        $a_id = (int)$matches[2];
        $token = $matches[3] ?? '';
    }

    if ($u_id > 0 && $a_id > 0) {
        /* ตรวจสอบความถูกต้องของรหัส QR Code */
        if (!empty($token) && !verifyQrToken($u_id, $a_id, $token)) {
            $_SESSION['alert'] = [
                'type'  => 'error', 
                'title' => 'QR Code ไม่ถูกต้อง', 
                'text'  => 'รหัสตรวจสอบ Signature ของ QR Code ไม่ถูกต้อง'
            ];
            header("Location: qr_scanner.php");
            exit;
        }

        /* ตรวจสอบข้อมูลการลงทะเบียนของสมาชิกในกิจกรรมนี้ */
        $stmt_chk = $conn->prepare("
            SELECT ua.status, u.name, u.student_id, a.title AS activity_title, a.points 
            FROM user_activities ua
            JOIN users u ON ua.user_id = u.user_id
            JOIN activities a ON ua.activity_id = a.activity_id
            WHERE ua.user_id = ? AND ua.activity_id = ?
        ");
        $stmt_chk->bind_param("ii", $u_id, $a_id);
        $stmt_chk->execute();
        $res_chk = $stmt_chk->get_result();

        if ($res_chk && $res_chk->num_rows > 0) {
            $memberData = $res_chk->fetch_assoc();
            $status     = $memberData['status'];
            $userName   = $memberData['name'];
            $studentId  = $memberData['student_id'] ?: '-';
            $actTitle   = $memberData['activity_title'];
            $pts        = (int)$memberData['points'];

            if ($status === 'registered') {
                /* บันทึกเวลาเช็คอิน */
                $stmt_upd = $conn->prepare("UPDATE user_activities SET status = 'checked_in', check_in_time = NOW() WHERE user_id = ? AND activity_id = ?");
                $stmt_upd->bind_param("ii", $u_id, $a_id);
                $stmt_upd->execute();
                $stmt_upd->close();

                /* ดึงจำนวนผู้เข้าร่วมทั้งหมดของกิจกรรมนี้ */
                $statStmt = $conn->prepare("
                    SELECT 
                        COUNT(*) AS total_reg,
                        SUM(CASE WHEN status IN ('checked_in', 'checked_out') THEN 1 ELSE 0 END) AS checked_in_count,
                        SUM(CASE WHEN status = 'registered' THEN 1 ELSE 0 END) AS absent_count
                    FROM user_activities 
                    WHERE activity_id = ?
                ");
                $statStmt->bind_param("i", $a_id);
                $statStmt->execute();
                $statRow = $statStmt->get_result()->fetch_assoc();
                $statStmt->close();

                $totalReg  = (int)($statRow['total_reg'] ?? 0);
                $checkedIn = (int)($statRow['checked_in_count'] ?? 0);
                $absent    = (int)($statRow['absent_count'] ?? 0);

                $_SESSION['alert'] = [
                    'type'  => 'success',
                    'title' => "เช็คอินสำเร็จ (เช็คอินแล้ว {$checkedIn} จาก {$totalReg} คน)",
                    'text'  => "บันทึกเวลาเช็คอินของ {$userName} ในกิจกรรม \"{$actTitle}\" เรียบร้อยแล้ว (เช็คอินแล้ว {$checkedIn} จาก {$totalReg} คน)"
                ];

            } else {
                /* สแกนเช็คอินรอบเดียวพอ: หากเช็คอินแล้วจะแจ้งเตือนว่าเช็คอินไปแล้ว */
                $_SESSION['alert'] = [
                    'type'  => 'info',
                    'title' => 'เช็คอินแล้ว',
                    'text'  => "สมาชิก {$userName} ({$studentId}) ได้เช็คอินเข้าร่วมกิจกรรมนี้เรียบร้อยแล้ว"
                ];
            }
        } else {
            $_SESSION['alert'] = [
                'type'  => 'error',
                'title' => 'ไม่พบข้อมูลการลงทะเบียน',
                'text'  => "ไม่พบข้อมูลการลงทะเบียนสำหรับรหัสผู้ใช้ ID: {$u_id} ในกิจกรรม ID: {$a_id}"
            ];
        }
        $stmt_chk->close();
    } else {
        $_SESSION['alert'] = [
            'type'  => 'error',
            'title' => 'รูปแบบไม่ถูกต้อง',
            'text'  => 'ข้อมูล QR Code ไม่ครบถ้วนหรือไม่ใช่คิวอาร์โค้ดสำหรับเช็คอินของชมรม'
        ];
    }

    header("Location: qr_scanner.php");
    exit;
}

include 'includes/header.php';
?>

<!-- ส่งข้อมูล Alert ไปให้ JS -->
<?php if (isset($_SESSION['alert'])): ?>
    <div id="flash-message" data-type="<?= $_SESSION['alert']['type'] ?>" data-title="<?= $_SESSION['alert']['title'] ?>" data-text="<?= $_SESSION['alert']['text'] ?>" class="d-none"></div>
    <?php unset($_SESSION['alert']); ?>
<?php endif; ?>

<div class="container py-4" style="max-width: 600px;">
    <div class="d-flex justify-between align-center mb-4">
        <h2 class="m-0 section-title" style="color: #000;">สแกนเช็คอิน</h2>
        <a href="index.php" class="btn btn-surface border-light text-muted text-sm hide-on-desktop">กลับหน้าแรก</a>
    </div>

    <!-- ฟอร์มสำหรับส่งข้อมูลจากกล้อง (มี CSRF Token ป้องกัน Token Mismatch) -->
    <form id="qr-camera-form" method="POST" action="qr_scanner.php" style="display: none;">
        <?php csrfField(); ?>
        <input type="hidden" name="qr_data" id="qr_camera_data">
    </form>

    <div class="text-center p-0">
        <!-- พื้นที่สำหรับแสดงกล้อง -->
        <div id="reader" class="rounded overflow-hidden mb-3" style="border: 2px solid var(--text-light); background: #000; min-height: 260px;"></div>

        <!-- ฟอร์มกรอกมือ (Fallback) -->
        <div class="border-top pt-4 text-left">
            <label class="form-label text-sm mb-2" style="color: #000; font-weight: 600;">หรือกรอกรหัสจากคิวอาร์โค้ดด้วยตนเอง</label>
            <form action="qr_scanner.php" method="POST" class="d-flex gap-2">
                <?php csrfField(); ?>
                <input type="text" name="qr_data" class="form-control flex-grow-1" placeholder="ตัวอย่าง: checkin-1-5 หรือ checkin-1-5-xxxxxxxx" onfocus="this.dataset.ph=this.placeholder; this.placeholder=''" onblur="this.placeholder=this.dataset.ph || ''" required>
                <button type="submit" class="btn btn-primary" style="white-space: nowrap;">ตรวจสอบ</button>
            </form>
        </div>
    </div>
</div>

<script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
<script src="assets/js/scanner.js?v=<?= time() ?>"></script>

<?php include 'includes/footer.php'; ?>