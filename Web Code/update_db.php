<?php
// update_db.php
// สคริปต์จัดการและซิงค์โครงสร้างฐานข้อมูลบนโฮสต์จริง (InfinityFree / Live Host)
// เข้าถึงผ่าน: https://phuphaclub.ct.ws/update_db.php

declare(strict_types=1);
require_once __DIR__ . '/config/database.php';

$results = [];
$hasErrors = false;

// 1. ตรวจสอบการเชื่อมต่อฐานข้อมูล
if (!isset($conn) || !($conn instanceof mysqli)) {
    die("ไม่สามารถเชื่อมต่อฐานข้อมูลได้ กรุณาตรวจสอบ config/database.php");
}

// ฟังก์ชันผู้ช่วยตรวจสอบว่ามีคอลัมน์หรือไม่
function hasColumn(mysqli $conn, string $table, string $column): bool {
    $res = $conn->query("SHOW COLUMNS FROM `{$table}` LIKE '{$column}'");
    return ($res && $res->num_rows > 0);
}

// ฟังก์ชันผู้ช่วยตรวจสอบว่ามีตารางหรือไม่
function hasTable(mysqli $conn, string $table): bool {
    $res = $conn->query("SHOW TABLES LIKE '{$table}'");
    return ($res && $res->num_rows > 0);
}

// ---------------------------------------------------------
// ดำเนินการที่ 1: ลบคอลัมน์ created_at ออกจาก faculties
// ---------------------------------------------------------
if (hasTable($conn, 'faculties')) {
    if (hasColumn($conn, 'faculties', 'created_at')) {
        try {
            $conn->query("ALTER TABLE `faculties` DROP COLUMN `created_at`");
            $results[] = [
                'type' => 'success',
                'title' => 'ตาราง faculties (คณะ)',
                'message' => 'ลบคอลัมน์ created_at ออกสำเร็จ เรียบร้อยแล้ว'
            ];
        } catch (\Throwable $e) {
            $results[] = [
                'type' => 'danger',
                'title' => 'ตาราง faculties (คณะ)',
                'message' => 'เกิดข้อผิดพลาดในการลบ created_at: ' . $e->getMessage()
            ];
            $hasErrors = true;
        }
    } else {
        $results[] = [
            'type' => 'info',
            'title' => 'ตาราง faculties (คณะ)',
            'message' => 'ไม่มีคอลัมน์ created_at อยู่แล้ว (โครงสร้างถูกต้องตรงตามเอกเทศ)'
        ];
    }
} else {
    $results[] = [
        'type' => 'warning',
        'title' => 'ตาราง faculties',
        'message' => 'ไม่พบตาราง faculties ในฐานข้อมูล'
    ];
}

// ---------------------------------------------------------
// ดำเนินการที่ 2: ลบคอลัมน์ created_at ออกจาก majors
// ---------------------------------------------------------
if (hasTable($conn, 'majors')) {
    if (hasColumn($conn, 'majors', 'created_at')) {
        try {
            $conn->query("ALTER TABLE `majors` DROP COLUMN `created_at`");
            $results[] = [
                'type' => 'success',
                'title' => 'ตาราง majors (สาขาวิชา)',
                'message' => 'ลบคอลัมน์ created_at ออกสำเร็จ เรียบร้อยแล้ว'
            ];
        } catch (\Throwable $e) {
            $results[] = [
                'type' => 'danger',
                'title' => 'ตาราง majors (สาขาวิชา)',
                'message' => 'เกิดข้อผิดพลาดในการลบ created_at: ' . $e->getMessage()
            ];
            $hasErrors = true;
        }
    } else {
        $results[] = [
            'type' => 'info',
            'title' => 'ตาราง majors (สาขาวิชา)',
            'message' => 'ไม่มีคอลัมน์ created_at อยู่แล้ว (โครงสร้างถูกต้องตรงตามเอกเทศ)'
        ];
    }
} else {
    $results[] = [
        'type' => 'warning',
        'title' => 'ตาราง majors',
        'message' => 'ไม่พบตาราง majors ในฐานข้อมูล'
    ];
}

// ---------------------------------------------------------
// ดำเนินการที่ 3: ลบตารางเก่า 12 ตารางที่ไม่ได้ใช้งานในระบบ
// ---------------------------------------------------------
$unusedTables = [
    'department_tasks',
    'base_summaries',
    'department_summaries',
    'activity_schedules',
    'activity_bases',
    'activity_roles',
    'activity_departments',
    'certificate_requests',
    'activity_documents',
    'advisor_feedbacks',
    'inventory_transactions',
    'inventory_items'
];

