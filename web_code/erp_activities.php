<?php
// erp_activities.php
// ไฟล์นี้ทำหน้าที่ให้คณะกรรมการ (Committee) จัดการกิจกรรมทั้งหมดในระบบ
// รวมถึงการเสนอโครงการใหม่ แก้ไข และลบกิจกรรม

require_once 'config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();
}


// ส่วนที่ 1 ตรวจสอบสิทธิ์
// เฉพาะ committee และ leader เท่านั้น (ในที่นี้คือ committee)
// แอดมิน (admin) ดูแลระบบเฉยๆ ไม่จัดการกิจกรรมโดยตรง
requireCommitteePermission('activities');

$currentUserRole = getCurrentUserRole();


// ฟังก์ชันช่วยเหลือ: แปลงปี พ.ศ. เป็น ค.ศ.
// กรณีที่ผู้ใช้กรอกปีผิด หรือ Browser ส่งปี พ.ศ. มา (เช่น 2566-01-01)
function convertBuddhistEraToChristianEra($datetimeString) {
    if (empty($datetimeString)) {
        return $datetimeString;
    }
    
    // ตรวจสอบแพทเทิร์น YYYY-MM-DD...
    if (preg_match('/^(\d{4})-(.*)/', $datetimeString, $matches)) {
        $yearPart = (int)$matches[1];
        
        // ถ้าปีมากกว่า 2400 ถือว่าเป็นปี พ.ศ. ให้แปลงเป็น ค.ศ.
        if ($yearPart >= 2400) {
            $yearPart -= 543;
            return sprintf('%04d-%s', $yearPart, $matches[2]);
        }
    }
    
    return $datetimeString;
}


// ส่วนที่ 2 จัดการการเพิ่มกิจกรรมใหม่ (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_activity') {
    
    // สร้างตัวแปรรับค่าจากฟอร์ม
    $activityTitle       = trim($_POST['title'] ?? '');
    $activityDescription = trim($_POST['description'] ?? '');
    $facebookLink        = trim($_POST['facebook_link'] ?? '');
    $activityType        = trim($_POST['activity_type'] ?? 'single_day');
    $activityCategory    = trim($_POST['category'] ?? 'volunteer');
    
    $startDate           = convertBuddhistEraToChristianEra(trim($_POST['start_date'] ?? ''));
    $endDate             = convertBuddhistEraToChristianEra(trim($_POST['end_date'] ?? ''));
    
    // ตรวจสอบวันสิ้นสุด ถ้าไม่มีให้ใช้วันเริ่มกิจกรรม
    if (empty($endDate)) {
        $endDate = $startDate;
    }
    
    $locationName      = trim($_POST['location_name'] ?? '');
    $maxParticipants   = (int)($_POST['max_participants'] ?? 0);
    $volunteerPoints   = (int)($_POST['points'] ?? 0);
    $venueContactId    = (int)($_POST['venue_contact_user_id'] ?? 0);
    $meetingPoint      = trim($_POST['meeting_point'] ?? '');
    
    $meetingTimePost   = convertBuddhistEraToChristianEra(trim($_POST['meeting_time'] ?? ''));
    $meetingTime       = $meetingTimePost !== '' ? $meetingTimePost : null;
    
    $isSelfCheckinEnabled = isset($_POST['self_checkin_enabled']) ? 1 : 0;


    // ตรวจสอบและจัดการอัปโหลดไฟล์รูปภาพปก
    $coverImagePathInDB = null;
    
    if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
        $tempFilePath = $_FILES['cover_image']['tmp_name'];
        $originalFileName = $_FILES['cover_image']['name'];

        // ตรวจสอบประเภทไฟล์ (MIME Type)
        $fileInfoHandler = finfo_open(FILEINFO_MIME_TYPE);
        $mimeTypeResult  = finfo_file($fileInfoHandler, $tempFilePath);
        finfo_close($fileInfoHandler);

        $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif'];
        $fileExtension    = strtolower(pathinfo($originalFileName, PATHINFO_EXTENSION));
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];

        // ตรวจสอบนามสกุลไฟล์
        if (in_array($mimeTypeResult, $allowedMimeTypes) && in_array($fileExtension, $allowedExtensions)) {
            
            // สร้างชื่อไฟล์ใหม่
            $newGeneratedFilename = uniqid('cover_') . '_' . time() . '.' . $fileExtension;
            $uploadDirectoryPath  = 'uploads/activities/';

            // สร้างแฟ้มจัดเก็บหากไม่มีแฟ้มอยู่
            if (!is_dir($uploadDirectoryPath)) {
                mkdir($uploadDirectoryPath, 0777, true);
            }

            $finalTargetFile = $uploadDirectoryPath . $newGeneratedFilename;
            
            // ย้ายไฟล์รูปภาพไปเก็บในแฟ้มปลายทาง
            if (move_uploaded_file($tempFilePath, $finalTargetFile)) {
                $coverImagePathInDB = $finalTargetFile;
            }
            
        } else {
            // สร้าง session แจ้งเตือนข้อผิดพลาดเรื่องไฟล์
            $_SESSION['alert'] = [
                'type'  => 'error',
                'title' => 'ไฟล์ไม่ถูกต้อง',
                'text'  => 'กรุณาอัปโหลดไฟล์รูปภาพ (JPG, PNG, GIF) เท่านั้น'
            ];
            header("Location: erp_activities.php");
            exit;
        }
    }


    // เตรียมข้อมูลเพื่อบันทึกลงฐานข้อมูล
    $facebookDbValue = $facebookLink ?: null;
    $venueDbValue    = $venueContactId > 0 ? $venueContactId : null;

    // สร้างคำสั่ง SQL สำหรับเพิ่มข้อมูล
    $insertActivityStatement = $conn->prepare(
        "INSERT INTO activities (
            title, description, cover_image, facebook_link, activity_type, 
            category, start_date, end_date, location_name, meeting_point, 
            meeting_time, max_participants, points, approval_status, 
            venue_contact_user_id, self_checkin_enabled
         ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?)"
    );

    // นำตัวแปรมาผูกกับคำสั่ง SQL
    $insertActivityStatement->bind_param(
        "sssssssssssiiii", 
        $activityTitle, $activityDescription, $coverImagePathInDB, $facebookDbValue, 
        $activityType, $activityCategory, $startDate, $endDate, 
        $locationName, $meetingPoint, $meetingTime, 
        $maxParticipants, $volunteerPoints, $venueDbValue, $isSelfCheckinEnabled
    );

    // ทำการ execute คำสั่ง SQL
    if ($insertActivityStatement->execute()) {
        $_SESSION['alert'] = [
            'type'  => 'success',
            'title' => 'ร่างกิจกรรมสำเร็จ!',
            'text'  => 'ส่งคำขอไปยังอาจารย์ที่ปรึกษาเรียบร้อยแล้ว'
        ];
    } else {
        $_SESSION['alert'] = [
            'type'  => 'error',
            'title' => 'เกิดข้อผิดพลาด',
            'text'  => 'ไม่สามารถบันทึกข้อมูลได้'
        ];
    }

    $insertActivityStatement->close();
    header("Location: erp_activities.php");
    exit;
}


