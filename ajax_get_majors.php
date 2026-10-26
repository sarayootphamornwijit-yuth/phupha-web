<?php
// ajax_get_majors.php
// ไฟล์นี้ใช้รับค่ารหัสคณะ (faculty_id) แล้วส่งรายชื่อสาขาวิชากลับไปเป็น JSON
// ใช้สำหรับทำ Dropdown 2 ชั้น (เลือกคณะแล้วเปลี่ยนสาขา)

require_once 'config/database.php';

// กำหนด Header ให้เบราว์เซอร์รู้ว่าเป็นข้อมูล JSON ภาษาไทย
header('Content-Type: application/json; charset=utf-8');

// รับค่า faculty_id จาก GET หรือ POST
$faculty_id = isset($_GET['faculty_id']) ? (int)$_GET['faculty_id'] : 0;
if ($faculty_id <= 0 && isset($_POST['faculty_id'])) {
    $faculty_id = (int)$_POST['faculty_id'];
}

$majors = [];

// ถ้ามีรหัสคณะ ให้ค้นหาสาขาที่อยู่ในคณะนั้น
if ($faculty_id > 0 && isset($conn) && $conn instanceof mysqli) {
    try {
        $sql = "SELECT major_id, major_name FROM majors WHERE faculty_id = ? ORDER BY major_name ASC";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("i", $faculty_id);
            $stmt->execute();
            $result = $stmt->get_result();

            while ($row = $result->fetch_assoc()) {
                $majors[] = [
                    'major_id'   => (int)$row['major_id'],
                    'major_name' => $row['major_name']
                ];
            }
            $stmt->close();
        }
    } catch (\Throwable $e) {
        error_log("ajax_get_majors error: " . $e->getMessage());
    }
}

// ส่งข้อมูลกลับไปเป็น JSON เพื่อให้ JavaScript นำไปแสดงผล
echo json_encode($majors, JSON_UNESCAPED_UNICODE);
exit;
