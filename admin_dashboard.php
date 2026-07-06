<?php
session_start();
include 'db.php';

// ดักจับ: ถ้าไม่ใช่ Admin ห้ามเข้าหน้านี้เด็ดขาด!
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    echo "<script>alert('คุณไม่มีสิทธิ์เข้าถึงหน้านี้!'); window.location.href='index.php';</script>";
    exit();
}

$msg = "";

// 1. ระบบจัดการเมื่อ Admin กดเพิ่มกิจกรรมใหม่
if (isset($_POST['add_activity'])) {
    $title = $conn->real_escape_string($_POST['title']);
    $description = $conn->real_escape_string($_POST['description']);
    $act_date = $_POST['act_date'];
    $location = $conn->real_escape_string($_POST['location']);
    $type = $_POST['type'];

    // หากระบบของคุณมีการอัปโหลดรูปภาพด้วย ให้นำโค้ดอัปโหลดมาแทรกตรงนี้ได้เลยครับ
    $sql = "INSERT INTO activities (title, description, act_date, location, type) VALUES ('$title', '$description', '$act_date', '$location', '$type')";

    if ($conn->query($sql) === TRUE) {
        $msg = "<div class='alert-success'>เพิ่มกิจกรรมสำเร็จแล้ว!</div>";
    } else {
        $msg = "<div class='alert-danger'>เกิดข้อผิดพลาด: " . $conn->error . "</div>";
    }
}

// 2. ระบบจัดการเมื่อ Admin กดลบกิจกรรม
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    if ($conn->query("DELETE FROM activities WHERE id = $delete_id")) {
        $msg = "<div class='alert-success'>ลบกิจกรรมเรียบร้อยแล้ว!</div>";
    } else {
        $msg = "<div class='alert-danger'>ไม่สามารถลบได้: " . $conn->error . "</div>";
    }
}

// 3. ระบบจัดการเมื่อ Admin กดบันทึกการแก้ไขกิจกรรม
if (isset($_POST['update_activity'])) {
    $id = intval($_POST['activity_id']);
    $title = $conn->real_escape_string($_POST['title']);
    $description = $conn->real_escape_string($_POST['description']);
    $act_date = $_POST['act_date'];
    $location = $conn->real_escape_string($_POST['location']);
    $type = $_POST['type'];

    $sql = "UPDATE activities SET title='$title', description='$description', act_date='$act_date', location='$location', type='$type' WHERE id=$id";
    if ($conn->query($sql) === TRUE) {
        $msg = "<div class='alert-success'>อัปเดตแก้ไขข้อมูลกิจกรรมสำเร็จ!</div>";
    } else {
        $msg = "<div class='alert-danger'>แก้ไขล้มเหลว: " . $conn->error . "</div>";
    }
}