// ส่วนที่ 3 จัดการแก้ไขกิจกรรม (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_activity') {
    
    // ตรวจสอบสิทธิ์ว่าผู้ใช้เป็นคณะกรรมการหรือผู้นำหรือไม่
    if (!in_array($currentUserRole, ['committee', 'leader', 'admin'])) {
        $_SESSION['alert'] = [
            'type'  => 'error',
            'title' => 'ไม่มีสิทธิ์',
            'text'  => 'เฉพาะคณะกรรมการเท่านั้นที่สามารถแก้ไขกิจกรรมได้'
        ];
        header("Location: erp_activities.php");
        exit;
    }

    // สร้างตัวแปรรับค่าจากฟอร์มสำหรับแก้ไข
    $editActivityId      = (int)($_POST['edit_id'] ?? 0);
    $activityTitle       = trim($_POST['title'] ?? '');
    $activityDescription = trim($_POST['description'] ?? '');
    $facebookLink        = trim($_POST['facebook_link'] ?? '');
    $activityType        = trim($_POST['activity_type'] ?? 'single_day');
    $activityCategory    = trim($_POST['category'] ?? 'volunteer');
    
    $startDate           = convertBuddhistEraToChristianEra(trim($_POST['start_date'] ?? ''));
    $endDate             = convertBuddhistEraToChristianEra(trim($_POST['end_date'] ?? ''));
    // ตรวจสอบวันสิ้นสุด ถ้าไม่มีให้ใช้วันเริ่มกิจกรรม
    if (empty($endDate)) {
        $endDate = $startDate;
    }
    
    $locationName      = trim($_POST['location_name'] ?? '');
    $meetingPoint      = trim($_POST['meeting_point'] ?? '');
    $meetingTimePost   = convertBuddhistEraToChristianEra(trim($_POST['meeting_time'] ?? ''));
    $meetingTime       = $meetingTimePost !== '' ? $meetingTimePost : null;
    $maxParticipants   = (int)($_POST['max_participants'] ?? 0);
    $volunteerPoints   = (int)($_POST['points'] ?? 0);
    $venueContactId    = (int)($_POST['venue_contact_user_id'] ?? 0);
    
    $venueDbValue      = $venueContactId > 0 ? $venueContactId : null;
    $facebookDbValue   = $facebookLink !== '' ? $facebookLink : null;
    $isSelfCheckinEnabled = isset($_POST['self_checkin_enabled']) ? 1 : 0;


    // ตรวจสอบการอัปโหลดไฟล์รูปภาพใหม่
    $coverImagePathInDB = null;
    $isCoverImageUpdated = false;
    
    if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
        $tempFilePath = $_FILES['cover_image']['tmp_name'];
        $originalFileName = $_FILES['cover_image']['name'];
        
        $fileInfoHandler = finfo_open(FILEINFO_MIME_TYPE);
        $mimeTypeResult  = finfo_file($fileInfoHandler, $tempFilePath);
        finfo_close($fileInfoHandler);
        
        $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif'];
        $fileExtension    = strtolower(pathinfo($originalFileName, PATHINFO_EXTENSION));
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];

        // ตรวจสอบประเภทไฟล์
        if (in_array($mimeTypeResult, $allowedMimeTypes) && in_array($fileExtension, $allowedExtensions)) {
            // สร้างชื่อไฟล์รูปภาพใหม่
            $newGeneratedFilename = uniqid('cover_') . '_' . time() . '.' . $fileExtension;
            $uploadDirectoryPath  = 'uploads/activities/';
            
            // สร้างแฟ้มจัดเก็บหากไม่มีแฟ้มอยู่
            if (!is_dir($uploadDirectoryPath)) {
                mkdir($uploadDirectoryPath, 0777, true);
            }
            
            $finalTargetFile = $uploadDirectoryPath . $newGeneratedFilename;
            // ย้ายไฟล์ไปเก็บในปลายทาง
            if (move_uploaded_file($tempFilePath, $finalTargetFile)) {
                $coverImagePathInDB = $finalTargetFile;
                $isCoverImageUpdated = true;
            }
        }
    }


    // ตรวจสอบเงื่อนไขไอดีกิจกรรม
    if ($editActivityId > 0 && $activityTitle !== '') {
        // ตรวจสอบว่ามีการอัปเดตรูปภาพหรือไม่
        if ($isCoverImageUpdated) {
            // สร้างคำสั่ง SQL สำหรับอัปเดตข้อมูลรวมถึงรูปภาพ
            $updateStatement = $conn->prepare(
                "UPDATE activities SET 
                    title = ?, description = ?, facebook_link = ?, cover_image = ?, 
                    activity_type = ?, category = ?, start_date = ?, end_date = ?, 
                    location_name = ?, meeting_point = ?, meeting_time = ?, 
                    max_participants = ?, points = ?, self_checkin_enabled = ?, 
                    venue_contact_user_id = ?, 
                    approval_status = IF(approval_status = 'rejected', 'pending', approval_status) 
                 WHERE activity_id = ?"
            );
            $updateStatement->bind_param(
                "sssssssssssiiiii", 
                $activityTitle, $activityDescription, $facebookDbValue, $coverImagePathInDB, 
                $activityType, $activityCategory, $startDate, $endDate, 
                $locationName, $meetingPoint, $meetingTime, 
                $maxParticipants, $volunteerPoints, $isSelfCheckinEnabled, 
                $venueDbValue, $editActivityId
            );
        } else {
            // สร้างคำสั่ง SQL สำหรับอัปเดตข้อมูลโดยไม่มีรูปภาพ
            $updateStatement = $conn->prepare(
                "UPDATE activities SET 
                    title = ?, description = ?, facebook_link = ?, 
                    activity_type = ?, category = ?, start_date = ?, end_date = ?, 
                    location_name = ?, meeting_point = ?, meeting_time = ?, 
                    max_participants = ?, points = ?, self_checkin_enabled = ?, 
                    venue_contact_user_id = ?, 
                    approval_status = IF(approval_status = 'rejected', 'pending', approval_status) 
                 WHERE activity_id = ?"
            );
            $updateStatement->bind_param(
                "ssssssssssiiiii", 
                $activityTitle, $activityDescription, $facebookDbValue, 
                $activityType, $activityCategory, $startDate, $endDate, 
                $locationName, $meetingPoint, $meetingTime, 
                $maxParticipants, $volunteerPoints, $isSelfCheckinEnabled, 
                $venueDbValue, $editActivityId
            );
        }

        // ทำการ execute คำสั่ง SQL
        if ($updateStatement->execute()) {
            $_SESSION['alert'] = [
                'type'  => 'success',
                'title' => 'แก้ไขสำเร็จ!',
                'text'  => 'อัปเดตข้อมูลกิจกรรมเรียบร้อยแล้ว (หากถูกตีกลับ สถานะจะเปลี่ยนเป็นรออนุมัติ)'
            ];
        } else {
            $_SESSION['alert'] = [
                'type'  => 'error',
                'title' => 'เกิดข้อผิดพลาด',
                'text'  => 'ไม่สามารถอัปเดตข้อมูลได้'
            ];
        }
        $updateStatement->close();
    }

    header("Location: erp_activities.php");
    exit;
}


