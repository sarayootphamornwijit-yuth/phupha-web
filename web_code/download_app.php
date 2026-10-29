<?php
// ไฟล์สำหรับดาวน์โหลดแอพพลิเคชัน Android (.apk) ของชมรมเพื่อนภูผา
$apkPath = __DIR__ . '/uploads/app-release.apk';

if (is_file($apkPath) && is_readable($apkPath) && filesize($apkPath) > 0) {
    // ส่งไฟล์ APK ให้ดาวน์โหลดโดยตรง
    header('Content-Description: File Transfer');
    header('Content-Type: application/vnd.android.package-archive');
    header('Content-Disposition: attachment; filename="phupha_club.apk"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($apkPath));
    readfile($apkPath);
    exit;
} else {
    //ส่งสถานะให้ทราบว่ายังไม่มีไฟล์ติดตั้ง แทนการตอบว่าดาวน์โหลดสำเร็จ
    http_response_code(404);
    // หากโฮสต์บล็อกหรือขนาดไฟล์เกิน 10MB ให้แจ้งข้อความและแสดงทางเลือกดาวน์โหลด
    echo '<!DOCTYPE html><html lang="th"><head><meta charset="UTF-8"><title>ดาวน์โหลดแอพชมรมเพื่อนภูผา</title>';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
    echo '<style>body{font-family:sans-serif;background:#F1F8E9;padding:24px;text-align:center;} .card{background:#fff;border-radius:16px;padding:24px;max-width:480px;margin:40px auto;box-shadow:0 4px 12px rgba(0,0,0,0.08);} .btn{display:inline-block;background:#2E7D32;color:#fff;padding:12px 24px;border-radius:8px;text-decoration:none;font-weight:bold;margin-top:16px;}</style>';
    echo '</head><body><div class="card">';
    echo '<h2 style="color:#2E7D32;">ดาวน์โหลดแอพชมรมเพื่อนภูผา</h2>';
    echo '<p>ยังไม่มีไฟล์ติดตั้ง Android บนเซิร์ฟเวอร์ กรุณาให้ผู้ดูแลสร้าง APK แล้ววางไฟล์ในโฟลเดอร์ uploads ก่อน</p>';
    echo '<a href="download_app.php" class="btn">ตรวจสอบไฟล์ติดตั้งอีกครั้ง</a>';
    echo '<div style="margin-top:20px;"><a href="index.php" style="color:#666;font-size:14px;">« กลับหน้าหลักเว็บไซต์</a></div>';
    echo '</div></body></html>';
    exit;
}
