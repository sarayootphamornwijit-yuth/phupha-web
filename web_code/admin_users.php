<?php
// admin_users.php
// ไฟล์นี้ทำหน้าที่จัดการสมาชิกในระบบสำหรับ Admin
// ฟีเจอร์: เปลี่ยน Role, กำหนดตำแหน่ง, กำหนดชั้นปี, ค้นหา/กรอง, ลบสมาชิก
// แสดงผลตารางสมาชิกทั้งหมดรวมในตารางเดียว สะดวกต่อการจัดการ

require_once 'config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();
}


// ส่วนที่ 1 ตรวจสอบสิทธิ์ (เฉพาะผู้ดูแลระบบ)
$currentUserRole = getCurrentUserRole();
if (!isLoggedIn()) {
    header("Location: index.php");
    exit;
}

// หากเป็นอาจารย์ที่ปรึกษา ให้ส่งไปยังหน้าดูข้อมูลสมาชิกของอาจารย์
if ($currentUserRole === 'advisor') {
    header("Location: advisor_members.php");
    exit;
}

// อนุญาตเฉพาะแอดมินเท่านั้น
if ($currentUserRole !== 'admin') {
    header("Location: index.php");
    exit;
}

$adminUserId = $_SESSION['user_id'];


// ส่วนที่ 2 อัปเดตข้อมูลสมาชิก (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['action'])
    && $_POST['action'] === 'update_member'
) {
    // สร้างตัวแปรรับค่าจากฟอร์ม
    $targetUserId    = (int)($_POST['user_id'] ?? 0);
    $newRoleValue    = trim($_POST['role'] ?? '');
    $positionValue   = trim($_POST['committee_position'] ?? '');
    $academicYearVal = (int)($_POST['academic_year'] ?? 0);

    // ตรวจสอบไอดีเป้าหมายกับไอดีผู้ใช้ปัจจุบันเพื่อป้องกันการแก้ไขสิทธิ์ตัวเอง
    if ($targetUserId === $adminUserId) {
        // สร้าง session แจ้งเตือนข้อผิดพลาด
        $_SESSION['alert'] = [
            'type'  => 'error',
            'title' => 'ปฏิเสธการทำรายการ',
            'text'  => 'คุณไม่สามารถเปลี่ยนสิทธิ์ของตนเองได้',
        ];

    } else {
        // สร้างอาร์เรย์เก็บข้อมูล role ที่อนุญาตให้เปลี่ยนได้
        $allowedRoles    = ['member', 'committee', 'admin', 'advisor'];
        $isRoleAllowed   = in_array($newRoleValue, $allowedRoles);

        // ตรวจสอบค่า role ว่าอยู่ในรายการที่อนุญาตหรือไม่
        if ($isRoleAllowed) {
            // ตรวจสอบค่าว่างและแปลงเป็น null เพื่อบันทึกลงฐานข้อมูล
            $positionForDatabase  = !empty($positionValue) ? $positionValue : null;
            $yearForDatabase      = $academicYearVal > 0 ? $academicYearVal : null;

            // สร้างคำสั่ง SQL สำหรับอัปเดตข้อมูลสมาชิก
            $updateMemberStatement = $conn->prepare(
                "UPDATE users SET role = ?, committee_position = ?, academic_year = ? WHERE user_id = ?"
            );
            // นำตัวแปรมาผูกกับคำสั่ง SQL
            $updateMemberStatement->bind_param("ssii", $newRoleValue, $positionForDatabase, $yearForDatabase, $targetUserId);

            // ทำการ execute คำสั่ง SQL
            if ($updateMemberStatement->execute()) {
                // สร้าง session แจ้งเตือนความสำเร็จ
                $_SESSION['alert'] = [
                    'type'  => 'success',
                    'title' => 'อัปเดตสำเร็จ',
                    'text'  => 'บันทึกข้อมูลสิทธิ์ ตำแหน่ง และชั้นปีเรียบร้อยแล้ว',
                ];
            }

            $updateMemberStatement->close();
        }
    }

    // สร้าง URL ปลายทางโดยรักษาค่าค้นหาและกรองบทบาทเดิม
    $redirectUrl = 'admin_users.php';
    $redirectParams = [];
    $searchRedirect = trim($_POST['redirect_search'] ?? '');
    $roleRedirect   = trim($_POST['redirect_role'] ?? '');

    if ($searchRedirect !== '') {
        $redirectParams['search'] = $searchRedirect;
    }
    if ($roleRedirect !== '') {
        $redirectParams['role'] = $roleRedirect;
    }
    if (!empty($redirectParams)) {
        $redirectUrl .= '?' . http_build_query($redirectParams);
    }

    header("Location: " . $redirectUrl);
    exit;
}