// ส่วนที่ 4 จัดการลบกิจกรรม (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_activity') {
    
    // ตรวจสอบสิทธิ์ว่าผู้ใช้เป็นคณะกรรมการหรือไม่
    if (!in_array($currentUserRole, ['committee', 'leader', 'admin'])) {
        $_SESSION['alert'] = [
            'type'  => 'error',
            'title' => 'ไม่มีสิทธิ์',
            'text'  => 'เฉพาะคณะกรรมการเท่านั้นที่สามารถลบกิจกรรมได้'
        ];
    } else {
        // สร้างตัวแปรรับค่าไอดีกิจกรรมที่ต้องการลบ
        $deleteActivityId = (int)($_POST['delete_id'] ?? 0);
        
        // ตรวจสอบความถูกต้องของไอดี
        if ($deleteActivityId > 0) {
            // สร้างคำสั่ง SQL สำหรับลบกิจกรรม
            $deleteStatement = $conn->prepare("DELETE FROM activities WHERE activity_id = ?");
            // นำตัวแปรมาผูกกับคำสั่ง SQL
            $deleteStatement->bind_param("i", $deleteActivityId);
            
            // ทำการ execute คำสั่ง SQL
            if ($deleteStatement->execute()) {
                $_SESSION['alert'] = [
                    'type'  => 'success',
                    'title' => 'ลบสำเร็จ',
                    'text'  => 'ลบกิจกรรมและข้อมูลที่เกี่ยวข้องเรียบร้อยแล้ว'
                ];
            } else {
                $_SESSION['alert'] = [
                    'type'  => 'error',
                    'title' => 'เกิดข้อผิดพลาด',
                    'text'  => 'ไม่สามารถลบกิจกรรมได้'
                ];
            }
            $deleteStatement->close();
        }
    }
    
    // รีเฟรชหน้าเว็บ
    header("Location: erp_activities.php");
    exit;
}


