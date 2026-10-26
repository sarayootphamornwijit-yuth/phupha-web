<?php
// print_participants.php
// หน้าสำหรับพิมพ์รายชื่อผู้เข้าร่วมโครงการกิจกรรมลงกระดาษ A4
// สิทธิ์การเข้าถึง: ผู้ดูแลระบบ (Admin) และ คณะกรรมการฝ่ายกิจกรรม (Committee)

require_once 'config/database.php';

// ตรวจสอบการเข้าสู่ระบบ
if (!isLoggedIn()) {
    header('Location: index.php');
    exit;
}

// ตรวจสอบสิทธิ์: ผู้ดูแลระบบ หรือ คณะกรรมการที่ดูแลกิจกรรมเท่านั้น
$currentUserRole = getCurrentUserRole();
if ($currentUserRole === 'admin') {
    // ผู้ดูแลระบบเข้าถึงได้
} elseif ($currentUserRole === 'committee' && committeeCanAccess('activities')) {
    // คณะกรรมการฝ่ายกิจกรรมเข้าถึงได้
} else {
    header('Location: index.php');
    exit;
}

// รับค่ารหัสกิจกรรมจาก GET
$activityId = (int)($_GET['id'] ?? $_GET['activity_id'] ?? 0);
if ($activityId <= 0) {
    $_SESSION['alert'] = [
        'type'  => 'error',
        'title' => 'ไม่พบกิจกรรม',
        'text'  => 'กรุณาระบุรหัสโครงการกิจกรรมที่ต้องการพิมพ์รายชื่อ'
    ];
    header('Location: erp_activities.php');
    exit;
}

// ฟังก์ชันแปลงช่วงวันที่จัดกิจกรรมเป็นภาษาไทย
function getThaiDateRange($start, $end) {
    $thaiMonths = [
        1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม', 4 => 'เมษายน',
        5 => 'พฤษภาคม', 6 => 'มิถุนายน', 7 => 'กรกฎาคม', 8 => 'สิงหาคม',
        9 => 'กันยายน', 10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม'
    ];
    
    $startTime = strtotime($start);
    $startD = (int)date('d', $startTime);
    $startM = (int)date('n', $startTime);
    $startY = (int)date('Y', $startTime) + 543;
    
    if (empty($end)) {
        return "วันที่ $startD {$thaiMonths[$startM]} $startY";
    }
    
    $endTime = strtotime($end);
    $endD = (int)date('d', $endTime);
    $endM = (int)date('n', $endTime);
    $endY = (int)date('Y', $endTime) + 543;
    
    if ($startY === $endY && $startM === $endM && $startD === $endD) {
        return "วันที่ $startD {$thaiMonths[$startM]} $startY";
    } elseif ($startY === $endY && $startM === $endM) {
        return "วันที่ $startD - $endD {$thaiMonths[$startM]} $startY";
    } else {
        return "วันที่ $startD {$thaiMonths[$startM]} $startY - $endD {$thaiMonths[$endM]} $endY";
    }
}

// ดึงข้อมูลโครงการกิจกรรม
$stmtAct = $conn->prepare("SELECT title, start_date, end_date, location_name FROM activities WHERE activity_id = ?");
$stmtAct->bind_param("i", $activityId);
$stmtAct->execute();
$act = $stmtAct->get_result()->fetch_assoc();
$stmtAct->close();

if (!$act) {
    $_SESSION['alert'] = [
        'type'  => 'error',
        'title' => 'ไม่พบข้อมูล',
        'text'  => 'ไม่พบข้อมูลโครงการกิจกรรมในระบบ'
    ];
    header('Location: erp_activities.php');
    exit;
}

// ดึงรายชื่อนักศึกษาที่ลงทะเบียนในกิจกรรม พร้อมคณะ สาขาวิชา และสถานะเช็คอิน
$sqlParticipants = "
    SELECT 
        u.student_id, 
        u.name, 
        u.faculty, 
        u.major, 
        ua.status,
        ua.check_in_time
    FROM user_activities ua 
    JOIN users u ON ua.user_id = u.user_id 
    WHERE ua.activity_id = ? 
    ORDER BY u.student_id ASC
