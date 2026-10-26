<?php
// profile.php
// ระบบโปรไฟล์ผู้ใช้งานแบบรวมศูนย์ (Unified Profile System)
// รองรับทั้ง:
// 1. ดูโปรไฟล์ของตนเอง (เมื่อไม่ส่ง ?id= หรือส่ง id ตัวเอง) พร้อมปุ่มแก้ไขและประวัติกิจกรรม
// 2. ดูโปรไฟล์ของสมาชิกคนอื่น (เมื่อส่ง ?id=...) โดยปรับสิทธิ์การแสดงผลตามบทบาทของผู้เข้าชม

require_once 'config/database.php';

// ตรวจสอบการเข้าสู่ระบบ
if (!isLoggedIn()) {
    header("Location: login.php");
    exit;
}

$currentUserId = $_SESSION['user_id'];
$viewerRole    = getCurrentUserRole();

// ตรวจสอบว่าต้องการดูโปรไฟล์ใคร
$targetUserId = isset($_GET['id']) ? (int)$_GET['id'] : $currentUserId;
if ($targetUserId <= 0) {
    $targetUserId = $currentUserId;
}

$isMyProfile = ($targetUserId === $currentUserId);

// ดึงข้อมูลผู้ใช้งานจากฐานข้อมูล
$userStatement = $conn->prepare("
    SELECT u.*, f.faculty_name, m.major_name 
    FROM users u 
    LEFT JOIN faculties f ON u.faculty_id = f.faculty_id 
    LEFT JOIN majors m ON u.major_id = m.major_id 
    WHERE u.user_id = ?
");
$userStatement->bind_param("i", $targetUserId);
$userStatement->execute();
$userRecord = $userStatement->get_result()->fetch_assoc();
$userStatement->close();

if (!$userRecord) {
    if (isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] === 99999) {
        $userRecord = [
            'user_id'            => 99999,
            'name'               => $_SESSION['name'] ?? 'ผู้ทดสอบระบบ',
            'student_id'         => '6512345678',
            'role'               => $_SESSION['role'] ?? 'member',
            'user_type'          => $_SESSION['user_type'] ?? 'student',
            'committee_position' => $_SESSION['committee_position'] ?? '',
            'faculty_name'       => 'คณะวิทยาศาสตร์และเทคโนโลยี',
            'major_name'         => 'สาขาวิชาวิทยาการคอมพิวเตอร์',
            'phone'              => '081-234-5678',
            'points'             => 120,
            'image'              => '',
            'location'           => 'เชียงใหม่',
            'academic_year'      => 3
        ];
    } else {
        $_SESSION['alert'] = [
            'type'  => 'error',
            'title' => 'ไม่พบข้อมูล',
            'text'  => 'ไม่พบข้อมูลผู้ใช้งานที่ระบุในระบบ',
        ];
        header("Location: index.php");
        exit;
    }
}

// ตรวจสอบสิทธิ์การดูข้อมูลเชิงลึก
// ถ้าเป็นโปรไฟล์ตัวเอง หรือเป็น Admin, Advisor, Committee จะสามารถดูประวัติกิจกรรมได้ทั้งหมด
$allowedToViewFull = ['admin', 'committee', 'advisor'];
$canViewFullDetails = $isMyProfile || in_array($viewerRole, $allowedToViewFull, true);