// ส่วนที่ 5 ดึงข้อมูลกิจกรรมเพื่อแสดงผล
$activitiesResult = $conn->query("SELECT * FROM activities ORDER BY start_date DESC");


// ส่วนที่ 6 ดึงข้อมูลเพื่อใช้ในกรณีแก้ไขกิจกรรม
$activityToEditData = null;
if (isset($_GET['edit']) && in_array($currentUserRole, ['committee'])) {
    $editActivityIdParam = (int)$_GET['edit'];
    
    $fetchEditStatement = $conn->prepare("SELECT * FROM activities WHERE activity_id = ?");
    $fetchEditStatement->bind_param("i", $editActivityIdParam);
    $fetchEditStatement->execute();
    $activityToEditData = $fetchEditStatement->get_result()->fetch_assoc();
    $fetchEditStatement->close();
}


// ส่วนที่ 7 โหลด Header
include 'includes/header.php';
?>

<!-- แสดง Flash Alert ถ้ามี -->
<?php if (isset($_SESSION['alert'])): ?>
    <div id="flash-message"
         data-type="<?= $_SESSION['alert']['type'] ?>"
         data-title="<?= $_SESSION['alert']['title'] ?>"
         data-text="<?= $_SESSION['alert']['text'] ?>"
         class="d-none">
    </div>
    <?php unset($_SESSION['alert']); ?>
<?php endif; ?>

<div class="container py-4">

    <h2 class="section-title mb-4">เสนอโครงการและจัดการกิจกรรม</h2>

    <div class="grid-layout" style="grid-template-columns: 1fr 2fr; gap: var(--space-20);">

<!-- ฝั่งซ้าย: ฟอร์มสร้าง/แก้ไขกิจกรรม -->
        <div class="card h-100">
            
            <?php if ($activityToEditData): ?>
                <!-- โหมดแก้ไขกิจกรรม -->
                <h3 class="text-md m-0 mb-3 pb-3 border-bottom">
                    แก้ไขกิจกรรม
                    <a href="erp_activities.php" class="text-sm text-muted font-weight-normal" style="float: right;">ยกเลิก</a>
                </h3>

                <form method="POST" action="erp_activities.php" enctype="multipart/form-data" id="form-edit-activity">
