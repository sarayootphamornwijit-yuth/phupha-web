<?php
// edit_profile.php
// ไฟล์นี้ทำหน้าที่แสดงฟอร์มและประมวลผลการแก้ไขข้อมูลส่วนตัวของผู้ใช้
// รวมถึงการอัปโหลดรูปโปรไฟล์

require_once 'config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();
}


// ส่วนที่ 1 ตรวจสอบสิทธิ์การเข้าถึง
// เฉพาะผู้ที่ล็อกอินแล้วเท่านั้นถึงจะแก้ไขโปรไฟล์ได้
if (!isLoggedIn()) {
    header("Location: login.php");
    exit;
}


// ส่วนที่ 2 ดึงข้อมูลผู้ใช้ปัจจุบัน
$currentUserId = $_SESSION['user_id'];

// ดึงข้อมูลทั้งหมดของผู้ใช้จากฐานข้อมูล
$fetchUserStatement = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$fetchUserStatement->bind_param("i", $currentUserId);
$fetchUserStatement->execute();
$userRecord = $fetchUserStatement->get_result()->fetch_assoc();
$fetchUserStatement->close();

// ตรวจสอบว่าเป็นนักศึกษาหรือบุคคลทั่วไป เพื่อแสดงฟิลด์ที่แตกต่างกัน
$isStudent = ($userRecord['user_type'] === 'student');


// ส่วนที่ 3 ประมวลผลฟอร์มบันทึกข้อมูล (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {

    // รับข้อมูลทั่วไปจากฟอร์ม
    $ageValue       = (int)($_POST['age'] ?? 0);
    $locationValue  = trim($_POST['location'] ?? '');
    $phoneValue     = trim($_POST['phone'] ?? '');
    $interestsValue = trim($_POST['interests'] ?? '');

// จัดการการอัปโหลดรูปโปรไฟล์
    // เริ่มต้นด้วยรูปเดิมที่มีอยู่แล้ว
    $imagePathForDatabase = $userRecord['image'];

    // ตรวจสอบว่ามีการอัปโหลดไฟล์มาหรือไม่
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {

        $tempFilePath = $_FILES['image']['tmp_name'];

// ตรวจสอบประเภทไฟล์ด้วย MIME Type (ปลอดภัยกว่าการดูนามสกุลเพียงอย่างเดียว)
        $fileInfoResource = finfo_open(FILEINFO_MIME_TYPE);
        $detectedMimeType = finfo_file($fileInfoResource, $tempFilePath);
        finfo_close($fileInfoResource);

        // รายการ MIME Type และนามสกุลที่อนุญาต
        $allowedMimeTypes      = ['image/jpeg', 'image/png'];
        $fileExtension         = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        $allowedFileExtensions = ['jpg', 'jpeg', 'png'];

        // ตรวจสอบทั้ง MIME Type และนามสกุลไฟล์
        $isMimeAllowed      = in_array($detectedMimeType, $allowedMimeTypes);
        $isExtensionAllowed = in_array($fileExtension, $allowedFileExtensions);

        if ($isMimeAllowed && $isExtensionAllowed) {

            // สร้างชื่อไฟล์ใหม่ที่ไม่ซ้ำกัน โดยใช้ user_id และ timestamp
            $newFileName       = 'user_' . $currentUserId . '_' . time() . '.' . $fileExtension;
            $uploadDirectory   = 'uploads/profiles/';

            // สร้างโฟลเดอร์ถ้ายังไม่มี
            if (!is_dir($uploadDirectory)) {
                mkdir($uploadDirectory, 0777, true);
            }

            $targetFilePath = $uploadDirectory . $newFileName;

            // ย้ายไฟล์จาก temp ไปยังโฟลเดอร์ที่กำหนด
            $isUploadSuccess = move_uploaded_file($tempFilePath, $targetFilePath);

            if ($isUploadSuccess) {
                // ลบรูปเก่าออกจาก server เพื่อประหยัดพื้นที่
                if (!empty($userRecord['image']) && file_exists($userRecord['image'])) {
                    unlink($userRecord['image']);
                }
                $imagePathForDatabase = $targetFilePath;
            }

        } else {
            // ประเภทไฟล์ไม่ได้รับอนุญาต
            $_SESSION['alert'] = [
                'type'  => 'error',
                'title' => 'ไฟล์ไม่รองรับ',
                'text'  => 'กรุณาอัปโหลดรูปภาพ JPG หรือ PNG เท่านั้น',
            ];
            header("Location: edit_profile.php");
            exit;
        }
    }

