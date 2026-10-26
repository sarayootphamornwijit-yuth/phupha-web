<?php
// index.php
// ไฟล์นี้คือหน้าแรกของเว็บไซต์ แสดงกิจกรรมใหม่และเก่า และข่าวประชาสัมพันธ์

// หมายเหตุ: ไม่ต้องเรียก session_start() ไว้ที่นี่อีกครั้ง เพราะ config/database.php
// จัดการ session ไว้เรียบร้อยแล้วด้วย session_status() ก่อนเริ่ม
// หากเรียกซ้ำ พีเอชจะแสดง Warning "session already started"
require_once 'config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();
}


if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['action'])
    && $_POST['action'] === 'register'
) {
    if (!canRegisterActivity()) {
        $_SESSION['alert'] = [
            'type'  => 'warning',
            'title' => 'กรุณาเข้าสู่ระบบ',
            'text'  => 'เข้าสู่ระบบก่อนลงทะเบียนกิจกรรม',
        ];
        header("Location: login.php");
        exit;
    }

    try {
        $conn->query("
            CREATE TABLE IF NOT EXISTS user_activities (
                user_activity_id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                activity_id INT NOT NULL,
                status ENUM('registered', 'checked_in', 'checked_out', 'cancelled') DEFAULT 'registered',
                check_in_time DATETIME DEFAULT NULL,
                check_out_time DATETIME DEFAULT NULL,
                travel_method VARCHAR(50) DEFAULT NULL,
                vehicle_id INT DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                KEY idx_user (user_id),
                KEY idx_act (activity_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    } catch (\Throwable $e) {}

    try {
        $registerActivityId = (int)$_POST['activity_id'];
        $registerUserId     = $_SESSION['user_id'];

        // เริ่มต้น Transaction เพื่อป้องกันปัญหา Race Condition แย่งที่นั่งกิจกรรม
        $conn->begin_transaction();

        // ตรวจสอบว่าผู้ใช้ลงทะเบียนกิจกรรมนี้ซ้ำหรือไม่
        $duplicateCheckStatement = $conn->prepare(
            "SELECT 1 FROM user_activities WHERE user_id = ? AND activity_id = ? FOR UPDATE"
        );
        $duplicateCheckStatement->bind_param("ii", $registerUserId, $registerActivityId);
        $duplicateCheckStatement->execute();

        $duplicateCheckResult  = $duplicateCheckStatement->get_result();
        $isAlreadyRegistered   = ($duplicateCheckResult && $duplicateCheckResult->num_rows > 0);
        $duplicateCheckStatement->close();

        if ($isAlreadyRegistered) {
            $conn->commit();
            $_SESSION['alert'] = [
                'type'  => 'info',
                'title' => 'ลงทะเบียนแล้ว',
                'text'  => 'คุณได้ลงทะเบียนกิจกรรมนี้ไปแล้วครับ',
            ];

        } else {
            // ล็อคแถวกิจกรรมเพื่อตรวจจำนวนที่นั่งสูงสุดและเวลาเริ่มต้นกิจกรรม
            $actInfoStatement = $conn->prepare(
                "SELECT max_participants, start_date FROM activities WHERE activity_id = ? FOR UPDATE"
            );
            $actInfoStatement->bind_param("i", $registerActivityId);
            $actInfoStatement->execute();
            $actInfo = $actInfoStatement->get_result()->fetch_assoc();
            $actInfoStatement->close();

            $isStarted = (!empty($actInfo['start_date']) && strtotime($actInfo['start_date']) <= time());

            if ($isStarted) {
                $conn->commit();
                $_SESSION['alert'] = [
                    'type'  => 'warning',
                    'title' => 'ปิดรับสมัครแล้ว',
                    'text'  => 'กิจกรรมนี้ได้เริ่มต้นขึ้นแล้ว จึงไม่สามารถลงทะเบียนเข้าร่วมได้ครับ',
                ];
            } else {
                // นับจำนวนผู้ลงทะเบียนปัจจุบัน
                $countStmt = $conn->prepare("SELECT COUNT(*) as current_reg FROM user_activities WHERE activity_id = ?");
                $countStmt->bind_param("i", $registerActivityId);
                $countStmt->execute();
                $countRow = $countStmt->get_result()->fetch_assoc();
                $currentReg = (int)($countRow['current_reg'] ?? 0);
                $countStmt->close();

                $maxParticipants = (int)($actInfo['max_participants'] ?? 0);
                $isActivityFull = ($maxParticipants > 0 && $currentReg >= $maxParticipants);

                if ($isActivityFull) {
                    $conn->commit();
                    $_SESSION['alert'] = [
                        'type'  => 'error',
                        'title' => 'เต็มแล้ว',
                        'text'  => 'กิจกรรมนี้มีผู้ลงทะเบียนเต็มจำนวนแล้วครับ',
                    ];
                } else {
                    $insertRegistrationStatement = $conn->prepare(
                        "INSERT INTO user_activities (user_id, activity_id, status) VALUES (?, ?, 'registered')"
                    );
                    $insertRegistrationStatement->bind_param("ii", $registerUserId, $registerActivityId);

                    if ($insertRegistrationStatement->execute()) {
                        $conn->commit();
                        $_SESSION['alert'] = [
                            'type'  => 'success',
                            'title' => 'ลงทะเบียนสำเร็จ!',
                            'text'  => 'สามารถเลือกรูปแบบการเดินทางได้ที่หน้า "กิจกรรมที่เข้าร่วม"',
                        ];
                    } else {
                        $conn->rollback();
                        $_SESSION['alert'] = [
                            'type'  => 'error',
                            'title' => 'เกิดข้อผิดพลาด',
                            'text'  => 'ไม่สามารถทำรายการได้ กรุณาลองใหม่อีกครั้ง',
                        ];
                    }

                    $insertRegistrationStatement->close();
                }
            }
        }
    } catch (\Throwable $e) {
        $conn->rollback();
        $_SESSION['alert'] = [
            'type'  => 'error',
            'title' => 'เกิดข้อผิดพลาดในการลงทะเบียน',
            'text'  => 'ไม่สามารถลงทะเบียนได้ในขณะนี้ กรุณาลองใหม่อีกครั้ง',
        ];
    }

    $redirectUrl = $_SERVER['HTTP_REFERER'] ?? 'index.php';
    header("Location: " . $redirectUrl);
    exit;
}


$upcomingActivitiesResult = $conn->query(
    "SELECT a.*, (SELECT COUNT(*) FROM user_activities WHERE activity_id = a.activity_id) as current_participants
     FROM activities a
     WHERE IFNULL(a.end_date, a.start_date) >= NOW() AND a.approval_status = 'approved'
     ORDER BY a.start_date ASC"
);

$pastActivitiesResult = $conn->query(
    "SELECT * FROM activities
     WHERE IFNULL(end_date, start_date) < NOW() AND approval_status = 'approved'
     ORDER BY IFNULL(end_date, start_date) DESC
     LIMIT 9"
);

$newsResult = $conn->query(
    "SELECT n.*, COALESCE(u.name, 'ชมรมเพื่อนภูผา') as author_name
     FROM news n
     LEFT JOIN users u ON n.author_id = u.user_id
     WHERE n.is_published = 1
     ORDER BY n.created_at DESC
     LIMIT 6"
);


include 'includes/header.php';
?>


<?php if (isset($_SESSION['alert'])): ?>
    <div id="flash-message"
         data-type="<?= $_SESSION['alert']['type'] ?>"
         data-title="<?= $_SESSION['alert']['title'] ?>"
         data-text="<?= $_SESSION['alert']['text'] ?>"
         class="d-none">
    </div>
    <?php unset($_SESSION['alert']); ?>
<?php endif; ?>


<div class="banner-welcome">
    <h1 class="m-0">ยินดีต้อนรับสู่ ชมรมเพื่อนภูผา</h1>
    <p class="mt-2 mb-3">
        ระบบสารสนเทศเพื่อการบริหารจัดการชมรมเพื่อนภูผา มหาวิทยาลัยราชภัฏเชียงใหม่
        สนับสนุนการบริหารงาน การจัดกิจกรรม การพัฒนาสมาชิก และการสืบสานอุดมการณ์จิตอาสาอย่างมีประสิทธิภาพ
    </p>
</div>


<?php $hasUpcomingActivities = ($upcomingActivitiesResult && $upcomingActivitiesResult->num_rows > 0); ?>

<?php if ($hasUpcomingActivities): ?>
    <div class="d-flex justify-between align-center mb-3 mt-4">
        <h2 class="section-title m-0">กิจกรรมใหม่</h2>
    </div>
    <div class="grid-3 mb-4">
        <?php while ($upcomingActivity = $upcomingActivitiesResult->fetch_assoc()): ?>

        <div class="card p-0 overflow-hidden d-flex flex-column h-100">

            <a href="activity_details.php?id=<?= $upcomingActivity['activity_id'] ?>"
               class="position-relative"
               style="height: 160px; display: block; background: var(--bg-main);">

                <?php if (!empty($upcomingActivity['cover_image'])): ?>
                    <img src="<?= htmlspecialchars($upcomingActivity['cover_image']) ?>"
                         alt="Cover"
                         style="width: 100%; height: 100%; object-fit: cover;"
                         onerror="this.onerror=null; this.parentElement.innerHTML='<div class=\'d-flex justify-center align-center h-100 text-muted\' style=\'background: #f1f5f9;\'><i class=\'fa-solid fa-image fa-2x\' style=\'opacity: 0.4;\'></i></div>';">
                <?php else: ?>
                    <div class="d-flex justify-center align-center h-100 text-muted" style="background: #f1f5f9;">
                        <i class="fa-solid fa-image fa-2x" style="opacity: 0.4;"></i>
                    </div>
                <?php endif; ?>

            </a>

            <div class="card-body p-3 d-flex flex-column flex-grow-1">

                <div class="mb-2">
                    <?php
                    $upcomingActivityType = $upcomingActivity['activity_type'] ?? 'single_day';
                    if ($upcomingActivityType === 'multi_day') {
                        echo '<span class="badge text-xs" style="background: #E0E7FF; color: #4338CA; border: 1px solid #C7D2FE;">ค่ายหลายวัน</span>';
                    } else {
                        echo '<span class="badge text-xs" style="background: #F3F4F6; color: #374151; border: 1px solid #E5E7EB;">กิจกรรม 1 วัน</span>';
                    }
                    ?>
                </div>

                <a href="activity_details.php?id=<?= $upcomingActivity['activity_id'] ?>" class="text-decoration-none text-main">
                    <h3 class="mt-0 mb-2 text-md"><?= htmlspecialchars($upcomingActivity['title']) ?></h3>
                </a>

                <p class="text-muted text-sm flex-grow-1 mb-3 line-clamp-2">
                    <?= htmlspecialchars($upcomingActivity['description']) ?>
                </p>

                <div class="text-sm text-muted mb-1">วันที่: <?= date('d/m/Y', strtotime($upcomingActivity['start_date'])) ?></div>
                <div class="text-sm text-muted mb-3">สถานที่: <?= htmlspecialchars($upcomingActivity['location_name']) ?></div>

                <div class="mt-auto pt-3 border-top">
                    <div class="d-flex gap-2 flex-column">

                        <a href="activity_details.php?id=<?= $upcomingActivity['activity_id'] ?>"
                           class="btn btn-block btn-surface border-primary text-primary">
                            รายละเอียดกิจกรรม
                        </a>

                        <?php 
                        if (canRegisterActivity()) {
                            $cardUserId     = $_SESSION['user_id'];
                            $cardActivityId = $upcomingActivity['activity_id'];

                            $cardHasJoined = false;
                            try {
                                $cardCheckStatement = $conn->prepare(
                                    "SELECT 1 FROM user_activities WHERE user_id = ? AND activity_id = ?"
                                );
                                if ($cardCheckStatement) {
                                    $cardCheckStatement->bind_param("ii", $cardUserId, $cardActivityId);
                                    $cardCheckStatement->execute();
                                    $chkRes = $cardCheckStatement->get_result();
                                    if ($chkRes && $chkRes->num_rows > 0) {
                                        $cardHasJoined = true;
                                    }
                                    $cardCheckStatement->close();
                                }
                            } catch (Throwable $e) {
                                // Silently handle error (e.g. table not found) so the page doesn't crash
                            }

                            if ($cardHasJoined) {
                                echo '<button class="btn btn-block bg-main text-success cursor-default" disabled>ลงทะเบียนแล้ว</button>';
                            } elseif (strtotime($upcomingActivity['start_date']) <= time()) {
                                echo '<button class="btn btn-block cursor-default" style="background: #6c757d; border-color: #6c757d; color: #fff;" disabled>เริ่มกิจกรรมแล้ว (ปิดรับสมัคร)</button>';
                            } elseif ($upcomingActivity['max_participants'] > 0 && $upcomingActivity['current_participants'] >= $upcomingActivity['max_participants']) {
                                echo '<button class="btn btn-danger btn-block cursor-default" disabled>เต็มแล้ว</button>';
                            } else {
                        ?>
                                <form method="POST" action="index.php" class="m-0 w-100">
                                    <?php csrfField(); ?>
                                    <input type="hidden" name="action" value="register">
                                    <input type="hidden" name="activity_id" value="<?= $upcomingActivity['activity_id'] ?>">
                                    <button type="submit" class="btn btn-primary btn-block">ลงทะเบียนเข้าร่วม</button>
                                </form>
                        <?php
                            }
                        } elseif (!isLoggedIn()) {
                            if (strtotime($upcomingActivity['start_date']) <= time()) {
                                echo '<button class="btn btn-block cursor-default" style="background: #6c757d; border-color: #6c757d; color: #fff;" disabled>เริ่มกิจกรรมแล้ว (ปิดรับสมัคร)</button>';
                            } else {
                        ?>
                            <a href="login.php"
                               class="btn btn-block bg-main text-primary border-primary"
                               style="border: 1px solid var(--color-primary);">
                                เข้าสู่ระบบเพื่อลงทะเบียน
                            </a>
                        <?php 
                            }
                        } 
                        ?>

                    </div>
                </div>
            </div>
        </div>

        <?php endwhile; ?>
    </div>
<?php else: ?>
    <h2 class="section-title mb-3 mt-4">กิจกรรมใหม่</h2>
    <div class="grid-3 mb-4">
        <div class="card p-4 text-center text-muted grid-span-full border-dashed"
             style="border: 1px dashed var(--text-light); box-shadow: none;">
            <p class="m-0">ยังไม่มีกิจกรรมใหม่ขณะนี้</p>
        </div>
    </div>
<?php endif; ?>



<h2 class="section-title mb-3">ข่าวประชาสัมพันธ์</h2>
<div class="grid-3 mb-4">

    <?php if ($newsResult && $newsResult->num_rows > 0): ?>

        <?php while ($newsItem = $newsResult->fetch_assoc()): ?>

            <div class="card p-4 d-flex flex-column h-100">

                <!-- หัวข้อข่าว -->
                <h3 class="text-md text-main m-0 mb-2 font-weight-medium line-clamp-2">
                    <?= htmlspecialchars($newsItem['title']) ?>
                </h3>

                <!-- เนื้อหาข่าว -->
                <p class="text-muted text-sm flex-grow-1 mb-3 line-clamp-3">
                    <?= nl2br(htmlspecialchars($newsItem['content'])) ?>
                </p>

                <!-- ผู้ประกาศและวันที่ -->
                <div class="mt-auto pt-3 border-top text-xs text-muted d-flex justify-between">
                    <span>โดย: <?= htmlspecialchars($newsItem['author_name']) ?></span>
                    <span><?= date('d/m/Y', strtotime($newsItem['created_at'])) ?></span>
                </div>
            </div>

        <?php endwhile; ?>

    <?php else: ?>

        <div class="card p-4 text-center text-muted grid-span-full border-dashed"
             style="border: 1px dashed var(--text-light); box-shadow: none;">
            <p class="m-0">ยังไม่มีข่าวประชาสัมพันธ์ในขณะนี้</p>
        </div>

    <?php endif; ?>

</div>



<h2 class="section-title mb-3">กิจกรรมที่ผ่านมา</h2>

<div id="past-activities-container" class="grid-3 mb-4">

    <?php if ($pastActivitiesResult && $pastActivitiesResult->num_rows > 0): ?>

        <?php while ($pastActivity = $pastActivitiesResult->fetch_assoc()): ?>

            <div class="card p-0 overflow-hidden d-flex flex-column h-100">

                
                <a href="activity_details.php?id=<?= $pastActivity['activity_id'] ?>"
                   class="position-relative"
                   style="height: 160px; display: block; background: var(--bg-main);">

                    <?php if (!empty($pastActivity['cover_image'])): ?>
                        <img src="<?= htmlspecialchars($pastActivity['cover_image']) ?>"
                             alt="Cover"
                             style="width: 100%; height: 100%; object-fit: cover;"
                             onerror="this.onerror=null; this.parentElement.innerHTML='<div class=\'d-flex justify-center align-center h-100 text-muted\' style=\'background: #f1f5f9;\'><i class=\'fa-solid fa-image fa-2x\' style=\'opacity: 0.4;\'></i></div>';">
                    <?php else: ?>
                        <div class="d-flex justify-center align-center h-100 text-muted" style="background: #f1f5f9;">
                            <i class="fa-solid fa-image fa-2x" style="opacity: 0.4;"></i>
                        </div>
                    <?php endif; ?>

                </a>

                <div class="card-body p-3 d-flex flex-column flex-grow-1">
                    <h3 class="mt-0 mb-2 text-md"><?= htmlspecialchars($pastActivity['title']) ?></h3>

                    <p class="text-muted text-sm flex-grow-1 mb-3 line-clamp-2">
                        <?= htmlspecialchars($pastActivity['description'] ?? '') ?>
                    </p>

                    <div class="text-sm text-main mb-3">
                        <strong>วันที่:</strong> <?= date('d/m/Y', strtotime($pastActivity['start_date'])) ?>
                    </div>

                    <div class="mt-auto pt-3 border-top">

                        
                        <?php
                        $facebookLinkUrl = !empty($pastActivity['facebook_link'])
                            ? htmlspecialchars($pastActivity['facebook_link'])
                            : '';
                        ?>

                        <?php if ($facebookLinkUrl): ?>
                            <a href="<?= $facebookLinkUrl ?>"
                               target="_blank"
                               class="btn btn-block text-white mb-2"
                               style="background: #1976D2; border: none; text-decoration: none;">
                                ดูเพิ่มเติมบน Facebook
                            </a>
                        <?php else: ?>
                            <button class="btn btn-block bg-main text-muted cursor-default mb-2" disabled>
                                ไม่มีลิงก์เฟสบุ๊ค
                            </button>
                        <?php endif; ?>

                        <a href="activity_details.php?id=<?= $pastActivity['activity_id'] ?>"
                           class="btn btn-block btn-surface border-light text-muted">
                            ดูรายละเอียด
                        </a>
                    </div>
                </div>
            </div>

        <?php endwhile; ?>

    <?php else: ?>

        <div class="card p-4 text-center text-muted grid-span-full border-dashed"
             style="border: 1px dashed var(--text-light); box-shadow: none;">
            <p class="m-0">ยังไม่มีข้อมูลกิจกรรมย้อนหลัง</p>
        </div>

    <?php endif; ?>

</div>


<?php if ($pastActivitiesResult && $pastActivitiesResult->num_rows == 9): ?>
    <div class="text-center mb-5" id="load-more-wrapper">
        <button id="btn-load-more"
                class="btn btn-outline-primary"
                style="width: 100%; max-width: 500px; border-radius: 8px; padding: 12px; font-size: 16px;">
            แสดงกิจกรรมเพิ่มเติม
        </button>
    </div>
<?php endif; ?>



<script>
document.addEventListener('DOMContentLoaded', function() {

    var currentOffset    = 9;
    var btnLoadMore      = document.getElementById('btn-load-more');
    var pastContainer    = document.getElementById('past-activities-container');
    var loadMoreWrapper  = document.getElementById('load-more-wrapper');

    if (btnLoadMore) {
        btnLoadMore.addEventListener('click', function() {

            var originalButtonText = btnLoadMore.innerHTML;
            btnLoadMore.innerHTML  = 'กำลังโหลด...';
            btnLoadMore.disabled   = true;

            fetch('ajax_load_past_activities.php?offset=' + currentOffset)
                .then(function(response) {
                    return response.text();
                })
                .then(function(htmlContent) {

                    if (htmlContent.trim() === '') {
                        loadMoreWrapper.innerHTML = '<div class="text-muted text-sm text-center">แสดงกิจกรรมทั้งหมดแล้ว</div>';

                    } else {
                        pastContainer.insertAdjacentHTML('beforeend', htmlContent);
                        currentOffset += 6;

                        btnLoadMore.innerHTML = originalButtonText;
                        btnLoadMore.disabled  = false;

                        var newItemsCount = (htmlContent.match(/<div class="card/g) || []).length;
                        if (newItemsCount < 6) {
                            loadMoreWrapper.innerHTML = '<div class="text-muted text-sm text-center mt-3">แสดงกิจกรรมทั้งหมดแล้ว</div>';
                        }
                    }
                })
                .catch(function(error) {
                    console.error(error);
                    btnLoadMore.innerHTML = originalButtonText;
                    btnLoadMore.disabled  = false;
                });
        });
    }
});
</script>



<script src="assets/js/activities.js"></script>

<?php include 'includes/footer.php'; ?>
