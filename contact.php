<?php
// contact.php
// ไฟล์นี้ทำหน้าที่แสดงหน้า "ติดต่อเรา" พร้อมช่องทางการติดต่อต่างๆ
// ทุกคนสามารถเข้าชมได้โดยไม่ต้องล็อกอิน

require_once 'config/database.php';

// โหลด Header ซึ่งรวม navigation bar และ HTML head
include 'includes/header.php';
?>

<div class="container py-4" style="max-width: 800px;">

    <!-- ส่วนหัวของหน้า -->
    <div class="text-center mb-4">
        <h1 class="text-main m-0">ติดต่อเรา</h1>
    </div>

    <!-- การ์ดช่องทางการติดต่อ -->
    <div class="card text-center p-4">

        <!-- ไอคอนประกอบหัวข้อ -->
        <i class="fa-solid fa-envelope-open-text text-main mb-3" style="font-size: 60px;"></i>
        <h2 class="text-main mb-4">ช่องทางการติดต่อ</h2>

        <!-- รายการช่องทางติดต่อ (ใช้ d-inline-block เพื่อให้กล่องอยู่กลางหน้า แต่เนื้อหาชิดซ้าย) -->
        <div class="d-inline-block text-start mx-auto">
            <ul class="list-unstyled text-muted text-md mb-0">

                <!-- Facebook -->
                <li class="mb-3">
                    <a href="https://www.facebook.com/profile.php?id=100067001889511"
                       target="_blank"
                       class="d-flex align-items-center gap-3 text-decoration-none text-muted">
                        <!-- fa-fw ล็อกความกว้างไอคอนให้เท่ากัน ทำให้ข้อความเรียงตรงกัน -->
                        <i class="fa-brands fa-facebook text-main fa-fw" style="font-size: 1.5rem;"></i>
                        <span>ชมรมเพื่อนภูผา cmru</span>
                    </a>
                </li>

                <!-- TikTok -->
                <li class="mb-3">
                    <a href="https://www.tiktok.com/@puean_phupa?_r=1&_t=ZS-98lCjiCv9oS"
                       target="_blank"
                       class="d-flex align-items-center gap-3 text-decoration-none text-muted">
                        <i class="fa-brands fa-tiktok text-main fa-fw" style="font-size: 1.5rem;"></i>
                        <span>@puean_phupa</span>
                    </a>
                </li>

                <!-- เบอร์โทรศัพท์ -->
                <li class="mb-3">
                    <i class="fa-solid fa-mobile-screen-button text-main fa-fw" style="font-size: 1.5rem;"></i>
                    <span>093 171 0884</span>
                </li>

            </ul>
        </div>
    </div>

</div>

<?php include 'includes/footer.php'; ?>