// ดึงข้อมูลกิจกรรมที่ต้องการแก้ไขมาใส่ในฟอร์ม (ถ้ามีการกดปุ่มแก้ไข)
$edit_data = null;
if (isset($_GET['edit_id'])) {
    $edit_id = intval($_GET['edit_id']);
    $res = $conn->query("SELECT * FROM activities WHERE id = $edit_id");
    if ($res->num_rows > 0) {
        $edit_data = $res->fetch_assoc();
    }
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>ระบบหลังบ้าน Admin - ชมรมเพื่อนภูผา</title>
    <link rel="stylesheet" href="style.css">
</head>

<body>
    <?php include 'header.php'; ?>

    <div class="container">
        <h1 class="main-title">แผงควบคุมผู้ดูแลระบบ (Admin Dashboard)</h1>
        <?php echo $msg; ?>

        <div class="grid-3" style="grid-template-columns: 1fr 1fr; margin-bottom: 40px;">

            <div class="content-panel">
                <?php if ($edit_data): ?>
                    <h3 style="border-bottom: 2px solid #e2e8f0; padding-bottom: 10px;">แก้ไขข้อมูลกิจกรรม</h3>
                    <form action="admin_dashboard.php" method="POST">
                        <input type="hidden" name="activity_id" value="<?php echo $edit_data['id']; ?>">

                        <div class="form-group">
                            <label>ชื่อกิจกรรม</label>
                            <input type="text" name="title" class="form-control" value="<?php echo htmlspecialchars($edit_data['title']); ?>" required>
                        </div>

                        <div class="form-group">
                            <label>ประเภทกิจกรรม</label>
                            <select name="type" class="form-control" required>
                                <option value="new" <?php echo $edit_data['type'] == 'new' ? 'selected' : ''; ?>>กิจกรรมใหม่ (เปิดให้นักศึกษาสมัคร)</option>
                                <option value="past" <?php echo $edit_data['type'] == 'past' ? 'selected' : ''; ?>>กิจกรรมที่ผ่านมา</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>รายละเอียดกิจกรรม</label>
                            <textarea name="description" class="form-control" rows="4" required><?php echo htmlspecialchars($edit_data['description']); ?></textarea>
                        </div>

                        <div class="form-group">
                            <label>วันที่จัดกิจกรรม</label>
                            <input type="date" name="act_date" class="form-control" value="<?php echo $edit_data['act_date']; ?>" required>
                        </div>

                        <div class="form-group">
                            <label>สถานที่</label>
                            <input type="text" name="location" class="form-control" value="<?php echo htmlspecialchars($edit_data['location']); ?>" required>
                        </div>

                        <button type="submit" name="update_activity" class="btn-block">บันทึกการแก้ไข</button>
                        <a href="admin_dashboard.php" style="display:block; text-align:center; margin-top:15px; color:#64748b; text-decoration:none; font-size:14px;">ยกเลิกการแก้ไข</a>
                    </form>
                <?php else: ?>
                    <h3 style="border-bottom: 2px solid #e2e8f0; padding-bottom: 10px;">เพิ่มกิจกรรมของชมรม</h3>
                    <form action="" method="POST">
                        <div class="form-group">
                            <label>ชื่อกิจกรรม</label>
                            <input type="text" name="title" class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label>ประเภทกิจกรรม</label>
                            <select name="type" class="form-control" required>
                                <option value="new">กิจกรรมใหม่ (เปิดให้นักศึกษาสมัคร)</option>
                                <option value="past">กิจกรรมที่ผ่านมา (โชว์อัลบั้มรูป)</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>รายละเอียดกิจกรรม</label>
                            <textarea name="description" class="form-control" rows="4" required></textarea>
                        </div>

                        <div class="form-group">
                            <label>วันที่จัดกิจกรรม</label>
                            <input type="date" name="act_date" class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label>สถานที่</label>
                            <input type="text" name="location" class="form-control" required>
                        </div>

                        <button type="submit" name="add_activity" class="btn-block">ประกาศกิจกรรม</button>
                    </form>
                <?php endif; ?>
            </div>

            <div class="content-panel">
                <?php if ($edit_data): ?>
                    <h3 style="border-bottom: 2px solid #e2e8f0; padding-bottom: 10px;">รายชื่อผู้สมัคร (กิจกรรมที่กำลังแก้ไข)</h3>

                    <?php
                    $act_id = $edit_data['id'];
                    $users_reg = $conn->query("SELECT u.student_id, u.fullname, u.faculty FROM activity_registrations r JOIN users u ON r.student_id = u.student_id WHERE r.activity_id = $act_id");
                    $total_users = $users_reg->num_rows;

                    echo "<h4 style='color: var(--primary-color); margin-top:15px; background: #f8fafc; padding:15px; border-radius: 8px; border: 1px solid #e2e8f0; display:flex; flex-wrap:wrap; gap:10px; justify-content:space-between; align-items:center;'>";
                    echo "<span style='flex: 1 1 200px; font-size: 16px; font-weight: 500;'>กิจกรรม: " . htmlspecialchars($edit_data['title']) . "</span>";

                    echo "<div style='display:flex; gap:8px; flex-wrap:wrap;'>";
                    echo "<span style='font-size:13px; background:#3b82f6; color:white; padding:6px 12px; border-radius:6px; white-space:nowrap;'>ยอดผู้สมัคร: " . $total_users . " คน</span>";
                    echo "<a href='print_roster.php?act_id=" . $act_id . "' target='_blank' style='font-size:13px; background:#10b981; color:white; padding:6px 12px; border-radius:6px; text-decoration:none; white-space:nowrap; transition: 0.2s;'>พิมพ์รายชื่อ</a>";
                    echo "</div>";
                    echo "</h4>";

                    // แสดงตารางรายชื่อเฉพาะกิจกรรมที่ถูกเลือก
                    if ($total_users > 0) {
                        echo "<table class='admin-table'>";
                        echo "<tr><th style='width:50px; text-align:center;'>ลำดับ</th><th>รหัส นศ.</th><th>ชื่อ-นามสกุล</th><th>คณะ/สาขา</th></tr>";
                        $i = 1;
                        while ($user = $users_reg->fetch_assoc()) {
                            echo "<tr>";
                            echo "<td style='text-align:center; font-weight:500; color:#64748b;'>" . $i . "</td>";
                            echo "<td>" . htmlspecialchars($user['student_id']) . "</td>";
                            echo "<td>" . htmlspecialchars($user['fullname']) . "</td>";
                            echo "<td>" . htmlspecialchars($user['faculty']) . "</td>";
                            echo "</tr>";
                            $i++;
                        }
                        echo "</table>";
                    } else {
                        echo "<p style='color:#94a3b8; font-size:14px; padding:15px 0;'>ยังไม่มีนักศึกษาสมัครกิจกรรมนี้</p>";
                    }
                    ?>

                <?php else: ?>
                    <h3 style="border-bottom: 2px solid #e2e8f0; padding-bottom: 10px;">สรุปยอดผู้สมัครกิจกรรมใหม่</h3>

                    <?php
                    $activities_new = $conn->query("SELECT * FROM activities WHERE type='new' ORDER BY id DESC");

                    if ($activities_new->num_rows > 0) {
                        while ($act = $activities_new->fetch_assoc()) {
                            $act_id = $act['id'];

                            // นับยอดเท่านั้น ไม่ต้องดึงข้อมูลชื่อ-นามสกุล เพื่อประหยัดการทำงานของฐานข้อมูล
                            $count_query = $conn->query("SELECT id FROM activity_registrations WHERE activity_id = $act_id");
                            $total_users = $count_query->num_rows;

                            echo "<h4 style='color: var(--primary-color); margin-top:15px; background: #f8fafc; padding:15px; border-radius: 8px; border: 1px solid #e2e8f0; display:flex; flex-wrap:wrap; gap:10px; justify-content:space-between; align-items:center;'>";
                            echo "<span style='flex: 1 1 200px; font-size: 16px; font-weight: 500;'>กิจกรรม: " . htmlspecialchars($act['title']) . "</span>";

                            echo "<div style='display:flex; gap:8px; flex-wrap:wrap;'>";
                            echo "<span style='font-size:13px; background:#3b82f6; color:white; padding:6px 12px; border-radius:6px; white-space:nowrap;'>ยอดผู้สมัคร: " . $total_users . " คน</span>";
                            echo "<a href='print_roster.php?act_id=" . $act_id . "' target='_blank' style='font-size:13px; background:#10b981; color:white; padding:6px 12px; border-radius:6px; text-decoration:none; white-space:nowrap; transition: 0.2s;'>พิมพ์รายชื่อ</a>";
                            echo "</div>";
                            echo "</h4>";
                            // เราตัดลูปสร้าง <table> ทิ้งไปสำหรับส่วนนี้ ทำให้มันโชว์แค่บล็อกสรุปครับ
                        }
                    } else {
                        echo "<p style='color:#94a3b8; font-size:14px; margin-top:15px;'>ไม่มีกิจกรรมใหม่</p>";
                    }
                    ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="content-panel">
            <h3 style="border-bottom: 2px solid #e2e8f0; padding-bottom: 10px;">รายการกิจกรรมทั้งหมดในระบบ</h3>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>วันที่จัดงาน</th>
                        <th>ชื่อกิจกรรม</th>
                        <th>สถานที่</th>
                        <th>ประเภท</th>
                        <th style="text-align: center; width: 150px;">การจัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $all_act = $conn->query("SELECT * FROM activities ORDER BY act_date DESC");
                    if ($all_act->num_rows > 0) {
                        while ($row = $all_act->fetch_assoc()) {
                    ?>
                            <tr>
                                <td><?php echo $row['act_date']; ?></td>
                                <td><strong><?php echo htmlspecialchars($row['title']); ?></strong></td>
                                <td><?php echo htmlspecialchars($row['location']); ?></td>
                                <td>
                                    <?php
                                    if ($row['type'] == 'new') {
                                        echo "<span style='color:#10b981; font-weight:500;'>กิจกรรมใหม่</span>";
                                    } else {
                                        echo "<span style='color:#64748b; font-weight:500;'>กิจกรรมที่ผ่านมา</span>";
                                    }
                                    ?>
                                </td>
                                <td style="text-align: center; white-space: nowrap;">
                                    <a href="admin_dashboard.php?edit_id=<?php echo $row['id']; ?>" class="btn-sm-blue" style="margin-right:5px;">แก้ไข</a>
                                    <a href="admin_dashboard.php?delete_id=<?php echo $row['id']; ?>" onclick="return confirm('คุณแน่ใจหรือไม่ที่จะลบกิจกรรมนี้? ข้อมูลนักศึกษาที่สมัครจะถูกลบไปด้วย')" class="btn-sm-red">ลบ</a>
                                </td>
                            </tr>
                    <?php
                        }
                    } else {
                        echo "<tr><td colspan='5' style='text-align:center; color:#94a3b8;'>ไม่มีข้อมูลกิจกรรมในระบบ</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>

    </div>
</body>

</html>