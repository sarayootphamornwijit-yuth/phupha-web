<?php
// advisor_members.php
// ไฟล์นี้ทำหน้าที่แสดงรายชื่อสมาชิกทั้งหมดสำหรับอาจารย์ที่ปรึกษา คณะกรรมการ และแอดมิน
// มีฟังก์ชันการค้นหา กรอง และดูสถิติ (อ่านข้อมูลอย่างเดียว)

require_once 'config/database.php';


// ส่วนที่ 1 ตรวจสอบสิทธิ์
// อนุญาตอาจารย์ที่ปรึกษา คณะกรรมการที่มีสิทธิ์ และแอดมิน
if (!isLoggedIn()) {
    header("Location: index.php");
    exit;
}

$currentUserRole = getCurrentUserRole();

if ($currentUserRole === 'admin') {
    // แอดมินให้ไปหน้าจัดการสมาชิกของแอดมิน
    header("Location: admin_users.php");
    exit;
} elseif ($currentUserRole === 'advisor') {
    // อาจารย์ที่ปรึกษา สามารถดูข้อมูลสมาชิกได้แบบเดียวกับคณะกรรมการ
} elseif (in_array($currentUserRole, ['committee', 'leader'], true) && committeeCanAccess('members')) {
    // คณะกรรมการเข้าถึงได้เฉพาะตำแหน่งที่มีสิทธิ์ดูข้อมูลสมาชิก
} else {
    header("Location: index.php");
    exit;
}


// ส่วนที่ 2 รับค่าค้นหาและกรองข้อมูล
// สร้างตัวแปรรับค่าคีย์เวิร์ดค้นหาและบทบาทจาก GET
$searchKeyword = trim($_GET['search'] ?? '');
$filterRole    = trim($_GET['role'] ?? '');

// สร้างอาร์เรย์สำหรับเก็บเงื่อนไข SQL
$whereClauses  = [];
$queryParams   = [];
$paramTypes    = '';

// ตรวจสอบว่ามีการกรอกคีย์เวิร์ดค้นหาหรือไม่
if ($searchKeyword !== '') {
    // เพิ่มเงื่อนไขค้นหาจากชื่อ รหัสนักศึกษา หรือเบอร์โทร
    $whereClauses[] = "(u.name LIKE ? OR u.student_id LIKE ? OR u.phone LIKE ?)";
    $likeKeyword    = '%' . $searchKeyword . '%';
    
    // นำคีย์เวิร์ดไปใส่เป็น parameter 3 ตัว
    $queryParams[] = $likeKeyword;
    $queryParams[] = $likeKeyword;
    $queryParams[] = $likeKeyword;
    $paramTypes   .= 'sss';
}

// ตรวจสอบว่ามีการเลือกกรองตามบทบาทหรือไม่
if ($filterRole !== '') {
    // เพิ่มเงื่อนไขกรอง role
    $whereClauses[] = "u.role = ?";
    $queryParams[]  = $filterRole;
    $paramTypes    .= 's';
}

// นำเงื่อนไขทั้งหมดมารวมเป็นคำสั่ง WHERE
if (count($whereClauses) > 0) {
    $whereSql = 'WHERE ' . implode(' AND ', $whereClauses);
} else {
    $whereSql = '';
}

// ส่วนที่ 3 ดึงข้อมูลสมาชิก
$membersQueryString = "
    SELECT u.user_id, u.name, u.student_id, u.phone, u.role, u.user_type, 
           u.committee_position, u.points, u.academic_year, u.generation, u.created_at,
           (SELECT COUNT(*) FROM user_activities WHERE user_id = u.user_id) as activity_count
    FROM users u 
    $whereSql 
    ORDER BY u.name ASC
";

$membersStatement = $conn->prepare($membersQueryString);

// ถ้ามีการส่ง parameter ค้นหา
if (!empty($queryParams)) {
    $membersStatement->bind_param($paramTypes, ...$queryParams);
}

$membersStatement->execute();
$membersResult = $membersStatement->get_result();


