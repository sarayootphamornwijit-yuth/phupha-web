<?php
session_start();
include 'db.php';

$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['login'])) {
    $student_id = $conn->real_escape_string($_POST['student_id']);
    $password = $_POST['password'];

    $sql = "SELECT * FROM users WHERE student_id = '$student_id'";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();

        if (password_verify($password, $user['password'])) {
            $_SESSION['student_id'] = $user['student_id'];
            $_SESSION['title_prefix'] = $user['title']; // เก็บคำนำหน้า
            $_SESSION['fullname'] = $user['fullname'];
            $_SESSION['role'] = $user['role'];

            header("Location: index.php");
            exit();
        } else {
            $error = "รหัสผ่านไม่ถูกต้อง";
        }
    } else {
        $error = "ไม่พบรหัสประจำตัวนักศึกษานี้ในระบบ";
    }
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>เข้าสู่ระบบสมาชิก</title>
    <link rel="stylesheet" href="style.css">
</head>

<body class="auth-page">
    <div class="auth-box">
        <h2 style="text-align: center; margin-bottom: 25px; color: var(--primary-color);">เข้าสู่ระบบเพื่อนภูผา</h2>

        <?php if ($error): ?>
            <div class="alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <form action="" method="POST">
            <div class="form-group">
                <label>รหัสนักศึกษา</label>
                <input type="text" name="student_id" class="form-control" placeholder="เช่น 66123123" required>
            </div>
            <div class="form-group">
                <label>รหัสผ่าน</label>
                <input type="password" name="password" class="form-control" required>
            </div>
            <button type="submit" name="login" class="btn-block" style="margin-top: 10px;">ยืนยันเข้าสู่ระบบ</button>
        </form>

        <p style="text-align: center; margin-top: 20px; font-size: 14px; color: var(--text-muted);">
            ยังไม่มีบัญชีใช่ไหม? <a href="register.php" style="color:var(--accent-color); text-decoration:none; font-weight:500;">สมัครสมาชิกใหม่ที่นี่</a>
        </p>
    </div>
</body>

</html>