<?php csrfField(); ?>
                    <input type="hidden" name="action" value="edit_activity">
                    <input type="hidden" name="edit_id" value="<?= $activityToEditData['activity_id'] ?>">

                    <div class="form-group mb-3">
                        <label class="form-label">ชื่อกิจกรรม <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control" value="<?= htmlspecialchars($activityToEditData['title']) ?>" required>
                    </div>

                    <div class="form-group mb-3">
                        <label class="form-label">รายละเอียดกิจกรรม</label>
                        <textarea name="description" rows="3" class="form-control"><?= htmlspecialchars($activityToEditData['description'] ?? '') ?></textarea>
                    </div>

                    <div class="form-group mb-3">
                        <label class="form-label">รูปปกกิจกรรม (เปลี่ยนรูปใหม่)</label>
                        <?php if (!empty($activityToEditData['cover_image'])): ?>
                            <div class="mb-2">
                                <img src="<?= htmlspecialchars($activityToEditData['cover_image']) ?>" alt="Cover" style="max-width: 100%; max-height: 120px; object-fit: cover; border-radius: 6px;">
                            </div>
                        <?php endif; ?>
                        <input type="file" name="cover_image" accept="image/jpeg, image/png, image/gif" class="form-control text-sm">
                        <div class="text-xs text-muted mt-1">เว้นว่างไว้หากไม่ต้องการเปลี่ยนรูปปก</div>
                    </div>

                    <div class="form-group mb-3">
                        <label class="form-label">ลิงก์ Facebook ของกิจกรรม</label>
                        <input type="url" name="facebook_link" placeholder="https://facebook.com/..." class="form-control" value="<?= htmlspecialchars($activityToEditData['facebook_link'] ?? '') ?>">
                    </div>

                    <div class="form-group mb-3">
                        <label class="form-label">ประเภทกิจกรรม <span class="text-danger">*</span></label>
                        <select name="activity_type" class="form-control" required>
                            <?php $currentType = $activityToEditData['activity_type'] ?? ''; ?>
                            <option value="single_day" <?= ($currentType === 'single_day') ? 'selected' : '' ?>>กิจกรรม 1 วัน (ไป-กลับ)</option>
                            <option value="multi_day" <?= ($currentType === 'multi_day') ? 'selected' : '' ?>>ค่ายพักแรมหลายวัน (Multi-day Camp)</option>
                        </select>
                    </div>

                    <div class="form-group mb-3">
                        <label class="form-label">หมวดหมู่กิจกรรม <span class="text-danger">*</span></label>
                        <select name="category" class="form-control" required>
                            <?php $currentCategory = $activityToEditData['category'] ?? 'volunteer'; ?>
                            <option value="volunteer" <?= ($currentCategory === 'volunteer') ? 'selected' : '' ?>>กิจกรรมจิตอาสา</option>
                            <option value="sport" <?= ($currentCategory === 'sport') ? 'selected' : '' ?>>กิจกรรมกีฬา</option>
                            <option value="byaniyan" <?= ($currentCategory === 'byaniyan') ? 'selected' : '' ?>>กิจกรรมบายเนียร์</option>
                        </select>
                    </div>

                    <div class="d-flex gap-2 mb-4">
                        <div class="form-group flex-grow-1">
                            <label class="form-label">วันที่และเวลาเริ่มกิจกรรม <span class="text-danger">*</span></label>
                            <input type="datetime-local" name="start_date" class="form-control" value="<?= date('Y-m-d\TH:i', strtotime($activityToEditData['start_date'])) ?>" required>
                        </div>
                        <div class="form-group flex-grow-1">
                            <label class="form-label">วันที่และเวลาสิ้นสุดกิจกรรม</label>
                            <input type="datetime-local" name="end_date" class="form-control" value="<?= !empty($activityToEditData['end_date']) ? date('Y-m-d\TH:i', strtotime($activityToEditData['end_date'])) : '' ?>">
                        </div>
                    </div>

                    <div class="form-group mb-3">
                        <label class="form-label">สถานที่จัดกิจกรรม <span class="text-danger">*</span></label>
                        <input type="text" name="location_name" class="form-control" value="<?= htmlspecialchars($activityToEditData['location_name']) ?>" required>
                    </div>

                    <div class="d-flex gap-2 mb-4">
                        <div class="form-group flex-grow-1">
                            <label class="form-label">สถานที่ขึ้นรถ (จุดนัดพบ)</label>
                            <input type="text" name="meeting_point" class="form-control" value="<?= htmlspecialchars($activityToEditData['meeting_point'] ?? '') ?>">
                        </div>
                        <div class="form-group flex-grow-1">
                            <label class="form-label">เวลานัดหมายขึ้นรถ</label>
                            <input type="datetime-local" name="meeting_time" class="form-control" value="<?= !empty($activityToEditData['meeting_time']) ? date('Y-m-d\TH:i', strtotime($activityToEditData['meeting_time'])) : '' ?>">
                        </div>
                    </div>

                    <div class="d-flex gap-2 mb-4">
                        <div class="form-group flex-grow-1">
                            <label class="form-label">รับจำนวน (คน) <span class="text-danger">*</span></label>
                            <input type="number" name="max_participants" min="0" class="form-control" value="<?= $activityToEditData['max_participants'] ?>" required>
                        </div>
                        <div class="form-group flex-grow-1">
                            <label class="form-label">แต้มจิตอาสา <span class="text-danger">*</span></label>
                            <input type="number" name="points" min="1" class="form-control" value="<?= $activityToEditData['points'] ?>" required>
                        </div>
                    </div>

                    <div class="form-group mb-3">
                        <label class="form-label">ผู้ประสานงานโครงการ (ฝ่ายประชาสัมพันธ์)</label>
                        <select name="venue_contact_user_id" class="form-control">
                            <option value="0">ไม่ระบุ</option>
                            <?php
                            // ดึงรายชื่อฝ่ายประชาสัมพันธ์สำหรับเป็นผู้ประสานงานโครงการ
                            $venueContactUsersResult = $conn->query(
                                "SELECT user_id, name, student_id, phone, committee_position 
                                 FROM users 
                                 WHERE committee_position = 'pr' 
                                 ORDER BY student_id ASC, name ASC"
                            );
                            if (!$venueContactUsersResult || $venueContactUsersResult->num_rows === 0) {
                                $venueContactUsersResult = $conn->query(
                                    "SELECT user_id, name, student_id, phone, committee_position 
                                     FROM users 
                                     WHERE role IN ('committee') 
                                     ORDER BY student_id ASC, name ASC"
                                );
                            }
                            if ($venueContactUsersResult) {
                                while ($vcUser = $venueContactUsersResult->fetch_assoc()) {
                                    $isSelected = (isset($activityToEditData['venue_contact_user_id']) && $activityToEditData['venue_contact_user_id'] == $vcUser['user_id']) ? 'selected' : '';
                                    $stdText = !empty($vcUser['student_id']) ? $vcUser['student_id'] . '  ' : '';
                                    $phoneText = !empty($vcUser['phone']) ? ' เบอร์ ' . $vcUser['phone'] : '';
                                    $optionLabel = htmlspecialchars("{$stdText}{$vcUser['name']}{$phoneText}");
                                    echo "<option value=\"{$vcUser['user_id']}\" {$isSelected}>{$optionLabel}</option>";
                                }
                            }
                            ?>
                        </select>
                        <div class="text-xs text-muted mt-1">ข้อมูลจะแสดงให้อาจารย์ที่ปรึกษาเห็นเพื่อติดต่อสอบถาม</div>
                    </div>

                    <!-- แจ้งเตือนสถานะการอนุมัติ -->
                    <div class="p-3 rounded mb-3" style="background: #FFF8E1; border: 1px solid #FFCC80;">
                        <div class="text-xs text-warning font-weight-bold">หมายเหตุ</div>
                        <?php if ($activityToEditData['approval_status'] === 'rejected'): ?>
                            <div class="text-xs text-muted">
                                เมื่อบันทึกการแก้ไขแล้ว สถานะจะถูกเปลี่ยนเป็น "รออนุมัติ" เพื่อให้อาจารย์พิจารณาใหม่อีกครั้ง
                            </div>
                        <?php else: ?>
                            <div class="text-xs text-muted">
                                สถานะการอนุมัติจะคงเดิม (<?= $activityToEditData['approval_status'] === 'approved' ? 'อนุมัติแล้ว' : 'รออนุมัติ' ?>) ไม่ต้องส่งอนุมัติใหม่
                            </div>
                        <?php endif; ?>
                    </div>

                    <button type="submit" class="btn btn-warning btn-block">บันทึกการแก้ไข</button>
                </form>

            <?php else: ?>
                <!-- โหมดเพิ่มกิจกรรมใหม่ -->
                <h3 class="text-md m-0 mb-3 pb-3 border-bottom">ร่างกิจกรรมใหม่ (ส่งขออนุมัติ)</h3>

                <form method="POST" action="erp_activities.php" enctype="multipart/form-data" id="form-add-activity">
