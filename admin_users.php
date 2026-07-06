<?php
session_start();
include 'db.php';

if (!isset($_SESSION['role']) or $_SESSION['role'] !== 'admin') {
    echo "<script>alert('คุณไม่มีสิทธิ์เข้าถึงหน้านี้!'); window.location.href='index.php';</script>";
    exit();
}

$msg = "";

// ระบบลบสมาชิก
if (isset($_GET['delete_user'])) {
    $del_id = $conn->real_escape_string($_GET['delete_user']);

    // แอดมินลบตัวเองไม่ได้
    if ($del_id == $_SESSION['student_id']) {
        $msg = "<div class='alert-danger'>ไม่สามารถลบบัญชีของตนเองได้</div>";
    } else {
        // ลบประวัติการสมัครกิจกรรมก่อน แล้วค่อยลบบัญชี
        $conn->query("DELETE FROM activity_registrations WHERE student_id = '$del_id'");
        if ($conn->query("DELETE FROM users WHERE student_id = '$del_id'")) {
            $msg = "<div class='alert-success'>ลบสมาชิกเรียบร้อยแล้ว</div>";
        }
    }
}

// ระบบเปลี่ยนสิทธิ์ (ให้เป็น Admin หรือเป็น Member ธรรมดา)
if (isset($_GET['toggle_role'])) {
    $toggle_id = $conn->real_escape_string($_GET['toggle_role']);
    $current_role = $_GET['current'];

    $new_role = ($current_role == 'admin') ? 'member' : 'admin';

    if ($toggle_id == $_SESSION['student_id']) {
        $msg = "<div class='alert-danger'>ไม่สามารถลดสิทธิ์บัญชีของตนเองได้</div>";
    } else {
        if ($conn->query("UPDATE users SET role = '$new_role' WHERE student_id = '$toggle_id'")) {
            $msg = "<div class='alert-success'>เปลี่ยนสิทธิ์ผู้ใช้งานเรียบร้อยแล้ว</div>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>จัดการสมาชิก - Admin</title>
    <link rel="stylesheet" href="style.css">
</head>

<body>
    <?php include 'header.php'; ?>

    <div class="container">
        <h1 class="main-title">ระบบจัดการสมาชิกทั้งหมด</h1>
        <?php echo $msg; ?>

        <div class="content-panel">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>รหัสนักศึกษา</th>>
                        <th>ชื่อ-นามสกุล</th>
                        <th>คณะ/สาขา</th>
                        <th style="text-align:center;">สิทธิ์การใช้งาน</th>
                        <th style="text-align:center;">การจัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // ดึงข้อมูลสมาชิกทั้งหมด เรียงให้ Admin ขึ้นก่อน
                    $users_query = $conn->query("SELECT * FROM users ORDER BY role ASC, student_id ASC");

                    while ($u = $users_query->fetch_assoc()) {
                        $role_text = ($u['role'] == 'admin') ? '<span style="color:red; font-weight:bold;">Admin</span>' : 'Member';
                    ?>
                        <tr>
                            <td><?php echo htmlspecialchars($u['student_id']); ?></td>
                            <td><?php echo htmlspecialchars($u['title'] . " " . $u['fullname']); ?></td>
                            <td><?php echo htmlspecialchars($u['faculty']); ?></td>
                            <td style="text-align:center;"><?php echo $role_text; ?></td>

                            <td style="text-align:center; white-space: nowrap; width: 180px;">
                                <a href="admin_users.php?toggle_role=<?php echo $u['student_id']; ?>&current=<?php echo $u['role']; ?>"
                                    onclick="return confirm('ต้องการสลับสิทธิ์การใช้งานของบุคคลนี้ใช่หรือไม่?')"
                                    class="btn-sm-blue" style="margin-right: 5px;">สลับสิทธิ์</a>

                                <a href="admin_users.php?delete_user=<?php echo $u['student_id']; ?>"
                                    onclick="return confirm('ยืนยันการลบสมาชิกคนนี้? ข้อมูลการสมัครกิจกรรมทั้งหมดจะถูกลบทิ้งด้วย')"
                                    class="btn-sm-red">ลบทิ้ง</a>
                            </td>
                        </tr>
                    <?php
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</body>

</html>