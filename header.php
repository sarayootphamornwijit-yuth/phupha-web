<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// ตรวจสอบสิทธิ์การเข้าใช้งาน
$is_logged_in = isset($_SESSION['student_id']);
$is_admin = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin');
?>

<nav class="sidebar">
    <div class="sidebar-logo">
        <h2>ชมรมเพื่อนภูผา</h2>
    </div>

    <ul class="sidebar-links">
        <?php if ($is_logged_in): ?>
            <li><a href="profile.php">โปรไฟล์ของฉัน</a></li>
        <?php endif; ?>

        <li><a href="index.php">หน้าแรก</a></li>
        <li><a href="activities.php">กิจกรรม</a></li>
        <li><a href="about.php">เกี่ยวกับเรา</a></li>
        <li><a href="contact.php">ติดต่อเรา</a></li>

        <?php if ($is_admin): ?>
            <li class="admin-menu">
                <a href="admin_dashboard.php">จัดการกิจกรรม</a>
                <a href="admin_users.php">จัดการสมาชิก</a>
            </li>
        <?php endif; ?>
    </ul>

    <div class="sidebar-auth">
        <?php if ($is_logged_in): ?>
            <div class="user-info">
                <span style="font-size: 13px; color: #777;">เข้าสู่ระบบโดย:</span>
                <strong style="display: block; margin-bottom: 10px; color: #2c3e50;">
                    <?php echo htmlspecialchars($_SESSION['fullname']); ?>
                    <?php echo $is_admin ? '<span class="badge-admin">Admin</span>' : ''; ?>
                </strong>
                <a href="logout.php" class="btn-logout">ออกจากระบบ</a>
            </div>
        <?php else: ?>
            <a href="login.php" class="btn-login">เข้าสู่ระบบ</a>
        <?php endif; ?>
    </div>
</nav>