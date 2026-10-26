<?php
// about.php
// ไฟล์นี้ทำหน้าที่แสดงหน้า "เกี่ยวกับเรา" ของชมรมเพื่อนภูผา
// ทุกคนสามารถเข้าชมได้โดยไม่ต้องล็อกอิน

require_once 'config/database.php';

// โหลด Header ซึ่งรวม navigation bar และ HTML head
include 'includes/header.php';
?>

<div class="container py-4" style="max-width: 800px;">

    <!-- ส่วนหัวของหน้า -->
    <div class="text-center mb-4">
        <h1 class="text-main m-0">เกี่ยวกับเรา</h1>
    </div>

    <!-- การ์ดแสดงข้อมูลชมรม -->
    <div class="card text-center p-4">

        <!-- โลโก้ชมรม -->
        <img
            class="object-contain mx-auto mb-4"
            src="assets/images/logo.png"
            style="height: 120px; width: 120px;"
            alt="โลโก้ชมรมเพื่อนภูผา"
        >

        <!-- ชื่อชมรม -->
        <h2 class="text-main mb-3">ชมรมเพื่อนภูผา</h2>

        <!-- รายละเอียดเกี่ยวกับชมรม -->
        <p class="text-muted text-md m-0" style="line-height: 1.8;">
            เราคือองค์กรที่มุ่งเน้นการสร้างสรรค์กิจกรรมจิตอาสาเพื่อสังคม
            ไม่ว่าจะเป็นการพัฒนาชุมชน, การอนุรักษ์สิ่งแวดล้อม, และการศึกษา<br>

            แพลตฟอร์มนี้สร้างขึ้นเพื่ออำนวยความสะดวกในการจัดการสมาชิก
            การลงทะเบียนกิจกรรม และการสะสมแต้มจิตอาสา<br>

            เพื่อให้ทุกคนสามารถเข้าถึงและร่วมเป็นส่วนหนึ่งในการทำความดีได้อย่างง่ายดาย
        </p>
    </div>

</div>

<?php include 'includes/footer.php'; ?>