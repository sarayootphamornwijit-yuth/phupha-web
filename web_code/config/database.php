<?php
// config/database.php
// ไฟล์นี้ทำหน้าที่เชื่อมต่อฐานข้อมูล MySQL และกำหนด helper functions พื้นฐาน
// ถูก require_once ในทุกหน้าของระบบ


// กำหนดเขตเวลามาตรฐานประเทศไทย (UTC+7) ให้กับทั้งระบบ
date_default_timezone_set('Asia/Bangkok');

// ส่วนที่ 1 ตั้งค่า Session
// เริ่ม session เฉพาะถ้ายังไม่ได้เริ่มไว้แล้ว เพื่อป้องกัน error
if (session_status() === PHP_SESSION_NONE) {
    // ป้องกันการขโมย Cookie ผ่าน JavaScript (XSS)
    ini_set('session.cookie_httponly', 1);
    // บังคับให้ Session ID ส่งผ่าน Cookie เท่านั้น (ป้องกัน Session Fixation)
    ini_set('session.use_only_cookies', 1);
    session_start();
}


// ส่วนที่ 2 ข้อมูลการเชื่อมต่อฐานข้อมูล
// รองรับทั้งการรันทดสอบบนเครื่อง Local (XAMPP) และบนโฮสติ้งจริง (InfinityFree)
$httpHost = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '';

/* หากเข้าใช้งานผ่านโดเมนโฮสต์จริง (เช่น phuphaclub.ct.ws) ให้ใช้การตั้งค่าโฮสต์เสมอ */
if (strpos($httpHost, 'ct.ws') !== false || strpos($httpHost, 'infinityfree') !== false || strpos($httpHost, 'epizy') !== false) {
    $isLocalEnv = false;
} else {
    /* กรณีรันบนเครื่อง Local (XAMPP / CLI / IP LAN เช่น 192.168.x.x หรือ 10.0.2.2 จาก Emulator) */
    $isLocalEnv = true;
}

if ($isLocalEnv) {
    define('DB_HOST', '127.0.0.1');
    define('DB_USER', 'root');
    define('DB_PASS', '');
    define('DB_NAME', 'phupha_db');
} else {
    define('DB_HOST', 'sql311.infinityfree.com');
    define('DB_USER', 'if0_42265566');
    define('DB_PASS', 'HxjbTLXWz0cGc');
    define('DB_NAME', 'if0_42265566_phuphadb');
}


// ตั้งค่า Error Reporting: บนเครื่องทดสอบให้แสดงรายละเอียดเต็มที่ แต่บนโฮสต์จริงให้ปิดเพื่อความปลอดภัย
if ($isLocalEnv) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    error_reporting(0);
}

set_exception_handler(function (\Throwable $e) use ($isLocalEnv) {
    // หมายเหตุ: ไม่ใช้ http_response_code(500) เพื่อไม่ให้ InfinityFree/โฮสติ้งฟรี ดักจับแล้วแทนที่ด้วยหน้า "หน้านี้ใช้ไม่ได้ HTTP ERROR 500" ของบราวเซอร์
    echo '<!DOCTYPE html><html lang="th"><head><meta charset="UTF-8"><title>เกิดข้อผิดพลาดของระบบ</title>';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
    echo '<style>body{font-family:sans-serif;background:#fff;padding:24px;margin:0;color:#000;}.err-card{background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:24px;max-width:750px;margin:30px auto;}.btn{display:inline-block;padding:8px 16px;border-radius:6px;text-decoration:none;font-size:14px;margin-right:8px;}.btn-blue{background:#2563eb;color:#fff;}.btn-gray{background:#e2e8f0;color:#000;}</style>';
    echo '</head><body><div class="err-card">';
    echo '<h3 style="color:#000;margin-top:0;">เกิดข้อผิดพลาดของระบบ</h3>';
    if ($isLocalEnv) {
        echo '<p style="color:#000;"><strong>ข้อความ:</strong> ' . htmlspecialchars($e->getMessage()) . '</p>';
        echo '<p style="color:#000;font-size:14px;"><strong>ไฟล์:</strong> ' . htmlspecialchars($e->getFile()) . ' (บรรทัดที่ ' . $e->getLine() . ')</p>';
    } else {
        echo '<p style="color:#000;">ขออภัย เกิดข้อผิดพลาดในการประมวลผล กรุณาลองใหม่อีกครั้งหรือติดต่อผู้ดูแลระบบ</p>';
    }
    echo '<div style="margin-top:20px;"><a href="javascript:history.back()" class="btn btn-blue">« ย้อนกลับ</a> <a href="index.php" class="btn btn-gray">กลับหน้าแรก</a></div>';
    echo '</div></body></html>';
    exit;
});