<?php csrfField(); ?>
                    <input type="hidden" name="action" value="add_activity">

                    <div class="form-group mb-3">
                        <label class="form-label">ชื่อกิจกรรม <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control" required>
                    </div>

                    <div class="form-group mb-3">
                        <label class="form-label">รายละเอียดกิจกรรม</label>
                        <textarea name="description" rows="3" class="form-control"></textarea>
                    </div>

                    <div class="form-group mb-3">
                        <label class="form-label">รูปปกกิจกรรม (ถ้ามี)</label>
                        <input type="file" name="cover_image" id="cover_image" accept="image/jpeg, image/png, image/gif" class="form-control text-sm">
                        <div class="text-xs text-muted mt-1">รองรับไฟล์ JPG, PNG ขนาดไม่เกิน 2MB</div>
                    </div>

                    <div class="form-group mb-3">
                        <label class="form-label">ลิงก์ Facebook ของกิจกรรม (ถ้ามี)</label>
                        <input type="url" name="facebook_link" placeholder="https://facebook.com/..." class="form-control">
                    </div>

                    <div class="form-group mb-3">
                        <label class="form-label">รูปแบบกิจกรรม <span class="text-danger">*</span></label>
                        <select name="activity_type" class="form-control" required>
                            <option value="single_day" selected>กิจกรรม 1 วัน</option>
                            <option value="multi_day">กิจกรรมหลายวัน</option>
                        </select>
                    </div>

                    <div class="form-group mb-3">
                        <label class="form-label">หมวดหมู่กิจกรรม <span class="text-danger">*</span></label>
                        <select name="category" class="form-control" required>
                            <option value="volunteer" selected>กิจกรรมจิตอาสา</option>
                            <option value="sport">กิจกรรมกีฬา</option>
                            <option value="byaniyan">กิจกรรมบายเนียร์</option>
                        </select>
                    </div>

                    <div class="d-flex gap-2 mb-4">
                        <div class="form-group flex-grow-1">
                            <label class="form-label">วันที่และเวลาเริ่มกิจกรรม <span class="text-danger">*</span></label>
                            <input type="datetime-local" name="start_date" class="form-control" required>
                        </div>
                        <div class="form-group flex-grow-1">
                            <label class="form-label">วันที่และเวลาสิ้นสุดกิจกรรม</label>
                            <input type="datetime-local" name="end_date" class="form-control">
                        </div>
                    </div>

                    <div class="form-group mb-3">
                        <label class="form-label">สถานที่จัดกิจกรรม</label>
                        <input type="text" name="location_name" class="form-control" required>
                    </div>

                    <div class="d-flex gap-2 mb-4">
                        <div class="form-group flex-grow-1">
                            <label class="form-label">สถานที่ขึ้นรถ (จุดนัดพบ)</label>
                            <input type="text" name="meeting_point" class="form-control">
                        </div>
                        <div class="form-group flex-grow-1">
                            <label class="form-label">เวลานัดหมายขึ้นรถ</label>
                            <input type="datetime-local" name="meeting_time" class="form-control">
                        </div>
                    </div>

                    <div class="d-flex gap-2 mb-4">
                        <div class="form-group flex-grow-1">
                            <label class="form-label">รับจำนวน (คน)</label>
                            <input type="number" name="max_participants" min="0" placeholder="0 = ไม่จำกัด" class="form-control" required>
                        </div>
                        <div class="form-group flex-grow-1">
                            <label class="form-label">แต้มจิตอาสา</label>
                            <input type="number" name="points" min="1" class="form-control" required>
                        </div>
                    </div>

                    <div class="form-group mb-3">
                        <label class="form-label">ผู้ประสานงานโครงการ (ฝ่ายประชาสัมพันธ์)</label>
                        <select name="venue_contact_user_id" class="form-control">
                            <option value="0">ไม่ระบุ</option>
                            <?php
                            // ดึงรายชื่อฝ่ายประชาสัมพันธ์สำหรับเป็นผู้ประสานงานโครงการ
                            $venueContactUsersResult = $conn->query(
                                "SELECT user_id, name, student_id, phone, committee_position 
                                 FROM users 
                                 WHERE committee_position = 'pr' 
                                 ORDER BY student_id ASC, name ASC"
                            );
                            if (!$venueContactUsersResult || $venueContactUsersResult->num_rows === 0) {
                                $venueContactUsersResult = $conn->query(
                                    "SELECT user_id, name, student_id, phone, committee_position 
                                     FROM users 
                                     WHERE role IN ('committee') 
                                     ORDER BY student_id ASC, name ASC"
                                );
                            }
                            if ($venueContactUsersResult) {
                                while ($vcUser = $venueContactUsersResult->fetch_assoc()) {
                                    $stdText = !empty($vcUser['student_id']) ? $vcUser['student_id'] . '  ' : '';
                                    $phoneText = !empty($vcUser['phone']) ? ' เบอร์ ' . $vcUser['phone'] : '';
                                    $optionLabel = htmlspecialchars("{$stdText}{$vcUser['name']}{$phoneText}");
                                    echo "<option value=\"{$vcUser['user_id']}\">{$optionLabel}</option>";
                                }
                            }
                            ?>
                        </select>
                        <div class="text-xs text-muted mt-1">ข้อมูลจะแสดงให้อาจารย์ที่ปรึกษาเห็นเพื่อติดต่อสอบถาม</div>
                    </div>

                    <button type="submit" class="btn btn-primary btn-block">ยื่นเสนอกิจกรรม</button>
                </form>
            <?php endif; ?>
        </div>

