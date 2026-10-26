<?php
// erp_reports.php
// หน้ารายงานสรุปสถิติภาพรวมของกิจกรรม (ERP Reports)
// สำหรับ: คณะกรรมการ (Committee), อาจารย์ที่ปรึกษา (Advisor) และผู้ดูแลระบบ (Admin)
// สรุป 4 ด้าน: 1. สถิติผู้เข้าร่วม 2. รูปแบบการเดินทาง 3. ความพึงพอใจ 4. สถานะงบประมาณ

require_once 'config/database.php';

// ตรวจสอบสิทธิ์การเข้าใช้งาน: ผู้ดูแลระบบ อาจารย์ที่ปรึกษา และคณะกรรมการ
requireReportPermission();

// ดึงรายชื่อกิจกรรมทั้งหมดมาแสดงใน Dropdown สำหรับเลือกดูรายงาน
$activitiesQuery = $conn->query("SELECT activity_id, title, start_date, location_name FROM activities ORDER BY start_date DESC");

// รับค่ารหัสกิจกรรมที่ต้องการดูรายงาน
$selectedActivityId = (int)($_GET['activity_id'] ?? 0);

// ตัวแปรสำหรับเก็บข้อมูลที่ประมวลผลแล้ว
$activityData = null;
$participantStats = null;
$travelStats = null;
$vehicleList = [];
$evaluationStats = null;
$budgetStats = null;

