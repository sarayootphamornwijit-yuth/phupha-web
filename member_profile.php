<?php
// member_profile.php
// ปรับรวมระบบโปรไฟล์เข้ากับ profile.php เพื่อใช้งานร่วมกันในระบบเดียว (Unified Profile System)
// ทำหน้าที่ส่งต่อไปยัง profile.php?id=... เพื่อรักษาความเข้ากันได้ย้อนหลัง 100%

require_once 'config/database.php';

$targetId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
header("Location: profile.php" . ($targetId > 0 ? "?id=" . $targetId : ""));
exit;