// ส่วนที่ 3 ลบสมาชิก (GET)
// ส่วนที่ 3 จัดการการลบสมาชิก (เปลี่ยนเป็น POST เพื่อป้องกัน CSRF)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_user' && isset($_POST['delete_id'])) {
    // สร้างตัวแปรรับค่าไอดีเป้าหมาย
    $deleteTargetId = (int)$_POST['delete_id'];

    // ตรวจสอบไอดีเพื่อป้องกันการลบตัวเอง
    if ($deleteTargetId === $adminUserId) {
        $_SESSION['alert'] = [
            'type'  => 'error',
            'title' => 'ปฏิเสธการทำรายการ',
            'text'  => 'คุณไม่สามารถลบบัญชีของตนเองได้',
        ];

    } else {
        // เริ่ม Transaction เพื่อความปลอดภัยของข้อมูล
        $conn->begin_transaction();
        try {
            // ลบข้อมูลที่เกี่ยวข้องในตารางลูกก่อน เพื่อป้องกันข้อผิดพลาด Foreign Key Constraint
            $childTables = ['user_activities', 'certificates', 'evaluations'];
            foreach ($childTables as $childTable) {
                $checkTable = $conn->query("SHOW TABLES LIKE '$childTable'");
                if ($checkTable && $checkTable->num_rows > 0) {
                    $delStmt = $conn->prepare("DELETE FROM `$childTable` WHERE user_id = ?");
                    if ($delStmt) {
                        $delStmt->bind_param("i", $deleteTargetId);
                        $delStmt->execute();
                        $delStmt->close();
                    }
                }
            }

            // ลบผู้ใช้งานจากตารางหลัก users
            $deleteUserStatement = $conn->prepare("DELETE FROM users WHERE user_id = ?");
            $deleteUserStatement->bind_param("i", $deleteTargetId);
            $deleteUserStatement->execute();
            $deleteUserStatement->close();

            $conn->commit();

            $_SESSION['alert'] = [
                'type'  => 'success',
                'title' => 'ลบสำเร็จ',
                'text'  => 'ลบผู้ใช้งานและข้อมูลที่เกี่ยวข้องเรียบร้อยแล้ว',
            ];
        } catch (\Throwable $e) {
            $conn->rollback();
            $_SESSION['alert'] = [
                'type'  => 'error',
                'title' => 'เกิดข้อผิดพลาดในการลบ',
                'text'  => 'ไม่สามารถลบผู้ใช้งานได้: ' . $e->getMessage(),
            ];
        }
    }

    // สร้าง URL ปลายทางโดยรักษาค่าค้นหาและกรองบทบาทเดิม
    $redirectUrl = 'admin_users.php';
    $redirectParams = [];
    $searchRedirect = trim($_POST['search'] ?? '');
    $roleRedirect   = trim($_POST['role'] ?? '');

    if ($searchRedirect !== '') {
        $redirectParams['search'] = $searchRedirect;
    }
    if ($roleRedirect !== '') {
        $redirectParams['role'] = $roleRedirect;
    }
    if (!empty($redirectParams)) {
        $redirectUrl .= '?' . http_build_query($redirectParams);
    }

    header("Location: " . $redirectUrl);
    exit;
}


// ส่วนที่ 4 รับค่าค้นหาและกรองข้อมูล
$searchKeyword = trim($_GET['search'] ?? '');
$filterRole    = trim($_GET['role'] ?? '');

$whereClauses = [];
$queryParams  = [];
$paramTypes   = '';

if ($searchKeyword !== '') {
    $whereClauses[] = "(name LIKE ? OR student_id LIKE ? OR phone LIKE ? OR username LIKE ?)";
    $likeSearch     = '%' . $searchKeyword . '%';
    $queryParams[]  = $likeSearch;
    $queryParams[]  = $likeSearch;
    $queryParams[]  = $likeSearch;
    $queryParams[]  = $likeSearch;
    $paramTypes    .= 'ssss';
}

