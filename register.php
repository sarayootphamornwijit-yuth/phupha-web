<?php
// register.php
// ไฟล์นี้ทำหน้าที่แสดงฟอร์มสมัครสมาชิก และประมวลผลการลงทะเบียนผู้ใช้ใหม่

require_once 'config/database.php';


// ส่วนที่ 1 ตรวจสอบสถานะล็อกอิน
// ถ้าล็อกอินอยู่แล้ว ไม่จำเป็นต้องสมัครอีก ให้ redirect กลับไปหน้าหลัก
if (isLoggedIn()) {
    header("Location: index.php");
    exit;
}


// ส่วนที่ 2 กำหนดตัวแปรเริ่มต้น
$errorMessage        = '';
$successMessage      = '';
$alertUsernameChange = ''; // เก็บ username ใหม่เพื่อแจ้งเตือนผู้ใช้ (กรณีนักศึกษา)


// ส่วนที่ 3 ประมวลผลฟอร์มสมัครสมาชิก (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

// รับข้อมูลพื้นฐานจากฟอร์ม
    $inputUsername       = trim($_POST['username'] ?? '');
    $inputPassword       = $_POST['password'] ?? '';
    $inputConfirmPassword = $_POST['confirm_password'] ?? '';

// จัดการคำนำหน้าชื่อ
    // ถ้าผู้ใช้เลือก "อื่นๆ" ให้รับค่าจาก other_prefix field แทน
    $selectedPrefix = $_POST['prefix'] ?? '';
    if ($selectedPrefix === 'other') {
        $namePrefix = trim($_POST['other_prefix'] ?? '');
    } else {
        $namePrefix = trim($selectedPrefix);
    }

    // รวมคำนำหน้าและชื่อ-นามสกุลเข้าด้วยกัน
    $fullName = $namePrefix . trim($_POST['name'] ?? '');


// รับข้อมูลประเภทผู้ใช้
    $userType         = $_POST['user_type'] ?? 'outsider';
    $occupation       = null;
    $faculty          = null;
    $major            = null;
    $studentId        = null;
    $academicYear     = null;
    // ผู้สมัครใหม่ทุกคนเริ่มต้นด้วย role เป็น 'member' และไม่มีตำแหน่งกรรมการ
    // แอดมินจะเป็นผู้กำหนดสิทธิ์และตำแหน่งให้ในภายหลังผ่านระบบจัดการสมาชิก
    $userRole          = 'member';
    $committeePosition = null;

// รับข้อมูลเพิ่มเติมตามประเภทผู้ใช้
    if ($userType === 'student') {
        // นักศึกษา: รับข้อมูลที่เกี่ยวข้องกับการเป็นนักศึกษา
        $occupation   = 'นักศึกษา';
        $faculty_id   = !empty($_POST['faculty_id']) ? (int)$_POST['faculty_id'] : null;
        $major_id     = !empty($_POST['major_id']) ? (int)$_POST['major_id'] : null;
        $studentId    = trim($_POST['student_id'] ?? '');

        // ดึงชื่อคณะและสาขาเก็บไว้ด้วยเพื่อความเข้ากันได้ย้อนหลัง
        $faculty = null;
        $major   = null;
        if ($faculty_id) {
            $fStmt = $conn->prepare("SELECT faculty_name FROM faculties WHERE faculty_id = ?");
            $fStmt->bind_param("i", $faculty_id);
            $fStmt->execute();
            $fRes = $fStmt->get_result()->fetch_assoc();
            if ($fRes) $faculty = $fRes['faculty_name'];
            $fStmt->close();
        }
        if ($major_id) {
            $mStmt = $conn->prepare("SELECT major_name FROM majors WHERE major_id = ?");
            $mStmt->bind_param("i", $major_id);
            $mStmt->execute();
            $mRes = $mStmt->get_result()->fetch_assoc();
            if ($mRes) $major = $mRes['major_name'];
            $mStmt->close();
        }

        // แปลงชั้นปีเป็นตัวเลข ถ้าเป็น 0 ให้บันทึกเป็น null
        $academicYearValue = (int)($_POST['academic_year'] ?? 0);
        if ($academicYearValue > 0) {
            $academicYear = $academicYearValue;
        } else {
            $academicYear = null;
        }

    } else {
        // บุคคลทั่วไป: รับเฉพาะอาชีพ
        $occupation = trim($_POST['occupation'] ?? '');
        $faculty_id = null;
        $major_id   = null;
        $faculty    = null;
        $major      = null;
    }


