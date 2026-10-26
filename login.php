<?php
// login.php
// ไฟล์นี้ทำหน้าที่แสดงฟอร์มเข้าสู่ระบบ และประมวลผลการล็อกอินของผู้ใช้

require_once 'config/database.php';


// ส่วนที่ 1 ตรวจสอบสถานะล็อกอิน
// ถ้าล็อกอินอยู่แล้ว ให้ redirect กลับไปหน้าหลักทันที ไม่ต้องแสดงหน้า login
if (isLoggedIn()) {
    header("Location: index.php");
    exit;
}


// ส่วนที่ 2 กำหนดตัวแปรเริ่มต้น
// ตัวแปรสำหรับเก็บข้อความ error ที่อาจเกิดขึ้นระหว่างการล็อกอิน
$errorMessage = '';


// ส่วนที่ 3 ประมวลผลฟอร์มล็อกอิน (POST)
// ทำงานเฉพาะเมื่อผู้ใช้กด Submit ฟอร์ม
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // รับค่าจากฟอร์ม และลบ whitespace หัวท้ายออก
    $inputUsername = trim($_POST['username'] ?? '');
    $inputPassword = $_POST['password'] ?? '';

    // ค้นหาผู้ใช้จากฐานข้อมูล
    // ใช้ Prepared Statement เพื่อป้องกัน SQL Injection
    $statement = $conn->prepare(
        "SELECT user_id, username, password, name, role, committee_position, user_type
        FROM users
        WHERE username = ?"
    );
    $statement->bind_param("s", $inputUsername);
    $statement->execute();
    $queryResult = $statement->get_result();

    // ตรวจสอบผลลัพธ์การค้นหา
    if ($queryResult->num_rows === 1) {

        // พบผู้ใช้ในระบบ ดึงข้อมูลออกมา
        $userRecord = $queryResult->fetch_assoc();

        // ตรวจสอบรหัสผ่านที่ผู้ใช้กรอกกับรหัสผ่านที่เข้ารหัสไว้ในฐานข้อมูล
        $isPasswordCorrect = password_verify($inputPassword, $userRecord['password']);

        if ($isPasswordCorrect) {

            // ป้องกัน Session Fixation โดยการเปลี่ยน Session ID เมื่อล็อกอินสำเร็จ
            session_regenerate_id(true);

            // รหัสผ่านถูกต้อง: บันทึกข้อมูลลง Session
            $_SESSION['user_id']            = $userRecord['user_id'];
            $_SESSION['username']           = $userRecord['username'];
            $_SESSION['name']               = $userRecord['name'];
            $_SESSION['role']               = $userRecord['role'];
            $_SESSION['committee_position'] = $userRecord['committee_position'] ?? null;
            $_SESSION['user_type']          = $userRecord['user_type'] ?? null;

            // กำหนดหน้าที่จะ Redirect ไปหลังล็อกอิน
            // ถ้ามี redirect URL ให้ไป จะนำทางไปยัง URL นั้น (ตรวจสอบความปลอดภัยด้วย regex)
            // ถ้าไม่มี จะไปที่หน้า index.php ตามปกติ
            $redirectUrl = 'index.php';

            if (!empty($_POST['redirect'])) {
                $redirectUrl = $_POST['redirect'];
            } elseif (!empty($_GET['redirect'])) {
                $redirectUrl = $_GET['redirect'];
            }

            // ตรวจสอบว่า redirect URL ปลอดภัย (ไม่มีอักขระอันตราย)
            $isSafeRedirect = preg_match('/^[a-zA-Z0-9_.\-\/]+(?:\?[a-zA-Z0-9_.&=%\-]+)?$/', $redirectUrl) && !preg_match('/^(?:https?:)?\/\//i', $redirectUrl);
            if (!$isSafeRedirect) {
                $redirectUrl = 'index.php';
            }

            // นำทางไปยังหน้าปลายทาง
            header("Location: " . $redirectUrl);
            exit;
        } else {

            // ข้อความ error แบบรวม ป้องกันผู้ไม่หวังดีสุ่มเดาว่ามี Username นี้ในระบบหรือไม่
            $errorMessage = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';
        }
    } else {

        // ไม่พบผู้ใช้งานในระบบ (ใช้ข้อความเดียวกับรหัสผ่านผิดเพื่อความปลอดภัย)
        $errorMessage = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';
    }

    // ปิด Prepared Statement เพื่อคืน resource
    $statement->close();
}


// ส่วนที่ 4 แสดงหน้า HTML
include 'includes/header.php';
?>

<div class="container d-flex justify-center align-center" style="min-height: 80vh;">
    <div class="card" style="max-width: 400px; width: 100%; border: 1px solid var(--border-color); box-shadow: none;">

        <!-- ส่วนหัวของฟอร์ม -->
        <div class="text-center mb-4">
            <h2 class="m-0" style="color: #000;">เข้าสู่ระบบ</h2>
            <p class="text-muted text-sm mt-1">ลงชื่อเข้าใช้เพื่อจัดการกิจกรรมของคุณ</p>
        </div>

        <!-- แสดงข้อความ error ถ้ามี -->
        <?php if ($errorMessage !== ''): ?>
            <div class="alert alert-danger text-center mb-3">
                <?= htmlspecialchars($errorMessage) ?>
            </div>
        <?php endif; ?>

        <!-- ฟอร์มเข้าสู่ระบบ -->
        <form action="login.php" method="POST">

            <!-- ส่ง redirect URL ต่อไปถ้ามีค่า -->
            <?php if (!empty($_GET['redirect'])): ?>
                <input type="hidden" name="redirect" value="<?= htmlspecialchars($_GET['redirect']) ?>">
            <?php endif; ?>

            <!-- ฟิลด์ Username -->
            <div class="form-group mb-3">
                <label class="form-label" style="color: #000;">ชื่อผู้ใช้งาน</label>
                <input type="text" name="username" class="form-control" required autofocus>
            </div>

            <!-- ฟิลด์ Password -->
            <div class="form-group mb-4">
                <label class="form-label" style="color: #000;">รหัสผ่าน</label>
                <input type="password" name="password" class="form-control" required>
            </div>

            <!-- ปุ่ม Submit -->
            <button type="submit" class="btn btn-primary btn-block" style="box-shadow: none !important;">เข้าสู่ระบบ</button>
        </form>

        <!-- ลิงก์ไปหน้าสมัครสมาชิก -->
        <div class="text-center mt-4 text-sm">
            ยังไม่มีบัญชีใช่หรือไม่? <a href="register.php" class="text-link" style="text-decoration: none !important; border-bottom: none !important; box-shadow: none !important; outline: none !important;">สมัครสมาชิกใหม่</a>
        </div>

    </div>
</div>

<?php include 'includes/footer.php'; ?>