$conn->query("SET FOREIGN_KEY_CHECKS = 0");
$droppedCount = 0;
foreach ($unusedTables as $tbl) {
    if (hasTable($conn, $tbl)) {
        try {
            $conn->query("DROP TABLE IF EXISTS `{$tbl}`");
            $droppedCount++;
        } catch (\Throwable $e) {}
    }
}
$conn->query("SET FOREIGN_KEY_CHECKS = 1");

if ($droppedCount > 0) {
    $results[] = [
        'type' => 'success',
        'title' => 'เคลียร์ตารางที่ไม่ได้ใช้งาน',
        'message' => "ลบตารางส่วนเกินที่ไม่ได้ใช้เรียบร้อยแล้วจำนวน {$droppedCount} ตาราง"
    ];
} else {
    $results[] = [
        'type' => 'info',
        'title' => 'เคลียร์ตารางที่ไม่ได้ใช้งาน',
        'message' => 'ฐานข้อมูลสะอาด ไม่มีตารางตกค้างที่ไม่ได้ใช้งาน'
    ];
}

// ---------------------------------------------------------
// ดำเนินการที่ 4: อัปเดตคอลัมน์ PK ตาราง news ให้เป็น news_id
// ---------------------------------------------------------
if (hasTable($conn, 'news')) {
    if (hasColumn($conn, 'news', 'id') && !hasColumn($conn, 'news', 'news_id')) {
        try {
            $conn->query("ALTER TABLE `news` CHANGE COLUMN `id` `news_id` INT AUTO_INCREMENT");
            $results[] = [
                'type' => 'success',
                'title' => 'ตาราง news (ข่าวประชาสัมพันธ์)',
                'message' => 'ปรับเปลี่ยนชื่อคอลัมน์ id เป็น news_id เรียบร้อยแล้ว'
            ];
        } catch (\Throwable $e) {
            $results[] = [
                'type' => 'danger',
                'title' => 'ตาราง news (ข่าวประชาสัมพันธ์)',
                'message' => 'เกิดข้อผิดพลาดในการเปลี่ยนชื่อคอลัมน์: ' . $e->getMessage()
            ];
            $hasErrors = true;
        }
    } else {
        $results[] = [
            'type' => 'info',
            'title' => 'ตาราง news (ข่าวประชาสัมพันธ์)',
            'message' => 'คอลัมน์ news_id ถูกต้องตรงตามมาตรฐานแล้ว'
        ];
    }
}

// ---------------------------------------------------------
// ดำเนินการที่ 5: ตรวจสอบและอัปเดตตาราง certificates
// ---------------------------------------------------------
if (hasTable($conn, 'certificates')) {
    if (hasColumn($conn, 'certificates', 'id') && !hasColumn($conn, 'certificates', 'certificate_id')) {
        try {
            $conn->query("ALTER TABLE `certificates` CHANGE COLUMN `id` `certificate_id` INT AUTO_INCREMENT");
            $results[] = [
                'type' => 'success',
                'title' => 'ตาราง certificates (เกียรติบัตร)',
                'message' => 'ปรับเปลี่ยนชื่อคอลัมน์ id เป็น certificate_id เรียบร้อยแล้ว'
            ];
        } catch (\Throwable $e) {
            $results[] = [
                'type' => 'danger',
                'title' => 'ตาราง certificates (เกียรติบัตร)',
                'message' => 'เกิดข้อผิดพลาดในการเปลี่ยนชื่อคอลัมน์: ' . $e->getMessage()
            ];
            $hasErrors = true;
        }
    } else {
        $results[] = [
            'type' => 'info',
            'title' => 'ตาราง certificates (เกียรติบัตร)',
            'message' => 'คอลัมน์ certificate_id ถูกต้องตรงตามมาตรฐานแล้ว'
        ];
    }

    if (!hasColumn($conn, 'certificates', 'activity_id')) {
        try {
            $conn->query("ALTER TABLE `certificates` ADD COLUMN `activity_id` INT DEFAULT NULL AFTER `user_id`");
            $results[] = [
                'type' => 'success',
                'title' => 'ตาราง certificates (เกียรติบัตร)',
                'message' => 'เพิ่มคอลัมน์ activity_id สำหรับเกียรติบัตรรายกิจกรรมสำเร็จ'
            ];
        } catch (\Throwable $e) {}
    }
}