// ตรวจสอบรหัสผ่าน
    if ($inputPassword !== $inputConfirmPassword) {
        $errorMessage = "รหัสผ่านและการยืนยันรหัสผ่านไม่ตรงกัน";

    } else {

// กำหนด Username ที่จะใช้จริง
        // สำหรับนักศึกษา: ระบบจะใช้รหัสนักศึกษาเป็น Username โดยอัตโนมัติ
        $actualUsername = $inputUsername;

        if ($userType === 'student' && !empty($studentId)) {
            $actualUsername = $studentId;
        }

// ตรวจสอบว่า Username ซ้ำกับที่มีในระบบแล้วหรือไม่
        $checkStatement = $conn->prepare("SELECT user_id FROM users WHERE username = ?");
        $checkStatement->bind_param("s", $actualUsername);
        $checkStatement->execute();
        $checkResult = $checkStatement->get_result();

        if ($checkResult->num_rows > 0) {
            // Username ถูกใช้งานแล้ว
            if ($userType === 'student') {
                $errorMessage = "ชื่อผู้ใช้นี้ถูกใช้งานแล้ว (รหัสนักศึกษา {$actualUsername} ถูกใช้แล้ว)";
            } else {
                $errorMessage = "ชื่อผู้ใช้นี้ถูกใช้งานแล้ว";
            }

        } else {

// Username ยังไม่มีในระบบ: ดำเนินการสร้างบัญชีใหม่
            // เข้ารหัสรหัสผ่านด้วย bcrypt (PASSWORD_DEFAULT) เพื่อความปลอดภัย
            $hashedPassword = password_hash($inputPassword, PASSWORD_DEFAULT);

            // เตรียม SQL สำหรับ INSERT ข้อมูลผู้ใช้ใหม่
            $insertStatement = $conn->prepare(
                "INSERT INTO users
                    (username, password, name, user_type, occupation, faculty_id, major_id, faculty, major, student_id, role, committee_position, academic_year)
                 VALUES
                    (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );

            // ผูกตัวแปรกับ Prepared Statement
            $insertStatement->bind_param(
                "sssssiisssssi",
                $actualUsername,
                $hashedPassword,
                $fullName,
                $userType,
                $occupation,
                $faculty_id,
                $major_id,
                $faculty,
                $major,
                $studentId,
                $userRole,
                $committeePosition,
                $academicYear
            );

            // บันทึกข้อมูลลงฐานข้อมูล
            $insertSuccess = $insertStatement->execute();

            if ($insertSuccess) {
// บันทึกสำเร็จ
                // ตรวจสอบว่า Username ถูกเปลี่ยนเป็นรหัสนักศึกษาหรือไม่
                // ถ้าใช่ ต้องแจ้งเตือนผู้ใช้ให้ทราบ username จริงที่ต้องใช้ login
                if ($userType === 'student' && $actualUsername !== $inputUsername) {
                    $successMessage      = "สมัครสมาชิกสำเร็จ!";
                    $alertUsernameChange = $actualUsername;
                } else {
                    $successMessage = "สมัครสมาชิกสำเร็จ กรุณาเข้าสู่ระบบ";
                }

            } else {
                // บันทึกไม่สำเร็จ
                $errorMessage = "เกิดข้อผิดพลาดในการลงทะเบียน กรุณาลองใหม่อีกครั้ง";
            }

            $insertStatement->close();
        }

        $checkStatement->close();
    }
}


// ดึงรายชื่อคณะทั้งหมดสำหรับแสดงใน Dropdown
$facultiesList = [];
$facQuery = $conn->query("SELECT faculty_id, faculty_name FROM faculties ORDER BY faculty_id ASC");
if ($facQuery) {
    while ($fRow = $facQuery->fetch_assoc()) {
        $facultiesList[] = $fRow;
    }
}

// ส่วนที่ 4 แสดงหน้า HTML
include 'includes/header.php';
?>

