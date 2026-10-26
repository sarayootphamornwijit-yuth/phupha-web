<?php
// generate_certificate.php
// ไฟล์สำหรับสร้างและพิมพ์เกียรติบัตรอิเล็กทรอนิกส์ ชมรมเพื่อนภูผา มหาวิทยาลัยราชภัฏเชียงใหม่
// เกณฑ์: สมาชิกต้องสะสมคะแนนจิตอาสาครบตามเกณฑ์ที่กำหนดใน club_settings จึงจะได้รับเกียรติบัตร

require_once 'config/database.php';

// ฟังก์ชันแปลงวันที่ภาษาไทยสำรอง (กรณีที่ไม่มีในฐานข้อมูล)
if (!function_exists('formatDateThai')) {
    function formatDateThai($dateString) {
        if (function_exists('DateThai')) {
            return DateThai($dateString);
        }
        $ts = strtotime($dateString);
        if (!$ts) return '-';
        $mThai = ['', 'มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน', 'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'];
        return date('j', $ts) . ' ' . ($mThai[(int)date('n', $ts)] ?? '') . ' ' . ((int)date('Y', $ts) + 543);
    }
}

// ตรวจสอบว่าผู้ใช้เข้าสู่ระบบหรือยัง
if (!isLoggedIn()) {
    header("Location: login.php");
    exit;
}

$currentUserId = $_SESSION['user_id'];

// ดึงข้อมูลชื่อและคะแนนของผู้ใช้งาน
$userStmt = $conn->prepare("SELECT name, points FROM users WHERE user_id = ?");
$userStmt->bind_param("i", $currentUserId);
$userStmt->execute();
$userData = $userStmt->get_result()->fetch_assoc();
$userStmt->close();

if (!$userData) {
    $_SESSION['alert'] = [
        'type'  => 'error',
        'title' => 'ไม่พบข้อมูล',
        'text'  => 'ไม่พบข้อมูลผู้ใช้งานในระบบ'
    ];
    header('Location: certificates.php');
    exit;
}

$recipientName = $userData['name'];
$userPoints    = (int)($userData['points'] ?? 0);
$certRecordId  = 0;

// ดึงเกณฑ์คะแนนสะสมจิตอาสาขั้นต่ำ
$threshold = getCertificatePointThreshold();

// ตรวจสอบเงื่อนไขคะแนนสะสม: ต้องมีคะแนนสะสมถึงเกณฑ์เท่านั้น จึงจะได้รับเกียรติบัตร
if ($userPoints < $threshold) {
    $_SESSION['alert'] = [
        'type'  => 'warning',
        'title' => 'ยังไม่ผ่านเกณฑ์',
        'text'  => "ขออภัย คุณยังไม่ผ่านเกณฑ์คะแนนจิตอาสาสำหรับรับเกียรติบัตร (ต้องมีคะแนนสะสมอย่างน้อย {$threshold} คะแนน ปัจจุบันมี {$userPoints} คะแนน)"
    ];
    header('Location: certificates.php');
    exit;
}

// ตรวจสอบโครงสร้างคอลัมน์ Primary Key ในตาราง certificates เพื่อรองรับทั้ง id และ certificate_id
$certPkCol = 'certificate_id';
try {
    $colCheck = $conn->query("SHOW COLUMNS FROM `certificates` LIKE 'certificate_id'");
    if (!$colCheck || $colCheck->num_rows === 0) {
        $certPkCol = 'id';
    }
} catch (\Throwable $e) {
    $certPkCol = 'id';
}

// ข้อมูลเกียรติบัตรเชิดชูเกียรติผู้มีจิตอาสา (สะสมคะแนนครบตามเกณฑ์)
$certMainTitle = "เกียรติบัตรเชิดชูเกียรติ";
$certSubtitle  = "ขอมอบเกียรติบัตรฉบับนี้เพื่อแสดงว่า";
$certTitleDb   = "เกียรติบัตรเชิดชูเกียรติผู้มีจิตอาสา (คะแนนสะสมครบตามเกณฑ์)";
$certDescHtml  = "เป็นผู้มีความเสียสละ อุทิศตน และมีจิตสาธารณะ ได้เข้าร่วมกิจกรรมและสะสมคะแนนจิตอาสาครบตามเกณฑ์ที่กำหนด<br>"
               . "ของ <strong>ชมรมเพื่อนภูผา มหาวิทยาลัยราชภัฏเชียงใหม่</strong><br>"
               . "ขอให้มีความสุข ความเจริญ และเป็นกำลังสำคัญในการพัฒนาสังคมและประเทศชาติต่อไป";

// บันทึกประวัติการออกเกียรติบัตรคะแนนสะสม (ครอบด้วย try-catch เพื่อความปลอดภัยสูงสุด)
try {
    $checkCert = $conn->prepare("SELECT {$certPkCol} FROM certificates WHERE user_id = ? AND title = ? LIMIT 1");
    $checkCert->bind_param("is", $currentUserId, $certTitleDb);
    $checkCert->execute();
    $certLog = $checkCert->get_result();
    if ($certLog && $certLog->num_rows > 0) {
        $certRecordId = (int)$certLog->fetch_assoc()[$certPkCol];
    } else {
        $insertCert = $conn->prepare("INSERT INTO certificates (user_id, title) VALUES (?, ?)");
        $insertCert->bind_param("is", $currentUserId, $certTitleDb);
        $insertCert->execute();
        $certRecordId = (int)$conn->insert_id;
        $insertCert->close();
    }
    $checkCert->close();
} catch (\Throwable $e) {
    error_log("บันทึกประวัติเกียรติบัตรไม่สำเร็จ: " . $e->getMessage());
}

