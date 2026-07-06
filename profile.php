<?php
session_start();
include 'db.php';

if (!isset($_SESSION['student_id'])) {
    header("Location: login.php");
    exit();
}

$std_id = $_SESSION['student_id'];
$msg = "";

// --- ระบบยกเลิกการสมัครกิจกรรม ---
if (isset($_GET['cancel_id'])) {
    $cancel_id = intval($_GET['cancel_id']);
    $sql_cancel = "DELETE FROM activity_registrations WHERE student_id = '$std_id' AND activity_id = $cancel_id";
    if ($conn->query($sql_cancel) === TRUE) {
        $msg = "<div class='alert-success'>ยกเลิกการสมัครกิจกรรมเรียบร้อยแล้ว</div>";
    } else {
        $msg = "<div class='alert-danger'>เกิดข้อผิดพลาด: " . $conn->error . "</div>";
    }
}

// --- ระบบอัปเดตข้อมูลส่วนตัว และเปลี่ยนรหัสผ่าน ---
if (isset($_POST['update_profile'])) {
    $fullname = $conn->real_escape_string($_POST['fullname']);
    $faculty = $conn->real_escape_string($_POST['faculty']);

    // รับค่ารหัสผ่านใหม่ และ รหัสผ่านยืนยัน
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // กรณีที่ผู้ใช้มีการพิมพ์รหัสผ่านใหม่เข้ามา
    if (!empty($new_password)) {

        // เช็กว่ารหัสผ่านใหม่และช่องยืนยัน ตรงกันหรือไม่?
        if ($new_password !== $confirm_password) {
            $msg = "<div class='alert-danger'>การบันทึกล้มเหลว: รหัสผ่านใหม่และการยืนยันรหัสผ่านไม่ตรงกัน กรุณาลองใหม่อีกครั้ง</div>";
        } else {
            // ถ้ารหัสผ่านตรงกัน ให้เข้ารหัสและเตรียมบันทึกลงฐานข้อมูล
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $sql = "UPDATE users SET fullname='$fullname', faculty='$faculty', password='$hashed_password' WHERE student_id='$std_id'";

            if ($conn->query($sql) === TRUE) {
                $_SESSION['fullname'] = $fullname;
                $msg = "<div class='alert-success'>บันทึกข้อมูลและเปลี่ยนรหัสผ่านสำเร็จ</div>";
            }
        }
    } else {
        // กรณีที่ผู้ใช้เว้นว่างรหัสผ่าน (ต้องการเปลี่ยนแค่ชื่อหรือคณะ)
        $sql = "UPDATE users SET fullname='$fullname', faculty='$faculty' WHERE student_id='$std_id'";

        if ($conn->query($sql) === TRUE) {
            $_SESSION['fullname'] = $fullname;
            $msg = "<div class='alert-success'>บันทึกข้อมูลส่วนตัวสำเร็จ</div>";
        }
    }
}

// ดึงข้อมูลผู้ใช้มาแสดงในช่องฟอร์ม
$user_query = $conn->query("SELECT * FROM users WHERE student_id='$std_id'");
$user_data = $user_query->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>โปรไฟล์ของฉัน - ชมรมเพื่อนภูผา</title>
    <link rel="stylesheet" href="style.css">
</head>

<body>
    <?php include 'header.php'; ?>

    <div class="container">
        <h2 class="main-title">โปรไฟล์ของฉัน</h2>

        <?php echo $msg; ?>

        <div class="grid-3" style="grid-template-columns: 1fr 1fr;">

            <div class="content-panel">
                <h3 style="border-bottom: 2px solid #e2e8f0; padding-bottom: 10px;">แก้ไขข้อมูลส่วนตัว</h3>

                <form action="" method="POST">
                    <div class="form-group">
                        <label>รหัสนักศึกษา (ไม่สามารถแก้ไขได้)</label>
                        <input type="text" class="form-control" value="<?php echo $user_data['student_id']; ?>" disabled>
                    </div>
                    <div class="form-group">
                        <label>ชื่อ-นามสกุล</label>
                        <input type="text" name="fullname" class="form-control" value="<?php echo htmlspecialchars($user_data['fullname']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>คณะ / สาขาวิชา</label>
                        <input type="text" name="faculty" class="form-control" value="<?php echo htmlspecialchars($user_data['faculty']); ?>" required>
                    </div>

                    <div class="form-group" style="margin-top: 30px;">
                        <label>เปลี่ยนรหัสผ่านใหม่ (หากไม่ต้องการเปลี่ยน ให้เว้นว่างไว้)</label>
                        <input type="password" name="new_password" class="form-control" placeholder="พิมพ์รหัสผ่านใหม่ที่นี่...">
                    </div>

                    <div class="form-group">
                        <label>ยืนยันรหัสผ่านใหม่</label>
                        <input type="password" name="confirm_password" class="form-control" placeholder="พิมพ์รหัสผ่านใหม่อีกครั้ง...">
                    </div>

                    <button type="submit" name="update_profile" class="btn-block" style="margin-top: 10px;">บันทึกการเปลี่ยนแปลง</button>
                </form>
            </div>

            <div class="content-panel">
                <h3 style="border-bottom: 2px solid #e2e8f0; padding-bottom: 10px;">กิจกรรมที่ฉันเข้าร่วม</h3>

                <?php
                $sql_acts = "SELECT a.id, a.title, a.act_date, a.location 
                             FROM activity_registrations r 
                             JOIN activities a ON r.activity_id = a.id 
                             WHERE r.student_id = '$std_id' 
                             ORDER BY a.act_date DESC";

                $my_activities = $conn->query($sql_acts);

                if ($my_activities->num_rows > 0) {
                    echo "<ul style='list-style-type: none;'>";
                    while ($act = $my_activities->fetch_assoc()) {
                        echo "<li style='background: #f8fafc; margin-bottom: 15px; padding: 15px; border-left: 4px solid #10b981; border-radius: 6px; display:flex; justify-content:space-between; align-items:center;'>";
                        echo "<div>";
                        echo "<strong style='display:block; font-size:16px; color:#1e293b; margin-bottom:5px;'>" . htmlspecialchars($act['title']) . "</strong>";
                        echo "<span style='font-size:13px; color:#64748b;'>วันที่จัด: " . $act['act_date'] . "</span><br>";
                        echo "<span style='font-size:13px; color:#64748b;'>สถานที่: " . htmlspecialchars($act['location']) . "</span>";
                        echo "</div>";

                        echo "<a href='profile.php?cancel_id=" . $act['id'] . "' onclick=\"return confirm('คุณแน่ใจหรือไม่ที่จะยกเลิกการเข้าร่วมกิจกรรมนี้?')\" class='btn-danger'>ยกเลิกสมัคร</a>";

                        echo "</li>";
                    }
                    echo "</ul>";
                } else {
                    echo "<p style='color:#94a3b8; text-align:center; margin-top:30px;'>คุณยังไม่ได้สมัครกิจกรรมใดๆ เลย</p>";
                }
                ?>
            </div>
        </div>
    </div>
</body>

</html>