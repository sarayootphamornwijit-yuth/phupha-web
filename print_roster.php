<?php
session_start();
include 'db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    echo "คุณไม่มีสิทธิ์เข้าถึงหน้านี้";
    exit();
}

if (!isset($_GET['act_id'])) {
    echo "ไม่พบรหัสกิจกรรมที่ต้องการพิมพ์";
    exit();
}

$act_id = intval($_GET['act_id']);

// ดึงข้อมูลกิจกรรม
$act_query = $conn->query("SELECT * FROM activities WHERE id = $act_id");
$act_data = $act_query->fetch_assoc();

// ดึงรายชื่อนักศึกษาที่สมัคร
$users_reg = $conn->query("SELECT u.student_id,u.title, u.fullname, u.faculty FROM activity_registrations r JOIN users u ON r.student_id = u.student_id WHERE r.activity_id = $act_id");
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>พิมพ์รายชื่อ - <?php echo htmlspecialchars($act_data['title']); ?></title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding: 20px;
            background: white;
            color: black;
        }

        h2,
        h3 {
            text-align: center;
            margin-bottom: 10px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        th,
        td {
            border: 1px solid #000;
            padding: 10px;
            text-align: left;
        }

        th {
            background-color: #f2f2f2;
        }

        .print-btn {
            display: block;
            width: 200px;
            margin: 0 auto 20px auto;
            padding: 10px;
            background: #2c3e50;
            color: white;
            text-align: center;
            cursor: pointer;
            border: none;
            font-size: 16px;
            border-radius: 5px;
        }

        /* ซ่อนปุ่มกดตอนพิมพ์กระดาษจริง */
        @media print {
            .no-print {
                display: none;
            }
        }
    </style>
</head>

<body onload="window.print()">

    <div class="no-print">
        <button class="print-btn" onclick="window.print()">พิมพ์รายชื่อ</button>
    </div>

    <h2>ใบรายชื่อผู้เข้าร่วมกิจกรรม: <?php echo htmlspecialchars($act_data['title']); ?></h2>
    <h3>วันที่จัด <?php echo $act_data['act_date']; ?> สถานที่ <?php echo htmlspecialchars($act_data['location']); ?></h3>

    <table>
        <thead>
            <tr>
                <th style="width: 50px; text-align: center;">ลำดับ</th>
                <th style="width: 150px;">รหัสนักศึกษา</th>
                <th>ชื่อ-นามสกุล</th>
                <th>คณะ / สาขาวิชา</th>
                <th style="width: 150px; text-align: center;">ลายมือชื่อผู้เข้าร่วม</th>
            </tr>
        </thead>
        <tbody>
            <?php
            if ($users_reg->num_rows > 0) {
                $i = 1;
                while ($user = $users_reg->fetch_assoc()) {
                    echo "<tr>";
                    echo "<td style='text-align: center;'>" . $i . "</td>";
                    echo "<td>" . htmlspecialchars($user['student_id']) . "</td>";
                    echo "<td>" . htmlspecialchars($user['title'] . " " . $user['fullname']) . "</td>";
                    echo "<td>" . htmlspecialchars($user['faculty']) . "</td>";
                    echo "<td></td>"; // ช่องว่างไว้ให้เซ็นชื่อด้วยปากกา
                    echo "</tr>";
                    $i++;
                }
            } else {
                echo "<tr><td colspan='5' style='text-align: center;'>ยังไม่มีผู้สมัครในระบบ</td></tr>";
            }
            ?>
        </tbody>
    </table>
</body>

</html>