// ส่วนที่ 3 เปิดใช้งานโหมด Exception สำหรับ MySQLi
// ทำให้ MySQLi โยน Exception เมื่อเกิด SQL Error แทนการ return false
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);


// ส่วนที่ 4 สร้างการเชื่อมต่อฐานข้อมูล
try {
    // สร้าง object การเชื่อมต่อ mysqli
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

    // ตั้งค่า charset เป็น utf8mb4 เพื่อรองรับภาษาไทยและ Emoji อย่างสมบูรณ์
    $conn->set_charset('utf8mb4');
    try { $conn->query("SET time_zone = '+07:00'"); } catch (\Throwable $e) {}

    // ตรวจสอบและสร้างตาราง / คอลัมน์ที่จำเป็นโดยอัตโนมัติ (Auto-healing)
    try {
        $conn->query("
            CREATE TABLE IF NOT EXISTS vehicles (
                vehicle_id INT AUTO_INCREMENT PRIMARY KEY,
                activity_id INT NOT NULL,
                vehicle_type VARCHAR(100) NOT NULL,
                license_plate VARCHAR(50) NOT NULL,
                driver_name VARCHAR(100) NOT NULL,
                driver_phone VARCHAR(50) DEFAULT NULL,
                capacity INT DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                KEY idx_activity (activity_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    } catch (\Throwable $e) {}

    try { $conn->query("ALTER TABLE vehicles CHANGE COLUMN id vehicle_id INT AUTO_INCREMENT"); } catch (\Throwable $e) {}
    try { $conn->query("ALTER TABLE news CHANGE COLUMN id news_id INT AUTO_INCREMENT"); } catch (\Throwable $e) {}
    try { $conn->query("ALTER TABLE vehicles ADD COLUMN activity_id INT NOT NULL"); } catch (\Throwable $e) {}
    try { $conn->query("ALTER TABLE vehicles ADD COLUMN vehicle_type VARCHAR(100) NOT NULL"); } catch (\Throwable $e) {}
    try { $conn->query("ALTER TABLE vehicles ADD COLUMN license_plate VARCHAR(50) NOT NULL"); } catch (\Throwable $e) {}
    try { $conn->query("ALTER TABLE vehicles ADD COLUMN driver_name VARCHAR(100) NOT NULL"); } catch (\Throwable $e) {}
    try { $conn->query("ALTER TABLE vehicles ADD COLUMN driver_phone VARCHAR(50) DEFAULT NULL"); } catch (\Throwable $e) {}
    try { $conn->query("ALTER TABLE vehicles ADD COLUMN capacity INT DEFAULT 0"); } catch (\Throwable $e) {}

    try { $conn->query("ALTER TABLE user_activities ADD COLUMN vehicle_id INT DEFAULT NULL"); } catch (\Throwable $e) {}
    try { $conn->query("ALTER TABLE user_activities ADD COLUMN travel_method VARCHAR(50) DEFAULT NULL"); } catch (\Throwable $e) {}
    try { $conn->query("ALTER TABLE activities ADD COLUMN meeting_point VARCHAR(255) DEFAULT NULL"); } catch (\Throwable $e) {}
    try { $conn->query("ALTER TABLE activities ADD COLUMN meeting_time DATETIME DEFAULT NULL"); } catch (\Throwable $e) {}
    try { $conn->query("ALTER TABLE activities ADD COLUMN survey_details TEXT DEFAULT NULL"); } catch (\Throwable $e) {}
    try { $conn->query("ALTER TABLE activities ADD COLUMN category ENUM('volunteer','sport','byaniyan','other') DEFAULT 'volunteer'"); } catch (\Throwable $e) {}

    // ตรวจสอบและสร้างตาราง Master Data: faculties และ majors
    try {
        $conn->query("
            CREATE TABLE IF NOT EXISTS faculties (
                faculty_id INT AUTO_INCREMENT PRIMARY KEY,
                faculty_name VARCHAR(150) NOT NULL UNIQUE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    } catch (\Throwable $e) {}

    try {
        $conn->query("
            CREATE TABLE IF NOT EXISTS majors (
                major_id INT AUTO_INCREMENT PRIMARY KEY,
                faculty_id INT NOT NULL,
                major_name VARCHAR(150) NOT NULL,
                KEY idx_faculty (faculty_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    } catch (\Throwable $e) {}

    try { $conn->query("ALTER TABLE faculties DROP COLUMN created_at"); } catch (\Throwable $e) {}
    try { $conn->query("ALTER TABLE majors DROP COLUMN created_at"); } catch (\Throwable $e) {}

    try { $conn->query("ALTER TABLE users ADD COLUMN faculty_id INT DEFAULT NULL AFTER occupation"); } catch (\Throwable $e) {}
    try { $conn->query("ALTER TABLE users ADD COLUMN major_id INT DEFAULT NULL AFTER faculty_id"); } catch (\Throwable $e) {}
    try { $conn->query("ALTER TABLE certificates CHANGE COLUMN id certificate_id INT AUTO_INCREMENT"); } catch (\Throwable $e) {}
    try { $conn->query("ALTER TABLE certificates ADD COLUMN activity_id INT DEFAULT NULL AFTER user_id"); } catch (\Throwable $e) {}

    // ตรวจสอบและสร้างตารางตั้งค่าระบบ club_settings
    try {
        $conn->query("
            CREATE TABLE IF NOT EXISTS club_settings (
                setting_key VARCHAR(50) PRIMARY KEY,
                setting_value VARCHAR(255) NOT NULL,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $conn->query("INSERT IGNORE INTO club_settings (setting_key, setting_value) VALUES ('certificate_point_threshold', '50')");
    } catch (\Throwable $e) {}

    // ตรวจสอบและเพิ่มข้อมูลตั้งต้น faculties / majors หากตารางยังว่างเปล่า
    try {
        $checkFac = $conn->query("SELECT COUNT(*) AS cnt FROM faculties");
        if ($checkFac && ($row = $checkFac->fetch_assoc()) && (int)$row['cnt'] === 0) {
            $conn->query("INSERT IGNORE INTO faculties (faculty_id, faculty_name) VALUES
                (1, 'คณะครุศาสตร์'),
                (2, 'คณะวิทยาศาสตร์และเทคโนโลยี'),
                (3, 'คณะวิทยาการจัดการ'),
                (4, 'คณะมนุษยศาสตร์และสังคมศาสตร์'),
                (5, 'คณะเทคโนโลยีการเกษตร'),
                (6, 'คณะพยาบาลศาสตร์'),
                (7, 'วิทยาลัยนานาชาติ'),
                (8, 'วิทยาลัยแม่ฮ่องสอน'),
                (9, 'วิทยาลัยพัฒนาเศรษฐกิจและเทคโนโลยีชุมชนแห่งเอเชีย (adiCET)')");

            $conn->query("INSERT IGNORE INTO majors (major_id, faculty_id, major_name) VALUES
                (1, 1, 'สาขาวิชาการศึกษาปฐมวัย'),
                (2, 1, 'สาขาวิชาการประถมศึกษา'),
                (5, 1, 'สาขาวิชาภาษาไทย'),
                (6, 1, 'สาขาวิชาภาษาอังกฤษ'),
                (9, 1, 'สาขาวิชาคณิตศาสตร์'),
                (21, 2, 'สาขาวิชาวิทยาการคอมพิวเตอร์'),
                (22, 2, 'สาขาวิชาเทคโนโลยีสารสนเทศ'),
                (23, 2, 'สาขาวิชาเทคโนโลยีคอมพิวเตอร์และดิจิทัล'),
                (24, 2, 'สาขาวิชาเคมี'),
                (37, 3, 'สาขาวิชาการจัดการ'),
                (38, 3, 'สาขาวิชาการตลาด'),
                (39, 3, 'สาขาวิชาการบัญชี'),
                (47, 4, 'สาขาวิชาภาษาไทย'),
                (48, 4, 'สาขาวิชาภาษาอังกฤษ'),
                (54, 4, 'สาขาวิชานิติศาสตร์'),
                (63, 5, 'สาขาวิชาเกษตรศาสตร์'),
                (66, 6, 'สาขาวิชาพยาบาลศาสตร์')");
        }

        // ตรวจสอบและเพิ่มกิจกรรมเริ่มต้นหากยังไม่มี
        $checkAct = $conn->query("SELECT COUNT(*) AS cnt FROM activities");
        if ($checkAct && ($row = $checkAct->fetch_assoc()) && (int)$row['cnt'] === 0) {
            $conn->query("INSERT IGNORE INTO activities (activity_id, title, category, start_date, location_name, meeting_point, points, approval_status, max_participants) VALUES
                (1, 'กิจกรรมปลูกป่าสร้างฝายเพื่อเพื่อนภูผา', 'volunteer', '2026-10-15 08:30:00', 'อ.แม่ริม จ.เชียงใหม่', 'ลานกิจกรรม มรภ.เชียงใหม่', 10, 'approved', 50)");
        }
    } catch (\Throwable $e) {}

} catch (mysqli_sql_exception $exception) {
    // บันทึก error ลง log file เพื่อให้ผู้ดูแลระบบตรวจสอบได้ภายหลัง
    error_log($exception->getMessage());

    if (defined('TEST_MODE_ALLOW_DB_FAILURE') && TEST_MODE_ALLOW_DB_FAILURE === true) {
        $GLOBALS['db_connection_error'] = $exception->getMessage();
        $conn = null;
    } else {
        /* แสดงข้อความแจ้งเตือนพร้อมรายละเอียดสาเหตุเพื่อให้ผู้ดูแลระบบตรวจสอบได้ทันที */
        $errorMsg = $exception->getMessage();
        echo "<!DOCTYPE html><html lang='th'><head><meta charset='UTF-8'><title>เชื่อมต่อฐานข้อมูลไม่สำเร็จ</title>";
        echo "<style>body{font-family:sans-serif;background:#fff;padding:24px;color:#000;}.card{border:1px solid #cbd5e1;border-radius:8px;padding:20px;max-width:650px;margin:30px auto;}pre{background:#f1f5f9;padding:10px;border-radius:6px;color:#b91c1c;font-weight:600;word-break:break-all;}</style></head><body>";
        echo "<div class='card'>";
        echo "<h3 style='color:#000;margin-top:0;'>ขออภัย ระบบไม่สามารถเชื่อมต่อฐานข้อมูลได้ในขณะนี้</h3>";
        echo "<p style='color:#000;'><strong>สาเหตุทางเทคนิค (MySQL Error):</strong></p>";
        echo "<pre>" . htmlspecialchars($errorMsg) . "</pre>";
        echo "<p style='font-size:14px;color:#000;'><strong>ข้อมูลที่กำลังใช้เชื่อมต่อ:</strong><br>";
        echo "Host: " . htmlspecialchars(DB_HOST) . "<br>";
        echo "User: " . htmlspecialchars(DB_USER) . "<br>";
        echo "Database: " . htmlspecialchars(DB_NAME) . "</p>";
        echo "<p style='font-size:13px;color:#555;'>วิธีแก้ไข: ตรวจสอบ MySQL Hostname, Password และ Database Name ในหน้า Dashboard ของ InfinityFree (แท็บแรกในเบราว์เซอร์) แล้วนำมาอัปเดตในไฟล์ <code>config/database.php</code></p>";
        echo "</div></body></html>";
        exit;
    }
}

// คีย์ความลับสำหรับสร้าง Signature ป้องกันการปลอมแปลง QR Code
define('QR_SECRET_KEY', 'phupha_qr_secret_salt_2026');

/* สร้างรหัสยืนยัน QR Code */
function generateQrToken($userId, $activityId)
{
    return substr(hash_hmac('sha256', "checkin-{$userId}-{$activityId}", QR_SECRET_KEY), 0, 8);
}

/* ตรวจสอบความถูกต้องของรหัส QR Code */
function verifyQrToken($userId, $activityId, $token)
{
    if (empty($token)) {
        return true;
    }
    return hash_equals(generateQrToken($userId, $activityId), (string)$token);
}


// ส่วนที่ 5 Helper Functions พื้นฐาน
/**
 * ดึง User ID ของผู้ใช้ที่กำลังล็อกอินอยู่
 * คืนค่า null ถ้ายังไม่ได้ล็อกอิน
 */
function getCurrentUserId()
{
    if (isset($_SESSION['user_id'])) {
        return $_SESSION['user_id'];
    }

    return null;
}

/**
 * ดึง Role ของผู้ใช้ที่กำลังล็อกอินอยู่
 * เช่น 'admin', 'committee', 'member', 'advisor'
 * คืนค่า null ถ้ายังไม่ได้ล็อกอิน
 */
function getCurrentUserRole()
{
    if (isset($_SESSION['role'])) {
        return $_SESSION['role'];
    }

    return null;
}

/**
 * ตรวจสอบว่าผู้ใช้ล็อกอินอยู่หรือไม่
 * คืนค่า true ถ้าล็อกอินแล้ว, false ถ้ายังไม่ได้ล็อกอิน
 */
function isLoggedIn()
{
    if (isset($_SESSION['user_id'])) {
        return true;
    }

    return false;
}

/**
 * ตรวจสอบว่าผู้ใช้ปัจจุบันเป็นนักศึกษาที่สามารถลงทะเบียนกิจกรรมได้หรือไม่
 * สมาชิกทั่วไปและคณะกรรมการลงทะเบียนได้ แต่อาจารย์และผู้ดูแลระบบไม่ลงทะเบียนกิจกรรม
 */
/**
 * สร้าง CSRF token สำหรับแบบฟอร์มที่แก้ไขข้อมูล
 */
function generateCsrfToken()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

/**
 * ตรวจสอบ CSRF token ที่ส่งมากับคำขอ POST
 */
function verifyCsrfToken()
{
    $submittedToken = $_POST['csrf_token'] ?? '';
    $sessionToken = $_SESSION['csrf_token'] ?? '';

    if ($submittedToken === '' || $sessionToken === '' || !hash_equals($sessionToken, $submittedToken)) {
        $_SESSION['alert'] = [
            'type'  => 'warning',
            'title' => 'เซสชันหมดอายุ',
            'text'  => 'กรุณาลองทำรายการใหม่อีกครั้ง',
        ];
        $fallbackUrl = $_SERVER['HTTP_REFERER'] ?? 'index.php';
        header("Location: " . $fallbackUrl);
        exit;
    }
}

/**
 * แสดง hidden input สำหรับ CSRF token
 */
function csrfField()
{
    $token = htmlspecialchars(generateCsrfToken(), ENT_QUOTES, 'UTF-8');
    echo '<input type="hidden" name="csrf_token" value="' . $token . '">';
}

function canRegisterActivity()
{
    if (!isLoggedIn()) {
        return false;
    }
    
    $userType = $_SESSION['user_type'] ?? 'student';
    $role = getCurrentUserRole();
    
    return ($userType !== 'advisor' && $role !== 'advisor');
}

/**
 * ตรวจสอบสิทธิ์เมนู ERP ของคณะกรรมการ
 * ใช้ชุดกฎเดียวกับเมนูใน Header และบังคับใช้กับ backend ด้วย
 */
function committeeCanAccess($menuName)
{
    $role = getCurrentUserRole();
    $position = $_SESSION['committee_position'] ?? null;

    // ผู้ดูแลระบบและอาจารย์ที่ปรึกษาเข้าถึงระบบจัดการได้ทั้งหมด
    if ($role === 'admin' || $role === 'advisor') {
        return true;
    }

    if ($role !== 'committee' && $role !== 'leader') {
        return false;
    }

    if ($role === 'leader') {
        $position = $position ?: 'president';
    }

    $permissionMap = [
        'activities' => ['president', 'vice_president', 'secretary_saluang', 'secretary_wiangbua'],
        'budgets' => ['president', 'vice_president', 'treasurer'],
        'vehicles' => ['president', 'vice_president', 'venue_coordinator'],
        'reports' => ['president', 'vice_president', 'secretary_saluang', 'secretary_wiangbua'],
        'qr_scanner' => ['president', 'vice_president', 'treasurer', 'secretary_saluang', 'secretary_wiangbua', 'nurse', 'venue_coordinator'],
        'members' => ['president', 'vice_president', 'secretary_saluang', 'secretary_wiangbua'],
    ];

    if (!isset($permissionMap[$menuName])) {
        return false;
    }

    return in_array($position, $permissionMap[$menuName], true);
}

/**
 * บังคับสิทธิ์เมนู ERP ฝั่ง server
 * ไม่พึ่งการซ่อนเมนูใน HTML เพราะผู้ใช้สามารถเปิด URL โดยตรงได้
 */
function requireCommitteePermission($menuName)
{
    if (!committeeCanAccess($menuName)) {
        header('Location: index.php');
        exit;
    }
}



/**
 * บังคับสิทธิ์สำหรับหน้ารายงานและผลการประเมิน
 */
function requireReportPermission()
{
    $role = getCurrentUserRole();

    if (in_array($role, ['admin', 'advisor'], true)) {
        return;
    }

    if ($role === 'committee' && committeeCanAccess('reports')) {
        return;
    }

    header('Location: index.php');
    exit;
}



/**
 * แปลงวันที่ภาษาอังกฤษ (YYYY-MM-DD) เป็นรูปแบบวันที่ภาษาไทย
 * เช่น "2024-01-15" → "15 มกราคม 2567"
 * คืนค่า "-" ถ้าข้อมูลวันที่ไม่ถูกต้องหรือว่างเปล่า
 */
function DateThai($dateString)
{
    // ตรวจสอบว่ามีข้อมูลวันที่หรือไม่
    if (empty($dateString)) {
        return "-";
    }

    // แปลง string เป็น timestamp
    $timestamp = strtotime($dateString);

    // ตรวจสอบว่า timestamp ถูกต้องหรือไม่
    if ($timestamp === false || $timestamp === 0) {
        return "-";
    }

    // ดึงปี ค.ศ. และแปลงเป็นปี พ.ศ. (บวก 543)
    $yearChristianEra = (int)date("Y", $timestamp);
    $yearBuddhistEra = $yearChristianEra + 543;

    // ดึงเดือนและวันที่
    $monthNumber = (int)date("n", $timestamp);
    $dayNumber   = (int)date("j", $timestamp);

    // รายชื่อเดือนภาษาไทย (index เริ่มต้นจาก 1)
    $thaiMonthNames = [
        '',
        'มกราคม',
        'กุมภาพันธ์',
        'มีนาคม',
        'เมษายน',
        'พฤษภาคม',
        'มิถุนายน',
        'กรกฎาคม',
        'สิงหาคม',
        'กันยายน',
        'ตุลาคม',
        'พฤศจิกายน',
        'ธันวาคม',
    ];

    // ดึงชื่อเดือนจาก array
    $monthNameThai = $thaiMonthNames[$monthNumber];

    // คืนค่าวันที่ในรูปแบบภาษาไทย
    return "$dayNumber $monthNameThai $yearBuddhistEra";
}

/**
 * ฟังก์ชันชื่อสำรอง formatDateThai() เพื่อความเข้ากันได้
 */
function formatDateThai($dateString)
{
    return DateThai($dateString);
}

// ฟังก์ชันสำหรับระบบเกณฑ์คะแนนเกียรติบัตร

// ดึงเกณฑ์คะแนนจิตอาสาสำหรับรับเกียรติบัตร (ค่าเริ่มต้นคือ 50 แต้ม)
function getCertificatePointThreshold()
{
    global $conn;
    if (isset($conn) && $conn instanceof mysqli) {
        try {
            $res = $conn->query("SELECT setting_value FROM club_settings WHERE setting_key = 'certificate_point_threshold'");
            if ($res && $row = $res->fetch_assoc()) {
                return (int)$row['setting_value'];
            }
        } catch (\Throwable $e) {
            error_log($e->getMessage());
        }
    }
    return 50;
}

// บันทึกเกณฑ์คะแนนจิตอาสาใหม่
function setCertificatePointThreshold($points)
{
    global $conn;
    if (!isset($conn) || !($conn instanceof mysqli)) {
        return false;
    }
    $points = (int)$points;
    if ($points < 1) {
        $points = 1;
    }
    try {
        $stmt = $conn->prepare("INSERT INTO club_settings (setting_key, setting_value) VALUES ('certificate_point_threshold', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        $pointsStr = (string)$points;
        $stmt->bind_param("ss", $pointsStr, $pointsStr);
        $res = $stmt->execute();
        $stmt->close();
        return $res;
    } catch (\Throwable $e) {
        error_log($e->getMessage());
        return false;
    }
}
