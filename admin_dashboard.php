<?php
// admin_dashboard.php
// ไฟล์นี้ทำหน้าที่แสดง Dashboard ภาพรวมระบบสำหรับ Admin
// แสดงสถิติสำคัญ เช่น จำนวนสมาชิก, กิจกรรม, แต้มสะสม
// และ Chart แสดงข้อมูลเชิงสถิติ

require_once 'config/database.php';


// ส่วนที่ 1 ตรวจสอบสิทธิ์ Admin
if (!isLoggedIn() || getCurrentUserRole() !== 'admin') {
    header("Location: index.php");
    exit;
}

// ส่วนประมวลผลการปรับปรุงเกณฑ์คะแนนจิตอาสา (POST)
$successMessage = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_cert_threshold') {
    verifyCsrfToken();
    $newThreshold = (int)($_POST['point_threshold'] ?? 50);
    setCertificatePointThreshold($newThreshold);
    $successMessage = 'บันทึกเกณฑ์คะแนนจิตอาสาเรียบร้อยแล้ว';
}

$certThreshold = getCertificatePointThreshold();


// ส่วนที่ 2 ดึงสถิติภาพรวม
// จำนวนสมาชิกทั้งหมด
$totalUsers = $conn->query("SELECT COUNT(*) as total FROM users")->fetch_assoc()['total'];

// จำนวนกิจกรรมทั้งหมด
$totalActivities = $conn->query("SELECT COUNT(*) as total FROM activities")->fetch_assoc()['total'];


// จำนวนสมาชิกใหม่ในเดือนนี้
$newUsersThisMonth = $conn->query(
    "SELECT COUNT(*) as total FROM users
     WHERE MONTH(created_at) = MONTH(CURRENT_DATE())
     AND YEAR(created_at) = YEAR(CURRENT_DATE())"
)->fetch_assoc()['total'];


// ส่วนที่ 3 ดึงข้อมูล Chart สถานะกิจกรรม
// แสดงจำนวนกิจกรรมแยกตาม approval_status
$activityStatusQuery = $conn->query(
    "SELECT approval_status as status_type, COUNT(*) as count
     FROM activities
     GROUP BY approval_status"
);

$chartStatusLabels = [];
$chartStatusCounts = [];

while ($statusRow = $activityStatusQuery->fetch_assoc()) {
    $labelText = $statusRow['status_type'] ? $statusRow['status_type'] : 'ไม่ระบุ';
    $chartStatusLabels[] = $labelText;
    $chartStatusCounts[] = $statusRow['count'];
}


// ส่วนที่ 4 ดึงข้อมูล Chart ระดับการมีส่วนร่วม
// แบ่งสมาชิกเป็น 3 กลุ่ม ตามจำนวนกิจกรรมที่เข้าร่วม

// กลุ่ม Active: เข้าร่วมมากกว่า 3 กิจกรรม
$activeUsersCount = $conn->query(
    "SELECT COUNT(*) as total FROM (
         SELECT user_id, COUNT(*) as activity_count
         FROM user_activities
         GROUP BY user_id
         HAVING activity_count > 3
     ) active_subquery"
)->fetch_assoc()['total'];

// กลุ่ม Regular: เข้าร่วม 1-3 กิจกรรม
$regularUsersCount = $conn->query(
    "SELECT COUNT(*) as total FROM (
         SELECT user_id, COUNT(*) as activity_count
         FROM user_activities
         GROUP BY user_id
         HAVING activity_count BETWEEN 1 AND 3
     ) regular_subquery"
)->fetch_assoc()['total'];

// กลุ่ม Inactive: ไม่เคยเข้าร่วมกิจกรรมเลย
$inactiveUsersCount = $totalUsers - $activeUsersCount - $regularUsersCount;


// ส่วนที่ 5 เตรียม JSON สำหรับส่งให้ JavaScript
$chartDataJson = json_encode([
    'status_labels' => $chartStatusLabels,
    'status_counts' => $chartStatusCounts,
    'engagement'    => [$activeUsersCount, $regularUsersCount, $inactiveUsersCount],
]);