<!-- ฝั่งขวา: ตารางกิจกรรมทั้งหมด -->
        <div class="card h-100">
            <h3 class="text-md m-0 mb-3 pb-3 border-bottom">สถานะการยื่นโครงการ</h3>

            <div class="table-responsive">
                <table class="table w-100 text-sm">
                    
                    <thead class="bg-main text-muted">
                        <tr>
                            <th class="p-2 text-left">ชื่อกิจกรรม</th>
                            <th class="p-2 text-center">วันที่จัด</th>
                            <th class="p-2 text-center">สถานะ</th>
                            <th class="p-2 text-center" style="width: 120px;">จัดการ</th>
                            <th class="p-2 text-center" style="width: 100px;">เปิด Self Check-in</th>
                        </tr>
                    </thead>
                    
                    <tbody>
                        <?php if ($activitiesResult && $activitiesResult->num_rows > 0): ?>
                            
                            <?php 
                            // เริ่มจาก index 0 
                            $activitiesResult->data_seek(0); 
                            while ($activityRow = $activitiesResult->fetch_assoc()): 
                            ?>
                                <tr>
                                    
                                    <!-- ชื่อกิจกรรม -->
                                    <td class="p-2 font-weight-medium text-main">
                                        <div style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 200px;" title="<?= htmlspecialchars($activityRow['title']) ?>">
                                            <?= htmlspecialchars($activityRow['title']) ?>
                                        </div>
                                        
                                        <!-- ป้ายกำกับเปิดการเช็คอิน -->
                                        <?php if (isset($activityRow['self_checkin_enabled']) && $activityRow['self_checkin_enabled'] == 1): ?>
                                            <div class="mt-1">
                                                <span class="text-success text-xs" style="background: #D1FAE5; padding: 2px 6px; border-radius: 4px;">
                                                    เปิดเช็คอิน
                                                </span>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <!-- วันที่จัดกิจกรรม -->
                                    <td class="p-2 text-center text-muted text-xs">
                                        <?= date('d/m/y', strtotime($activityRow['start_date'])) ?>
                                        
                                        <!-- ถ้าระบุวันสิ้นสุด และไม่ใช่วันเดียวกับวันเริ่มต้น -->
                                        <?php if (!empty($activityRow['end_date']) && date('Y-m-d', strtotime($activityRow['start_date'])) !== date('Y-m-d', strtotime($activityRow['end_date']))): ?>
                                            <br>ถึง <?= date('d/m/y', strtotime($activityRow['end_date'])) ?>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <!-- สถานะการอนุมัติ -->
                                    <td class="p-2 text-center">
                                        <?php if ($activityRow['approval_status'] == 'pending'): ?>
                                            <span class="badge badge-warning text-xs">รออนุมัติ</span>
                                        <?php elseif ($activityRow['approval_status'] == 'approved'): ?>
                                            <span class="badge badge-success text-xs">อนุมัติแล้ว</span>
                                        <?php else: ?>
                                            <span class="badge badge-danger text-xs">ยกเลิกการอนุมัติ</span>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <!-- ปุ่มจัดการ (แก้ไข, ลบ, ดูรายละเอียด) -->
                                    <td class="p-2 text-center">
                                        <div class="d-flex gap-1 justify-center">
                                            
                                            <?php if (in_array($currentUserRole, ['committee'])): ?>
                                                
                                                <!-- ปุ่มแก้ไข -->
                                                <a href="erp_activities.php?edit=<?= $activityRow['activity_id'] ?>" class="btn btn-surface" style="width: 32px; height: 32px; display: inline-flex; align-items: center; justify-content: center; padding: 0;" title="แก้ไข">
                                                    <i class="fa-solid fa-pen"></i>
                                                </a>
                                                
                                                <!-- ปุ่มลบ (จะมีการเรียก JS Confirm) -->
                                                <form method="POST" action="erp_activities.php" class="m-0" id="delete-form-<?= $activityRow['activity_id'] ?>">
