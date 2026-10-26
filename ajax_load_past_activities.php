<?php
// ajax_load_past_activities.php
// สคริปต์สำหรับโหลดข้อมูลกิจกรรมที่ผ่านมาเพิ่มขึ้นเรื่อยๆ (Infinite Scroll / Load More)
// ข้อมูลที่แสดงจะต้องผ่านการอนุมัติ (approved) และวันที่จัดต้องน้อยกว่าวันปัจจุบัน

require_once 'config/database.php';


// ส่วนที่ 1 ตั้งค่าและเตรียมการค้นหา
// กำหนดตัวแปรสำหรับ Offset เพื่อใช้ในคำสั่ง LIMIT และดึงข้อมูลจำนวน 6 รายการต่อครั้ง
$queryOffset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
$limitPerLoad = 6;


// ส่วนที่ 2 ดึงข้อมูลกิจกรรมที่ผ่านมาเพิ่มจากฐานข้อมูล
// เงื่อนไข: วันเวลาสิ้นสุดต้องเป็นอดีต (< NOW()) และ approval_status ต้องเท่ากับ 'approved'
$pastActivitiesStatement = $conn->prepare("
    SELECT * 
    FROM activities 
    WHERE IFNULL(end_date, start_date) < NOW() AND approval_status = 'approved' 
    ORDER BY IFNULL(end_date, start_date) DESC 
    LIMIT ? OFFSET ?
");

$pastActivitiesStatement->bind_param("ii", $limitPerLoad, $queryOffset);
$pastActivitiesStatement->execute();
$pastActivitiesResult = $pastActivitiesStatement->get_result();


// ส่วนที่ 3 วนลูปสร้าง HTML ของแต่ละกิจกรรมเพื่อส่งกลับไปให้หน้าบ้าน
if ($pastActivitiesResult->num_rows > 0) {
    
    while ($activityItem = $pastActivitiesResult->fetch_assoc()) {
        
        // 3.1 การตรวจสอบและเตรียมแสดงภาพปก
        if (!empty($activityItem['cover_image'])) {
            $coverImageHtml = '<img src="' . htmlspecialchars($activityItem['cover_image']) . '" alt="Cover" style="width: 100%; height: 100%; object-fit: cover;" onerror="this.onerror=null; this.parentElement.innerHTML=\'<div class=\\\'d-flex justify-center align-center h-100 text-muted bg-surface\\\'><i class=\\\'fa-solid fa-image fa-2x\\\' style=\\\'opacity: 0.4;\\\'></i></div>\';">';
        } else {
            $coverImageHtml = '<div class="d-flex justify-center align-center h-100 text-muted bg-surface"><i class="fa-solid fa-image fa-2x" style="opacity: 0.4;"></i></div>';
        }
            
        // 3.2 จัดเตรียมข้อมูลทั่วไปของกิจกรรม
        $descriptionText = htmlspecialchars($activityItem['description'] ?? '');
        $activityTitle   = htmlspecialchars($activityItem['title']);
        $startDateStr    = date('d/m/Y', strtotime($activityItem['start_date']));
        $activityIdValue = $activityItem['activity_id'];
        
        // 3.3 ตรวจสอบและสร้างปุ่มลิงก์ไปยัง Facebook (ถ้ามี)
        $facebookLinkUrl = !empty($activityItem['facebook_link']) ? htmlspecialchars($activityItem['facebook_link']) : '#';
        
        if ($facebookLinkUrl !== '#') {
            $facebookButtonHtml = '<a href="' . $facebookLinkUrl . '" target="_blank" class="btn btn-block text-white mb-2" style="background: #1976D2; border: none; text-decoration: none;"><i class="fa-brands fa-facebook me-1"></i> ดูเพิ่มเติมบน Facebook</a>';
        } else {
            $facebookButtonHtml = '<button class="btn btn-block bg-main text-muted cursor-default mb-2" disabled>ไม่มีลิงก์เฟสบุ๊ค</button>';
        }

        // 3.4 รวบรวม HTML แล้วทำการ echo ออกไปให้ Javascript รับไปแสดง
        echo <<<HTML
        <div class="card p-0 overflow-hidden d-flex flex-column h-100 shadow-hover transition-all">
            
            <!-- ส่วนแสดงรูปภาพปกกิจกรรม -->
            <a href="activity_details.php?id={$activityIdValue}" class="position-relative" style="height: 160px; display: block; background: var(--bg-main);">
                {$coverImageHtml}
            </a>

            <!-- ส่วนแสดงรายละเอียดกิจกรรม -->
            <div class="card-body p-3 d-flex flex-column flex-grow-1">
                <h3 class="mt-0 mb-2 text-md text-main font-weight-bold">{$activityTitle}</h3>
                
                <p class="text-muted text-sm flex-grow-1 mb-3 line-clamp-2">
                    {$descriptionText}
                </p>
                
                <div class="text-sm text-main mb-3">
                    <i class="fa-solid fa-calendar-days text-muted me-1"></i>
                    <strong>วันที่:</strong> {$startDateStr}
                </div>

                <!-- ส่วนปุ่มกด (ลิงก์เฟสบุ๊ค และ ลิงก์รายละเอียด) -->
                <div class="mt-auto pt-3 border-top">
                    {$facebookButtonHtml}
                    <a href="activity_details.php?id={$activityIdValue}" class="btn btn-block btn-surface border-light text-muted">ดูรายละเอียด</a>
                </div>
            </div>
            
        </div>
HTML;
    }
}

// ปิดการเชื่อมต่อ Statement
$pastActivitiesStatement->close();
?>
