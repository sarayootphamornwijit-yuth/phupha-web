<?php
include 'db.php';

$error = '';
$success = '';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['register'])) {
    $student_id = $conn->real_escape_string($_POST['student_id']);
    $title = $conn->real_escape_string($_POST['title']);
    $password1 = $_POST['password1'];
    $password2 = $_POST['password2'];
    $fullname = $conn->real_escape_string($_POST['fullname']);
    $faculty = $conn->real_escape_string($_POST['faculty']);

    // 1. เช็กรหัสผ่านตรงกันหรือไม่

    if (empty($student_id)) {
        $error = 'กรุณากรอกรหัสนักศึกษา';
    } else if (empty($title)) {
        $error = "กรุณาเลือกนามหน้าชื่อ";
    } else if (strlen($student_id) != 8) {
        $error = 'รหัสนักศึกษาไม่ถูกต้อง';
    } else if (empty($fullname)) {
        $error = 'กรุณากรอกชื่อและสกุล';
    } else if (empty($faculty)) {
        $error = 'กรุณากรอกชื่อคณะ/สาขา';
    } else if (empty($password1)) {
        $error = 'กรุณากรอกรหัสผ่านในช่องตั้งรหัสผ่าน';
    } else if (empty($password2)) {
        $error = 'กรุณากรอกรหัสผ่านในช่องยืนยันรหัสผ่าน';
    } else if (strlen($password1) < 5) {
        $error = 'ใส่รหัสผ่านอย่างน้อย 5 ตัว';
    } else if ($password1 != $password2) {
        $error = 'รหัสผ่านไม่ตรงกัน';
    } else {
        try {
            $stmt = $conn->prepare("SELECT student_id FROM users WHERE student_id = ?");
            $stmt->bind_param("s", $student_id);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $error = 'รหัสนักศึกษานี้ได้ลงทะเบียนไว้ในระบบแล้ว';
            } else {
                $hashed_password = password_hash($password1, PASSWORD_DEFAULT);

                $insert_stmt = $conn->prepare("INSERT INTO users (student_id,password,title,fullname,faculty)VALUES (?,?,?,?,?)");
                $insert_stmt->bind_param("sssss", $student_id, $hashed_password, $title, $fullname, $faculty);

                if ($insert_stmt->execute()) {
                    $success = 'สมัครสมาชิกสำเร็จ ระบบกำลังพาท่านไปหน้าเข้าสู่ระบบ';
                    header("refresh:1 url=login.php");
                    exit();
                } else {
                    $error = 'เกิดข้อผิดพลาด';
                }
            }
        } catch (Exception $e) {
            $error = 'ระบบฐานข้อมูลขัดข้อง' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>สมัครสมาชิกใหม่</title>
    <link rel="stylesheet" href="style.css">
</head>

<body class="auth-page">
    <div class="auth-box">
        <h2 style="text-align: center; margin-bottom: 25px; color: var(--primary-color);">สมัครสมาชิกชมรม</h2>

        <?php if ($error): ?>
            <div class="alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert-success"><?php echo $success; ?></div>
        <?php else: ?>

            <form action="" method="POST">
                <div class="form-group">
                    <label>รหัสนักศึกษา</label>
                    <input type="text" name="student_id" class="form-control" placeholder="เช่น 66143123" required>
                </div>

                <!-- เพิ่มช่องเลือกคำนำหน้าชื่อ  -->
                <div class="form-group">
                    <label>ระบุนามหน้าชื่อ</label>
                    <input name="title" class="form-control" type="text" required placeholder="เช่น นาย นางสาว">
                </div>

                <div class="form-group">
                    <label>ชื่อ-นามสกุล (กรุณาระบุนามหน้าชื่อ)</label>
                    <input type="text" name="fullname" class="form-control" placeholder="เช่น สมศักดิ์ หัตถ์ตระกูล" required>
                </div>
                <div class="form-group">
                    <label>คณะ / สาขาวิชา</label>
                    <input type="text" name="faculty" class="form-control" placeholder="เช่น คณะวิทยาศาสตร์และเทคโนโลยี สาขาวิทยาศาสตร์สิ่งแวดล้อม" required>
                </div>
                <div class="form-group">
                    <label>ตั้งรหัสผ่าน</label>
                    <input type="password" name="password1" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>ยืนยันรหัสผ่าน</label>
                    <input type="password" name="password2" class="form-control" required>
                </div>
                <button type="submit" name="register" class="btn-block" style="margin-top: 10px;">ลงทะเบียนข้อมูล</button>
            </form>
            <p style="text-align: center; margin-top: 20px; font-size: 14px; color: var(--text-muted);">
                มีบัญชีอยู่แล้วใช่ไหม? <a href="login.php" style="color:var(--accent-color); text-decoration:none; font-weight:500;">เข้าสู่ระบบที่นี่</a>
            </p>

        <?php endif; ?>
    </div>
</body>

</html>