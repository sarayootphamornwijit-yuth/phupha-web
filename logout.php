<?php
// logout.php
// ไฟล์นี้ทำหน้าที่ออกจากระบบ (Log Out) ของผู้ใช้งาน
// เมื่อเข้าถึงหน้านี้ ระบบจะลบ Session ทั้งหมดและพาผู้ใช้ไปยังหน้าหลัก

// ส่วนที่ 1 เริ่มต้น Session
// เริ่ม Session เพื่อให้สามารถลบข้อมูลใน Session ได้
session_start();


// ส่วนที่ 2 ลบข้อมูล Session ทั้งหมด
// ลบตัวแปรใน Session ทั้งหมด (user_id, name, role, ฯลฯ)
session_unset();

// ทำลาย Session ให้สิ้นสุดอย่างสมบูรณ์
session_destroy();


// ส่วนที่ 3 พาผู้ใช้กลับไปยังหน้าหลัก
// หลังออกจากระบบแล้ว เปลี่ยนเส้นทาง (Redirect) ไปยังหน้า index
header("Location: index.php");
exit;
