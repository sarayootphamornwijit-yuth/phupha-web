<?php
//ใช้ตารางเดียวกับเว็บ แต่ตอบเป็น JSON เพื่อให้ Flutter สร้างหน้าจอของตัวเองได้
if (!isset($action, $conn)) {
    http_response_code(404);
    exit;
}

//รับเฉพาะไฟล์ที่ผู้ใช้เลือก ขนาดไม่เกิน 5 MB และตรวจชนิดจากเนื้อไฟล์จริง
function saveMobileUpload($field, $folder, $allowPdf = false) {
    if (!isset($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    $file = $_FILES[$field];
    if (in_array($file['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
        sendResponse('error', 'ไฟล์มีขนาดเกินที่ระบบรับได้ กรุณาเลือกไฟล์เล็กลง');
    }
    if ($file['error'] !== UPLOAD_ERR_OK || $file['size'] > 5 * 1024 * 1024) {
        sendResponse('error', 'อัปโหลดไม่สำเร็จ กรุณาเลือกไฟล์ไม่เกิน 5 MB');
    }
    $types = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif'];
    if ($allowPdf) {
        $types['application/pdf'] = 'pdf';
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if (!isset($types[$mime])) {
        sendResponse('error', 'ชนิดไฟล์ไม่ถูกต้อง กรุณาเลือกรูปภาพ' . ($allowPdf ? ' หรือ PDF' : ''));
    }
    $directory = __DIR__ . '/../uploads/' . $folder;
    if (!is_dir($directory)) {
        mkdir($directory, 0755, true);
    }
    $path = 'uploads/' . $folder . '/' . bin2hex(random_bytes(16)) . '.' . $types[$mime];
    if (!move_uploaded_file($file['tmp_name'], __DIR__ . '/../' . $path)) {
        sendResponse('error', 'เซิร์ฟเวอร์บันทึกไฟล์ไม่ได้ กรุณาตรวจโฟลเดอร์ uploads');
    }
    return $path;
}

function mobileActivity($conn, $activityId, $lock = false) {
    $sql = 'SELECT * FROM activities WHERE activity_id = ?';
    if ($lock) {
        $sql .= ' FOR UPDATE';
    }
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $activityId);
    $stmt->execute();
    $activity = $stmt->get_result()->fetch_assoc();
    if (!$activity) {
        if ($lock) {
            $conn->rollback();
        }
        sendResponse('error', 'ไม่พบกิจกรรม');
    }
    return $activity;
}

//ตรวจรูปแบบวันเวลาเต็ม ไม่ยอมให้วันที่ผิดกลายเป็นวันอื่นเงียบ ๆ
function mobileDate($value) {
    $value = str_replace('T', ' ', trim($value));
    if (strlen($value) === 16) {
        $value .= ':00';
    }
    $date = DateTime::createFromFormat('!Y-m-d H:i:s', $value);
    if (!$date || $date->format('Y-m-d H:i:s') !== $value) {
        sendResponse('error', 'วันเวลาไม่ถูกต้อง');
    }
    return $value;
}

//แต่ละฝ่ายต้องเลือกกิจกรรมก่อนจัดรถ บัญชี หรือรายงานได้ แม้ไม่มีสิทธิ์แก้กิจกรรม
if ($action === 'get_management_options') {
    $menu = $_GET['menu'] ?? '';
    if (!in_array($menu, ['activities', 'vehicles', 'budgets', 'reports'], true) || !committeeCanAccess($menu)) {
        http_response_code(403);
        sendResponse('error', 'คุณไม่มีสิทธิ์ใช้งานส่วนนี้');
    }
    $rows = $conn->query('SELECT activity_id,title,start_date FROM activities ORDER BY start_date DESC')->fetch_all(MYSQLI_ASSOC);
    sendResponse('success', 'โหลดกิจกรรมแล้ว', $rows);
}

//ใช้รายชื่อผู้ประสานงานแบบเดียวกับฟอร์มเว็บ ไม่ผูกกับชั้นปีที่ใช้แสดงทำเนียบ
if ($action === 'get_activity_contacts') {
    $rows = $conn->query("SELECT user_id,name,phone,student_id FROM users WHERE role='committee' AND committee_position='venue_coordinator' ORDER BY student_id,name")->fetch_all(MYSQLI_ASSOC);
    if (empty($rows)) {
        $rows = $conn->query("SELECT user_id,name,phone,student_id FROM users WHERE role='committee' ORDER BY student_id,name")->fetch_all(MYSQLI_ASSOC);
    }
    sendResponse('success', 'โหลดผู้ประสานงานแล้ว', $rows);
}

if ($action === 'save_activity') {
    $activityId = (int)($_POST['activity_id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category = $_POST['category'] ?? 'volunteer';
    $type = $_POST['activity_type'] ?? 'single_day';
    $location = trim($_POST['location_name'] ?? '');
    $start = mobileDate($_POST['start_date'] ?? '');
    $endInput = trim($_POST['end_date'] ?? '');
    $end = $endInput === '' ? $start : mobileDate($endInput);
    $capacity = filter_var($_POST['max_participants'] ?? '', FILTER_VALIDATE_INT);
    $points = filter_var($_POST['points'] ?? '', FILTER_VALIDATE_INT);
    if ($title === '' || $location === '' || !preg_match('/^.{1,255}$/us', $title) || $capacity === false || $capacity < 0 || $points === false || $points < 1 || $end < $start) {
        sendResponse('error', 'กรุณาตรวจชื่อ สถานที่ จำนวนคน แต้ม และช่วงเวลากิจกรรม');
    }
    if (!in_array($category, ['volunteer', 'sport', 'byaniyan', 'other'], true) || !in_array($type, ['single_day', 'multi_day'], true)) {
        sendResponse('error', 'ประเภทกิจกรรมไม่ถูกต้อง');
    }
    $facebook = trim($_POST['facebook_link'] ?? '');
    if ($facebook !== '' && (!filter_var($facebook, FILTER_VALIDATE_URL) || !in_array(parse_url($facebook, PHP_URL_SCHEME), ['https', 'http'], true))) {
        sendResponse('error', 'ลิงก์ Facebook ไม่ถูกต้อง');
    }
    $meeting = trim($_POST['meeting_point'] ?? '');
    $meetingTime = trim($_POST['meeting_time'] ?? '');
    $meetingTime = $meetingTime === '' ? null : mobileDate($meetingTime);
    $selfCheckin = ($_POST['self_checkin_enabled'] ?? '0') === '1' ? 1 : 0;
    $venue = (int)($_POST['venue_contact_user_id'] ?? 0);
    $venue = $venue > 0 ? $venue : null;
    if ($venue !== null) {
        $stmt = $conn->prepare('SELECT user_id FROM users WHERE user_id = ? AND role IN (\'committee\', \'leader\')');
        $stmt->bind_param('i', $venue);
        $stmt->execute();
        if (!$stmt->get_result()->fetch_assoc()) {
            sendResponse('error', 'ไม่พบผู้ประสานงาน');
        }
    }
    $old = $activityId > 0 ? mobileActivity($conn, $activityId) : null;
    $cover = saveMobileUpload('cover_image', 'activities');
    if ($cover === null && $old) {
        $cover = $old['cover_image'];
    }
    if ($old) {
        $stmt = $conn->prepare("UPDATE activities SET title=?, description=?, category=?, activity_type=?, location_name=?, start_date=?, end_date=?, max_participants=?, points=?, facebook_link=?, meeting_point=?, meeting_time=?, self_checkin_enabled=?, venue_contact_user_id=?, cover_image=?, approval_status=IF(approval_status='rejected','pending',approval_status) WHERE activity_id=?");
        $stmt->bind_param('sssssssiisssiisi', $title, $description, $category, $type, $location, $start, $end, $capacity, $points, $facebook, $meeting, $meetingTime, $selfCheckin, $venue, $cover, $activityId);
    } else {
        $stmt = $conn->prepare("INSERT INTO activities(title,description,category,activity_type,location_name,start_date,end_date,max_participants,points,facebook_link,meeting_point,meeting_time,self_checkin_enabled,venue_contact_user_id,cover_image,approval_status) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'pending')");
        $stmt->bind_param('sssssssiisssiis', $title, $description, $category, $type, $location, $start, $end, $capacity, $points, $facebook, $meeting, $meetingTime, $selfCheckin, $venue, $cover);
    }
    $stmt->execute();
    if (!$old) {
        $activityId = $conn->insert_id;
    }
    sendResponse('success', $old ? 'บันทึกกิจกรรมแล้ว หากถูกตีกลับจะส่งขออนุมัติใหม่' : 'สร้างกิจกรรมและส่งให้อาจารย์อนุมัติแล้ว', ['activity_id' => $activityId]);
}

if ($action === 'delete_activity' || $action === 'toggle_activity_checkin') {
    $activityId = (int)($_POST['activity_id'] ?? 0);
    $conn->begin_transaction();
    mobileActivity($conn, $activityId, true);
    if ($action === 'delete_activity') {
        //ป้องกันลบประวัติคนที่ร่วมงานแล้วจนคะแนนกับหลักฐานไม่ตรงกัน
        $stmt = $conn->prepare("SELECT user_activity_id FROM user_activities WHERE activity_id=? AND status != 'cancelled' LIMIT 1");
        $stmt->bind_param('i', $activityId);
        $stmt->execute();
        if ($stmt->get_result()->fetch_assoc()) {
            $conn->rollback();
            sendResponse('error', 'กิจกรรมนี้มีผู้ลงทะเบียนแล้ว ไม่สามารถลบประวัติได้');
        }
        $stmt = $conn->prepare('DELETE FROM activities WHERE activity_id=?');
        $stmt->bind_param('i', $activityId);
    } else {
        $enabled = ($_POST['enabled'] ?? '0') === '1' ? 1 : 0;
        $stmt = $conn->prepare('UPDATE activities SET self_checkin_enabled=? WHERE activity_id=?');
        $stmt->bind_param('ii', $enabled, $activityId);
    }
    $stmt->execute();
    $conn->commit();
    sendResponse('success', 'บันทึกเรียบร้อยแล้ว');
}

$activityId = (int)($_POST['activity_id'] ?? $_GET['activity_id'] ?? 0);
if ($action === 'get_vehicle_management') {
    $activity = mobileActivity($conn, $activityId);
    $stmt = $conn->prepare("SELECT v.*, (SELECT COUNT(*) FROM user_activities ua WHERE ua.vehicle_id=v.vehicle_id AND ua.status!='cancelled') AS booked_seats FROM vehicles v WHERE activity_id=? ORDER BY vehicle_id");
    $stmt->bind_param('i', $activityId);
    $stmt->execute();
    sendResponse('success', 'โหลดข้อมูลเดินทางแล้ว', ['activity' => $activity, 'vehicles' => $stmt->get_result()->fetch_all(MYSQLI_ASSOC)]);
}

if (in_array($action, ['save_logistics', 'add_vehicle', 'delete_vehicle'], true)) {
    $conn->begin_transaction();
    mobileActivity($conn, $activityId, true);
    if ($action === 'save_logistics') {
        $meeting = trim($_POST['meeting_point'] ?? '');
        $survey = trim($_POST['survey_details'] ?? '');
        if ($meeting === '') {
            $conn->rollback();
            sendResponse('error', 'กรุณากรอกจุดนัดพบ');
        }
        $stmt = $conn->prepare('UPDATE activities SET meeting_point=?,survey_details=? WHERE activity_id=?');
        $stmt->bind_param('ssi', $meeting, $survey, $activityId);
    } elseif ($action === 'add_vehicle') {
        $type = trim($_POST['vehicle_type'] ?? '');
        $plate = trim($_POST['license_plate'] ?? '');
        $driver = trim($_POST['driver_name'] ?? '');
        $phone = trim($_POST['driver_phone'] ?? '');
        $capacity = filter_var($_POST['capacity'] ?? '', FILTER_VALIDATE_INT);
        if ($type === '' || $plate === '' || $driver === '' || $capacity === false || $capacity < 1) {
            $conn->rollback();
            sendResponse('error', 'กรุณากรอกประเภทรถ ทะเบียน คนขับ และจำนวนที่นั่งให้ครบ');
        }
        $stmt = $conn->prepare("SELECT vehicle_id FROM vehicles WHERE activity_id=? AND REPLACE(license_plate,' ','')=REPLACE(?,' ','')");
        $stmt->bind_param('is', $activityId, $plate);
        $stmt->execute();
        if ($stmt->get_result()->fetch_assoc()) {
            $conn->rollback();
            sendResponse('error', 'ทะเบียนรถนี้มีในกิจกรรมแล้ว');
        }
        $stmt = $conn->prepare('INSERT INTO vehicles(activity_id,vehicle_type,license_plate,driver_name,driver_phone,capacity) VALUES(?,?,?,?,?,?)');
        $stmt->bind_param('issssi', $activityId, $type, $plate, $driver, $phone, $capacity);
    } else {
        $vehicleId = (int)($_POST['vehicle_id'] ?? 0);
        $stmt = $conn->prepare("SELECT user_activity_id FROM user_activities WHERE vehicle_id=? AND status!='cancelled' LIMIT 1");
        $stmt->bind_param('i', $vehicleId);
        $stmt->execute();
        if ($stmt->get_result()->fetch_assoc()) {
            $conn->rollback();
            sendResponse('error', 'รถมีผู้จองแล้ว กรุณาให้สมาชิกเปลี่ยนรถก่อนลบ');
        }
        $stmt = $conn->prepare('DELETE FROM vehicles WHERE vehicle_id=? AND activity_id=?');
        $stmt->bind_param('ii', $vehicleId, $activityId);
    }
    $stmt->execute();
    $conn->commit();
    sendResponse('success', 'บันทึกข้อมูลเดินทางเรียบร้อยแล้ว');
}

if ($action === 'get_activity_budget' || $action === 'get_activity_report') {
    $activity = mobileActivity($conn, $activityId);
    $stmt = $conn->prepare('SELECT * FROM budget_transactions WHERE activity_id=? ORDER BY transaction_id DESC');
    $stmt->bind_param('i', $activityId);
    $stmt->execute();
    $transactions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $income = 0;
    $expense = 0;
    foreach ($transactions as $row) {
        if ($row['transaction_type'] === 'income') {
            $income += (float)$row['amount'];
        } elseif ($row['transaction_type'] === 'expense') {
            $expense += (float)$row['amount'];
        }
    }
    $data = ['activity' => $activity, 'transactions' => $transactions, 'income' => $income, 'expense' => $expense, 'balance' => $income - $expense];
    if ($action === 'get_activity_report') {
        //ใช้รายชื่อและสถานะเดียวกับหน้าพิมพ์ของเว็บ ไม่ประมาณจำนวนจากหน้าจอมือถือ
        $stmt = $conn->prepare('SELECT u.user_id,u.student_id,u.name,u.faculty,u.major,ua.status,ua.travel_method,ua.vehicle_id FROM user_activities ua JOIN users u ON u.user_id=ua.user_id WHERE ua.activity_id=? ORDER BY u.student_id');
        $stmt->bind_param('i', $activityId);
        $stmt->execute();
        unset($data['transactions']);
        $data['participants'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt = $conn->prepare('SELECT COUNT(*) AS count,AVG(q1) AS q1,AVG(q2) AS q2,AVG(q3) AS q3,AVG(q4) AS q4,AVG(q5) AS q5,AVG(q6) AS q6,AVG((q1+q2+q3+q4+q5+q6)/6.0) AS average FROM evaluations WHERE activity_id=?');
        $stmt->bind_param('i', $activityId);
        $stmt->execute();
        $data['evaluation'] = $stmt->get_result()->fetch_assoc();
        $stmt = $conn->prepare("SELECT v.*,(SELECT COUNT(*) FROM user_activities ua WHERE ua.vehicle_id=v.vehicle_id AND ua.status!='cancelled') AS booked_seats FROM vehicles v WHERE v.activity_id=?");
        $stmt->bind_param('i', $activityId);
        $stmt->execute();
        $data['vehicles'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    sendResponse('success', 'โหลดข้อมูลแล้ว', $data);
}

if ($action === 'add_budget' || $action === 'delete_budget') {
    $conn->begin_transaction();
    //ล็อกกิจกรรมระหว่างตรวจยอดและบันทึก ป้องกันเบิกพร้อมกันจนยอดติดลบ
    mobileActivity($conn, $activityId, true);
    $stmt = $conn->prepare("SELECT COALESCE(SUM(CASE WHEN transaction_type='income' THEN amount WHEN transaction_type='expense' THEN -amount ELSE 0 END),0) AS balance FROM budget_transactions WHERE activity_id=?");
    $stmt->bind_param('i', $activityId);
    $stmt->execute();
    $balance = (float)$stmt->get_result()->fetch_assoc()['balance'];
    if ($action === 'add_budget') {
        $type = $_POST['type'] ?? '';
        $category = trim($_POST['category'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $amount = $_POST['amount'] ?? '';
        if (!preg_match('/^\d{1,8}(\.\d{1,2})?$/', $amount) || (float)$amount <= 0 || !in_array($type, ['income', 'expense'], true) || $category === '') {
            $conn->rollback();
            sendResponse('error', 'กรุณากรอกประเภทรายการ หมวดหมู่ และจำนวนเงินที่มากกว่า 0');
        }
        $amount = (float)$amount;
        if ($type === 'expense' && $amount > $balance) {
            $conn->rollback();
            sendResponse('error', 'ยอดเงินไม่เพียงพอ');
        }
        $receipt = saveMobileUpload('receipt_image', 'receipts', true);
        $userId = (int)$actor['user_id'];
        $stmt = $conn->prepare('INSERT INTO budget_transactions(activity_id,transaction_type,category,description,amount,receipt_file,created_by) VALUES(?,?,?,?,?,?,?)');
        $stmt->bind_param('isssdsi', $activityId, $type, $category, $description, $amount, $receipt, $userId);
    } else {
        $id = (int)($_POST['transaction_id'] ?? 0);
        $stmt = $conn->prepare('SELECT * FROM budget_transactions WHERE transaction_id=? AND activity_id=?');
        $stmt->bind_param('ii', $id, $activityId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if (!$row || ($row['transaction_type'] === 'income' && $balance < (float)$row['amount'])) {
            $conn->rollback();
            sendResponse('error', 'ไม่พบรายการ หรือลบรายรับนี้แล้วเงินจะไม่พอ');
        }
        $stmt = $conn->prepare('DELETE FROM budget_transactions WHERE transaction_id=? AND activity_id=?');
        $stmt->bind_param('ii', $id, $activityId);
    }
    $stmt->execute();
    $conn->commit();
    sendResponse('success', 'บันทึกบัญชีเรียบร้อยแล้ว');
}

if ($action === 'get_management_news') {
    $rows = $conn->query('SELECT news_id,title,content,image_url,created_at,is_published FROM news ORDER BY created_at DESC')->fetch_all(MYSQLI_ASSOC);
    sendResponse('success', 'โหลดข่าวแล้ว', $rows);
}
if ($action === 'edit_news') {
    $id = (int)($_POST['news_id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    if ($title === '' || $content === '') {
        sendResponse('error', 'กรุณากรอกหัวข้อและเนื้อหาข่าว');
    }
    $stmt = $conn->prepare('SELECT news_id FROM news WHERE news_id=?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    if (!$stmt->get_result()->fetch_assoc()) {
        sendResponse('error', 'ไม่พบข่าว');
    }
    $stmt = $conn->prepare('UPDATE news SET title=?,content=?,is_published=1 WHERE news_id=?');
    $stmt->bind_param('ssi', $title, $content, $id);
    $stmt->execute();
    sendResponse('success', 'แก้ไขข่าวแล้ว');
}

if ($action === 'get_member_detail') {
    $id = (int)($_GET['target_id'] ?? 0);
    $stmt = $conn->prepare('SELECT user_id,username,name,user_type,role,committee_position,student_id,faculty,major,phone,points,occupation,academic_year FROM users WHERE user_id=?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    sendResponse('success', 'โหลดสมาชิกแล้ว', $stmt->get_result()->fetch_assoc());
}

if (in_array($action, ['get_admin_dashboard', 'get_admin_users', 'update_member_role', 'delete_member', 'update_certificate_threshold'], true)) {
    if ($actor['role'] !== 'admin') {
        http_response_code(403);
        sendResponse('error', 'เฉพาะผู้ดูแลระบบเท่านั้น');
    }
    if ($action === 'get_admin_dashboard') {
        //นับตามเงื่อนไขเดียวกับ admin_dashboard.php ไม่ใช้ตัวเลขตัวอย่างในมือถือ
        $users = (int)$conn->query('SELECT COUNT(*) FROM users')->fetch_row()[0];
        $activities = (int)$conn->query('SELECT COUNT(*) FROM activities')->fetch_row()[0];
        $newUsers = (int)$conn->query('SELECT COUNT(*) FROM users WHERE MONTH(created_at)=MONTH(CURRENT_DATE()) AND YEAR(created_at)=YEAR(CURRENT_DATE())')->fetch_row()[0];
        $statuses = $conn->query('SELECT approval_status AS status_type,COUNT(*) AS count FROM activities GROUP BY approval_status')->fetch_all(MYSQLI_ASSOC);
        $active = (int)$conn->query('SELECT COUNT(*) FROM (SELECT user_id FROM user_activities GROUP BY user_id HAVING COUNT(*)>3) AS joined_users')->fetch_row()[0];
        $regular = (int)$conn->query('SELECT COUNT(*) FROM (SELECT user_id FROM user_activities GROUP BY user_id HAVING COUNT(*) BETWEEN 1 AND 3) AS joined_users')->fetch_row()[0];
        sendResponse('success', 'โหลดแดชบอร์ดแล้ว', ['users'=>$users,'activities'=>$activities,'new_users'=>$newUsers,'statuses'=>$statuses,'active'=>$active,'regular'=>$regular,'inactive'=>$users-$active-$regular,'threshold'=>getCertificatePointThreshold()]);
    }
    if ($action === 'get_admin_users') {
        $users = $conn->query('SELECT user_id,username,name,user_type,role,committee_position,academic_year FROM users ORDER BY user_id DESC')->fetch_all(MYSQLI_ASSOC);
        sendResponse('success', 'โหลดข้อมูลผู้ดูแลแล้ว', ['users' => $users, 'threshold' => getCertificatePointThreshold(), 'current_user_id' => (int)$actor['user_id'], 'is_test_database' => str_ends_with((string)$conn->query('SELECT DATABASE()')->fetch_row()[0], '_test')]);
    }
    if ($action === 'update_certificate_threshold') {
        $threshold = filter_var($_POST['threshold'] ?? '', FILTER_VALIDATE_INT);
        if ($threshold === false || $threshold < 1) {
            sendResponse('error', 'เกณฑ์คะแนนต้องเป็นจำนวนเต็มมากกว่า 0');
        }
        setCertificatePointThreshold($threshold);
        sendResponse('success', 'บันทึกเกณฑ์คะแนนแล้ว');
    }
    $target = (int)($_POST['target_id'] ?? 0);
    if ($target === (int)$actor['user_id']) {
        sendResponse('error', 'ไม่สามารถเปลี่ยนสิทธิ์หรือลบบัญชีตัวเอง');
    }
    $stmt = $conn->prepare('SELECT user_id,user_type FROM users WHERE user_id=?');
    $stmt->bind_param('i', $target);
    $stmt->execute();
    $targetUser = $stmt->get_result()->fetch_assoc();
    if (!$targetUser) {
        sendResponse('error', 'ไม่พบสมาชิก');
    }
    if ($action === 'delete_member') {
        $stmt = $conn->prepare('DELETE FROM users WHERE user_id=?');
        $stmt->bind_param('i', $target);
    } else {
        $role = $_POST['role'] ?? '';
        $position = trim($_POST['committee_position'] ?? '');
        $positions = ['president','vice_president','treasurer','secretary_saluang','secretary_wiangbua','nurse','venue_coordinator','pr'];
        $year = (int)($_POST['academic_year'] ?? 0);
        if (!in_array($role, ['member','committee','advisor','admin'], true) || ($position !== '' && !in_array($position, $positions, true)) || $year < 0 || $year > 4) {
            sendResponse('error', 'สิทธิ์ ตำแหน่ง หรือชั้นปีไม่ถูกต้อง');
        }
        $position = $position === '' ? null : $position;
        $year = $targetUser['user_type'] === 'student' && $year > 0 ? $year : null;
        $stmt = $conn->prepare('UPDATE users SET role=?,committee_position=?,academic_year=? WHERE user_id=?');
        $stmt->bind_param('ssii', $role, $position, $year, $target);
    }
    $stmt->execute();
    sendResponse('success', 'บันทึกสมาชิกแล้ว');
}

//ข้อมูลส่วนตัวใช้รหัสจาก session เสมอ ผู้ใช้จึงแก้ได้เฉพาะบัญชีตัวเอง
if ($action === 'update_profile_details') {
    $userId = (int)$actor['user_id'];
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $age = (int)($_POST['age'] ?? 0);
    $location = trim($_POST['location'] ?? '');
    $interests = trim($_POST['interests'] ?? '');
    $occupation = trim($_POST['occupation'] ?? '');
    if ($age < 0 || $age > 150 || ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL))) {
        sendResponse('error', 'อายุหรืออีเมลไม่ถูกต้อง');
    }
    $age = $age === 0 ? null : $age;
    //คงข้อมูลที่ฟอร์มไม่ได้ให้แก้ไว้ เช่น คณะเดิมของอาจารย์ที่สมัครแบบบุคคลทั่วไป
    $facultyId = $actor['faculty_id'];
    $majorId = $actor['major_id'];
    $faculty = $actor['faculty'];
    $major = $actor['major'];
    $studentId = $actor['student_id'];
    if ($actor['user_type'] === 'student') {
        $studentId = trim($_POST['student_id'] ?? '');
        $facultyId = (int)($_POST['faculty_id'] ?? 0);
        $majorId = (int)($_POST['major_id'] ?? 0);
        if (!preg_match('/^\d{8}$/', $studentId)) {
            sendResponse('error', 'รหัสนักศึกษาต้องมี 8 หลัก');
        }
        $stmt = $conn->prepare('SELECT user_id FROM users WHERE student_id=? AND user_id!=?');
        $stmt->bind_param('si', $studentId, $userId);
        $stmt->execute();
        if ($stmt->get_result()->fetch_assoc()) {
            sendResponse('error', 'รหัสนักศึกษานี้ถูกใช้แล้ว');
        }
        $stmt = $conn->prepare('SELECT f.faculty_name,m.major_name FROM majors m JOIN faculties f ON f.faculty_id=m.faculty_id WHERE m.major_id=? AND f.faculty_id=?');
        $stmt->bind_param('ii', $majorId, $facultyId);
        $stmt->execute();
        $education = $stmt->get_result()->fetch_assoc();
        if (!$education) {
            sendResponse('error', 'คณะและสาขาไม่ตรงกัน');
        }
        $faculty = $education['faculty_name'];
        $major = $education['major_name'];
        $occupation = 'นักศึกษา';
    }
    $image = saveMobileUpload('image', 'profiles');
    if ($image === null) {
        $image = $actor['image'] ?: ($actor['profile_picture'] ?? null);
    }
    $stmt = $conn->prepare('UPDATE users SET phone=?,email=?,age=?,location=?,interests=?,occupation=?,faculty_id=?,major_id=?,faculty=?,major=?,student_id=?,image=?,profile_picture=? WHERE user_id=?');
    $stmt->bind_param('ssisssiisssssi', $phone, $email, $age, $location, $interests, $occupation, $facultyId, $majorId, $faculty, $major, $studentId, $image, $image, $userId);
    $stmt->execute();
    sendResponse('success', 'บันทึกข้อมูลส่วนตัวแล้ว');
}