<?php csrfField(); ?>
                                                    <input type="hidden" name="action" value="delete_activity">
                                                    <input type="hidden" name="delete_id" value="<?= $activityRow['activity_id'] ?>">
                                                    <button type="button" class="btn btn-danger" style="width: 32px; height: 32px; display: inline-flex; align-items: center; justify-content: center; padding: 0;" title="ลบ" onclick="confirmDeleteActivity(<?= $activityRow['activity_id'] ?>)">
                                                        <i class="fa-solid fa-trash"></i>
                                                    </button>
                                                </form>
                                                
                                            <?php endif; ?>
                                            
                                            <!-- ปุ่มดูรายละเอียดโครงการ (Link ไปอีกหน้า) -->
                                            <a href="activity_details.php?id=<?= $activityRow['activity_id'] ?>" class="btn btn-primary" style="width: 32px; height: 32px; display: inline-flex; align-items: center; justify-content: center; padding: 0;" title="ดูรายละเอียดและจัดการ">
                                                <i class="fa-solid fa-folder-open"></i>
                                            </a>
                                            
                                        </div>
                                    </td>
                                    
                                    <!-- เปิด/ปิด Self Check-in แบบเร็วผ่าน AJAX -->
                                    <td class="p-2 text-center">
                                        <?php $isCheckedInChecked = (isset($activityRow['self_checkin_enabled']) && $activityRow['self_checkin_enabled'] == 1) ? 'checked' : ''; ?>
                                        <input type="checkbox" class="toggle-checkin cursor-pointer" data-id="<?= $activityRow['activity_id'] ?>" <?= $isCheckedInChecked ?> style="width: 20px; height: 20px;">
                                    </td>
                                    
                                </tr>
                            <?php endwhile; ?>
                            
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center p-4 text-muted mt-3">ยังไม่มีกิจกรรมที่ยื่นอนุมัติ</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                    
                </table>
            </div>
            
        </div>
        
    </div>
</div>

<!-- สคริปต์หลักสำหรับ ERP Activities -->
<script src="assets/js/erp_activities.js"></script>

<!-- สคริปต์การกดยืนยันการลบ -->
<script>
    function confirmDeleteActivity(activityId) {
        if (confirm('ยืนยันการลบกิจกรรม?\nข้อมูลผู้เข้าร่วม ไฟล์เอกสาร และการเงินที่เกี่ยวข้องจะถูกลบทั้งหมดและไม่สามารถกู้คืนได้!')) {
            document.getElementById('delete-form-' + activityId).submit();
        }
    }
</script>

<?php include 'includes/footer.php'; ?>