";
$stmtPart = $conn->prepare($sqlParticipants);
$stmtPart->bind_param("i", $activityId);
$stmtPart->execute();
$participants = $stmtPart->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtPart->close();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>รายชื่อผู้เข้าร่วม - <?= htmlspecialchars($act['title']) ?></title>
    <!-- เรียกใช้ฟอนต์ Sarabun สำหรับพิมพ์หนังสือทางการ -->
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Sarabun', sans-serif;
            margin: 0;
            padding: 20px;
            color: #1e293b;
            background: #ffffff;
        }
        .print-toolbar {
            margin-bottom: 20px;
            padding: 12px;
            background: #f1f5f9;
            border: 1px solid #cbd5e1;
            text-align: center;
            border-radius: 6px;
        }
        .btn-print {
            padding: 8px 24px;
            background: #1e40af;
            color: #ffffff;
            border: none;
            border-radius: 4px;
            font-size: 15px;
            font-family: 'Sarabun', sans-serif;
            cursor: pointer;
            font-weight: 600;
        }
        .btn-print:hover {
            background: #1d4ed8;
        }
        .btn-close {
            padding: 8px 18px;
            background: #ffffff;
            color: #334155;
            border: 1px solid #94a3b8;
            border-radius: 4px;
            font-size: 15px;
            font-family: 'Sarabun', sans-serif;
            cursor: pointer;
            margin-left: 10px;
            text-decoration: none;
            display: inline-block;
        }
        .header-title {
            text-align: center;
            margin-bottom: 20px;
        }
        .header-title h2 {
            margin: 0 0 5px 0;
            font-size: 19px;
            color: #0f172a;
        }
        .header-title h3 {
            margin: 0 0 5px 0;
            font-size: 17px;
            font-weight: normal;
            color: #1e293b;
        }
        .header-title p {
            margin: 0;
            font-size: 14px;
            color: #475569;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        th, td {
            border: 1px solid #000000;
            padding: 6px 8px;
            font-size: 13px;
        }
        th {
            background-color: #f1f5f9;
            text-align: center;
            font-weight: 600;
        }
        .text-center {
            text-align: center;
        }
        .text-right {
            text-align: right;
        }
        .summary-box {
            margin-top: 15px;
            font-size: 13px;
            display: flex;
            justify-content: space-between;
        }

        /* รูปแบบสำหรับพิมพ์ลงกระดาษ A4 */
        @media print {
            .no-print {
                display: none !important;
            }
            body {
                padding: 0;
                margin: 0;
                background: #ffffff;
            }
            @page {
                size: A4 portrait;
                margin: 15mm 12mm 15mm 12mm;
            }
            table {
                page-break-inside: auto;
            }
            tr {
                page-break-inside: avoid;
                page-break-after: auto;
            }
            thead {
                display: table-header-group;
            }
            tfoot {
                display: table-footer-group;
            }
        }
    </style>
</head>
<body>

    <!-- แถบปุ่มสั่งพิมพ์ (ซ่อนเวลาสั่งพิมพ์จริง) -->
    <div class="print-toolbar no-print">
        <button type="button" class="btn-print" onclick="window.print()">
            พิมพ์เอกสาร (Print)
        </button>
        <button type="button" class="btn-close" onclick="window.close()">
            ปิดหน้าต่างนี้
        </button>
    </div>

    <!-- ส่วนหัวเอกสาร -->
    <div class="header-title">
        <h2>รายชื่อผู้เข้าร่วมโครงการกิจกรรม</h2>
        <h3><?= htmlspecialchars($act['title']) ?></h3>
        <p>
            <?= getThaiDateRange($act['start_date'], $act['end_date']) ?>
            &nbsp;|&nbsp;
            สถานที่: <?= htmlspecialchars($act['location_name'] ?: 'ไม่ระบุสถานที่') ?>
        </p>
    </div>

    <!-- ตารางรายชื่อผู้เข้าร่วมโครงการ 6 คอลัมน์ตามข้อกำหนด -->
    <table>
        <thead>
            <tr>
                <th style="width: 50px;">ลำดับ</th>
                <th style="width: 120px;">รหัสนักศึกษา</th>
                <th>ชื่อ - นามสกุล</th>
                <th style="width: 170px;">คณะ</th>
                <th style="width: 170px;">สาขาวิชา</th>
                <th style="width: 110px;">สถานะเช็คอิน</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($participants)): ?>
                <?php foreach ($participants as $index => $p): ?>
                    <?php
                    // แปลงสถานะเช็คอิน
                    $isCheckedIn = ($p['status'] === 'checked_in' || $p['status'] === 'checked_out');
                    $statusText = $isCheckedIn ? 'เช็คอินแล้ว' : 'ยังไม่เช็คอิน';
                    ?>
                    <tr>
                        <td class="text-center"><?= $index + 1 ?></td>
                        <td class="text-center"><?= htmlspecialchars($p['student_id'] ?: '-') ?></td>
                        <td><?= htmlspecialchars($p['name']) ?></td>
                        <td><?= htmlspecialchars($p['faculty'] ?: '-') ?></td>
                        <td><?= htmlspecialchars($p['major'] ?: '-') ?></td>
                        <td class="text-center"><?= $statusText ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="6" class="text-center" style="padding: 20px;">
                        ยังไม่มีผู้ลงทะเบียนเข้าร่วมกิจกรรมนี้
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- สรุปจำนวนท้ายตาราง -->
    <div class="summary-box">
        <div>
            จำนวนผู้ลงทะเบียนทั้งหมด: <strong><?= count($participants) ?></strong> คน
        </div>
        <div>
            พิมพ์เมื่อ: <?= date('d/m/Y H:i') ?> น.
        </div>
    </div>

</body>
</html>