// ส่วนที่ 4 ดึงสถิติจำนวนสมาชิก
$statisticsData = [
    'total'     => $conn->query("SELECT COUNT(*) as c FROM users")->fetch_assoc()['c'],
    'students'  => $conn->query("SELECT COUNT(*) as c FROM users WHERE user_type = 'student'")->fetch_assoc()['c'],
    'committee' => $conn->query("SELECT COUNT(*) as c FROM users WHERE role IN ('committee')")->fetch_assoc()['c'],
    'advisors'  => $conn->query("SELECT COUNT(*) as c FROM users WHERE role = 'advisor'")->fetch_assoc()['c'],
];


// ส่วนที่ 5 เตรียมข้อมูล Labels ภาษาไทย
$roleLabelsThai = [
    'admin'     => 'ผู้ดูแลระบบ',
    'committee' => 'คณะกรรมการ',
    'advisor'   => 'อาจารย์ที่ปรึกษา',
    'member'    => 'สมาชิกทั่วไป'
];

$positionLabelsThai = [
    'president'          => 'ประธานชมรม',
    'vice_president'     => 'รองประธาน',
    'treasurer'          => 'เหรัญญิก',
    'secretary_saluang'  => 'เลขาสะลวง',
    'secretary_wiangbua' => 'เลขาเวียงบัว',
    'nurse'              => 'พยาบาล',
    'venue_coordinator'  => 'ฝ่ายสถานที่',
    'pr'                 => 'ฝ่ายประชาสัมพันธ์'
];


// ส่วนที่ 6 โหลด Header
include 'includes/header.php';
?>

<div class="container py-4">

    <h2 class="section-title mb-4">สมาชิกชมรม</h2>

<!-- ส่วนที่ 6.1: สถิติสมาชิก -->
    <div class="grid-4 mb-4">
        
        <div class="card text-center">
            <div class="text-sm text-muted font-weight-medium">สมาชิกทั้งหมด</div>
            <div class="text-xl font-weight-bold text-main"><?= number_format($statisticsData['total']) ?></div>
        </div>
        
        <div class="card text-center">
            <div class="text-sm text-muted font-weight-medium">นักศึกษา</div>
            <div class="text-xl font-weight-bold text-main"><?= number_format($statisticsData['students']) ?></div>
        </div>
        
        <div class="card text-center">
            <div class="text-sm text-muted font-weight-medium">คณะกรรมการ</div>
            <div class="text-xl font-weight-bold text-main"><?= number_format($statisticsData['committee']) ?></div>
        </div>
        
        <div class="card text-center">
            <div class="text-sm text-muted font-weight-medium">อาจารย์</div>
            <div class="text-xl font-weight-bold text-main"><?= number_format($statisticsData['advisors']) ?></div>
        </div>
        
    </div>

<!-- ส่วนที่ 6.2: ค้นหาและกรอง -->
    <div class="card mb-4">
        <form method="GET" action="advisor_members.php" class="d-flex align-end gap-3 flex-wrap">
            
            <div class="flex-grow-1">
                <label class="form-label">ค้นหาสมาชิก</label>
                <input type="text" 
                       name="search" 
                       value="<?= htmlspecialchars($searchKeyword) ?>" 
                       placeholder="ชื่อ, รหัสนักศึกษา, เบอร์โทร..." 
                       class="form-control">
            </div>
            
            <div style="min-width: 170px;">
                <label class="form-label">บทบาท</label>
                <select name="role" class="form-control">
                    <option value="">ทั้งหมด</option>
                    <option value="member" <?= $filterRole === 'member' ? 'selected' : '' ?>>สมาชิกทั่วไป</option>
                    <option value="committee" <?= $filterRole === 'committee' ? 'selected' : '' ?>>คณะกรรมการ</option>
                    <option value="advisor" <?= $filterRole === 'advisor' ? 'selected' : '' ?>>อาจารย์ที่ปรึกษา</option>
                </select>
            </div>
            
            <button type="submit" class="btn btn-primary">
                ค้นหา
            </button>
            
            <?php if ($searchKeyword !== '' || $filterRole !== ''): ?>
                <a href="advisor_members.php" class="btn btn-surface">
                    ล้าง
                </a>
            <?php endif; ?>
            
        </form>
    </div>