if ($selectedActivityId > 0) {
    // 1. ดึงข้อมูลพื้นฐานของกิจกรรม
    $actStmt = $conn->prepare("SELECT * FROM activities WHERE activity_id = ?");
    $actStmt->bind_param("i", $selectedActivityId);
    $actStmt->execute();
    $activityData = $actStmt->get_result()->fetch_assoc();
    $actStmt->close();

    if ($activityData) {
        // 2. สรุปสถิติผู้เข้าร่วมกิจกรรม
        // 2.1 จำนวนลงทะเบียนทั้งหมด
        $regStmt = $conn->prepare("SELECT COUNT(*) AS total_reg FROM user_activities WHERE activity_id = ?");
        $regStmt->bind_param("i", $selectedActivityId);
        $regStmt->execute();
        $totalRegistered = (int)$regStmt->get_result()->fetch_assoc()['total_reg'];
        $regStmt->close();

        /* 2.2 จำนวนคนที่เช็คอินกิจกรรมแล้ว */
        $cinStmt = $conn->prepare("SELECT COUNT(*) AS checked_in FROM user_activities WHERE activity_id = ? AND status IN ('checked_in', 'checked_out')");
        $cinStmt->bind_param("i", $selectedActivityId);
        $cinStmt->execute();
        $totalCheckedIn = (int)$cinStmt->get_result()->fetch_assoc()['checked_in'];
        $cinStmt->close();

        /* 2.3 จำนวนคนที่ยังไม่ได้เช็คอินกิจกรรมนี้ */
        $absentCount = $totalRegistered - $totalCheckedIn;
        if ($absentCount < 0) {
            $absentCount = 0;
        }

        $participantStats = [
            'total_registered' => $totalRegistered,
            'checked_in'       => $totalCheckedIn,
            'absent'           => $absentCount
        ];

        // 3. สรุปรูปแบบการเดินทาง
        // 3.1 จำนวนคนเดินทางเอง (own_travel หรือ 'เดินทางเอง')
        $ownStmt = $conn->prepare("SELECT COUNT(*) AS own_count FROM user_activities WHERE activity_id = ? AND (travel_method = 'own_travel' OR travel_method = 'เดินทางเอง')");
        $ownStmt->bind_param("i", $selectedActivityId);
        $ownStmt->execute();
        $ownTravelCount = (int)$ownStmt->get_result()->fetch_assoc()['own_count'];
        $ownStmt->close();

        // 3.2 จำนวนคนเดินทางโดยรถชมรม
        $clubStmt = $conn->prepare("SELECT COUNT(*) AS club_count FROM user_activities WHERE activity_id = ? AND (vehicle_id IS NOT NULL OR travel_method = 'club_vehicle' OR travel_method = 'ไปด้วยรถที่จัดให้' OR travel_method = 'ไปกับรถที่จัดหาให้')");
        $clubStmt->bind_param("i", $selectedActivityId);
        $clubStmt->execute();
        $clubVehicleCount = (int)$clubStmt->get_result()->fetch_assoc()['club_count'];
        $clubStmt->close();

        $travelStats = [
            'own_travel'   => $ownTravelCount,
            'club_vehicle' => $clubVehicleCount
        ];

        // 3.3 รายการยานพาหนะแยกรายคัน
        $vStmt = $conn->prepare("
            SELECT v.vehicle_id, v.vehicle_type, v.license_plate, v.driver_name, v.driver_phone, v.capacity,
                   (SELECT COUNT(*) FROM user_activities ua WHERE ua.vehicle_id = v.vehicle_id) AS passenger_count
            FROM vehicles v
            WHERE v.activity_id = ?
            ORDER BY v.vehicle_id ASC
        ");
        $vStmt->bind_param("i", $selectedActivityId);
        $vStmt->execute();
        $vResult = $vStmt->get_result();
        while ($vRow = $vResult->fetch_assoc()) {
            $vehicleList[] = $vRow;
        }
        $vStmt->close();

        // 4. สรุปผลการประเมินความพึงพอใจ (q1 - q6)
        $evalStmt = $conn->prepare("
            SELECT 
                COUNT(*) AS total_responses,
                AVG(q1) AS avg_q1,
                AVG(q2) AS avg_q2,
                AVG(q3) AS avg_q3,
                AVG(q4) AS avg_q4,
                AVG(q5) AS avg_q5,
                AVG(q6) AS avg_q6
            FROM evaluations
            WHERE activity_id = ?
        ");
        $evalStmt->bind_param("i", $selectedActivityId);
        $evalStmt->execute();
        $evalRow = $evalStmt->get_result()->fetch_assoc();
        $evalStmt->close();

        $totalResponses = (int)($evalRow['total_responses'] ?? 0);
        if ($totalResponses > 0) {
            $avgTotal = ($evalRow['avg_q1'] + $evalRow['avg_q2'] + $evalRow['avg_q3'] + $evalRow['avg_q4'] + $evalRow['avg_q5'] + $evalRow['avg_q6']) / 6;
            $evaluationStats = [
                'total_responses' => $totalResponses,
                'avg_q1'          => round((float)$evalRow['avg_q1'], 2),
                'avg_q2'          => round((float)$evalRow['avg_q2'], 2),
                'avg_q3'          => round((float)$evalRow['avg_q3'], 2),
                'avg_q4'          => round((float)$evalRow['avg_q4'], 2),
                'avg_q5'          => round((float)$evalRow['avg_q5'], 2),
                'avg_q6'          => round((float)$evalRow['avg_q6'], 2),
                'avg_total'       => round($avgTotal, 2)
            ];
        }

        // 5. สรุปสถานะงบประมาณ (รายรับ, รายจ่าย, เงินคงเหลือสุทธิ)
        $budgetStmt = $conn->prepare("
            SELECT 
                SUM(CASE WHEN transaction_type = 'income' THEN amount ELSE 0 END) AS total_income,
                SUM(CASE WHEN transaction_type = 'expense' THEN amount ELSE 0 END) AS total_expense
            FROM budget_transactions
            WHERE activity_id = ?
        ");
        $budgetStmt->bind_param("i", $selectedActivityId);
        $budgetStmt->execute();
        $budgetRow = $budgetStmt->get_result()->fetch_assoc();
        $budgetStmt->close();

        $totalIncome  = (float)($budgetRow['total_income'] ?? 0);
        $totalExpense = (float)($budgetRow['total_expense'] ?? 0);
        $netBalance   = $totalIncome - $totalExpense;

        $budgetStats = [
            'income'  => $totalIncome,
            'expense' => $totalExpense,
            'balance' => $netBalance
        ];
    }
}

include 'includes/header.php';
?>

<div class="container py-4" style="max-width: 1000px;">

    <!-- ส่วนหัวหน้าจอ -->
    <h2 class="section-title mb-2">รายงานสรุปสถิติภาพรวมกิจกรรม</h2>
    <p class="text-muted text-sm mb-4">รายงานสรุปผลข้อมูลสถิติผู้เข้าร่วม การเดินทาง การประเมินผล และงบประมาณ</p>

    <!-- ตัวกรองเลือกโครงการกิจกรรม -->
    <div class="card p-3 mb-4">
        <form method="GET" action="erp_reports.php" class="d-flex align-end gap-3 flex-wrap">
            <div class="flex-grow-1">
                <label for="activity_id" class="form-label" style="font-weight: 600;">เลือกโครงการกิจกรรมที่ต้องการดูรายงาน:</label>
                <select name="activity_id" id="activity_id" class="form-control" required>
                    <option value="">-- กรุณาเลือกโครงการกิจกรรม --</option>
                    <?php if ($activitiesQuery): ?>
                        <?php while ($act = $activitiesQuery->fetch_assoc()): ?>
                            <option value="<?= $act['activity_id'] ?>" <?= ($selectedActivityId == $act['activity_id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($act['title']) ?> (<?= date('d/m/Y', strtotime($act['start_date'])) ?>)
                            </option>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary" style="padding: 9px 20px;">
                ดูรายงาน
            </button>
        </form>
    </div>

    <?php if ($activityData): ?>

        <!-- กล่องข้อมูลกิจกรรมและปุ่มพิมพ์รายชื่อ (จัดเนื้อหาอยู่ซ้าย ปุ่มพิมพ์อยู่ขวา) -->
        <div class="card p-3 mb-4" style="background: #f8fafc;">
            <div class="d-flex justify-between align-center flex-wrap gap-3">
                <div>
                    <h3 class="text-md m-0 mb-1" style="font-weight: 600; color: #000;">
                        <?= htmlspecialchars($activityData['title']) ?>
                    </h3>
                    <div class="text-sm" style="color: #000;">
                        วันที่จัด: <?= date('d/m/Y', strtotime($activityData['start_date'])) ?> | สถานที่: <?= htmlspecialchars($activityData['location_name'] ?: 'ไม่ระบุ') ?>
                    </div>
                </div>
                <div>
                    <a href="print_participants.php?id=<?= $selectedActivityId ?>" target="_blank" class="btn btn-surface text-sm" style="border: 1px solid #94a3b8; color: #000; white-space: nowrap;">
                        พิมพ์รายชื่อผู้เข้าร่วม (A4)
                    </a>
                </div>
            </div>
        </div>

        <!-- 1. สรุปสถิติผู้เข้าร่วมกิจกรรม -->
        <div class="card p-4 mb-4">
            <h3 class="text-md m-0 mb-3 pb-2 border-bottom" style="font-weight: 600; color: #000;">
                1. สรุปสถิติผู้เข้าร่วมกิจกรรม
            </h3>

            <div class="grid-3 gap-3 text-center">
                <div class="p-3 bg-main rounded border">
                    <div class="text-xs" style="color: #000;">จำนวนคนลงทะเบียนทั้งหมด</div>
                    <div class="text-lg font-weight-bold mt-1" style="color: #000;">
                        <?= number_format($participantStats['total_registered']) ?> คน
                    </div>
                </div>
                <div class="p-3 bg-main rounded border">
                    <div class="text-xs" style="color: #000;">เช็คอินกิจกรรมแล้ว</div>
                    <div class="text-lg font-weight-bold mt-1" style="color: #000;">
                        <?= number_format($participantStats['checked_in']) ?> คน
                    </div>
                </div>
                <div class="p-3 bg-main rounded border">
                    <div class="text-xs" style="color: #000;">ยังไม่ได้เช็คอินกิจกรรมนี้</div>
                    <div class="text-lg font-weight-bold mt-1" style="color: #000;">
                        <?= number_format($participantStats['absent']) ?> คน
                    </div>
                </div>
            </div>
        </div>

        <!-- 2. สรุปรูปแบบการเดินทาง -->
        <div class="card p-4 mb-4">
            <h3 class="text-md m-0 mb-3 pb-2 border-bottom" style="font-weight: 600; color: #000;">
                2. สรุปรูปแบบการเดินทาง
            </h3>

            <div class="grid-2 gap-3 mb-4 text-center">
                <div class="p-3 bg-main rounded border">
                    <div class="text-xs" style="color: #000;">เดินทางด้วยตนเอง</div>
                    <div class="text-lg font-weight-bold mt-1" style="color: #000;">
                        <?= number_format($travelStats['own_travel']) ?> คน
                    </div>
                </div>
                <div class="p-3 bg-main rounded border">
                    <div class="text-xs" style="color: #000;">เดินทางโดยรถของชมรม</div>
                    <div class="text-lg font-weight-bold mt-1" style="color: #000;">
                        <?= number_format($travelStats['club_vehicle']) ?> คน
                    </div>
                </div>
            </div>

            <!-- ตารางสรุปรถแยกรายคัน -->
            <h4 class="text-sm m-0 mb-2" style="font-weight: 600; color: #000;">รายละเอียดยานพาหนะของชมรม (แยกรายคัน)</h4>
            <?php if (!empty($vehicleList)): ?>
                <div class="table-responsive">
                    <table class="table w-100 text-sm">
                        <thead>
                            <tr style="background: #f8fafc; color: #000;">
                                <th class="p-2 text-left">ประเภทรถ</th>
                                <th class="p-2 text-center" style="width: 120px;">ทะเบียน</th>
                                <th class="p-2 text-left">ชื่อคนขับ</th>
                                <th class="p-2 text-center" style="width: 130px;">เบอร์โทรศัพท์</th>
                                <th class="p-2 text-center" style="width: 100px;">ความจุ</th>
                                <th class="p-2 text-center" style="width: 110px;">ผู้โดยสาร</th>
                                <th class="p-2 text-center" style="width: 110px;">ที่นั่งว่าง</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($vehicleList as $vh): ?>
                                <?php
                                $vCap = (int)$vh['capacity'];
                                $vPas = (int)$vh['passenger_count'];
                                $vAvail = $vCap - $vPas;
                                ?>
                                <tr class="border-bottom">
                                    <td class="p-2 font-weight-medium" style="color: #000;"><?= htmlspecialchars($vh['vehicle_type']) ?></td>
                                    <td class="p-2 text-center" style="color: #000;"><?= htmlspecialchars($vh['license_plate']) ?></td>
                                    <td class="p-2" style="color: #000;"><?= htmlspecialchars($vh['driver_name']) ?></td>
                                    <td class="p-2 text-center text-muted"><?= htmlspecialchars($vh['driver_phone'] ?: '-') ?></td>
                                    <td class="p-2 text-center" style="color: #000;"><?= $vCap ?> ที่นั่ง</td>
                                    <td class="p-2 text-center font-weight-bold" style="color: #000;"><?= $vPas ?> คน</td>
                                    <td class="p-2 text-center font-weight-bold" style="color: #000;">
                                        <?= $vAvail <= 0 ? '(เต็มแล้ว)' : $vAvail . ' ที่นั่ง' ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-sm text-muted p-2 text-center border rounded">
                    ไม่มีข้อมูลยานพาหนะสำหรับกิจกรรมนี้
                </div>
            <?php endif; ?>
        </div>

        <!-- 3. สรุปผลการประเมินความพึงพอใจ -->
        <div class="card p-4 mb-4">
            <h3 class="text-md m-0 mb-3 pb-2 border-bottom" style="font-weight: 600; color: #000;">
                3. สรุปผลการประเมินความพึงพอใจ (คะแนนเต็ม 5.00 คะแนน)
            </h3>

            <?php if ($evaluationStats): ?>
                <div class="mb-3 text-sm" style="color: #000;">
                    จำนวนผู้ตอบแบบประเมินทั้งหมด <strong><?= number_format($evaluationStats['total_responses']) ?></strong> คน
                    &nbsp;&nbsp;&nbsp;&nbsp;
                    คะแนนเฉลี่ยรวมทุกด้าน <strong style="color: #000; font-size: 16px;"><?= number_format($evaluationStats['avg_total'], 2) ?></strong> จาก 5.00 คะแนน
                </div>

                <div class="table-responsive">
                    <table class="table w-100 text-sm">
                        <thead>
                            <tr style="background: #f8fafc; color: #000;">
                                <th class="p-2 text-left">ด้านที่ประเมิน</th>
                                <th class="p-2 text-right" style="width: 140px;">คะแนนเฉลี่ย</th>
                                <th class="p-2 text-center" style="width: 140px;">ระดับความพึงพอใจ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // หัวข้อแบบประเมิน 6 ด้าน (แสดงผลหัวข้อสะอาดตา ไม่ใช้เครื่องหมาย :, /, |)
                            $evalQuestions = [
                                ['label' => "ข้อที่ 1 การประชาสัมพันธ์กิจกรรม", 'score' => $evaluationStats['avg_q1']],
                                ['label' => "ข้อที่ 2 สถานที่และการจัดการ", 'score' => $evaluationStats['avg_q2']],
                                ['label' => "ข้อที่ 3 ความเหมาะสมของเวลา", 'score' => $evaluationStats['avg_q3']],
                                ['label' => "ข้อที่ 4 การดำเนินกิจกรรมและเนื้อหา", 'score' => $evaluationStats['avg_q4']],
                                ['label' => "ข้อที่ 5 สวัสดิการอาหารและเครื่องดื่ม", 'score' => $evaluationStats['avg_q5']],
                                ['label' => "ข้อที่ 6 ความพึงพอใจในภาพรวม", 'score' => $evaluationStats['avg_q6']],
                            ];
                            foreach ($evalQuestions as $eq):
                                $score = $eq['score'];
                                
                                // การแปลผลค่าเฉลี่ยตามหลักสถิติ (Likert Scale 5 ระดับ: ช่วงชั้นละ 0.80 คะแนน)
                                // สูตร: (คะแนนสูงสุด 5 - คะแนนต่ำสุด 1) / 5 = 0.80
                                // 4.21 - 5.00 = ดีมาก
                                // 3.41 - 4.20 = ดี
                                // 2.61 - 3.40 = ปานกลาง
                                // 1.81 - 2.60 = น้อย
                                // 1.00 - 1.80 = น้อยที่สุด
                                $ratingText = 'น้อยที่สุด';
                                if ($score >= 4.21) {
                                    $ratingText = 'ดีมาก';
                                } elseif ($score >= 3.41) {
                                    $ratingText = 'ดี';
                                } elseif ($score >= 2.61) {
                                    $ratingText = 'ปานกลาง';
                                } elseif ($score >= 1.81) {
                                    $ratingText = 'น้อย';
                                } else {
                                    $ratingText = 'น้อยที่สุด';
                                }
                            ?>
                                <tr class="border-bottom">
                                    <td class="p-2" style="color: #000;"><?= htmlspecialchars($eq['label']) ?></td>
                                    <td class="p-2 text-right font-weight-bold" style="color: #000;">
                                        <?= number_format($score, 2) ?>
                                    </td>
                                    <td class="p-2 text-center">
                                        <span class="badge" style="background: #f1f5f9; color: #000;">
                                            <?= $ratingText ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-sm text-muted p-2 text-center border rounded">
                    ยังไม่มีผู้ทำแบบประเมินความพึงพอใจสำหรับกิจกรรมนี้
                </div>
            <?php endif; ?>
        </div>

        <!-- 4. สรุปสถานะงบประมาณ -->
        <div class="card p-4 mb-4">
            <h3 class="text-md m-0 mb-3 pb-2 border-bottom" style="font-weight: 600; color: #000;">
                4. สรุปสถานะงบประมาณ
            </h3>

            <div class="grid-3 gap-3 text-center">
                <div class="p-3 bg-main rounded border">
                    <div class="text-xs" style="color: #000;">ยอดรวมรายรับ</div>
                    <div class="text-lg font-weight-bold mt-1" style="color: #000;">
                        <?= number_format($budgetStats['income'], 2) ?> บาท
                    </div>
                </div>
                <div class="p-3 bg-main rounded border">
                    <div class="text-xs" style="color: #000;">ยอดรวมรายจ่าย</div>
                    <div class="text-lg font-weight-bold mt-1" style="color: #000;">
                        <?= number_format($budgetStats['expense'], 2) ?> บาท
                    </div>
                </div>
                <div class="p-3 bg-main rounded border">
                    <div class="text-xs" style="color: #000;">ยอดเงินคงเหลือสุทธิ</div>
                    <div class="text-lg font-weight-bold mt-1" style="color: #000;">
                        <?= number_format($budgetStats['balance'], 2) ?> บาท
                    </div>
                </div>
            </div>
        </div>

    <?php elseif ($selectedActivityId > 0): ?>
        <div class="card p-4 text-center text-muted">
            ไม่พบข้อมูลโครงการกิจกรรมที่เลือก
        </div>
    <?php else: ?>
        <div class="card p-4 text-center text-muted">
            กรุณาเลือกโครงการกิจกรรมด้านบน เพื่อดูรายงานสรุปสถิติภาพรวม
        </div>
    <?php endif; ?>

</div>

<?php include 'includes/footer.php'; ?>