if ($filterRole !== '') {
    $whereClauses[] = "role = ?";
    $queryParams[]  = $filterRole;
    $paramTypes    .= 's';
}

$whereSql = !empty($whereClauses) ? 'WHERE ' . implode(' AND ', $whereClauses) : '';
$usersSql = "SELECT * FROM users $whereSql ORDER BY created_at DESC";
$usersStmt = $conn->prepare($usersSql);
if (!empty($queryParams)) {
    $usersStmt->bind_param($paramTypes, ...$queryParams);
}
$usersStmt->execute();
$usersResult = $usersStmt->get_result();


// ส่วนที่ 5 กำหนด Labels สำหรับ Dropdown
// แปลง Role key เป็นชื่อภาษาไทย
$roleLabelsThai = [
    'member'    => 'สมาชิก',
    'committee' => 'คณะกรรมการ',
    'admin'     => 'แอดมิน',
    'advisor'   => 'อาจารย์',
];

// แปลงตำแหน่ง key เป็นชื่อภาษาไทย
$positionLabelsThai = [
    'president'          => 'ประธาน',
    'vice_president'     => 'รองประธาน',
    'treasurer'          => 'เหรัญญิก',
    'secretary_saluang'  => 'เลขาสะลวง',
    'secretary_wiangbua' => 'เลขาเวียงบัว',
    'nurse'              => 'พยาบาล',
    'venue_coordinator'  => 'ฝ่ายสถานที่',
    'pr'                 => 'ฝ่ายประชาสัมพันธ์',
];


// ส่วนที่ 6 โหลด Header
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

<style>
.tab-btn, .btn, .btn-icon, .form-control, .text-link {
    transition: none !important;
    transform: none !important;
    box-shadow: none !important;
    text-decoration: none !important;
}
.tab-btn:hover, .btn:hover, .btn-icon:hover {
    transition: none !important;
    transform: none !important;
    box-shadow: none !important;
}
.text-link, .text-link:hover, .text-link::after, .tab-btn::after {
    border-bottom: none !important;
}
</style>