<div class="container d-flex justify-center align-center" style="min-height: 80vh; padding: 20px 0;">
    <div class="card" style="max-width: 540px; width: 100%; border: 1px solid var(--border-color); box-shadow: none;">

        <!-- ส่วนหัวของฟอร์ม -->
        <div class="text-center mb-4">
            <h2 class="m-0" style="color: #000;">สมัครสมาชิก</h2>
        </div>

        <!-- แสดงข้อความ error ถ้ามี -->
        <?php if ($errorMessage !== ''): ?>
            <div class="alert alert-danger text-center mb-3">
                <?= htmlspecialchars($errorMessage) ?>
            </div>
        <?php endif; ?>

        <!-- แสดงข้อความสำเร็จ พร้อมการแจ้งเตือนและปุ่มไปล็อกอิน -->
        <?php if ($successMessage !== ''): ?>
            <div class="alert alert-success text-center mb-3">
                <?= htmlspecialchars($successMessage) ?>
            </div>

            <!-- แจ้งเตือน username ที่ถูกเปลี่ยน (สำหรับนักศึกษา) -->
            <?php if (!empty($alertUsernameChange)): ?>
                <div class="alert alert-warning mb-3">
                    <div class="font-weight-bold mb-1">
                        สำคัญ!
                    </div>
                    <div class="text-sm">
                        Username ของคุณถูกเปลี่ยนเป็นรหัสนักศึกษา:
                        <strong class="text-danger"><?= htmlspecialchars($alertUsernameChange) ?></strong>
                    </div>
                    <div class="text-sm mt-1">
                        กรุณาใช้ <strong>รหัสนักศึกษา</strong> เป็น Username ในการเข้าสู่ระบบทุกครั้ง
                    </div>
                </div>
            <?php endif; ?>

            <div class="text-center mt-4">
                <a href="login.php" class="btn btn-primary">ไปหน้าเข้าสู่ระบบ</a>
            </div>

        <?php else: ?>

            <!-- ฟอร์มสมัครสมาชิก (แสดงเฉพาะเมื่อยังไม่สำเร็จ) -->
            <form action="register.php" method="POST" id="registerForm">

                <!-- ฟิลด์ Username -->
                <div class="form-group">
                    <label class="form-label">ชื่อผู้ใช้ </label>
                    <input type="text" name="username" class="form-control" required>
                    <div class="text-xs text-muted mt-1" id="usernameHint"></div>
                </div>

                <!-- ฟิลด์ Password -->
                <div class="form-group mt-4">
                    <label class="form-label">รหัสผ่าน </label>
                    <input type="password" name="password" id="password" class="form-control" required minlength="6">
                </div>

                <!-- ฟิลด์ Confirm Password -->
                <div class="form-group mb-4">
                    <label class="form-label">ยืนยันรหัสผ่าน </label>
                    <input type="password" name="confirm_password" id="confirm_password" class="form-control" required minlength="6">
                    <div id="passwordError" class="text-danger text-sm mt-1 d-none">รหัสผ่านไม่ตรงกัน</div>
                </div>

                <!-- ฟิลด์คำนำหน้าและชื่อ-นามสกุล -->
                <div class="d-flex gap-2 mb-3">
                    <div class="form-group" style="width: 150px;">
                        <label class="form-label">คำนำหน้า</label>
                        <select name="prefix" id="prefix" class="form-control" required>
                            <option value="">- เลือก -</option>
                            <option value="นาย">นาย</option>
                            <option value="นางสาว">นางสาว</option>
                            <option value="นาง">นาง</option>
                            <option value="other">อื่นๆ (ระบุ)</option>
                        </select>
                    </div>
                    <div class="form-group" style="flex: 1;">
                        <label class="form-label">ชื่อ-นามสกุล</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                </div>

                <!-- ฟิลด์คำนำหน้าแบบระบุเอง (ซ่อนอยู่ แสดงเมื่อเลือก "อื่นๆ") -->
                <div class="form-group d-none" id="otherPrefixContainer">
                    <label class="form-label">ระบุคำนำหน้าชื่อ</label>
                    <input type="text" name="other_prefix" id="other_prefix" class="form-control">
                </div>

                <!-- เลือกประเภทผู้ใช้งาน -->
                <div class="bg-main p-3 rounded mb-3">
                    <label class="form-label mb-2">สถานะผู้ใช้งาน</label>
                    <div class="d-flex gap-3">
                        <label class="cursor-pointer">
                            <input type="radio" name="user_type" value="outsider" checked> บุคคลภายนอกทั่วไป
                        </label>
                        <label class="cursor-pointer">
                            <input type="radio" name="user_type" value="student"> นิสิต / นักศึกษา
                        </label>
                    </div>
                </div>

                <!-- ฟิลด์สำหรับบุคคลทั่วไป (แสดงโดยค่าเริ่มต้น) -->
                <div id="outsiderFields" class="form-group">
                    <label class="form-label">อาชีพ</label>
                    <input type="text" name="occupation" id="occupation" class="form-control" placeholder="เช่น พนักงานบริษัท, ธุรกิจส่วนตัว">
                </div>

                <!-- ฟิลด์สำหรับนักศึกษา (ซ่อนอยู่ แสดงเมื่อเลือก "นักศึกษา") -->
                <div id="studentFields" class="bg-blue-light p-3 rounded mb-3 d-none">
                    <div class="form-group">
                        <label class="form-label text-primary-dark">รหัสนักศึกษา</label>
                        <input type="text" name="student_id" id="student_id" class="form-control" placeholder="8 หลัก" pattern="\d{8}" title="ตัวเลข 8 หลัก">
                    </div>
                    <div class="form-group">
                        <label class="form-label text-primary-dark">คณะ</label>
                        <select name="faculty_id" id="faculty_select" class="form-control">
                            <option value="">-- กรุณาเลือกคณะ --</option>
                            <?php foreach ($facultiesList as $fac): ?>
                                <option value="<?= $fac['faculty_id'] ?>">
                                    <?= htmlspecialchars($fac['faculty_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label text-primary-dark">สาขาวิชา</label>
                        <select name="major_id" id="major_select" class="form-control" disabled>
                            <option value="">-- กรุณาเลือกคณะก่อน --</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label text-primary-dark">ชั้นปี</label>
                        <select name="academic_year" id="academic_year" class="form-control">
                            <option value="0">-- เลือกชั้นปี --</option>
                            <option value="1">ปี 1</option>
                            <option value="2">ปี 2</option>
                            <option value="3">ปี 3</option>
                            <option value="4">ปี 4</option>
                        </select>
                    </div>

                    <!-- ระดับผู้ใช้ (เฉพาะนักศึกษา) -->
                    <div class="bg-main p-3 rounded mb-3">
                        <label class="form-label mb-2">ระดับผู้ใช้</label>
                        <div class="d-flex gap-3">
                            <label class="cursor-pointer">
                                <input type="radio" name="user_level" value="member" checked> สมาชิก
                            </label>
                            <label class="cursor-pointer">
                                <input type="radio" name="user_level" value="committee"> คณะกรรมการ
                            </label>
                        </div>
                    </div>

                    <!-- ตำแหน่งคณะกรรมการ (แสดงเมื่อเลือกคณะกรรมการ) -->
                    <div id="committeePositionField" class="form-group d-none">
                        <label class="form-label">ตำแหน่งคณะกรรมการ</label>
                        <select name="committee_position" id="committee_position" class="form-control">
                            <option value="">-- เลือกตำแหน่ง --</option>
                            <option value="president">ประธาน</option>
                            <option value="vice_president">รองประธาน</option>
                            <option value="treasurer">เหรัญญิก</option>
                            <option value="secretary_saluang">เลขาสะลวง</option>
                            <option value="secretary_wiangbua">เลขาเวียงบัว</option>
                            <option value="nurse">พยาบาล</option>
                            <option value="venue_coordinator">ฝ่ายสถานที่</option>
                            <option value="pr">ฝ่ายประชาสัมพันธ์</option>
                        </select>
                    </div>
                </div>

                <!-- ปุ่ม Submit -->
                <button type="submit" class="btn btn-primary btn-block" style="box-shadow: none !important;">สมัครสมาชิก</button>
            </form>

            <!-- ลิงก์ไปหน้าล็อกอิน -->
            <div class="text-center mt-4 text-sm">
                มีบัญชีอยู่แล้ว? <a href="login.php" class="text-link font-weight-bold" style="text-decoration: none !important; border-bottom: none !important; box-shadow: none !important; outline: none !important;">เข้าสู่ระบบ</a>
            </div>

        <?php endif; ?>

    </div>
</div>

<!-- เรียกใช้ไฟล์ JavaScript สำหรับ logic ฟอร์มสมัครสมาชิก -->
<script src="assets/js/auth.js"></script>

<script>
// โค้ด JavaScript จัดการ Dropdown เลือกคณะและโหลดสาขาวิชา (AJAX Fetch)
const facultySelect = document.getElementById('faculty_select');
const majorSelect = document.getElementById('major_select');

if (facultySelect && majorSelect) {
    // ดักจับเมื่อผู้ใช้เลือกเปลี่ยนคณะ
    facultySelect.addEventListener('change', function() {
        const facultyId = this.value;

        // ถ้ายังไม่ได้เลือกคณะ ให้ปิดการใช้งานและรีเซ็ตค่า
        if (!facultyId) {
            majorSelect.innerHTML = '<option value="">-- กรุณาเลือกคณะก่อน --</option>';
            majorSelect.disabled = true;
            return;
        }

        // เมื่อเลือกคณะแล้ว ให้เปิดการใช้งานและแสดงสถานะกำลังโหลด
        majorSelect.disabled = false;
        majorSelect.innerHTML = '<option value="">-- กำลังโหลดสาขาวิชา... --</option>';

        // ยิง fetch ไปที่ ajax_get_majors.php เพื่อขอข้อมูลสาขา
        fetch('ajax_get_majors.php?faculty_id=' + encodeURIComponent(facultyId))
            .then(function(res) {
                return res.json();
            })
            .then(function(data) {
                majorSelect.innerHTML = '<option value="">-- กรุณาเลือกสาขาวิชา --</option>';
                data.forEach(function(m) {
                    const opt = document.createElement('option');
                    opt.value = m.major_id;
                    opt.textContent = m.major_name;
                    majorSelect.appendChild(opt);
                });
            })
            .catch(function(err) {
                console.error(err);
                majorSelect.innerHTML = '<option value="">โหลดข้อมูลสาขาวิชาไม่สำเร็จ</option>';
            });
    });
}
</script>

<?php include 'includes/footer.php'; ?>