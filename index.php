<?php include 'db.php'; ?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>ชมรมเพื่อนภูผา - หน้าหลัก</title>
    <link rel="stylesheet" href="style.css">
</head>

<body>
    <?php include 'header.php'; ?>

    <div class="container">
        <h1 class="main-title">ยินดีต้อนรับสู่ ชมรมเพื่อนภูผา</h1>
        <p style="text-align: center; margin-bottom: 40px; color:#666;">
            ศูนย์รวมกิจกรรมสร้างสรรค์และค่ายอาสาพัฒนาเพื่อสังคมและโรงเรียน
        </p>

        <div class="grid-3">
            <?php
            // ดึงข้อมูล 6 กิจกรรมล่าสุด
            $sql = "SELECT * FROM activities ORDER BY act_date DESC LIMIT 6";
            $result = $conn->query($sql);

            while ($row = $result->fetch_assoc()) {
            ?>
                <div class="card">
                    <div class="card-img" style="height: 200px; overflow: hidden;">
                        <img src="uploads/<?php echo $row['cover_img']; ?>" style="width: 100%; height: 100%; object-fit: cover;" onerror="this.src='https:'">
                    </div>

                    <div class="card-body" style="padding: 20px;">
                        <span class="card-tag"><?php echo $row['type'] == 'new' ? 'กิจกรรมใหม่' : 'กิจกรรมที่ผ่านมา'; ?></span>
                        <h3 class="card-title" style="margin-bottom: 10px; font-size: 18px; color: #2c3e50;"><?php echo $row['title']; ?></h3>
                        <p class="card-text" style="font-size: 14px; color: #666; margin-bottom: 15px; line-height: 1.5;">
                            <?php echo mb_substr($row['description'], 0, 100, 'utf-8') . '...'; ?>
                        </p>
                        <div style="margin-bottom: 12px;">
                            <small style="color: #999;">จัดเมื่อ: <?php echo $row['act_date']; ?></small>
                        </div>

                        <?php if (!empty($row['fb_link'])): ?>
                            <a href="<?php echo $row['fb_link']; ?>" target="_blank" rel="noopener noreferrer" style="display: inline-block; background-color: #1877F2; color: white; text-decoration: none; padding: 10px 16px; border-radius: 5px; font-size: 13px; font-weight: bold; transition: background 0.2s;">
                                <i class="fa-brands fa-facebook"></i> ดูรายละเอียดบน Facebook
                            </a>
                        <?php else: ?>
                            <a href="#" style="display: inline-block; background-color: #7f8c8d; color: white; text-decoration: none; padding: 10px 16px; border-radius: 5px; font-size: 13px; font-weight: bold; pointer-events: none; opacity: 0.6;">
                                <i class="fa-brands fa-facebook"></i> ไม่มีลิงก์กิจกรรม
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php
            }
            ?>
        </div>
    </div>
</body>

</html>