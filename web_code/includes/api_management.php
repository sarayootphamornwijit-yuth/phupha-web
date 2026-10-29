<?php
//ไฟล์นี้เรียกจาก api.php เท่านั้น โดยตรวจ session และสิทธิ์เมนูมาก่อนแล้ว
if (!isset($action, $conn) || !defined('DB_NAME')) {
    http_response_code(404);
    exit;
}

//หน้ารายชื่อสมาชิกใช้ข้อมูลทุกคน ไม่ได้ประมาณจำนวนจากคณะกรรมการ
if ($action === 'get_members') {
    $result = $conn->query("SELECT user_id, username, name, user_type, student_id, role, committee_position, faculty, major, phone FROM users ORDER BY name");
    sendResponse('success', 'ดึงข้อมูลสมาชิกสำเร็จ', $result->fetch_all(MYSQLI_ASSOC));
}

//คนที่ยกเลิกกิจกรรมแล้วจะไม่นับเป็นผู้จองที่นั่ง
if ($action === 'get_all_vehicles') {
    $result = $conn->query("SELECT v.*, a.title AS activity_title, (SELECT COUNT(*) FROM user_activities ua WHERE ua.vehicle_id = v.vehicle_id AND ua.status != 'cancelled') AS booked_seats FROM vehicles v JOIN activities a ON a.activity_id = v.activity_id ORDER BY a.start_date DESC, v.vehicle_id");
    sendResponse('success', 'ดึงข้อมูลรถสำเร็จ', $result->fetch_all(MYSQLI_ASSOC));
}

//แยกยอดจัดสรรกับยอดใช้จริง โดยอ่านจากรายการบัญชีเดียวกับเว็บไซต์
if ($action === 'get_budget_summary') {
    $totals = $conn->query("SELECT COALESCE(SUM(CASE WHEN transaction_type = 'income' THEN amount ELSE 0 END), 0) AS allocated, COALESCE(SUM(CASE WHEN transaction_type = 'expense' THEN amount ELSE 0 END), 0) AS spent FROM budget_transactions")->fetch_assoc();
    $rows = $conn->query("SELECT b.*, a.title AS activity_title FROM budget_transactions b JOIN activities a ON a.activity_id = b.activity_id ORDER BY b.created_at DESC, b.transaction_id DESC LIMIT 50")->fetch_all(MYSQLI_ASSOC);
    sendResponse('success', 'ดึงข้อมูลบัญชีสำเร็จ', ['allocated' => (float)$totals['allocated'], 'spent' => (float)$totals['spent'], 'transactions' => $rows]);
}

//คะแนนเฉลี่ยมาจากคำถามทั้งหกข้อ และนับผู้ร่วมงานจากคนที่เช็คอินจริง
if ($action === 'get_report_summary') {
    $activityCount = (int)$conn->query("SELECT COUNT(*) FROM activities")->fetch_row()[0];
    $participantCount = (int)$conn->query("SELECT COUNT(*) FROM user_activities WHERE status IN ('checked_in', 'checked_out')")->fetch_row()[0];
    $average = (float)$conn->query("SELECT COALESCE(AVG((q1 + q2 + q3 + q4 + q5 + q6) / 6.0), 0) FROM evaluations")->fetch_row()[0];
    $reports = $conn->query("SELECT a.activity_id, a.title, COUNT(e.evaluation_id) AS evaluation_count, COALESCE(AVG((e.q1 + e.q2 + e.q3 + e.q4 + e.q5 + e.q6) / 6.0), 0) AS average_score FROM activities a LEFT JOIN evaluations e ON e.activity_id = a.activity_id GROUP BY a.activity_id, a.title ORDER BY a.activity_id DESC")->fetch_all(MYSQLI_ASSOC);
    sendResponse('success', 'ดึงข้อมูลรายงานสำเร็จ', ['activity_count' => $activityCount, 'participant_count' => $participantCount, 'average_score' => $average, 'reports' => $reports]);
}

if ($action === 'create_news') {
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    if ($title === '' || $content === '') {
        sendResponse('error', 'กรุณากรอกหัวข้อและเนื้อหาข่าว');
    }
    $authorId = (int)$_SESSION['user_id'];
    $stmt = $conn->prepare("INSERT INTO news (title, content, author_id, is_published) VALUES (?, ?, ?, 1)");
    $stmt->bind_param('ssi', $title, $content, $authorId);
    $stmt->execute();
    sendResponse('success', 'เผยแพร่ข่าวประชาสัมพันธ์แล้ว');
}

if ($action === 'delete_news') {
    $newsId = (int)($_POST['news_id'] ?? 0);
    $stmt = $conn->prepare("DELETE FROM news WHERE news_id = ?");
    $stmt->bind_param('i', $newsId);
    $stmt->execute();
    if ($stmt->affected_rows === 0) {
        sendResponse('error', 'ไม่พบข่าวที่ต้องการลบ');
    }
    sendResponse('success', 'ลบข่าวเรียบร้อย');
}