// ส่วนที่ 6 โหลด Header
include 'includes/header.php';
?>

<div class="container py-4">

    <h2 class="section-title mb-4">แผงควบคุม</h2>

<!-- Summary Cards: ตัวเลขสรุปสำคัญ -->
    <div class="grid-3 mb-4">

        <div class="card text-center">
            <h3 class="text-muted text-sm m-0 font-weight-medium">สมาชิก</h3>
            <div class="text-xl font-weight-bold text-main mt-1"><?= number_format($totalUsers) ?></div>
        </div>

        <div class="card text-center">
            <h3 class="text-muted text-sm m-0 font-weight-medium">จำนวนกิจกรรม</h3>
            <div class="text-xl font-weight-bold text-main mt-1"><?= number_format($totalActivities) ?></div>
        </div>

        <div class="card text-center">
            <h3 class="text-muted text-sm m-0 font-weight-medium">สมาชิกใหม่ (เดือนนี้)</h3>
            <div class="text-xl font-weight-bold text-main mt-1"><?= number_format($newUsersThisMonth) ?></div>
        </div>

    </div>

    <!-- แสดงข้อความแจ้งเตือนเมื่อบันทึกเกณฑ์คะแนนสำเร็จ -->
    <?php if ($successMessage !== ''): ?>
        <div class="alert alert-success text-center mb-3">
            <?= htmlspecialchars($successMessage) ?>
        </div>
    <?php endif; ?>

    <!-- ส่วนแก้ไขเกณฑ์คะแนนจิตอาสาสำหรับรับเกียรติบัตร -->
    <div class="card p-3 mb-4">
        <h3 class="text-md m-0 mb-2">เกณฑ์คะแนนจิตอาสาสำหรับรับเกียรติบัตร</h3>
        <p class="text-sm text-muted mb-2">กำหนดคะแนนสะสมขั้นต่ำที่สมาชิกต้องมีเพื่อรับสิทธิ์ดาวน์โหลดเกียรติบัตร</p>
        <form action="admin_dashboard.php" method="POST" class="d-flex align-center gap-2 flex-wrap">
            <?php csrfField(); ?>
            <input type="hidden" name="action" value="update_cert_threshold">
            <label for="point_threshold" class="text-sm">เกณฑ์คะแนนสะสมขั้นต่ำ:</label>
            <input type="number" name="point_threshold" id="point_threshold" class="form-control" value="<?= $certThreshold ?>" min="1" style="max-width: 120px;" required>
            <span class="text-sm">คะแนน</span>
            <button type="submit" class="btn btn-primary">บันทึกเกณฑ์คะแนน</button>
        </form>
    </div>

<!-- Charts: กราฟสถิติ -->
    <div class="grid-2 gap-4">

        <!-- กราฟสถานะกิจกรรม -->
        <div class="card">
            <h3 class="text-md text-center text-primary mb-3">สถิติการจัดกิจกรรมตามสถานะ</h3>
            <div class="position-relative w-100" style="height: 250px;">
                <canvas id="typeChart"></canvas>
            </div>
        </div>

        <!-- กราฟระดับการมีส่วนร่วมของสมาชิก -->
        <div class="card">
            <h3 class="text-md text-center text-primary mb-3">สถิติระดับการมีส่วนร่วมของสมาชิก</h3>
            <div class="position-relative w-100" style="height: 250px;">
                <canvas id="engageChart"></canvas>
            </div>
            <div class="text-xs text-muted text-center mt-3">
                * มาก (>3 ครั้ง), ปานกลาง (1-3 ครั้ง), น้อย (0 ครั้ง)
            </div>
        </div>

    </div>

    <!-- ส่ง JSON ข้อมูลให้ JavaScript สร้าง Chart -->
    <script id="dashboardData" type="application/json">
        <?= $chartDataJson ?>
    </script>

</div>

<!-- Chart.js Library -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<!-- JavaScript สำหรับวาด Chart -->
<script src="assets/js/dashboard.js"></script>

<?php include 'includes/footer.php'; ?>