// ดึงประวัติการเข้าร่วมกิจกรรม
$activityHistory = [];
if ($canViewFullDetails) {
    $actStmt = $conn->prepare("
        SELECT a.activity_id, a.title, a.start_date, a.location_name, a.points, ua.status, ua.check_in_time
        FROM user_activities ua
        JOIN activities a ON ua.activity_id = a.activity_id
        WHERE ua.user_id = ?
        ORDER BY a.start_date DESC
    ");
    $actStmt->bind_param("i", $targetUserId);
    $actStmt->execute();
    $activityHistory = $actStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $actStmt->close();
}

$totalActivityCount = count($activityHistory);

// ป้ายกำกับบทบาทภาษาไทย
$roleLabelsThai = [
    'admin'     => 'ผู้ดูแลระบบ',
    'committee' => 'คณะกรรมการ',
    'advisor'   => 'อาจารย์ที่ปรึกษา',
    'member'    => 'สมาชิกทั่วไป',
];

$positionLabelsThai = [
    'president'          => 'ประธานชมรม',
    'vice_president'     => 'รองประธานชมรม',
    'treasurer'          => 'เหรัญญิก',
    'secretary_saluang'  => 'เลขาสะลวง',
    'secretary_wiangbua' => 'เลขาเวียงบัว',
    'nurse'              => 'พยาบาล',
    'venue_coordinator'  => 'ฝ่ายสถานที่',
    'pr'                 => 'ฝ่ายประชาสัมพันธ์',
];

$statusLabelsThai = [
    'registered'  => 'ลงทะเบียน',
    'checked_in'  => 'เช็คอินแล้ว',
    'checked_out' => 'เข้าร่วมแล้ว',
    'cancelled'   => 'ยกเลิก',
];

// ชื่อคณะและสาขา (ดึงจาก Master Data หรือฟิลด์ข้อความเดิม)
$displayFaculty = !empty($userRecord['faculty_name']) ? $userRecord['faculty_name'] : (!empty($userRecord['faculty']) ? $userRecord['faculty'] : '-');
$displayMajor   = !empty($userRecord['major_name']) ? $userRecord['major_name'] : (!empty($userRecord['major']) ? $userRecord['major'] : '-');

include 'includes/header.php';
?>

<!-- Flash Alert -->
<?php if (isset($_SESSION['alert'])): ?>
    <div id="flash-message"
         data-type="<?= $_SESSION['alert']['type'] ?>"
         data-title="<?= $_SESSION['alert']['title'] ?>"
         data-text="<?= $_SESSION['alert']['text'] ?>"
         class="d-none">
    </div>
    <?php unset($_SESSION['alert']); ?>
<?php endif; ?>

<div class="container py-4" style="max-width: 850px;">

    <!-- ปุ่มย้อนกลับ (กรณีดูโปรไฟล์สมาชิกคนอื่น) -->
    <?php if (!$isMyProfile): ?>
        <div class="mb-3">
            <a href="javascript:history.back()" class="btn btn-surface text-sm" style="border: 1px solid var(--border-color); color: #000; text-decoration: none;">
                <i class="fa-solid fa-arrow-left mr-1"></i> ย้อนกลับ
            </a>
        </div>
    <?php endif; ?>

    <!-- การ์ดส่วนหัวโปรไฟล์ -->
    <div class="card p-4 mb-4" style="box-shadow: none; border: 1px solid var(--border-color);">
        <div class="d-flex align-center gap-4 flex-wrap">

            <!-- รูปภาพโปรไฟล์ -->
            <div class="rounded-circle d-flex justify-center align-center shadow-sm"
                 style="width: 90px; height: 90px; font-size: 36px; flex-shrink: 0; background: #e2e8f0; color: #475569; overflow: hidden;">
                <?php if (!empty($userRecord['image'])): ?>
                    <img src="<?= htmlspecialchars($userRecord['image']) ?>"
                         style="width: 100%; height: 100%; object-fit: cover;"
                         alt="รูปโปรไฟล์">
                <?php else: ?>
                    <i class="fa-solid fa-user"></i>
                <?php endif; ?>
            </div>

            <!-- ข้อมูลชื่อ และตำแหน่ง -->
            <div class="flex-grow-1">
                <div class="d-flex align-center gap-2 flex-wrap">
                    <h2 class="m-0 text-main" style="font-size: 22px; font-weight: 700;">
                        <?= htmlspecialchars($userRecord['name']) ?>
                    </h2>
                    <?php if ($isMyProfile): ?>
                        <span class="badge" style="background: #e0f2fe; color: #0369a1; padding: 2px 8px; font-size: 11px;">
                            (บัญชีของคุณ)
                        </span>
                    <?php endif; ?>
                </div>

                <!-- รหัสนักศึกษา (ถ้ามี) -->
                <?php if (!empty($userRecord['student_id'])): ?>
                    <div class="text-sm text-muted mt-1">
                        รหัสนักศึกษา: <strong><?= htmlspecialchars($userRecord['student_id']) ?></strong>
                        <?php if (!empty($userRecord['academic_year'])): ?>
                            &bull; ชั้นปีที่ <?= (int)$userRecord['academic_year'] ?>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <!-- ป้ายบทบาท -->
                <div class="d-flex gap-2 align-center mt-2 flex-wrap">
                    <?php
                    $roleKey = $userRecord['role'] ?? 'member';
                    $roleBadgeStyle = 'background: #f1f5f9; color: #475569;';
                    if ($roleKey === 'admin') $roleBadgeStyle = 'background: #fee2e2; color: #b91c1c;';
                    elseif ($roleKey === 'advisor') $roleBadgeStyle = 'background: #ede9fe; color: #6d28d9;';
                    elseif ($roleKey === 'committee') $roleBadgeStyle = 'background: #dcfce7; color: #15803d;';
                    ?>
                    <span class="badge" style="<?= $roleBadgeStyle ?> padding: 4px 10px; font-size: 12px; font-weight: 600;">
                        <?= $roleLabelsThai[$roleKey] ?? $roleKey ?>
                    </span>

                    <?php if (!empty($userRecord['committee_position'])): ?>
                        <span class="text-xs text-muted" style="font-weight: 500;">
                            (<?= $positionLabelsThai[$userRecord['committee_position']] ?? $userRecord['committee_position'] ?>)
                        </span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ปุ่มแก้ไขข้อมูล (เฉพาะโปรไฟล์ตัวเอง) -->
            <?php if ($isMyProfile): ?>
                <div class="text-right">
                    <a href="edit_profile.php" class="btn btn-surface text-sm" style="border: 1px solid var(--border-color); color: #0f172a; text-decoration: none;">
                        <i class="fa-solid fa-pen mr-1"></i> แก้ไขข้อมูล
                    </a>
                </div>
            <?php endif; ?>

        </div>
    </div>

    <!-- การ์ดข้อมูลส่วนตัว -->
    <div class="card p-4 mb-4" style="box-shadow: none; border: 1px solid var(--border-color);">
        <h3 class="text-md m-0 mb-3 pb-2 border-bottom" style="font-weight: 600;">
            ข้อมูลทั่วไปและข้อมูลติดต่อ
        </h3>

        <div class="grid-2" style="gap: 16px;">
            <div class="d-flex align-center gap-2 flex-wrap">
                <span class="text-sm text-muted">คณะที่สังกัด</span>
                <span class="text-sm text-main"><?= htmlspecialchars($displayFaculty) ?></span>
            </div>

            <div class="d-flex align-center gap-2 flex-wrap">
                <span class="text-sm text-muted">สาขาวิชา</span>
                <span class="text-sm text-main"><?= htmlspecialchars($displayMajor) ?></span>
            </div>

            <div class="d-flex align-center gap-2 flex-wrap">
                <span class="text-sm text-muted">เบอร์โทรศัพท์</span>
                <span class="text-sm text-main">
                    <?= !empty($userRecord['phone']) ? htmlspecialchars($userRecord['phone']) : 'ไม่ได้ระบุเบอร์โทร' ?>
                </span>
            </div>

            <div class="d-flex align-center gap-2 flex-wrap">
                <span class="text-sm text-muted">คะแนนจิตอาสาสะสม</span>
                <span class="text-sm" style="color: var(--color-primary);">
                    <i class="fa-solid fa-star text-warning mr-1"></i> <?= number_format($userRecord['points'] ?? 0) ?> คะแนน
                </span>
            </div>

            <?php if (!empty($userRecord['location'])): ?>
                <div class="d-flex align-center gap-2 flex-wrap" style="grid-column: 1 / -1;">
                    <span class="text-sm text-muted">ที่อยู่ / ข้อมูลติดต่อเพิ่มเติม</span>
                    <span class="text-sm text-main"><?= htmlspecialchars($userRecord['location']) ?></span>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- การ์ดประวัติการเข้าร่วมกิจกรรม -->
    <?php if ($canViewFullDetails): ?>
        <div class="card p-4" style="box-shadow: none; border: 1px solid var(--border-color);">
            <div class="d-flex justify-between align-center mb-3 pb-2 border-bottom flex-wrap gap-2">
                <h3 class="text-md m-0" style="font-weight: 600;">
                    ประวัติการเข้าร่วมกิจกรรม
                </h3>
                <span class="text-sm text-muted">
                    เข้าร่วมทั้งหมด <strong><?= $totalActivityCount ?></strong> กิจกรรม
                </span>
            </div>

            <?php if ($totalActivityCount > 0): ?>
                <div class="table-responsive">
                    <table class="table w-100 text-sm">
                        <thead class="bg-main text-muted">
                            <tr>
                                <th class="p-2 text-left">ชื่อกิจกรรม</th>
                                <th class="p-2 text-center" style="width: 120px;">วันที่จัด</th>
                                <th class="p-2 text-center" style="width: 110px;">สถานะ</th>
                                <th class="p-2 text-right" style="width: 90px;">แต้มที่ได้</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($activityHistory as $act): ?>
                                <tr class="border-bottom">
                                    <td class="p-2">
                                        <div class="font-weight-medium text-main">
                                            <a href="activity_details.php?id=<?= $act['activity_id'] ?>" style="color: inherit; text-decoration: none;">
                                                <?= htmlspecialchars($act['title']) ?>
                                            </a>
                                        </div>
                                        <div class="text-xs text-muted mt-1">
                                            <?= htmlspecialchars($act['location_name'] ?: 'ไม่ระบุสถานที่') ?>
                                        </div>
                                    </td>
                                    <td class="p-2 text-center text-muted text-xs">
                                        <?= date('d/m/Y', strtotime($act['start_date'])) ?>
                                    </td>
                                    <td class="p-2 text-center">
                                        <?php if ($act['status'] === 'checked_in' || $act['status'] === 'checked_out'): ?>
                                            <span class="badge" style="background: #dcfce7; color: #166534; padding: 3px 8px; font-size: 11px;">
                                                เข้าร่วมแล้ว
                                            </span>
                                        <?php elseif ($act['status'] === 'registered'): ?>
                                            <span class="badge" style="background: #f1f5f9; color: #64748b; padding: 3px 8px; font-size: 11px;">
                                                ลงทะเบียน
                                            </span>
                                        <?php else: ?>
                                            <span class="badge" style="background: #fee2e2; color: #b91c1c; padding: 3px 8px; font-size: 11px;">
                                                <?= $statusLabelsThai[$act['status']] ?? $act['status'] ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="p-2 text-right font-weight-bold" style="color: var(--color-primary);">
                                        +<?= (int)$act['points'] ?> แต้ม
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-4 text-muted text-sm">
                    ยังไม่มีประวัติการเข้าร่วมกิจกรรม
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

</div>

<?php include 'includes/footer.php'; ?>