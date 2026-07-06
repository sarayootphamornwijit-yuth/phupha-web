<?php
// เริ่มต้นใช้งาน Session เพื่อดึงรหัสนักศึกษาของคนที่ล็อกอินอยู่มาใช้สมัครกิจกรรม
session_start();
include 'db.php';

$msg = "";

// ระบบประมวลผลการสมัครเข้าร่วมกิจกรรม

if (isset($_POST['join_activity'])) {
    // 1. เช็กก่อนว่าล็อกอินหรือยัง
    if (!isset($_SESSION['student_id'])) {
        $msg = "<div style='background-color: #fce4e4; color: #cc0000; padding: 12px; border-radius: 5px; margin-bottom: 20px; font-weight: bold; border: 1px solid #f8baba; text-align:center;'>กรุณาเข้าสู่ระบบก่อนสมัครเข้าร่วมกิจกรรม</div>";
    } else {
        $student_id = $_SESSION['student_id'];
        $activity_id = intval($_POST['activity_id']);

        try {
            // 2. เช็กซ้ำว่าเคยสมัครกิจกรรมนี้ไปแล้วหรือยัง
            $stmt_check = $conn->prepare("SELECT id FROM activity_registrations WHERE student_id = ? AND activity_id = ?");
            $stmt_check->bind_param("si", $student_id, $activity_id);
            $stmt_check->execute();
            $res_check = $stmt_check->get_result();

            if ($res_check->num_rows > 0) {
                $msg = "<div style='background-color: #fef9e7; color: #b7950b; padding: 12px; border-radius: 5px; margin-bottom: 20px; font-weight: bold; border: 1px solid #f9e79f; text-align:center;'>คุณได้สมัครเข้าร่วมกิจกรรมนี้ไปแล้ว</div>";
            } else {
                // 3. บันทึกข้อมูลลงตารางเชื่อมโยง
                $stmt_insert = $conn->prepare("INSERT INTO activity_registrations (student_id, activity_id) VALUES (?, ?)");
                $stmt_insert->bind_param("si", $student_id, $activity_id);

                if ($stmt_insert->execute()) {
                    $msg = "<div style='background-color: #eafaf1; color: #27ae60; padding: 12px; border-radius: 5px; margin-bottom: 20px; font-weight: bold; border: 1px solid #c3f0d4; text-align:center;'>สมัครเข้าร่วมกิจกรรมสำเร็จ</div>";
                } else {
                    $msg = "<div style='background-color: #fce4e4; color: #cc0000; padding: 12px; border-radius: 5px; margin-bottom: 20px; font-weight: bold; border: 1px solid #f8baba; text-align:center;'>เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง</div>";
                }
            }
        } catch (Exception $e) {
            $msg = "<div style='background-color: #fce4e4; color: #cc0000; padding: 12px; border-radius: 5px; margin-bottom: 20px; font-weight: bold; border: 1px solid #f8baba; text-align:center;'>ระบบขัดข้อง: " . $e->getMessage() . "</div>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>กิจกรรมชมรม - ชมรมเพื่อนภูผา</title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* จัดระเบียบหัวข้อประจำหมวดหมู่ */
        .section-title {
            font-size: 24px;
            color: #2c3e50;
            margin-top: 40px;
            margin-bottom: 20px;
            padding-bottom: 8px;
            border-bottom: 3px solid #27ae60;
            display: inline-block;
        }

        /* เพิ่มระยะห่างระหว่างข้อความกับขอบการ์ดให้สวยงามและอ่านง่ายขึ้น */
        .card-body {
            padding: 25px !important;
            background: #fff;
        }

        /* เว้นระยะบรรทัดเนื้อหาให้นุ่มนวลขึ้น */
        .card-text {
            line-height: 1.6;
            margin-bottom: 18px;
        }

        /* ยกเลิกเอฟเฟกต์เมาส์ชี้ (Hover) ทั้งหมด ให้การ์ดอยู่นิ่ง ๆ ไม่ลอยขึ้น */
        .card {
            transform: none !important;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05) !important;
            transition: none !important;
            cursor: default !important;
        }

        .card:hover {
            transform: none !important;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05) !important;
        }

        /* ปุ่มสมัครกิจกรรม */
        .btn-join {
            display: block;
            width: 100%;
            background-color: #27ae60;
            color: white;
            border: none;
            padding: 10px;
            border-radius: 5px;
            font-size: 14px;
            font-weight: bold;
            cursor: pointer;
            text-align: center;
            margin-top: 15px;
            transition: background 0.2s;
        }

        .btn-join:hover {
            background-color: #219653;
        }
    </style>
</head>

<body>
    <?php include 'header.php'; ?>

    <div class="container" style="max-width: 1200px; margin-bottom: 60px;">

        <?php echo $msg; ?>

        <div>
            <h2 class="section-title">กิจกรรมใหม่ (เปิดรับนักศึกษาเข้าร่วมกิจกรรม)</h2>
        </div>

        <div class="grid-3">
            <?php
            $sql_new = "SELECT * FROM activities WHERE type='new' ORDER BY act_date DESC";
            $res_new = $conn->query($sql_new);

            if ($res_new->num_rows > 0) {
                while ($row = $res_new->fetch_assoc()) {
            ?>
                    <div class="card">
                        <div class="card-img" style="height: 180px; overflow: hidden;">
                            <img src="uploads/<?php echo $row['cover_img']; ?>" style="width: 100%; height: 100%; object-fit: cover;" onerror="this.src='https://via.placeholder.com/350x200?text=Phupha+Club'">
                        </div>
                        <div class="card-body">
                            <h3 class="card-title"><?php echo htmlspecialchars($row['title']); ?></h3>
                            <p class="card-text"><?php echo htmlspecialchars($row['description']); ?></p>

                            <div style="font-size: 13px; color: #7f8c8d; border-top: 1px solid #eee; padding-top: 12px; margin-top: 10px;">
                                <div style="margin-bottom: 5px;"><strong>สถานที่:</strong> <?php echo htmlspecialchars($row['location']); ?></div>
                                <div><strong>วันที่จัด:</strong> <?php echo $row['act_date']; ?></div>
                            </div>

                            <form action="activities.php" method="POST">
                                <input type="hidden" name="activity_id" value="<?php echo $row['id']; ?>">
                                <button type="submit" name="join_activity" class="btn-join">สมัครเข้าร่วมกิจกรรม</button>
                            </form>
                        </div>
                    </div>
            <?php
                }
            }
            ?>
        </div>


        <div style="margin-top: 30px;">
            <h2 class="section-title">กิจกรรมที่ผ่านมา</h2>
        </div>

        <div class="grid-3">
            <?php
            $sql_past = "SELECT * FROM activities WHERE type='past' ORDER BY act_date DESC";
            $res_past = $conn->query($sql_past);

            if ($res_past->num_rows > 0) {
                while ($row = $res_past->fetch_assoc()) {
            ?>
                    <div class="card">
                        <div class="card-img" style="height: 180px; overflow: hidden;">
                            <img src="uploads/<?php echo $row['cover_img']; ?>" style="width: 100%; height: 100%; object-fit: cover;" onerror="this.src='https://via.placeholder.com/350x200?text=Phupha+Club'">
                        </div>
                        <div class="card-body">
                            <h3 class="card-title" style="color: #7f8c8d;"><?php echo htmlspecialchars($row['title']); ?></h3>
                            <p class="card-text"><?php echo htmlspecialchars($row['description']); ?></p>

                            <div style="font-size: 13px; color: #95a5a6; border-top: 1px solid #eee; padding-top: 12px; margin-top: 10px;">
                                <div style="margin-bottom: 5px;"><strong>สถานที่:</strong> <?php echo htmlspecialchars($row['location']); ?></div>
                                <div><strong>จัดเมื่อ:</strong> <?php echo $row['act_date']; ?></div>
                            </div>
                        </div>
                    </div>
            <?php
                }
            }
            ?>
        </div>

    </div>
</body>

</html>