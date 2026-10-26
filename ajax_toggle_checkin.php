<?php
// ajax_toggle_checkin.php
// สคริปต์สำหรับจัดการเปิด-ปิดระบบสแกน QR Code (Self-Checkin) ของกิจกรรมนั้นๆ
// ถูกเรียกใช้งานผ่าน AJAX (Asynchronous) จากฝั่ง Client

require_once 'config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();
}

// ส่วนที่ 1 ตรวจสอบสิทธิ์การเข้าใช้งาน
// อนุญาตให้เฉพาะระดับ admin และ committee ใช้งานฟังก์ชันนี้ได้เท่านั้น
// (นำ leader ออกตามความต้องการใหม่ของระบบ)
if (!isLoggedIn() || !committeeCanAccess('activities')) {
    
    // ตั้งค่า Content-Type เป็น JSON เสมอเพื่อป้องกันข้อผิดพลาดที่หน้าบ้าน
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false, 
        'message' => 'Unauthorized: คุณไม่มีสิทธิ์เข้าถึงฟังก์ชันนี้'
    ]);
    exit;
    
}

// ส่วนที่ 2 รับค่าตัวแปรจากฝั่ง Client
// รับค่าไอดีกิจกรรมและสถานะ (1 = เปิด, 0 = ปิด)
$activityIdTarget = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$targetStatus     = isset($_POST['status']) ? (int)$_POST['status'] : 0;

// ส่วนที่ 3 ดำเนินการอัปเดตฐานข้อมูล
if ($activityIdTarget > 0) {
    
    $updateCheckinStatusStatement = $conn->prepare("UPDATE activities SET self_checkin_enabled = ? WHERE activity_id = ?");
    $updateCheckinStatusStatement->bind_param("ii", $targetStatus, $activityIdTarget);
    
    header('Content-Type: application/json; charset=utf-8');
    
    if ($updateCheckinStatusStatement->execute()) {
        
        // ถ้าอัปเดตสำเร็จ ส่ง Success กลับไป
        echo json_encode([
            'success' => true,
            'message' => 'Status updated successfully.'
        ]);
        
    } else {
        
        // ถ้าไม่สำเร็จ แจ้งเตือนข้อผิดพลาด
        echo json_encode([
            'success' => false, 
            'message' => 'Database error: ไม่สามารถอัปเดตข้อมูลได้'
        ]);
        
    }
    
    $updateCheckinStatusStatement->close();
    
} else {
    
    // หากข้อมูลไอดีไม่ถูกต้อง
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false, 
        'message' => 'Invalid ID: ข้อมูลรหัสกิจกรรมไม่ถูกต้อง'
    ]);
    
}