// อัปเดตข้อมูลในฐานข้อมูล (SQL แยกตามประเภทผู้ใช้)
    if ($isStudent) {
        // นักศึกษา: อัปเดตข้อมูลเพิ่มเติม เช่น รหัสนักศึกษา คณะ สาขา
        $occupationValue  = 'นักศึกษา';
        $facultyIdValue   = !empty($_POST['faculty_id']) ? (int)$_POST['faculty_id'] : null;
        $majorIdValue     = !empty($_POST['major_id']) ? (int)$_POST['major_id'] : null;
        $studentIdValue   = trim($_POST['student_id'] ?? '');

        // ค้นหาชื่อคณะและชื่อสาขาเพื่อเก็บไว้ใน faculty และ major สำหรับความเข้ากันได้ย้อนหลัง
        $facultyValue = null;
        $majorValue   = null;
        if ($facultyIdValue) {
            $fStmt = $conn->prepare("SELECT faculty_name FROM faculties WHERE faculty_id = ?");
            $fStmt->bind_param("i", $facultyIdValue);
            $fStmt->execute();
            $fRes = $fStmt->get_result()->fetch_assoc();
            if ($fRes) $facultyValue = $fRes['faculty_name'];
            $fStmt->close();
        }
        if ($majorIdValue) {
            $mStmt = $conn->prepare("SELECT major_name FROM majors WHERE major_id = ?");
            $mStmt->bind_param("i", $majorIdValue);
            $mStmt->execute();
            $mRes = $mStmt->get_result()->fetch_assoc();
            if ($mRes) $majorValue = $mRes['major_name'];
            $mStmt->close();
        }

        $updateStatement = $conn->prepare(
            "UPDATE users
             SET age = ?, location = ?, phone = ?, interests = ?,
                 occupation = ?, faculty_id = ?, major_id = ?, faculty = ?, major = ?, student_id = ?, image = ?
             WHERE user_id = ?"
        );
        $updateStatement->bind_param(
            "issssiissssi",
            $ageValue,
            $locationValue,
            $phoneValue,
            $interestsValue,
            $occupationValue,
            $facultyIdValue,
            $majorIdValue,
            $facultyValue,
            $majorValue,
            $studentIdValue,
            $imagePathForDatabase,
            $currentUserId
        );

    } else {
        // บุคคลทั่วไป: อัปเดตเฉพาะข้อมูลทั่วไป
        $occupationValue = trim($_POST['occupation'] ?? '');

        $updateStatement = $conn->prepare(
            "UPDATE users
             SET age = ?, location = ?, phone = ?, interests = ?, occupation = ?, image = ?
             WHERE user_id = ?"
        );
        $updateStatement->bind_param(
            "isssssi",
            $ageValue,
            $locationValue,
            $phoneValue,
            $interestsValue,
            $occupationValue,
            $imagePathForDatabase,
            $currentUserId
        );
    }

    // ดำเนินการ UPDATE
    $isUpdateSuccess = $updateStatement->execute();

    if ($isUpdateSuccess) {
        $_SESSION['alert'] = [
            'type'  => 'success',
            'title' => 'อัปเดตสำเร็จ',
            'text'  => 'บันทึกข้อมูลส่วนตัวเรียบร้อยแล้ว',
        ];
    } else {
        $_SESSION['alert'] = [
            'type'  => 'error',
            'title' => 'เกิดข้อผิดพลาด',
            'text'  => 'ไม่สามารถบันทึกข้อมูลได้ กรุณาลองใหม่อีกครั้ง',
        ];
    }

    $updateStatement->close();

    // Redirect กลับมาที่หน้าเดิม (PRG Pattern เพื่อป้องกัน double submit)
    header("Location: edit_profile.php");
    exit;
}


// ดึงข้อมูลคณะทั้งหมดสำหรับแสดงใน Dropdown
$facultiesList = [];
$facQuery = $conn->query("SELECT faculty_id, faculty_name FROM faculties ORDER BY faculty_id ASC");
if ($facQuery) {
    while ($fRow = $facQuery->fetch_assoc()) {
        $facultiesList[] = $fRow;
    }
}

// ตรวจสอบคณะและสาขาปัจจุบันของผู้ใช้
$currentFacultyId = !empty($userRecord['faculty_id']) ? (int)$userRecord['faculty_id'] : 0;
$currentMajorId   = !empty($userRecord['major_id']) ? (int)$userRecord['major_id'] : 0;

