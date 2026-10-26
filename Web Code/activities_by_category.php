<?php
// activities_by_category.php
// หน้าแสดงกิจกรรมแยกตามหมวดหมู่
require_once 'config/database.php';

$allowed_categories = ['volunteer', 'sport', 'byaniyan'];
$cat = trim($_GET['cat'] ?? '');

if (!in_array($cat, $allowed_categories)) {
    header("Location: index.php");
    exit;
}

$category_labels = [
    'volunteer' => 'กิจกรรมจิตอาสา',
    'sport'     => 'กิจกรรมกีฬา',
    'byaniyan'  => 'กิจกรรมบายเนียร์',
];
$category_title = $category_labels[$cat];

// ดึงกิจกรรมที่เปิดรับอยู่ในหมวดนี้
$stmt = $conn->prepare("
    SELECT a.*, (SELECT COUNT(*) FROM user_activities WHERE activity_id = a.activity_id) as current_participants
    FROM activities a
    WHERE a.approval_status = 'approved' AND a.category = ?
    ORDER BY a.start_date ASC
");
$stmt->bind_param("s", $cat);
$stmt->execute();
$activities = $stmt->get_result();
$stmt->close();

include 'includes/header.php';
?>

<?php if (isset($_SESSION['alert'])): ?>
    <div id="flash-message" data-type="<?= $_SESSION['alert']['type'] ?>" data-title="<?= $_SESSION['alert']['title'] ?>" data-text="<?= $_SESSION['alert']['text'] ?>" class="d-none"></div>
    <?php unset($_SESSION['alert']); ?>
<?php endif; ?>

<div class="container py-4">
    <h2 class="section-title mb-4"><?= htmlspecialchars($category_title) ?></h2>

    <div class="grid-3 mb-4">
        <?php if ($activities->num_rows > 0): ?>
            <?php while ($act = $activities->fetch_assoc()): ?>
                <div class="card p-0 overflow-hidden d-flex flex-column h-100">
                    <a href="activity_details.php?id=<?= $act['activity_id'] ?>" style="height: 160px; display: block; background: var(--bg-main); position: relative;">
                        <?php if (!empty($act['cover_image'])): ?>
                            <img src="<?= htmlspecialchars($act['cover_image']) ?>" alt="Cover" style="width: 100%; height: 100%; object-fit: cover;" onerror="this.onerror=null; this.parentElement.innerHTML='<div class=\'d-flex justify-center align-center h-100 text-muted\' style=\'background: #f1f5f9;\'><i class=\'fa-solid fa-image fa-2x\' style=\'opacity: 0.4;\'></i></div>';">
                        <?php else: ?>
                            <div class="d-flex justify-center align-center h-100 text-muted" style="background: #f1f5f9;">
                                <i class="fa-solid fa-image fa-2x" style="opacity: 0.4;"></i>
                            </div>
                        <?php endif; ?>
                    </a>

                    <div class="card-body p-3 d-flex flex-column flex-grow-1">
                        <a href="activity_details.php?id=<?= $act['activity_id'] ?>" class="text-decoration-none text-main">
                            <h3 class="mt-0 mb-2 text-md"><?= htmlspecialchars($act['title']) ?></h3>
                        </a>
                        <p class="text-muted text-sm flex-grow-1 mb-3 line-clamp-2"><?= htmlspecialchars($act['description'] ?? '') ?></p>

                        <div class="text-sm text-muted mb-1">วันที่: <?= date('d/m/Y', strtotime($act['start_date'])) ?></div>
                        <div class="text-sm text-muted mb-3">สถานที่: <?= htmlspecialchars($act['location_name']) ?></div>

                        <div class="mt-auto pt-3 border-top">
                            <div class="d-flex gap-2 flex-column">
                                <?php 
                                $actEndDate = !empty($act['end_date']) ? $act['end_date'] : $act['start_date'];
                                $isActivityPast = (strtotime($actEndDate) <= time()); 
                                ?>
                                
                                <?php if ($isActivityPast): ?>
                                    <!-- ปุ่ม Facebook Link สำหรับกิจกรรมที่ผ่านมา -->
                                    <?php
                                    $facebookLinkUrl = !empty($act['facebook_link']) ? htmlspecialchars($act['facebook_link']) : '';
                                    ?>
                                    <?php if ($facebookLinkUrl): ?>
                                        <a href="<?= $facebookLinkUrl ?>" target="_blank" class="btn btn-block text-white" style="background: #1976D2; border: none; text-decoration: none;">
                                            ดูเพิ่มเติมบน Facebook
                                        </a>
                                    <?php else: ?>
                                        <button class="btn btn-block bg-main text-muted cursor-default" disabled>
                                            ไม่มีลิงก์เฟสบุ๊ค
                                        </button>
                                    <?php endif; ?>

                                    <a href="activity_details.php?id=<?= $act['activity_id'] ?>" class="btn btn-block btn-surface border-light text-muted">
                                        ดูรายละเอียด
                                    </a>
                                
                                <?php else: ?>
                                    <!-- ปุ่มสำหรับกิจกรรมที่ยังเปิดรับสมัคร -->
                                    <a href="activity_details.php?id=<?= $act['activity_id'] ?>" class="btn btn-block btn-surface border-primary text-primary">รายละเอียดกิจกรรม</a>
                                    
                                    <?php if (isLoggedIn()): ?>
                                        <?php
                                        $u_id = $_SESSION['user_id'];
                                        $a_id = $act['activity_id'];
                                        $check_stmt = $conn->prepare("SELECT 1 FROM user_activities WHERE user_id = ? AND activity_id = ?");
                                        $check_stmt->bind_param("ii", $u_id, $a_id);
                                        $check_stmt->execute();

                                        // ตรวจสอบว่า get_result() สำเร็จหรือไม่ก่อนเรียก num_rows
                                        // ถ้าไม่ตรวจสอบ และ DB มีปัญหา โค้ดจะหยุดทำงานกลางคัน
                                        $check_result = $check_stmt->get_result();
                                        $has_joined   = false;
                                        if ($check_result && $check_result->num_rows > 0) {
                                            $has_joined = true;
                                        }
                                        $check_stmt->close();
                                        ?>
                                        <?php if ($has_joined): ?>
                                            <button class="btn btn-block bg-main text-success cursor-default" disabled>ลงทะเบียนแล้ว</button>
                                        <?php elseif (strtotime($act['start_date']) <= time()): ?>
                                            <button class="btn btn-block cursor-default" style="background: #6c757d; border-color: #6c757d; color: #fff;" disabled>เริ่มกิจกรรมแล้ว (ปิดรับสมัคร)</button>
                                        <?php elseif ($act['max_participants'] > 0 && $act['current_participants'] >= $act['max_participants']): ?>
                                            <button class="btn btn-danger btn-block cursor-default" disabled>เต็มแล้ว</button>
                                        <?php else: ?>
                                            <form method="POST" action="index.php" class="m-0 w-100">
                                                <?php csrfField(); ?>
                                                <input type="hidden" name="action" value="register">
                                                <input type="hidden" name="activity_id" value="<?= $act['activity_id'] ?>">
                                                <button type="submit" class="btn btn-primary btn-block">ลงทะเบียนเข้าร่วม</button>
                                            </form>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <?php if (strtotime($act['start_date']) <= time()): ?>
                                            <button class="btn btn-block cursor-default" style="background: #6c757d; border-color: #6c757d; color: #fff;" disabled>เริ่มกิจกรรมแล้ว (ปิดรับสมัคร)</button>
                                        <?php else: ?>
                                            <a href="login.php" class="btn btn-block bg-main text-primary border-primary" style="border: 1px solid var(--color-primary);">เข้าสู่ระบบเพื่อลงทะเบียน</a>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="card p-4 text-center text-muted grid-span-full border-dashed" style="border: 1px dashed var(--text-light); box-shadow: none;">
                <p class="m-0">ยังไม่มีกิจกรรมในหมวดนี้ในขณะนี้</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script src="assets/js/activities.js"></script>

<?php include 'includes/footer.php'; ?>