<!-- ส่วนที่ 6.3: ตารางรายชื่อสมาชิก -->
    <div class="card">
        <div class="table-responsive">
            <table class="table w-100 text-sm">
                <thead class="bg-main text-muted">
                    <tr>
                        <th class="p-2 text-left">สมาชิก</th>
                        <th class="p-2 text-left">รหัสนักศึกษา</th>
                        <th class="p-2 text-left">เบอร์โทร</th>
                        <th class="p-2 text-center">บทบาท</th>
                        <th class="p-2 text-center">ตำแหน่ง</th>
                        <th class="p-2 text-center">กิจกรรม</th>
                        <th class="p-2 text-center">แต้ม</th>
                        <th class="p-2 text-center">ดูข้อมูล</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($membersResult->num_rows > 0): ?>
                        <?php while ($memberRow = $membersResult->fetch_assoc()): ?>
                            <tr>
                                
                                <td class="p-2">
                                    <div class="d-flex align-center gap-2">
                                        <div style="width: 32px; height: 32px; background: #9E9E9E; color: white; border-radius: 50%;
                                                    display: flex; align-items: center; justify-content: center; font-size: 14px;
                                                    font-weight: bold; flex-shrink: 0;">
                                            <?php
                                            $cleanedName = preg_replace('/[^a-zA-Zก-๙]/u', '', $memberRow['name']);
                                            $firstLetter = mb_substr($cleanedName, 0, 1, 'UTF-8');
                                            echo $firstLetter ? mb_strtoupper($firstLetter, 'UTF-8') : 'U';
                                            ?>
                                        </div>
                                        <span class="font-weight-medium text-main">
                                            <?= htmlspecialchars($memberRow['name']) ?>
                                        </span>
                                    </div>
                                </td>
                                
                                <td class="p-2 text-muted">
                                    <?= htmlspecialchars($memberRow['student_id'] ?: '-') ?>
                                </td>
                                
                                <td class="p-2 text-muted">
                                    <?= htmlspecialchars($memberRow['phone'] ?: '-') ?>
                                </td>
                                
                                <td class="p-2 text-center">
                                    <?php
                                    $roleBadgeClass = 'badge-primary';
                                    if ($memberRow['role'] === 'admin') {
                                        $roleBadgeClass = 'badge-danger';
                                    } elseif ($memberRow['role'] === 'committee') {
                                        $roleBadgeClass = 'badge-warning';
                                    } elseif ($memberRow['role'] === 'advisor') {
                                        $roleBadgeClass = 'badge-success';
                                    }
                                    
                                    $roleDisplayName = isset($roleLabelsThai[$memberRow['role']]) 
                                        ? $roleLabelsThai[$memberRow['role']] 
                                        : $memberRow['role'];
                                    ?>
                                    <span class="badge <?= $roleBadgeClass ?> text-xs">
                                        <?= $roleDisplayName ?>
                                    </span>
                                </td>
                                
                                <td class="p-2 text-center text-muted text-xs">
                                    <?php
                                    if (!empty($memberRow['committee_position'])) {
                                        $positionKey = $memberRow['committee_position'];
                                        echo isset($positionLabelsThai[$positionKey]) 
                                            ? $positionLabelsThai[$positionKey] 
                                            : $positionKey;
                                    } else {
                                        echo '-';
                                    }
                                    ?>
                                </td>
                                
                                <td class="p-2 text-center text-muted">
                                    <?= number_format($memberRow['activity_count']) ?>
                                </td>
                                
                                <td class="p-2 text-center font-weight-bold text-main" style="color: #000000;">
                                    <?= number_format($memberRow['points']) ?>
                                </td>
                                
                                <td class="p-2 text-center">
                                    <a href="profile.php?id=<?= $memberRow['user_id'] ?>" 
                                       class="btn btn-surface text-xs" 
                                       style="padding: 4px 10px;">
                                        โปรไฟล์
                                    </a>
                                </td>
                                
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="text-center p-4 text-muted">
                                ไม่พบข้อมูลสมาชิก
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<?php include 'includes/footer.php'; ?>