// ถ้ายังไม่มี faculty_id แต่มีชื่อคณะเดิม ให้ค้นหา faculty_id ที่ตรงกัน
if ($currentFacultyId === 0 && !empty($userRecord['faculty'])) {
    foreach ($facultiesList as $fac) {
        if ($fac['faculty_name'] === $userRecord['faculty']) {
            $currentFacultyId = (int)$fac['faculty_id'];
            break;
        }
    }
}

// ดึงสาขาวิชาของคณะปัจจุบันมารอไว้ใน Dropdown
$currentMajorsList = [];
if ($currentFacultyId > 0) {
    $mStmt = $conn->prepare("SELECT major_id, major_name FROM majors WHERE faculty_id = ? ORDER BY major_name ASC");
    $mStmt->bind_param("i", $currentFacultyId);
    $mStmt->execute();
    $mRes = $mStmt->get_result();
    while ($mRow = $mRes->fetch_assoc()) {
        $currentMajorsList[] = $mRow;
        // กรณีมีชื่อสาขาเดิมแต่ยังไม่มี major_id ให้จับคู่
        if ($currentMajorId === 0 && !empty($userRecord['major']) && $mRow['major_name'] === $userRecord['major']) {
            $currentMajorId = (int)$mRow['major_id'];
        }
    }
    $mStmt->close();
}

// ส่วนที่ 4 แสดงหน้า HTML
include 'includes/header.php';
?>

<!-- แสดง Flash Alert (จาก Session) ถ้ามีการแจ้งเตือนจากการบันทึก -->
<?php if (isset($_SESSION['alert'])): ?>
    <div id="flash-message"
         data-type="<?= $_SESSION['alert']['type'] ?>"
         data-title="<?= $_SESSION['alert']['title'] ?>"
         data-text="<?= $_SESSION['alert']['text'] ?>"
         class="d-none">
    </div>
    <?php unset($_SESSION['alert']); ?>
<?php endif; ?>


<div class="container py-4" style="max-width: 600px;">
    <div class="card">
        <h2 class="section-title mb-4">แก้ไขข้อมูลส่วนตัว</h2>

        <form action="edit_profile.php" method="POST" enctype="multipart/form-data">
<?php csrfField(); ?>
            <input type="hidden" name="action" value="update_profile">

<!-- ส่วนอัปโหลดรูปโปรไฟล์ -->
            <div class="text-center mb-4">

                <!-- แสดง preview รูปโปรไฟล์ -->
                <div class="rounded-circle overflow-hidden bg-main d-flex align-center justify-center shadow-sm mx-auto mb-3 position-relative"
                     style="width: 120px; height: 120px; border: 3px solid var(--text-light);">

                    <?php
                    // ถ้ามีรูปอยู่แล้ว ให้แสดงรูป และซ่อนไอคอน User
                    $hasExistingImage = !empty($userRecord['image']);
                    $imageDisplayClass = $hasExistingImage ? '' : 'd-none';
                    $iconDisplayClass  = $hasExistingImage ? 'd-none' : '';
                    ?>

                    <img id="imgPreview"
                         src="<?= $hasExistingImage ? htmlspecialchars($userRecord['image']) : '' ?>"
                         class="<?= $imageDisplayClass ?>"
                         style="width: 100%; height: 100%; object-fit: cover;"
                         alt="รูปโปรไฟล์">

                    <i id="iconPreview" class="fa-solid fa-user text-light fa-3x <?= $iconDisplayClass ?>"></i>
                </div>

                <!-- ปุ่มเลือกรูป -->
                <label for="imageUpload" class="badge badge-primary cursor-pointer text-sm px-3 py-2">
                    เลือกรูปโปรไฟล์
                </label>

                <!-- Input file (ซ่อน ควบคุมผ่าน label ด้านบน) -->
                <input type="file"
                       id="imageUpload"
                       name="image"
                       accept="image/jpeg, image/png"
                       class="d-none">

                <div class="text-xs text-muted mt-2">รองรับไฟล์ JPG, PNG ขนาดไม่เกิน 2MB</div>
            </div>

<!-- ฟิลด์ชื่อ-นามสกุล (ไม่ให้แก้ไข) -->
            <div class="form-group">
                <label class="form-label text-muted">ชื่อ-นามสกุล (ไม่สามารถแก้ไขได้)</label>
                <input type="text"
                       class="form-control bg-main text-muted cursor-default"
                       value="<?= htmlspecialchars($userRecord['name']) ?>"
                       disabled>
            </div>