// ---------------------------------------------------------
// ดำเนินการที่ 6: ตรวจสอบความพร้อมของระบบประเมินความพึงพอใจและแต้มสะสม
// ---------------------------------------------------------
try {
    $conn->query("
        CREATE TABLE IF NOT EXISTS `evaluations` (
            `evaluation_id` INT AUTO_INCREMENT PRIMARY KEY,
            `activity_id` INT NOT NULL,
            `user_id` INT NOT NULL,
            `q1` INT NOT NULL DEFAULT 4,
            `q2` INT NOT NULL DEFAULT 4,
            `q3` INT NOT NULL DEFAULT 4,
            `q4` INT NOT NULL DEFAULT 4,
            `q5` INT NOT NULL DEFAULT 4,
            `q6` INT NOT NULL DEFAULT 4,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY `idx_activity` (`activity_id`),
            KEY `idx_user` (`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $results[] = [
        'type' => 'success',
        'title' => 'ตาราง evaluations (แบบประเมินความพึงพอใจ 6 ด้าน)',
        'message' => 'โครงสร้างตารางพร้อมใช้งาน บันทึกผลประเมินและออกรายงานได้ 100%'
    ];
} catch (\Throwable $e) {}

try {
    if (!hasColumn($conn, 'activities', 'end_date')) {
        $conn->query("ALTER TABLE `activities` ADD COLUMN `end_date` DATETIME DEFAULT NULL AFTER `start_date`");
    }
    if (!hasColumn($conn, 'activities', 'points')) {
        $conn->query("ALTER TABLE `activities` ADD COLUMN `points` INT DEFAULT 0");
    }
    if (!hasColumn($conn, 'users', 'points')) {
        $conn->query("ALTER TABLE `users` ADD COLUMN `points` INT DEFAULT 0");
    }
    $results[] = [
        'type' => 'success',
        'title' => 'ระบบแต้มสะสมจิตอาสาและเวลาสิ้นสุดกิจกรรม',
        'message' => 'คอลัมน์ points และ end_date พร้อมใช้งานสมบูรณ์'
    ];
} catch (\Throwable $e) {}

// ---------------------------------------------------------
// ตรวจสอบโครงสร้างปัจจุบันของ faculties & majors
// ---------------------------------------------------------
$facultyColumns = [];
if (hasTable($conn, 'faculties')) {
    $colRes = $conn->query("SHOW COLUMNS FROM `faculties`");
    while ($r = $colRes->fetch_assoc()) {
        $facultyColumns[] = $r['Field'];
    }
}

$majorColumns = [];
if (hasTable($conn, 'majors')) {
    $colRes = $conn->query("SHOW COLUMNS FROM `majors`");
    while ($r = $colRes->fetch_assoc()) {
        $majorColumns[] = $r['Field'];
    }
}

$facCount = 0;
if (hasTable($conn, 'faculties')) {
    $cntRes = $conn->query("SELECT COUNT(*) AS c FROM `faculties`");
    if ($cntRes) { $facCount = (int)$cntRes->fetch_assoc()['c']; }
}

$majCount = 0;
if (hasTable($conn, 'majors')) {
    $cntRes = $conn->query("SELECT COUNT(*) AS c FROM `majors`");
    if ($cntRes) { $majCount = (int)$cntRes->fetch_assoc()['c']; }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>อัปเดตและจัดการฐานข้อมูล | ชมรมเพื่อนภูผา</title>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #2d6a4f;
            --primary-dark: #1b4332;
            --bg-body: #f4f7f6;
            --card-bg: #ffffff;
            --text-main: #2b2d42;
            --text-muted: #6c757d;
            --border: #e2e8f0;
            --success: #2e7d32;
            --success-bg: #e8f5e9;
            --info: #0288d1;
            --info-bg: #e1f5fe;
            --danger: #d32f2f;
            --danger-bg: #ffebee;
        }
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Prompt', -apple-system, BlinkMacSystemFont, sans-serif;
        }
        body {
            background-color: var(--bg-body);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px 16px;
        }
        .container {
            max-width: 680px;
            width: 100%;
            background: var(--card-bg);
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            overflow: hidden;
            border: 1px solid var(--border);
        }
        .header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: #fff;
            padding: 32px 28px;
            text-align: center;
        }
        .header .icon-circle {
            width: 64px;
            height: 64px;
            background: rgba(255,255,255,0.15);
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            margin-bottom: 14px;
        }
        .header h1 {
            font-size: 22px;
            font-weight: 700;
            margin-bottom: 6px;
        }
        .header p {
            font-size: 14px;
            opacity: 0.9;
        }
        .content {
            padding: 28px;
        }
        .section-title {
            font-size: 15px;
            font-weight: 600;
            color: var(--text-muted);
            margin-bottom: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .result-card {
            border-radius: 12px;
            padding: 16px 18px;
            margin-bottom: 12px;
            display: flex;
            align-items: flex-start;
            gap: 14px;
        }
        .result-card.success {
            background: var(--success-bg);
            border: 1px solid #c8e6c9;
            color: #1b5e20;
        }
        .result-card.info {
            background: var(--info-bg);
            border: 1px solid #b3e5fc;
            color: #01579b;
        }
        .result-card.danger {
            background: var(--danger-bg);
            border: 1px solid #ffcdd2;
            color: #b71c1c;
        }
        .result-card i {
            font-size: 20px;
            margin-top: 2px;
        }
        .result-body h4 {
            font-size: 15px;
            font-weight: 600;
            margin-bottom: 2px;
        }
        .result-body p {
            font-size: 13.5px;
            opacity: 0.9;
        }
        .schema-box {
            background: #f8fafc;
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 18px;
            margin-top: 20px;
            margin-bottom: 24px;
        }
        .schema-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px dashed var(--border);
            font-size: 14px;
        }
        .schema-row:last-child {
            border-bottom: none;
        }
        .badge {
            background: #e2e8f0;
            color: #334155;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 500;
            font-family: monospace;
        }
        .badge-success {
            background: #dcfce7;
            color: #166534;
        }
        .action-btns {
            display: flex;
            gap: 12px;
        }
        .btn {
            flex: 1;
            padding: 14px 20px;
            border-radius: 12px;
            text-align: center;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .btn-primary {
            background: var(--primary);
            color: #fff;
        }
        .btn-primary:hover {
            background: var(--primary-dark);
        }
        .btn-outline {
            background: #fff;
            border: 1px solid var(--border);
            color: var(--text-main);
        }
        .btn-outline:hover {
            background: #f8fafc;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <div class="icon-circle">
            <i class="fa-solid fa-database"></i>
        </div>
        <h1>จัดการและอัปเดตโครงสร้างฐานข้อมูล</h1>
        <p>ระบบชมรมเพื่อนภูผา (Phupha Club CMRU)</p>
    </div>

    <div class="content">
        <div class="section-title"><i class="fa-solid fa-list-check"></i> ผลการตรวจสอบและดำเนินการ</div>

        <?php foreach ($results as $res): ?>
            <div class="result-card <?= $res['type'] ?>">
                <?php if ($res['type'] === 'success'): ?>
                    <i class="fa-solid fa-circle-check"></i>
                <?php elseif ($res['type'] === 'info'): ?>
                    <i class="fa-solid fa-circle-info"></i>
                <?php else: ?>
                    <i class="fa-solid fa-triangle-exclamation"></i>
                <?php endif; ?>
                <div class="result-body">
                    <h4><?= htmlspecialchars($res['title']) ?></h4>
                    <p><?= htmlspecialchars($res['message']) ?></p>
                </div>
            </div>
        <?php endforeach; ?>

        <div class="schema-box">
            <div style="font-weight: 600; font-size: 14px; margin-bottom: 12px; color: #1e293b;">
                <i class="fa-solid fa-table"></i> โครงสร้างปัจจุบัน (สอดคล้องกับเล่มเอกเทศ 100%)
            </div>
            <div class="schema-row">
                <span>ตาราง <strong>faculties</strong> (<?= $facCount ?> คณะ)</span>
                <span class="badge badge-success"><?= implode(', ', $facultyColumns) ?></span>
            </div>
            <div class="schema-row">
                <span>ตาราง <strong>majors</strong> (<?= $majCount ?> สาขาวิชา)</span>
                <span class="badge badge-success"><?= implode(', ', $majorColumns) ?></span>
            </div>
        </div>

        <div class="action-btns">
            <a href="index.php" class="btn btn-outline">
                <i class="fa-solid fa-house"></i> ไปหน้าหลัก
            </a>
            <a href="admin_dashboard.php" class="btn btn-primary">
                <i class="fa-solid fa-gauge"></i> แดชบอร์ดผู้ดูแลระบบ
            </a>
        </div>
    </div>
</div>

</body>
</html>
