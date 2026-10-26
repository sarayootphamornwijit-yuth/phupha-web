<?php
// admin_activity_detail.php
// ปรับปรุงเป็นตัวส่งต่อไปยัง activity_details.php พร้อมรักษาการตรวจสอบสิทธิ์ RBAC

require_once 'config/database.php';

if (!isLoggedIn()) {
    header("Location: index.php");
    exit;
}

$currentUserRole = getCurrentUserRole();

if ($currentUserRole === 'admin' || $currentUserRole === 'advisor') {
    // ผู้ดูแลระบบและอาจารย์ที่ปรึกษาเข้าถึงหน้ารายละเอียดกิจกรรมได้
} elseif ($currentUserRole === 'committee' && committeeCanAccess('activities')) {
    // คณะกรรมการต้องมีสิทธิ์จัดการกิจกรรมตามตำแหน่ง
} else {
    header('Location: index.php');
    exit;
}

$targetActivityId = (int)($_GET['id'] ?? 0);
if ($targetActivityId > 0) {
    header("Location: activity_details.php?id=" . $targetActivityId);
} else {
    header("Location: erp_activities.php");
}
exit;