<!-- ฟิลด์อายุและเบอร์โทร -->
            <div class="grid-2 gap-3 mb-3">
                <div class="form-group">
                    <label class="form-label">อายุ (ปี)</label>
                    <input type="number" name="age" class="form-control" value="<?= $userRecord['age'] ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">เบอร์โทรศัพท์</label>
                    <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($userRecord['phone'] ?? '') ?>">
                </div>
            </div>

<!-- ฟิลด์เฉพาะตามประเภทผู้ใช้ -->
            <?php if ($isStudent): ?>
                <!-- ฟิลด์สำหรับนักศึกษา -->
                <div class="bg-blue-light p-3 rounded mb-3">
                    <div class="form-group">
                        <label class="form-label text-primary-dark">รหัสนักศึกษา</label>
                        <input type="text" name="student_id" class="form-control" value="<?= htmlspecialchars($userRecord['student_id'] ?? '') ?>">
                    </div>
                    <div class="grid-2 gap-3">
                        <div class="form-group m-0">
                            <label class="form-label text-primary-dark">คณะ</label>
                            <select name="faculty_id" id="faculty_select" class="form-control">
                                <option value="">-- กรุณาเลือกคณะ --</option>
                                <?php foreach ($facultiesList as $fac): ?>
                                    <option value="<?= $fac['faculty_id'] ?>" <?= ($currentFacultyId == $fac['faculty_id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($fac['faculty_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group m-0">
                            <label class="form-label text-primary-dark">สาขาวิชา</label>
                            <select name="major_id" id="major_select" class="form-control" <?= empty($currentMajorsList) ? 'disabled' : '' ?>>
                                <?php if (empty($currentMajorsList)): ?>
                                    <option value="">-- กรุณาเลือกคณะก่อน --</option>
                                <?php else: ?>
                                    <option value="">-- กรุณาเลือกสาขาวิชา --</option>
                                    <?php foreach ($currentMajorsList as $maj): ?>
                                        <option value="<?= $maj['major_id'] ?>" <?= ($currentMajorId == $maj['major_id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($maj['major_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <!-- ฟิลด์สำหรับบุคคลทั่วไป -->
                <div class="form-group">
                    <label class="form-label">อาชีพ</label>
                    <input type="text" name="occupation" class="form-control" value="<?= htmlspecialchars($userRecord['occupation'] ?? '') ?>">
                </div>
            <?php endif; ?>

<!-- ฟิลด์ที่อยู่ -->
            <div class="form-group">
                <label class="form-label">สถานที่อยู่ / อำเภอ / จังหวัด</label>
                <input type="text" name="location" class="form-control" value="<?= htmlspecialchars($userRecord['location'] ?? '') ?>">
            </div>

<!-- ฟิลด์ความสนใจ -->
            <div class="form-group mb-4">
                <label class="form-label">ความสนใจในการทำกิจกรรม</label>
                <textarea name="interests" class="form-control" rows="3"><?= htmlspecialchars($userRecord['interests'] ?? '') ?></textarea>
            </div>

<!-- ปุ่ม Submit และ Cancel -->
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-grow-1">บันทึกข้อมูล</button>
                <a href="profile.php" class="btn btn-surface border-light text-muted flex-grow-1 text-center">ยกเลิก</a>
            </div>

        </form>
    </div>
</div>



<!-- JavaScript สำหรับ preview รูปก่อนอัปโหลด -->
<script src="assets/js/edit_profile.js"></script>

<script>
// โค้ด JavaScript สำหรับ Dropdown 2 ชั้น (คณะ -> สาขาวิชา)
const facultySelect = document.getElementById('faculty_select');
const majorSelect = document.getElementById('major_select');

if (facultySelect && majorSelect) {
    // ดักจับเมื่อผู้ใช้เลือกเปลี่ยนคณะ
    facultySelect.addEventListener('change', function() {
        const facultyId = this.value;

        // ถ้าไม่ได้เลือกคณะ ให้ปิดการใช้งานและรีเซ็ต
        if (!facultyId) {
            majorSelect.innerHTML = '<option value="">-- กรุณาเลือกคณะก่อน --</option>';
            majorSelect.disabled = true;
            return;
        }

        // เมื่อเลือกคณะแล้ว ให้เปิดการใช้งานและแสดงสถานะกำลังโหลด
        majorSelect.disabled = false;
        majorSelect.innerHTML = '<option value="">-- กำลังโหลดสาขาวิชา... --</option>';

        // ยิง fetch ไปยัง ajax_get_majors.php เพื่อดึงสาขาของคณะที่เลือก
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