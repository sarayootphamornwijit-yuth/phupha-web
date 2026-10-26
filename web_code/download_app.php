<?php
// ไฟล์สำหรับดาวน์โหลดแอพพลิเคชัน Android (.apk) ของชมรมเพื่อนภูผา
$apkPath = __DIR__ . '/uploads/app-release.apk';

if (file_exists($apkPath)) {
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
    // หากโฮสต์บล็อกหรือขนาดไฟล์เกิน 10MB ให้แจ้งข้อความและแสดงทางเลือกดาวน์โหลด
    echo '<!DOCTYPE html><html lang="th"><head><meta charset="UTF-8"><title>ดาวน์โหลดแอพชมรมเพื่อนภูผา</title>';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
    echo '<style>body{font-family:sans-serif;background:#F1F8E9;padding:24px;text-align:center;} .card{background:#fff;border-radius:16px;padding:24px;max-width:480px;margin:40px auto;box-shadow:0 4px 12px rgba(0,0,0,0.08);} .btn{display:inline-block;background:#2E7D32;color:#fff;padding:12px 24px;border-radius:8px;text-decoration:none;font-weight:bold;margin-top:16px;}</style>';
    echo '</head><body><div class="card">';
    echo '<h2 style="color:#2E7D32;">ดาวน์โหลดแอพชมรมเพื่อนภูผา</h2>';
    echo '<p>เนื่องจากข้อจำกัดขนาดไฟล์ของโฮสติ้งฟรี (InfinityFree จำกัดไฟล์ไม่เกิน 10MB) กรุณาดาวน์โหลดผ่านลิงก์สำรอง (Google Drive)</p>';
    echo '<a href="https://drive.google.com" class="btn" target="_blank">ดาวน์โหลดผ่าน Google Drive</a>';
    echo '<div style="margin-top:20px;"><a href="index.php" style="color:#666;font-size:14px;">« กลับหน้าหลักเว็บไซต์</a></div>';
    echo '</div></body></html>';
    exit;
}
