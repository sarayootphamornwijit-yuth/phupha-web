<?php
// ไฟล์ api.php ให้บริการข้อมูลสำหรับแอพพลิเคชัน เชื่อมต่อกับฐานข้อมูล MySQL ของชมรม

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

require_once __DIR__ . '/config/database.php';

// รับค่าคำสั่งการทำงานจาก URL หรือจากข้อมูลส่งเข้า
$action = $_GET['action'] ?? $_POST['action'] ?? '';

//รองรับชื่อคำสั่งเก่า แต่ใช้การตรวจสิทธิ์และที่นั่งชุดเดียวกับหน้าเลือกการเดินทาง
if ($action === 'update_travel') {
    $action = 'select_vehicle';
    $oldTravelMethod = $_POST['travel_method'] ?? '';
    if ($oldTravelMethod === 'เดินทางด้วยตนเอง' || $oldTravelMethod === 'own_travel') {
        $_POST['travel_type'] = 'own_travel';
    } else {
        $_POST['travel_type'] = $oldTravelMethod;
    }
}

// ฟังก์ชันช่วยส่งข้อมูลกลับเป็น JSON
function sendResponse($status, $message, $data = null) {
    global $conn;
    if ($status === 'error' && isset($conn)) {
        try {
            $conn->rollback();
        } catch (Throwable $rollbackError) {
            error_log((string)$rollbackError);
        }
    }
    echo json_encode([
        'status' => $status,
        'message' => $message,
        'data' => $data
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ป้องกัน Exception ไม่ให้แสดงผลเป็น HTML บนโฮสต์จริง
set_exception_handler(function (\Throwable $e) {
    error_log((string)$e);
    http_response_code(500);
    sendResponse('error', 'ระบบขัดข้อง กรุณาลองใหม่อีกครั้ง');
});

//คำสั่งที่บันทึกข้อมูลรับเฉพาะ POST เพื่อไม่ให้ลิงก์ทั่วไปสั่งแก้ข้อมูลได้
$writeActions = ['login', 'register', 'register_activity', 'update_travel', 'cancel_registration', 'update_profile', 'select_vehicle', 'self_checkin', 'submit_evaluation', 'advisor_approve_activity', 'checkin_qr', 'logout', 'create_news', 'delete_news', 'edit_news', 'save_activity', 'delete_activity', 'toggle_activity_checkin', 'save_logistics', 'add_vehicle', 'delete_vehicle', 'add_budget', 'delete_budget', 'update_member_role', 'delete_member', 'update_certificate_threshold', 'update_profile_details'];
if (in_array($action, $writeActions, true) && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    sendResponse('error', 'คำสั่งนี้ต้องส่งข้อมูลด้วย POST');
}

//มือถือส่ง HTTP ตรงและไม่มี Origin ส่วนเว็บที่ส่งคำสั่งบันทึกต้องเป็นเว็บเดียวกัน
//กันเว็บอื่นใช้ session ของคนที่ล็อกอินอยู่มาสั่งเปลี่ยนสิทธิ์หรือลบข้อมูลผ่านฟอร์ม
if (in_array($action, $writeActions, true) && isset($_SERVER['HTTP_ORIGIN'])) {
    $origin = parse_url($_SERVER['HTTP_ORIGIN']);
    $target = parse_url('http://' . ($_SERVER['HTTP_HOST'] ?? ''));
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    //Cloudflare ส่ง HTTPS เข้ามาที่ Apache ผ่าน HTTP จึงอ่านชนิดการเชื่อมต่อจาก proxy ด้วย
    if (strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') {
        $scheme = 'https';
    }
    $originScheme = strtolower($origin['scheme'] ?? '');
    $sameHost = strtolower($origin['host'] ?? '') === strtolower($target['host'] ?? '');
    $originPort = $origin['port'] ?? ($originScheme === 'https' ? 443 : 80);
    $targetPort = $target['port'] ?? ($scheme === 'https' ? 443 : 80);
    $samePort = $originPort === $targetPort;
    if (!$sameHost || !$samePort || $originScheme !== $scheme || empty($origin['host'])) {
        http_response_code(403);
        sendResponse('error', 'ไม่อนุญาตให้เว็บไซต์อื่นสั่งบันทึกข้อมูล');
    }
}

//คำสั่งที่เกี่ยวกับข้อมูลส่วนตัวต้องใช้ session ของคนที่เข้าสู่ระบบจริง
$publicActions = ['login', 'register', 'get_activities', 'get_activity', 'get_news', 'get_faculties', 'get_majors', 'get_committees', 'get_vehicles'];
if (!in_array($action, $publicActions, true)) {
    if (empty($_SESSION['user_id'])) {
        http_response_code(401);
        sendResponse('error', 'กรุณาเข้าสู่ระบบใหม่');
    }
    $sessionUserId = (int)$_SESSION['user_id'];
    $actorStmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
    $actorStmt->bind_param('i', $sessionUserId);
    $actorStmt->execute();
    $actor = $actorStmt->get_result()->fetch_assoc();
    $actorStmt->close();
    if (!$actor) {
        session_destroy();
        http_response_code(401);
        sendResponse('error', 'ไม่พบผู้ใช้งาน กรุณาเข้าสู่ระบบใหม่');
    }
    $_SESSION['role'] = $actor['role'];
    $_SESSION['committee_position'] = $actor['committee_position'];
    //ไม่ใช้ user_id ที่มือถือส่งมา เพราะอาจเป็นรหัสของสมาชิกคนอื่น
    $_GET['user_id'] = $sessionUserId;
    $_POST['user_id'] = $sessionUserId;
    $_POST['advisor_id'] = $sessionUserId;
    $staffActions = ['get_activity_participants', 'checkin_qr', 'get_management_activities'];
    if (in_array($action, $staffActions, true) && !in_array($actor['role'], ['admin', 'advisor', 'committee', 'leader'], true)) {
        http_response_code(403);
        sendResponse('error', 'คุณไม่มีสิทธิ์ใช้งานส่วนนี้');
    }
    $menuActions = ['get_activity_participants' => 'activities', 'get_management_activities' => 'activities', 'checkin_qr' => 'qr_scanner', 'get_members' => 'members', 'get_budget_summary' => 'budgets', 'get_report_summary' => 'reports', 'get_all_vehicles' => 'vehicles', 'get_activity_contacts' => 'activities', 'save_activity' => 'activities', 'delete_activity' => 'activities', 'toggle_activity_checkin' => 'activities', 'get_vehicle_management' => 'vehicles', 'save_logistics' => 'vehicles', 'add_vehicle' => 'vehicles', 'delete_vehicle' => 'vehicles', 'get_activity_budget' => 'budgets', 'add_budget' => 'budgets', 'delete_budget' => 'budgets', 'get_activity_report' => 'reports', 'get_member_detail' => 'members'];
    if (isset($menuActions[$action]) && !committeeCanAccess($menuActions[$action])) {
        http_response_code(403);
        sendResponse('error', 'คุณไม่มีสิทธิ์ใช้งานส่วนนี้');
    }
    if (in_array($action, ['create_news', 'edit_news', 'delete_news', 'get_management_news'], true) && !in_array($actor['role'], ['advisor', 'admin', 'committee', 'leader'], true)) {
        http_response_code(403);
        sendResponse('error', 'เฉพาะอาจารย์ที่ปรึกษาหรือผู้ดูแลระบบเท่านั้น');
    }
    if ($action === 'advisor_approve_activity' && !in_array($actor['role'], ['advisor', 'admin'], true)) {
        http_response_code(403);
        sendResponse('error', 'เฉพาะอาจารย์ที่ปรึกษาหรือผู้ดูแลระบบเท่านั้น');
    }
}

//คำสั่งเพิ่มสำหรับหน้าจัดการบนมือถือ ตรวจสิทธิ์ด้านบนก่อนเข้ามาในไฟล์นี้
require_once __DIR__ . '/includes/api_mobile_management.php';

if ($action === 'get_profile') {
    unset($actor['password']);
    sendResponse('success', 'ดึงข้อมูลผู้ใช้งานสำเร็จ', $actor);
}

if ($action === 'logout') {
    $_SESSION = [];
    session_destroy();
    sendResponse('success', 'ออกจากระบบแล้ว');
}

//หน้าอนุมัติต้องเห็นโครงการที่รออนุมัติด้วย ส่วนหน้าสาธารณะยังเห็นเฉพาะที่อนุมัติแล้ว
if ($action === 'get_management_activities') {
    $result = $conn->query("SELECT * FROM activities ORDER BY start_date DESC");
    sendResponse('success', 'ดึงข้อมูลกิจกรรมสำเร็จ', $result->fetch_all(MYSQLI_ASSOC));
}

// กรณีเรียกดูรายการกิจกรรมทั้งหมด และรองรับการค้นหา
if ($action === 'get_activities') {
    $searchKeyword = trim($_GET['search'] ?? '');
    
    // สร้างคำสั่ง SQL สำหรับดึงข้อมูลกิจกรรมที่ผ่านการอนุมัติแล้ว
    if (!empty($searchKeyword)) {
        $likePattern = "%" . $searchKeyword . "%";
        $stmt = $conn->prepare(
            "SELECT *
             FROM activities 
             WHERE approval_status = 'approved' AND (title LIKE ? OR location_name LIKE ? OR description LIKE ?) 
             ORDER BY start_date DESC"
        );
        $stmt->bind_param("sss", $likePattern, $likePattern, $likePattern);
    } else {
        $stmt = $conn->prepare(
            "SELECT *
             FROM activities 
             WHERE approval_status = 'approved' 
             ORDER BY start_date DESC"
        );
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $activities = [];
    
    while ($row = $result->fetch_assoc()) {
        $activities[] = $row;
    }
    
    $stmt->close();
    sendResponse('success', 'ดึงข้อมูลกิจกรรมเรียบร้อยแล้ว', $activities);
}

// กรณีเรียกดูรายละเอียดกิจกรรมตามรหัสกิจกรรม
if ($action === 'get_activity') {
    $activityId = (int)($_GET['id'] ?? 0);
    
    if ($activityId <= 0) {
        sendResponse('error', 'ไม่พบรหัสกิจกรรมที่ระบุ');
    }
    
    $stmt = $conn->prepare(
        "SELECT a.*, 
         (SELECT COUNT(*) FROM user_activities ua WHERE ua.activity_id = a.activity_id AND ua.status != 'cancelled') as participant_count 
         FROM activities a 
         WHERE a.activity_id = ?"
    );
    $stmt->bind_param("i", $activityId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($activity = $result->fetch_assoc()) {
        $stmt->close();
        sendResponse('success', 'พบข้อมูลกิจกรรม', $activity);
    } else {
        $stmt->close();
        sendResponse('error', 'ไม่พบกิจกรรมนี้ในระบบ');
    }
}

// กรณีเข้าสู่ระบบผ่านแอพพลิเคชัน
if ($action === 'login') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        sendResponse('error', 'กรุณากรอกรหัสนักศึกษาและรหัสผ่านให้ครบถ้วน');
    }
    
    // คิวรีเฉพาะคอลัมน์ที่มีอยู่ในฐานข้อมูลจริง
    $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            //สร้าง session ใหม่หลังตรวจรหัสผ่าน เพื่อใช้ยืนยันตัวตนในคำขอถัดไป
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int)$user['user_id'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['name'] = $user['name'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['committee_position'] = $user['committee_position'];
            $_SESSION['user_type'] = $user['user_type'];
            unset($user['password']);
            $stmt->close();
            sendResponse('success', 'เข้าสู่ระบบสำเร็จ', $user);
        }
    }
    
    $stmt->close();
    sendResponse('error', 'รหัสนักศึกษาหรือรหัสผ่านไม่ถูกต้อง');
}

// กรณีเพิ่มการลงทะเบียนเข้าร่วมกิจกรรม
if ($action === 'register_activity') {
    $userId = (int)$_SESSION['user_id'];
    $activityId = (int)($_POST['activity_id'] ?? 0);
    if (!canRegisterActivity()) {
        sendResponse('error', 'บัญชีนี้ไม่สามารถลงทะเบียนกิจกรรมได้');
    }
    //ล็อกกิจกรรมก่อนนับคน เพื่อไม่ให้สองคนจองที่นั่งสุดท้ายพร้อมกัน
    $conn->begin_transaction();
    $stmt = $conn->prepare("SELECT *, start_date > NOW() AS registration_open FROM activities WHERE activity_id = ? FOR UPDATE");
    $stmt->bind_param('i', $activityId);
    $stmt->execute();
    $activity = $stmt->get_result()->fetch_assoc();
    if (!$activity || $activity['approval_status'] !== 'approved' || !$activity['registration_open']) {
        $conn->rollback();
        sendResponse('error', 'กิจกรรมนี้ยังไม่อนุมัติ หรือปิดรับสมัครแล้ว');
    }
    $stmt = $conn->prepare("SELECT user_activity_id, status FROM user_activities WHERE user_id = ? AND activity_id = ? FOR UPDATE");
    $stmt->bind_param('ii', $userId, $activityId);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();
    if ($existing && $existing['status'] !== 'cancelled') {
        $conn->rollback();
        sendResponse('error', 'คุณได้ลงทะเบียนกิจกรรมนี้เรียบร้อยแล้ว');
    }
    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM user_activities WHERE activity_id = ? AND status != 'cancelled'");
    $stmt->bind_param('i', $activityId);
    $stmt->execute();
    $total = (int)$stmt->get_result()->fetch_assoc()['total'];
    if ((int)$activity['max_participants'] > 0 && $total >= (int)$activity['max_participants']) {
        $conn->rollback();
        sendResponse('error', 'กิจกรรมนี้มีผู้ลงทะเบียนเต็มแล้ว');
    }
    if ($existing) {
        $stmt = $conn->prepare("UPDATE user_activities SET status = 'registered', vehicle_id = NULL, travel_method = NULL, check_in_time = NULL, check_out_time = NULL WHERE user_id = ? AND activity_id = ?");
    } else {
        $stmt = $conn->prepare("INSERT INTO user_activities (user_id, activity_id, status) VALUES (?, ?, 'registered')");
    }
    $stmt->bind_param('ii', $userId, $activityId);
    $stmt->execute();
    $conn->commit();
    sendResponse('success', 'ลงทะเบียนกิจกรรมสำเร็จ');
}

// กรณีเรียกดูกิจกรรมที่ผู้ใช้ลงทะเบียนเข้าร่วมไว้
if ($action === 'get_joined_activities') {
    $userId = (int)($_GET['user_id'] ?? $_POST['user_id'] ?? 0);
    
    if ($userId <= 0) {
        sendResponse('error', 'ไม่พบรหัสผู้ใช้งาน');
    }
    
    $stmt = $conn->prepare(
        "SELECT a.*, ua.user_activity_id, ua.status, ua.travel_method
         FROM user_activities ua 
         JOIN activities a ON ua.activity_id = a.activity_id 
         WHERE ua.user_id = ? AND ua.status != 'cancelled' 
         ORDER BY a.start_date DESC"
    );
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $joined = [];
    
    while ($row = $result->fetch_assoc()) {
        $joined[] = $row;
    }
    
    $stmt->close();
    sendResponse('success', 'ดึงรายการกิจกรรมที่เข้าร่วมเรียบร้อยแล้ว', $joined);
}

// กรณียกเลิกการลงทะเบียนกิจกรรม
if ($action === 'cancel_registration') {
    $userId = (int)($_POST['user_id'] ?? 0);
    $activityId = (int)($_POST['activity_id'] ?? 0);
    
    if ($userId <= 0 || $activityId <= 0) {
        sendResponse('error', 'ข้อมูลไม่ถูกต้อง');
    }
    
    $stmt = $conn->prepare("UPDATE user_activities SET status = 'cancelled', vehicle_id = NULL, travel_method = NULL WHERE user_id = ? AND activity_id = ? AND status = 'registered'");
    $stmt->bind_param("ii", $userId, $activityId);
    
    if ($stmt->execute() && $stmt->affected_rows > 0) {
        $stmt->close();
        sendResponse('success', 'ยกเลิกการลงทะเบียนเรียบร้อยแล้ว');
    } else {
        $stmt->close();
        sendResponse('error', 'ไม่สามารถยกเลิกได้');
    }
}

// กรณีแก้ไขข้อมูลโปรไฟล์ผู้ใช้งาน
if ($action === 'update_profile') {
    $userId = (int)($_POST['user_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    
    if ($userId <= 0 || empty($name)) {
        sendResponse('error', 'กรุณากรอกชื่อ-นามสกุลให้เรียบร้อย');
    }
    
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        sendResponse('error', 'รูปแบบอีเมลไม่ถูกต้อง');
    }
    $stmt = $conn->prepare("UPDATE users SET name = ?, phone = ?, email = ? WHERE user_id = ?");
    $stmt->bind_param("sssi", $name, $phone, $email, $userId);
    
    if ($stmt->execute()) {
        $stmt->close();
        sendResponse('success', 'บันทึกข้อมูลโปรไฟล์เรียบร้อยแล้ว');
    } else {
        $stmt->close();
        sendResponse('error', 'ไม่สามารถบันทึกข้อมูลได้');
    }
}

// กรณีเรียกดูข่าวประชาสัมพันธ์ของชมรม
if ($action === 'get_news') {
    $stmt = $conn->prepare("SELECT news_id, title, content, image_url, created_at FROM news WHERE is_published = 1 ORDER BY created_at DESC");
    $stmt->execute();
    $result = $stmt->get_result();
    $newsList = [];
    while ($row = $result->fetch_assoc()) {
        $newsList[] = $row;
    }
    $stmt->close();
    sendResponse('success', 'ดึงข้อมูลข่าวสารเรียบร้อยแล้ว', $newsList);
}

// กรณีเรียกดูรายการยานพาหนะของกิจกรรม
if ($action === 'get_vehicles') {
    $activityId = (int)($_GET['activity_id'] ?? $_POST['activity_id'] ?? 0);
    $userId = (int)($_GET['user_id'] ?? $_POST['user_id'] ?? 0);
    
    if ($activityId <= 0) {
        sendResponse('error', 'ไม่พบรหัสกิจกรรม');
    }
    
    $stmt = $conn->prepare("
        SELECT v.*, 
               (SELECT COUNT(*) FROM user_activities ua WHERE ua.vehicle_id = v.vehicle_id AND ua.status != 'cancelled') as booked_seats,
               (v.capacity - (SELECT COUNT(*) FROM user_activities ua WHERE ua.vehicle_id = v.vehicle_id AND ua.status != 'cancelled')) as available_seats
        FROM vehicles v 
        WHERE v.activity_id = ?
    ");
    $stmt->bind_param("i", $activityId);
    $stmt->execute();
    $result = $stmt->get_result();
    $vehicles = [];
    while ($row = $result->fetch_assoc()) {
        $vehicles[] = $row;
    }
    $stmt->close();
    sendResponse('success', 'ดึงข้อมูลยานพาหนะเรียบร้อยแล้ว', $vehicles);
}

// กรณีเลือกวิธีการเดินทางและยานพาหนะ
if ($action === 'select_vehicle') {
    $userId = (int)$_SESSION['user_id'];
    $activityId = (int)($_POST['activity_id'] ?? 0);
    $travelType = trim($_POST['travel_type'] ?? '');
    $vehicleId = (int)($_POST['vehicle_id'] ?? 0);
    if (!in_array($travelType, ['own_travel', 'club_vehicle'], true)) {
        sendResponse('error', 'รูปแบบการเดินทางไม่ถูกต้อง');
    }
    $conn->begin_transaction();
    //ใช้ล็อกเดียวกับการสมัคร เพื่อไม่ให้จำนวนคนหรือจำนวนที่นั่งเปลี่ยนระหว่างบันทึก
    $stmt = $conn->prepare("SELECT activity_id FROM activities WHERE activity_id = ? FOR UPDATE");
    $stmt->bind_param('i', $activityId);
    $stmt->execute();
    $stmt->get_result()->free();
    $stmt->close();
    $stmt = $conn->prepare("SELECT status FROM user_activities WHERE user_id = ? AND activity_id = ? FOR UPDATE");
    $stmt->bind_param('ii', $userId, $activityId);
    $stmt->execute();
    $registration = $stmt->get_result()->fetch_assoc();
    if (!$registration || $registration['status'] !== 'registered') {
        $conn->rollback();
        sendResponse('error', 'ต้องลงทะเบียนกิจกรรมก่อนเลือกการเดินทาง และยังไม่ได้เช็คอิน');
    }
    if ($travelType === 'club_vehicle') {
        $stmt = $conn->prepare("SELECT capacity FROM vehicles WHERE vehicle_id = ? AND activity_id = ? FOR UPDATE");
        $stmt->bind_param('ii', $vehicleId, $activityId);
        $stmt->execute();
        $vehicle = $stmt->get_result()->fetch_assoc();
        $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM user_activities WHERE vehicle_id = ? AND user_id != ? AND status != 'cancelled'");
        $stmt->bind_param('ii', $vehicleId, $userId);
        $stmt->execute();
        $booked = (int)$stmt->get_result()->fetch_assoc()['total'];
        if (!$vehicle || $booked >= (int)$vehicle['capacity']) {
            $conn->rollback();
            sendResponse('error', 'ไม่พบรถ หรือที่นั่งในรถคันนี้เต็มแล้ว');
        }
    } else {
        $vehicleId = null;
    }
    $stmt = $conn->prepare("UPDATE user_activities SET travel_method = ?, vehicle_id = ? WHERE user_id = ? AND activity_id = ?");
    $stmt->bind_param('siii', $travelType, $vehicleId, $userId, $activityId);
    $stmt->execute();
    $conn->commit();
    sendResponse('success', 'บันทึกการเดินทางสำเร็จ');
}

// กรณีเช็คอินเข้าร่วมกิจกรรมด้วยตนเอง (Self Check-in)
if ($action === 'self_checkin') {
    $userId = (int)($_POST['user_id'] ?? 0);
    $activityId = (int)($_POST['activity_id'] ?? 0);
    
    if ($userId <= 0 || $activityId <= 0) {
        sendResponse('error', 'ข้อมูลไม่ถูกต้อง');
    }
    
    // ตรวจสอบว่ากิจกรรมเปิดเช็คอินในวันนี้หรือไม่
    $chkAct = $conn->prepare("SELECT activity_id, title FROM activities WHERE activity_id = ? AND self_checkin_enabled = 1 AND CURDATE() BETWEEN DATE(start_date) AND DATE(IFNULL(end_date, start_date))");
    $chkAct->bind_param("i", $activityId);
    $chkAct->execute();
    $actData = $chkAct->get_result()->fetch_assoc();
    $chkAct->close();
    
    if (!$actData) {
        sendResponse('error', 'กิจกรรมนี้ยังไม่เปิดให้เช็คอิน หรือไม่อยู่ในช่วงวันจัดกิจกรรม');
    }
    
    // ตรวจสอบสถานะการลงทะเบียน
    $chkReg = $conn->prepare("SELECT user_activity_id, status FROM user_activities WHERE user_id = ? AND activity_id = ?");
    $chkReg->bind_param("ii", $userId, $activityId);
    $chkReg->execute();
    $regData = $chkReg->get_result()->fetch_assoc();
    $chkReg->close();
    
    if (!$regData || $regData['status'] === 'cancelled') {
        sendResponse('error', 'คุณยังไม่ได้ลงทะเบียนเข้าร่วมกิจกรรมนี้');
    }
    
    if ($regData['status'] === 'checked_in' || $regData['status'] === 'checked_out') {
        sendResponse('info', 'คุณได้เช็คอินเข้าร่วมกิจกรรมนี้เรียบร้อยแล้ว');
    }
    
    // ทำการเช็คอิน
    $stmt = $conn->prepare("UPDATE user_activities SET status = 'checked_in', check_in_time = NOW() WHERE user_id = ? AND activity_id = ?");
    $stmt->bind_param("ii", $userId, $activityId);
    
    if ($stmt->execute()) {
        $stmt->close();
        sendResponse('success', 'เช็คอินเข้าร่วมกิจกรรมสำเร็จ!');
    } else {
        $stmt->close();
        sendResponse('error', 'เกิดข้อผิดพลาดในการบันทึกเวลาเช็คอิน');
    }
}

// กรณีประเมินผลกิจกรรมเพื่อรับแต้มจิตอาสา
if ($action === 'submit_evaluation') {
    $userId = (int)($_POST['user_id'] ?? 0);
    $activityId = (int)($_POST['activity_id'] ?? 0);
    $q1 = (int)($_POST['q1'] ?? 0);
    $q2 = (int)($_POST['q2'] ?? 0);
    $q3 = (int)($_POST['q3'] ?? 0);
    $q4 = (int)($_POST['q4'] ?? 0);
    $q5 = (int)($_POST['q5'] ?? 0);
    $q6 = (int)($_POST['q6'] ?? 0);
    
    if ($userId <= 0 || $activityId <= 0) {
        sendResponse('error', 'ข้อมูลไม่ถูกต้อง');
    }
    
    // ตรวจสอบความถูกต้องของคะแนน 1-5
    if ($q1 < 1 || $q1 > 5 || $q2 < 1 || $q2 > 5 || $q3 < 1 || $q3 > 5 || 
        $q4 < 1 || $q4 > 5 || $q5 < 1 || $q5 > 5 || $q6 < 1 || $q6 > 5) {
        sendResponse('error', 'คะแนนประเมินต้องอยู่ระหว่าง 1 ถึง 5');
    }
    
    $conn->begin_transaction();
    $endStmt = $conn->prepare("SELECT activity_id FROM activities WHERE activity_id = ? AND approval_status = 'approved' AND COALESCE(end_date, start_date) <= NOW()");
    $endStmt->bind_param('i', $activityId);
    $endStmt->execute();
    if (!$endStmt->get_result()->fetch_assoc()) {
        $conn->rollback();
        sendResponse('error', 'กิจกรรมยังไม่สิ้นสุด จึงยังทำแบบประเมินไม่ได้');
    }
    // ตรวจสอบว่าเคยเช็คอินหรือไม่
    $chkReg = $conn->prepare("SELECT status FROM user_activities WHERE user_id = ? AND activity_id = ? FOR UPDATE");
    $chkReg->bind_param("ii", $userId, $activityId);
    $chkReg->execute();
    $regData = $chkReg->get_result()->fetch_assoc();
    $chkReg->close();
    
    if (!$regData || ($regData['status'] !== 'checked_in' && $regData['status'] !== 'checked_out')) {
        sendResponse('error', 'คุณต้องเช็คอินเข้าร่วมกิจกรรมก่อน จึงจะสามารถทำแบบประเมินได้');
    }
    
    // ตรวจสอบว่าเคยประเมินหรือยัง
    $chkEval = $conn->prepare("SELECT evaluation_id FROM evaluations WHERE user_id = ? AND activity_id = ? FOR UPDATE");
    $chkEval->bind_param("ii", $userId, $activityId);
    $chkEval->execute();
    if ($chkEval->get_result()->num_rows > 0) {
        $chkEval->close();
        sendResponse('error', 'คุณได้ประเมินกิจกรรมนี้ไปเรียบร้อยแล้ว');
    }
    $chkEval->close();
    
    // บันทึกการประเมิน
    $stmt = $conn->prepare("INSERT INTO evaluations (activity_id, user_id, q1, q2, q3, q4, q5, q6) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("iiiiiiii", $activityId, $userId, $q1, $q2, $q3, $q4, $q5, $q6);
    
    if ($stmt->execute()) {
        $stmt->close();
        
        // ดึงแต้มของกิจกรรมและเพิ่มให้สมาชิก
        $actStmt = $conn->prepare("SELECT points FROM activities WHERE activity_id = ?");
        $actStmt->bind_param("i", $activityId);
        $actStmt->execute();
        $actRes = $actStmt->get_result()->fetch_assoc();
        $actStmt->close();
        
        $pointsEarned = $actor['user_type'] === 'student' ? (int)($actRes['points'] ?? 0) : 0;
        if ($pointsEarned > 0) {
            $userUpd = $conn->prepare("UPDATE users SET points = COALESCE(points, 0) + ? WHERE user_id = ? AND user_type = 'student'");
            $userUpd->bind_param("ii", $pointsEarned, $userId);
            $userUpd->execute();
            $userUpd->close();
        }
        
        $conn->commit();
        sendResponse('success', "บันทึกการประเมินเรียบร้อย ได้รับแต้มจิตอาสา {$pointsEarned} แต้ม", ['points_earned' => $pointsEarned]);
    } else {
        $stmt->close();
        sendResponse('error', 'ไม่สามารถบันทึกการประเมินได้');
    }
}

// กรณีตรวจสอบเกณฑ์และประวัติเกียรติบัตร
if ($action === 'get_certificates') {
    $userId = (int)($_GET['user_id'] ?? $_POST['user_id'] ?? 0);
    
    if ($userId <= 0) {
        sendResponse('error', 'ไม่พบรหัสผู้ใช้งาน');
    }
    
    // ดึงคะแนนสะสมของผู้ใช้
    $uStmt = $conn->prepare("SELECT points, name, role FROM users WHERE user_id = ?");
    $uStmt->bind_param("i", $userId);
    $uStmt->execute();
    $userData = $uStmt->get_result()->fetch_assoc();
    $uStmt->close();
    
    $userPoints = (int)($userData['points'] ?? 0);
    
    // ดึงเกณฑ์คะแนน
    $threshold = 50;
    $setStmt = $conn->query("SELECT setting_value FROM club_settings WHERE setting_key = 'certificate_point_threshold'");
    if ($setStmt && $sRow = $setStmt->fetch_assoc()) {
        $threshold = (int)$sRow['setting_value'];
    }
    
    // ดึงประวัติเกียรติบัตร
    $certs = [];
    $cStmt = $conn->prepare("SELECT * FROM certificates WHERE user_id = ? ORDER BY date_issued DESC");
    if ($cStmt) {
        $cStmt->bind_param("i", $userId);
        $cStmt->execute();
        $cRes = $cStmt->get_result();
        while ($row = $cRes->fetch_assoc()) {
            $certs[] = $row;
        }
        $cStmt->close();
    }
    
    sendResponse('success', 'ดึงข้อมูลเกียรติบัตรเรียบร้อยแล้ว', [
        'points' => $userPoints,
        'threshold' => $threshold,
        'is_qualified' => ($userPoints >= $threshold),
        'certificates' => $certs
    ]);
}

// กรณีเรียกดูรายชื่อคณะทั้งหมด
if ($action === 'get_faculties') {
    $faculties = [];
    $facQuery = $conn->query("SELECT faculty_id, faculty_name FROM faculties ORDER BY faculty_id ASC");
    if ($facQuery) {
        while ($row = $facQuery->fetch_assoc()) {
            $faculties[] = $row;
        }
    }
    sendResponse('success', 'ดึงข้อมูลคณะสำเร็จ', $faculties);
}

// กรณีเรียกดูรายชื่อสาขาวิชาตามรหัสคณะ
if ($action === 'get_majors') {
    $facultyId = (int)($_GET['faculty_id'] ?? $_POST['faculty_id'] ?? 0);
    $majors = [];
    if ($facultyId > 0) {
        $stmt = $conn->prepare("SELECT major_id, faculty_id, major_name FROM majors WHERE faculty_id = ? ORDER BY major_name ASC");
        if ($stmt) {
            $stmt->bind_param("i", $facultyId);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $majors[] = $row;
            }
            $stmt->close();
        }
    }
    sendResponse('success', 'ดึงข้อมูลสาขาวิชาสำเร็จ', $majors);
}

// กรณีเรียกดูรายชื่อคณะกรรมการทำเนียบผู้นำรุ่น
if ($action === 'get_committees') {
    $stmt = $conn->prepare("
        SELECT u.user_id, u.name, u.role, u.committee_position, u.academic_year, u.generation, 
               u.image, u.phone, u.faculty, u.major, u.student_id
        FROM users u 
        WHERE u.role IN ('committee') 
          AND u.user_type = 'student'
          AND u.academic_year IS NOT NULL
          AND u.academic_year BETWEEN 1 AND 4
        ORDER BY u.academic_year ASC, 
                 FIELD(u.committee_position, 'president', 'vice_president', 'treasurer', 
                       'secretary_saluang', 'secretary_wiangbua', 'nurse', 'venue_coordinator', 'pr'),
                 u.name ASC
    ");
    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();
        $committees = [];
        while ($row = $result->fetch_assoc()) {
            $committees[] = $row;
        }
        $stmt->close();
        sendResponse('success', 'ดึงข้อมูลทำเนียบผู้นำรุ่นสำเร็จ', $committees);
    } else {
        sendResponse('error', 'ไม่สามารถดึงข้อมูลทำเนียบผู้นำรุ่นได้');
    }
}

// กรณีสมัครสมาชิกใหม่

if ($action === 'register') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $name = trim($_POST['name'] ?? '');
    $userType = $_POST['user_type'] ?? 'student';
    $studentId = trim($_POST['student_id'] ?? '');
    $occupation = trim($_POST['occupation'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    if (!in_array($userType, ['student', 'outsider'], true) || strlen($password) < 6) {
        sendResponse('error', 'กรุณาเลือกประเภทสมาชิกให้ถูกต้อง และใช้รหัสผ่านอย่างน้อย 6 ตัว');
    }
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        sendResponse('error', 'รูปแบบอีเมลไม่ถูกต้อง');
    }
    $academicYear = !empty($_POST['academic_year']) ? (int)$_POST['academic_year'] : null;
    // ผู้สมัครใหม่ทุกคนเริ่มต้นด้วยสิทธิ์ 'member' เสมอ เพื่อความปลอดภัย
    // แต่สำหรับผู้ที่ระบุตำแหน่งกรรมการมา จะบันทึกตำแหน่งไว้เพื่อให้แอดมินเห็นในระบบ
    $userRole = 'member';
    $allowedPositions = [
        'president', 'vice_president', 'treasurer', 
        'secretary_saluang', 'secretary_wiangbua', 
        'nurse', 'venue_coordinator', 'pr'
    ];
    $committeePosition = (!empty($_POST['committee_position']) && in_array($_POST['committee_position'], $allowedPositions, true)) 
        ? $_POST['committee_position'] 
        : null;

    $facultyId = !empty($_POST['faculty_id']) ? (int)$_POST['faculty_id'] : null;
    $majorId = !empty($_POST['major_id']) ? (int)$_POST['major_id'] : null;
    $faculty = trim($_POST['faculty'] ?? '');
    $major = trim($_POST['major'] ?? '');
    
    if (empty($username) || empty($password) || empty($name)) {
        sendResponse('error', 'กรุณากรอกข้อมูลให้ครบถ้วน');
    }
    
    //ตรวจข้อมูลนักศึกษาที่เซิร์ฟเวอร์อีกครั้ง และใช้ชื่อคณะจากฐานข้อมูลจริง
    if ($userType === 'student') {
        if (!preg_match('/^\d{8}$/', $studentId) || !$facultyId || $academicYear < 1 || $academicYear > 4) {
            sendResponse('error', 'กรุณาระบุรหัสนักศึกษา 8 หลัก คณะ และชั้นปีให้ถูกต้อง');
        }
        $facultyStmt = $conn->prepare("SELECT faculty_name FROM faculties WHERE faculty_id = ?");
        $facultyStmt->bind_param('i', $facultyId);
        $facultyStmt->execute();
        $facultyRow = $facultyStmt->get_result()->fetch_assoc();
        if (!$facultyRow) {
            sendResponse('error', 'ไม่พบคณะที่เลือก');
        }
        $faculty = $facultyRow['faculty_name'];
        $occupation = 'นักศึกษา';
        $major = null;
        if ($majorId) {
            $majorStmt = $conn->prepare("SELECT major_name FROM majors WHERE major_id = ? AND faculty_id = ?");
            $majorStmt->bind_param('ii', $majorId, $facultyId);
            $majorStmt->execute();
            $majorRow = $majorStmt->get_result()->fetch_assoc();
            if (!$majorRow) {
                sendResponse('error', 'สาขาวิชาไม่ตรงกับคณะที่เลือก');
            }
            $major = $majorRow['major_name'];
        }
    } else {
        $studentId = null;
        $academicYear = null;
        $facultyId = null;
        $majorId = null;
        $faculty = null;
        $major = null;
        $committeePosition = null;
    }

    // หากเป็นนักศึกษา กำหนด Username ให้เป็นรหัสนักศึกษาตามระบบเว็บ
    $actualUsername = $username;
    if ($userType === 'student' && !empty($studentId)) {
        $actualUsername = $studentId;
    }
    
    // ตรวจสอบ username ซ้ำ
    $chkStmt = $conn->prepare("SELECT user_id FROM users WHERE username = ?");
    $chkStmt->bind_param("s", $actualUsername);
    $chkStmt->execute();
    if ($chkStmt->get_result()->num_rows > 0) {
        $chkStmt->close();
        sendResponse('error', 'ชื่อผู้ใช้นี้มีอยู่ในระบบแล้ว');
    }
    $chkStmt->close();
    
    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

    // ตรวจสอบและบันทึกข้อมูลผู้ใช้
    try {
        $insStmt = $conn->prepare(
            "INSERT INTO users 
                (username, password, name, user_type, student_id, occupation, faculty_id, major_id, faculty, major, academic_year, role, committee_position, points) 
             VALUES 
                (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)"
        );
        $insStmt->bind_param(
            "ssssssiisssss",
            $actualUsername,
            $hashedPassword,
            $name,
            $userType,
            $studentId,
            $occupation,
            $facultyId,
            $majorId,
            $faculty,
            $major,
            $academicYear,
            $userRole,
            $committeePosition
        );
        $insertSuccess = $insStmt->execute();
        $insStmt->close();
    } catch (\Throwable $e) {
        error_log((string)$e);
        sendResponse('error', 'สมัครสมาชิกไม่สำเร็จ กรุณาตรวจสอบคณะและสาขาวิชา หรือชื่อผู้ใช้ที่ซ้ำ');
    }
    if ($insertSuccess) {
        $newUserId = $conn->insert_id;
        $contactStmt = $conn->prepare("UPDATE users SET phone = ?, email = ? WHERE user_id = ?");
        $contactStmt->bind_param('ssi', $phone, $email, $newUserId);
        $contactStmt->execute();
    }

    if ($insertSuccess) {
        sendResponse('success', 'สมัครสมาชิกสำเร็จ เข้าสู่ระบบได้ทันที');
    } else {
        sendResponse('error', 'เกิดข้อผิดพลาดในการสมัครสมาชิก กรุณาลองใหม่อีกครั้ง');
    }
}

// กรณีอาจารย์ที่ปรึกษาพิจารณาอนุมัติหรือปฏิเสธโครงการ
if ($action === 'advisor_approve_activity') {
    $activityId = (int)($_POST['activity_id'] ?? 0);
    $advisorId = (int)($_POST['advisor_id'] ?? 0);
    $approvalStatus = trim($_POST['status'] ?? ''); // 'approved' หรือ 'rejected'

    if ($activityId <= 0 || !in_array($approvalStatus, ['approved', 'rejected'])) {
        sendResponse('error', 'ข้อมูลการอนุมัติไม่ถูกต้อง');
    }

    if ($approvalStatus === 'approved') {
        $stmt = $conn->prepare("UPDATE activities SET approval_status = 'approved', advisor_id = ?, approved_at = NOW() WHERE activity_id = ?");
        $stmt->bind_param("ii", $advisorId, $activityId);
    } else {
        $stmt = $conn->prepare("UPDATE activities SET approval_status = 'rejected', advisor_id = ?, approved_at = NULL WHERE activity_id = ?");
        $stmt->bind_param("ii", $advisorId, $activityId);
    }

    if ($stmt->execute()) {
        $stmt->close();
        $msg = ($approvalStatus === 'approved') ? 'อนุมัติโครงการเรียบร้อยแล้ว' : 'ปฏิเสธโครงการเรียบร้อยแล้ว';
        sendResponse('success', $msg, ['activity_id' => $activityId, 'status' => $approvalStatus]);
    } else {
        $stmt->close();
        sendResponse('error', 'ไม่สามารถบันทึกสถานะการอนุมัติได้');
    }
}

// กรณีดึงรายชื่อผู้ลงทะเบียน/ผู้เข้าร่วมกิจกรรม (สำหรับ ERP & คณะกรรมการ)
if ($action === 'get_activity_participants') {
    $activityId = (int)($_GET['activity_id'] ?? $_POST['activity_id'] ?? 0);

    if ($activityId <= 0) {
        sendResponse('error', 'ไม่พบรหัสกิจกรรม');
    }

    $stmt = $conn->prepare("
        SELECT ua.user_activity_id, ua.user_id, ua.status, ua.travel_method, ua.check_in_time,
               u.name, u.student_id, u.faculty, u.major, u.phone,
               v.vehicle_type, v.license_plate
        FROM user_activities ua
        JOIN users u ON ua.user_id = u.user_id
        LEFT JOIN vehicles v ON ua.vehicle_id = v.vehicle_id
        WHERE ua.activity_id = ? AND ua.status != 'cancelled'
        ORDER BY ua.user_activity_id ASC
    ");
    $stmt->bind_param("i", $activityId);
    $stmt->execute();
    $result = $stmt->get_result();
    $participants = [];
    while ($row = $result->fetch_assoc()) {
        $participants[] = $row;
    }
    $stmt->close();

    sendResponse('success', 'ดึงรายชื่อผู้เข้าร่วมเรียบร้อยแล้ว', $participants);
}

// กรณีสแกนหรือกรอกรหัสเช็คอินกิจกรรม (สำหรับแอปพลิเคชันมือถือและระบบ ERP)
if ($action === 'checkin_qr') {
    $qrData = trim($_POST['qr_data'] ?? $_GET['qr_data'] ?? '');
    $activityId = (int)($_POST['activity_id'] ?? $_GET['activity_id'] ?? 0);

    $u_id = 0;
    $a_id = 0;
    $token = '';

    if (preg_match('/checkin-(\d+)-(\d+)(?:-([a-zA-Z0-9]+))?/i', $qrData, $matches)) {
        $u_id = (int)$matches[1];
        $a_id = (int)$matches[2];
        $token = $matches[3] ?? '';
    } elseif (preg_match('/^CHK-ACT(\d+)-U(\d+)$/i', $qrData, $matches)) {
        $a_id = (int)$matches[1];
        $u_id = (int)$matches[2];
    } elseif (!empty($qrData) && $activityId > 0) {
        // กรณีพิมพ์รหัสนักศึกษา หรือ username
        $stmtUser = $conn->prepare("SELECT user_id FROM users WHERE student_id = ? OR username = ? LIMIT 1");
        $stmtUser->bind_param("ss", $qrData, $qrData);
        $stmtUser->execute();
        $uRes = $stmtUser->get_result();
        if ($uRow = $uRes->fetch_assoc()) {
            $u_id = (int)$uRow['user_id'];
            $a_id = $activityId;
        }
        $stmtUser->close();
    }

    if ($u_id <= 0 || $a_id <= 0) {
        sendResponse('error', 'รูปแบบรหัสเช็คอินไม่ถูกต้อง หรือไม่พบข้อมูลกิจกรรม');
    }

    if ($activityId > 0 && $activityId !== $a_id) {
        sendResponse('error', 'QR Code นี้เป็นของกิจกรรมอื่น กรุณาเลือกกิจกรรมให้ตรงกัน');
    }

    if (!empty($token) && function_exists('verifyQrToken') && !verifyQrToken($u_id, $a_id, $token)) {
        sendResponse('error', 'รหัสตรวจสอบ QR Code ไม่ถูกต้อง');
    }

    $stmt_chk = $conn->prepare("
        SELECT ua.status, u.name, u.student_id, a.title AS activity_title, a.points 
        FROM user_activities ua
        JOIN users u ON ua.user_id = u.user_id
        JOIN activities a ON ua.activity_id = a.activity_id
        WHERE ua.user_id = ? AND ua.activity_id = ?
    ");
    $stmt_chk->bind_param("ii", $u_id, $a_id);
    $stmt_chk->execute();
    $res_chk = $stmt_chk->get_result();

    if ($res_chk && $res_chk->num_rows > 0) {
        $memberData = $res_chk->fetch_assoc();
        $status     = $memberData['status'];
        $userName   = $memberData['name'];
        $studentId  = $memberData['student_id'] ?: '-';
        $actTitle   = $memberData['activity_title'];

        if ($status === 'cancelled') {
            sendResponse('error', 'สมาชิกยกเลิกการลงทะเบียนแล้ว');
        }
        if ($status === 'registered') {
            $stmt_upd = $conn->prepare("UPDATE user_activities SET status = 'checked_in', check_in_time = NOW() WHERE user_id = ? AND activity_id = ?");
            $stmt_upd->bind_param("ii", $u_id, $a_id);
            $stmt_upd->execute();
            $stmt_upd->close();

            sendResponse('success', 'เช็คอินสำเร็จเรียบร้อยแล้ว', [
                'user_name' => $userName,
                'student_id' => $studentId,
                'activity_title' => $actTitle,
                'status' => 'checked_in'
            ]);
        } else {
            sendResponse('info', "สมาชิก {$userName} ได้เช็คอินเข้าร่วมกิจกรรมนี้เรียบร้อยแล้ว", [
                'user_name' => $userName,
                'student_id' => $studentId,
                'activity_title' => $actTitle,
                'status' => $status
            ]);
        }
    } else {
        sendResponse('error', 'ไม่พบข้อมูลการลงทะเบียนสำหรับกิจกรรมนี้');
    }
}

require __DIR__ . '/includes/api_management.php';

// กรณีคำสั่งไม่ตรงกับเงื่อนไขใด
sendResponse('error', 'ไม่พบคำสั่งที่เรียกใช้งาน');