// ใช้วันที่ปัจจุบันเป็นภาษาไทย
$displayDateText = formatDateThai(date('Y-m-d'));
$refCodeNumber   = 'PPC-' . date('Y') . '-' . str_pad($certRecordId > 0 ? $certRecordId : $currentUserId, 5, '0', STR_PAD_LEFT);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เกียรติบัตร - <?= htmlspecialchars($recipientName) ?> | ชมรมเพื่อนภูผา</title>

    <!-- เรียกใช้ฟอนต์ Taviraj สำหรับพิมพ์หนังสือราชการและเกียรติบัตร -->
    <link href="https://fonts.googleapis.com/css2?family=Taviraj:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- CSS กำหนดรูปแบบหน้ากระดาษเกียรติบัตร -->
    <link rel="stylesheet" href="assets/css/certificate.css?v=<?= time() ?>">

    <style>
        /* สไตล์สำหรับแถบเครื่องมือพิมพ์ (ไม่แสดงเวลาพิมพ์จริง) */
        .print-toolbar {
            text-align: center;
            padding: 15px;
            background: #f1f5f9;
            border-bottom: 1px solid #cbd5e1;
        }
        .btn-print {
            background-color: #1e40af;
            color: #ffffff;
            border: none;
            padding: 8px 20px;
            font-size: 15px;
            font-family: 'Taviraj', serif;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-weight: 500;
        }
        .btn-print:hover {
            background-color: #1d4ed8;
        }
        .btn-back {
            background-color: #64748b;
            color: #ffffff;
            border: none;
            padding: 8px 16px;
            font-size: 15px;
            font-family: 'Taviraj', serif;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin-right: 10px;
        }
        .btn-back:hover {
            background-color: #475569;
        }
        @page {
            size: A4 landscape;
            margin: 0;
        }
        @media print {
            .print-toolbar {
                display: none !important;
            }
        }
    </style>
</head>
<body>

    <!-- แถบปุ่มสั่งพิมพ์ / ดาวน์โหลด PDF (ซ่อนอัตโนมัติเวลาสั่งพิมพ์) -->
    <div class="print-toolbar no-print">
        <a href="certificates.php" class="btn-back">« กลับหน้ารายการ</a>
        <button type="button" class="btn-print" onclick="window.print()">
            พิมพ์เกียรติบัตร / บันทึกเป็น PDF
        </button>
        <span style="font-size: 13px; color: #475569; margin-left: 15px;">
            (คำแนะนำ: ในหน้าต่างสั่งพิมพ์ ให้เลือก <strong>การวางแนว: แนวนอน / Landscape</strong>)
        </span>
    </div>

    <!-- โครงสร้างเอกสารเกียรติบัตร -->
    <div class="cert-container">
        <div class="cert-border">

            <!-- โลโก้ชมรม -->
            <div class="logo">
                <img src="assets/images/logo.png" alt="โลโก้ชมรม" onerror="this.style.display='none'">
            </div>

            <!-- หัวข้อเกียรติบัตร -->
            <div class="title"><?= htmlspecialchars($certMainTitle) ?></div>
            <div class="subtitle"><?= htmlspecialchars($certSubtitle) ?></div>

            <!-- ชื่อผู้ได้รับเกียรติบัตร -->
            <div class="name"><?= htmlspecialchars($recipientName) ?></div>

            <!-- ข้อความรับรองเกียรติบัตร -->
            <div class="desc">
                <?= $certDescHtml ?>
            </div>

            <!-- วันที่ออกเกียรติบัตร (กึ่งกลาง ด้านบนรหัสอ้างอิง) -->
            <div class="cert-issue-date">
                ออกให้ ณ วันที่ <?= $displayDateText ?>
            </div>

            <!-- รหัสอ้างอิง -->
            <div class="cert-ref">
                รหัสอ้างอิง: <?= htmlspecialchars($refCodeNumber) ?>
            </div>

            <!-- ส่วนท้าย: ลายเซ็น -->
            <div class="footer-sig">
                <div class="sig-box">
                    <div class="sig-line"></div>
                    <div class="sig-text">( คณะกรรมการบริหารชมรมเพื่อนภูผา )</div>
                </div>
                <div class="sig-box">
                    <div class="sig-line"></div>
                    <div class="sig-text">( อาจารย์ที่ปรึกษาชมรมเพื่อนภูผา )</div>
                </div>
            </div>

        </div>
    </div>

    <!-- สคริปต์เปิดหน้าต่างสั่งพิมพ์อัตโนมัติเมื่อเปิดหน้าขึ้นมา -->
    <script>
        window.onload = function() {
            setTimeout(function() {
                window.print();
            }, 600);
        };
    </script>

</body>
</html>