<div class="container py-4">

    <h2 class="section-title mb-4">จัดการสมาชิกในระบบ</h2>

    <!-- ส่วนค้นหาและกรองข้อมูล (เหมือนเมนูดูสมาชิกของคณะกรรมการ) -->
    <div class="card mb-4" style="box-shadow: none; border: 1px solid var(--border-color);">
        <form method="GET" action="admin_users.php" class="d-flex align-end gap-3 flex-wrap">
            
            <div class="flex-grow-1">
                <label class="form-label" style="color: #000; font-weight: 500;">ค้นหาสมาชิก</label>
                <input type="text" 
                       name="search" 
                       value="<?= htmlspecialchars($searchKeyword) ?>" 
                       placeholder="ชื่อ, รหัสนักศึกษา, เบอร์โทร..." 
                       class="form-control"
                       style="box-shadow: none;">
            </div>
            
            <div style="min-width: 170px;">
                <label class="form-label" style="color: #000; font-weight: 500;">บทบาท</label>
                <select name="role" class="form-control" style="box-shadow: none;">
                    <option value="">ทั้งหมด</option>
                    <option value="member" <?= $filterRole === 'member' ? 'selected' : '' ?>>สมาชิกทั่วไป</option>
                    <option value="committee" <?= $filterRole === 'committee' ? 'selected' : '' ?>>คณะกรรมการ</option>
                    <option value="advisor" <?= $filterRole === 'advisor' ? 'selected' : '' ?>>อาจารย์ที่ปรึกษา</option>
                    <option value="admin" <?= $filterRole === 'admin' ? 'selected' : '' ?>>ผู้ดูแลระบบ</option>
                </select>
            </div>
            
            <button type="submit" class="btn btn-primary" style="box-shadow: none !important; transition: none !important;">
                ค้นหา
            </button>
            
            <?php if ($searchKeyword !== '' || $filterRole !== ''): ?>
                <a href="admin_users.php" class="btn btn-surface" style="box-shadow: none !important; transition: none !important; border: 1px solid var(--border-color); color: #000;">
                    ล้าง
                </a>
            <?php endif; ?>
            
        </form>
    </div>

    <!-- ตารางสมาชิกทั้งหมดในระบบ -->
    <div class="card" style="box-shadow: none; border: 1px solid var(--border-color);">
        <div class="table-responsive">
            <table class="table w-100 text-sm">
                <thead class="bg-main text-muted">
                    <tr>
                        <th class="p-2 text-left">รหัส นศ. / ชื่อ-สกุล</th>
                        <th class="p-2 text-left">คณะ / สาขา</th>
                        <th class="p-2 text-left">ข้อมูลติดต่อ</th>
                        <th class="p-2 text-center">แต้ม</th>
                        <th class="p-2 text-center">ระดับสิทธิ์</th>
                        <th class="p-2 text-center">ตำแหน่ง / ชั้นปี</th>
                        <th class="p-2 text-center">จัดการ</th>
                    </tr>
                </thead>
                <tbody>

                    <?php while ($userRow = $usersResult->fetch_assoc()): ?>
                        <tr class="border-bottom">

                            <!-- รหัสนักศึกษา / Username และชื่อ -->
                            <td class="p-2">
                                <strong class="text-main">
                                    <?= htmlspecialchars($userRow['student_id'] ?: $userRow['username']) ?>
                                </strong><br>
                                <span class="text-xs text-muted"><?= htmlspecialchars($userRow['name']) ?></span>
                            </td>

                            <!-- คณะและสาขา หรือ อาชีพ -->
                            <td class="p-2 text-xs">
                                <?php if ($userRow['user_type'] === 'student'): ?>
                                    <strong class="text-primary"><?= htmlspecialchars($userRow['faculty'] ?: '-') ?></strong><br>
                                    <span class="text-muted"><?= htmlspecialchars($userRow['major'] ?: '-') ?></span>
                                <?php else: ?>
                                    <strong class="text-muted">บุคคลทั่วไป</strong><br>
                                    <span class="text-muted"><?= htmlspecialchars($userRow['occupation'] ?: '-') ?></span>
                                <?php endif; ?>
                            </td>

                            <!-- ข้อมูลติดต่อ -->
                            <td class="p-2 text-xs text-muted">
                                <?php
                                $contactInfoLines = [];

                                if (!empty($userRow['phone'])) {
                                    $contactInfoLines[] = htmlspecialchars($userRow['phone']);
                                }

                                if (!empty($userRow['location'])) {
                                    $contactInfoLines[] = 'สถานที่: ' . htmlspecialchars($userRow['location']);
                                }

                                if (!empty($contactInfoLines)) {
                                    echo implode('<br>', $contactInfoLines);
                                } else {
                                    echo '-';
                                }
                                ?>
                            </td>

                            <!-- แต้มสะสม -->
                            <td class="p-2 text-center font-weight-bold text-warning">
                                <?= $userRow['points'] ?>
                            </td>

                            <!-- Dropdown เปลี่ยน Role (อยู่ใน Form เดียวกับปุ่ม Save) -->
                            <td class="p-2 text-center">
                                <form action="admin_users.php" method="POST" class="form-update-member" style="min-width: 100px;">
                                    <?php csrfField(); ?>
                                    <input type="hidden" name="action" value="update_member">
                                    <input type="hidden" name="user_id" value="<?= $userRow['user_id'] ?>">
                                    <input type="hidden" name="redirect_search" value="<?= htmlspecialchars($searchKeyword) ?>">
                                    <input type="hidden" name="redirect_role" value="<?= htmlspecialchars($filterRole) ?>">

                                    <!-- Dropdown Role -->
                                    <?php
                                    $isCurrentAdmin = ($userRow['user_id'] === $adminUserId);
                                    $disabledAttr   = $isCurrentAdmin ? 'disabled' : '';
                                    ?>
                                    <select name="role" class="form-control text-xs p-1" style="width: 110px; height: auto;" <?= $disabledAttr ?>>
                                        <?php foreach ($roleLabelsThai as $roleKey => $roleLabel): ?>
                                            <?php $isSelected = ($userRow['role'] === $roleKey) ? 'selected' : ''; ?>
                                            <option value="<?= $roleKey ?>" <?= $isSelected ?>><?= $roleLabel ?></option>
                                        <?php endforeach; ?>
                                    </select>
                            </td>

                            <!-- Dropdown ตำแหน่งและชั้นปี -->
                            <td class="p-2 text-center">
                                    <div class="d-flex flex-column gap-1" style="min-width: 130px;">

                                        <!-- Dropdown ตำแหน่งคณะกรรมการ -->
                                        <select name="committee_position" class="form-control text-xs p-1" style="height: auto;" <?= $disabledAttr ?>>
                                            <option value="">ไม่มีตำแหน่ง</option>
                                            <?php foreach ($positionLabelsThai as $posKey => $posLabel): ?>
                                                <?php
                                                $currentPosition = $userRow['committee_position'] ?? '';
                                                $isSelectedPos   = ($currentPosition === $posKey) ? 'selected' : '';
                                                ?>
                                                <option value="<?= $posKey ?>" <?= $isSelectedPos ?>><?= $posLabel ?></option>
                                            <?php endforeach; ?>
                                        </select>

                                        <!-- Dropdown ชั้นปี (เฉพาะนักศึกษา) -->
                                        <?php if ($userRow['user_type'] === 'student'): ?>
                                            <select name="academic_year" class="form-control text-xs p-1" style="height: auto;" title="ชั้นปี" <?= $disabledAttr ?>>
                                                <option value="0">ชั้นปี</option>
                                                <?php for ($yearNum = 1; $yearNum <= 4; $yearNum++): ?>
                                                    <?php
                                                    $currentYear     = $userRow['academic_year'] ?? 0;
                                                    $isSelectedYear  = ($currentYear == $yearNum) ? 'selected' : '';
                                                    ?>
                                                    <option value="<?= $yearNum ?>" <?= $isSelectedYear ?>>ปี <?= $yearNum ?></option>
                                                <?php endfor; ?>
                                            </select>
                                        <?php else: ?>
                                            <input type="hidden" name="academic_year" value="0">
                                        <?php endif; ?>

                                    </div>
                            </td>

                            <!-- ปุ่ม Save และ Delete -->
                            <td class="p-2 text-center">
                                <div class="d-flex gap-1 justify-center">

                                    <?php if ($userRow['user_id'] !== $adminUserId): ?>
                                        <!-- ปุ่มบันทึก -->
                                        <button type="submit"
                                                class="btn-icon text-white"
                                                style="background: #43A047; border-radius: 6px; width: 32px; height: 32px; display: inline-flex; align-items: center; justify-content: center;"
                                                title="บันทึก">
                                            <i class="fa-solid fa-floppy-disk"></i>
                                        </button>
                                    </form>

                                        <!-- ปุ่มลบ (ใช้ฟอร์ม POST ป้องกัน CSRF) -->
                                        <form method="POST" action="admin_users.php" class="m-0 p-0 d-inline" onsubmit="return confirm('ลบผู้ใช้งาน? ยืนยันการลบข้อมูลหรือไม่?');">
                                            <?php csrfField(); ?>
                                            <input type="hidden" name="action" value="delete_user">
                                            <input type="hidden" name="delete_id" value="<?= $userRow['user_id'] ?>">
                                            <input type="hidden" name="search" value="<?= htmlspecialchars($searchKeyword) ?>">
                                            <input type="hidden" name="role" value="<?= htmlspecialchars($filterRole) ?>">
                                            <button type="submit"
                                                    class="btn-icon text-danger"
                                                    title="ลบผู้ใช้"
                                                    style="width: 32px; height: 32px; display: inline-flex; align-items: center; justify-content: center; background: none; border: none; cursor: pointer;">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </form>

                                    <?php else: ?>
                                    </form>
                                        <!-- Admin ไม่สามารถแก้ไขตัวเองได้ -->
                                        <span class="text-muted text-xs">-</span>
                                    <?php endif; ?>

                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>

                    <?php if ($usersResult->num_rows === 0): ?>
                        <tr>
                            <td colspan="7" class="p-4 text-center text-muted">
                                ไม่พบข้อมูลสมาชิก ตามเงื่อนไขที่ค้นหา
                            </td>
                        </tr>
                    <?php endif; ?>

                </tbody>
            </table>
        </div>
    </div>

</div>



<!-- JavaScript สำหรับ Tab switching และ Delete confirmation -->
<script src="assets/js/admin_users.js"></script>

<?php include 'includes/footer.php'; ?>