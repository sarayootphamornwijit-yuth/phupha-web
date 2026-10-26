<?php
// advisor_news.php
// ไฟล์นี้ทำหน้าที่ให้สิทธิ์อาจารย์ที่ปรึกษา คณะกรรมการ และแอดมิน จัดการข่าวประชาสัมพันธ์
// ได้แก่ การเพิ่มข่าวใหม่ การแก้ไขข่าวเดิม การลบข่าว และการกำหนดสถานะการเผยแพร่

require_once 'config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();
}

// ส่วนที่ 1 ตรวจสอบสิทธิ์
// อนุญาตเฉพาะอาจารย์ที่ปรึกษา แอดมิน คณะกรรมการ และประธาน/ผู้นำ
if (!isLoggedIn() || !in_array(getCurrentUserRole(), ['admin', 'advisor', 'committee', 'leader'], true)) {
    header("Location: index.php");
    exit;
}

$currentUserId = $_SESSION['user_id'];
$actionRequest = $_POST['action'] ?? '';

// ส่วนที่ 2 จัดการเพิ่ม / แก้ไข / ลบ ข่าวประชาสัมพันธ์ (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // ตรวจสอบเงื่อนไขว่าเป็นการเพิ่มหรือแก้ไขข่าว
    if ($actionRequest === 'add' || $actionRequest === 'edit') {
        
        // รับค่าข้อมูลจากฟอร์ม
        $newsTitle   = trim($_POST['title'] ?? '');
        $newsContent = trim($_POST['content'] ?? '');
        // เผยแพร่อัตโนมัติทันที
        $isPublished = 1;

        // กรณีเพิ่มข่าวใหม่
        if ($actionRequest === 'add' && $newsTitle !== '' && $newsContent !== '') {
            $insertStatement = $conn->prepare(
                "INSERT INTO news (title, content, image_url, author_id, is_published) VALUES (?, ?, '', ?, ?)"
            );
            $insertStatement->bind_param("ssii", $newsTitle, $newsContent, $currentUserId, $isPublished);
            
            if ($insertStatement->execute()) {
                $_SESSION['alert'] = [
                    'type'  => 'success',
                    'title' => 'เพิ่มข่าวสำเร็จ',
                    'text'  => 'เพิ่มข่าวประชาสัมพันธ์เรียบร้อยแล้ว'
                ];
            } else {
                $_SESSION['alert'] = [
                    'type'  => 'error',
                    'title' => 'เกิดข้อผิดพลาด',
                    'text'  => 'ไม่สามารถเพิ่มข่าวประชาสัมพันธ์ได้'
                ];
            }
            $insertStatement->close();
            
        } 
        // กรณีแก้ไขข่าว
        elseif ($actionRequest === 'edit') {
            $newsId = (int)($_POST['news_id'] ?? $_POST['id'] ?? 0);
            
            if ($newsId > 0 && $newsTitle !== '' && $newsContent !== '') {
                $updateStatement = $conn->prepare(
                    "UPDATE news SET title=?, content=?, is_published=? WHERE news_id=?"
                );
                $updateStatement->bind_param("ssii", $newsTitle, $newsContent, $isPublished, $newsId);
                
                if ($updateStatement->execute()) {
                    $_SESSION['alert'] = [
                        'type'  => 'success',
                        'title' => 'แก้ไขสำเร็จ',
                        'text'  => 'แก้ไขข่าวประชาสัมพันธ์เรียบร้อยแล้ว'
                    ];
                } else {
                    $_SESSION['alert'] = [
                        'type'  => 'error',
                        'title' => 'เกิดข้อผิดพลาด',
                        'text'  => 'ไม่สามารถแก้ไขข่าวประชาสัมพันธ์ได้'
                    ];
                }
                $updateStatement->close();
            }
        }
        
    } 
    // ตรวจสอบเงื่อนไขการลบข่าว
    elseif ($actionRequest === 'delete') {
        $newsId = (int)($_POST['news_id'] ?? $_POST['id'] ?? 0);
        
        if ($newsId > 0) {
            $deleteStatement = $conn->prepare("DELETE FROM news WHERE news_id=?");
            $deleteStatement->bind_param("i", $newsId);
            
            if ($deleteStatement->execute()) {
                $_SESSION['alert'] = [
                    'type'  => 'success',
                    'title' => 'ลบสำเร็จ',
                    'text'  => 'ลบข่าวประชาสัมพันธ์เรียบร้อยแล้ว'
                ];
            } else {
                $_SESSION['alert'] = [
                    'type'  => 'error',
                    'title' => 'เกิดข้อผิดพลาด',
                    'text'  => 'ไม่สามารถลบข่าวประชาสัมพันธ์ได้'
                ];
            }
            $deleteStatement->close();
        }
    }
    
    // หลังจากทำงานเสร็จสิ้น ให้รีเฟรชหน้า
    header("Location: advisor_news.php");
    exit;
}


// ส่วนที่ 3 ดึงรายการข่าวทั้งหมดเพื่อมาแสดงผล
$newsQueryString = "
    SELECT n.*, n.news_id, COALESCE(u.name, 'ชมรมเพื่อนภูผา') as author_name 
    FROM news n 
    LEFT JOIN users u ON n.author_id = u.user_id 
    ORDER BY n.created_at DESC
";
$newsResult = $conn->query($newsQueryString);


// ส่วนที่ 4 ตรวจสอบว่ากำลังแก้ไขข่าวอยู่หรือไม่
$newsToEditData = null;
if (isset($_GET['edit'])) {
    $editNewsId = (int)$_GET['edit'];
    if ($editNewsId > 0) {
        $editQueryStatement = $conn->prepare("SELECT * FROM news WHERE news_id=?");
        $editQueryStatement->bind_param("i", $editNewsId);
        $editQueryStatement->execute();
        
        $newsToEditData = $editQueryStatement->get_result()->fetch_assoc();
        $editQueryStatement->close();
    }
}


// ส่วนที่ 5 โหลด Header
include 'includes/header.php';
?>

<div class="container py-4">

    <h2 class="section-title mb-4">จัดการข่าวประชาสัมพันธ์</h2>

    <div class="grid-layout" style="grid-template-columns: 1fr 2fr; gap: var(--space-20);">
        
        <!-- ฝั่งซ้าย: ฟอร์มเพิ่ม/แก้ไขข่าว -->
        <div class="card h-100">
            
            <?php if ($newsToEditData): ?>
                <?php $editId = (int)$newsToEditData['news_id']; ?>
                <h3 class="text-md m-0 mb-3 pb-3 border-bottom">
                    แก้ไขข่าวประชาสัมพันธ์
                    <a href="advisor_news.php" class="text-sm text-muted font-weight-normal" style="float: right;">ยกเลิก</a>
                </h3>
                
                <form method="POST" action="advisor_news.php">
                    <?php csrfField(); ?>
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="news_id" value="<?= $editId ?>">
                    
                    <div class="form-group mb-3">
                        <label class="form-label">หัวข้อข่าว <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control" value="<?= htmlspecialchars($newsToEditData['title']) ?>" required>
                    </div>
                    
                    <div class="form-group mb-4">
                        <label class="form-label">เนื้อหาข่าว <span class="text-danger">*</span></label>
                        <textarea name="content" class="form-control" rows="8" required><?= htmlspecialchars($newsToEditData['content']) ?></textarea>
                    </div>
                    
                    <button type="submit" class="btn btn-warning btn-block">บันทึกการแก้ไข</button>
                </form>
                
            <?php else: ?>
                
                <h3 class="text-md m-0 mb-3 pb-3 border-bottom">เพิ่มข่าวใหม่</h3>
                
                <form method="POST" action="advisor_news.php">
                    <?php csrfField(); ?>
                    <input type="hidden" name="action" value="add">
                    
                    <div class="form-group mb-3">
                        <label class="form-label">หัวข้อข่าว <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control" placeholder="พิมพ์หัวข้อข่าว..." required>
                    </div>
                    
                    <div class="form-group mb-4">
                        <label class="form-label">เนื้อหาข่าว <span class="text-danger">*</span></label>
                        <textarea name="content" class="form-control" rows="8" placeholder="พิมพ์รายละเอียดข่าวสาร..." required></textarea>
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-block">บันทึกข่าว</button>
                </form>
                
            <?php endif; ?>
            
        </div>

        <!-- ฝั่งขวา: รายการข่าว -->
        <div class="card h-100">
            <h3 class="text-md m-0 mb-3 pb-3 border-bottom">รายการข่าวที่ประกาศไปแล้ว</h3>
            
            <?php if ($newsResult && $newsResult->num_rows > 0): ?>
                
                <div class="d-flex flex-column gap-3">
                    <?php while ($newsItem = $newsResult->fetch_assoc()): ?>
                        <?php $curNewsId = (int)$newsItem['news_id']; ?>
                        <div class="p-3 rounded border" style="border-left: 4px solid var(--color-primary);">
                            
                            <!-- หัวข้อข่าว -->
                            <div class="d-flex justify-between gap-2 mb-2">
                                <h4 class="m-0 text-md text-main font-weight-medium">
                                    <?= htmlspecialchars($newsItem['title']) ?>
                                </h4>
                            </div>
                            
                            <!-- เนื้อหาแบบย่อ -->
                            <div class="text-sm text-muted mb-2 line-clamp-3">
                                <?= nl2br(htmlspecialchars($newsItem['content'])) ?>
                            </div>
                            
                            <!-- ผู้เขียนและวันที่ / ปุ่มจัดการ -->
                            <div class="d-flex justify-between align-center border-top pt-2 mt-2">
                                
                                <div class="text-xs text-muted">
                                    โดย: <?= htmlspecialchars($newsItem['author_name']) ?> | 
                                    <?= date('d/m/Y H:i', strtotime($newsItem['created_at'])) ?>
                                </div>
                                
                                <div class="d-flex gap-2">
                                    <a href="advisor_news.php?edit=<?= $curNewsId ?>" class="btn btn-surface text-xs px-2 py-1" title="แก้ไข">
                                        <i class="fa-solid fa-pen"></i>
                                    </a>
                                    <form method="POST" action="advisor_news.php" class="d-inline" onsubmit="return confirm('ยืนยันการลบข่าวประชาสัมพันธ์นี้ใช่หรือไม่?');">
                                        <?php csrfField(); ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="news_id" value="<?= $curNewsId ?>">
                                        <button type="submit" class="btn btn-danger text-xs px-2 py-1" title="ลบ">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                                
                            </div>
                            
                        </div>
                    <?php endwhile; ?>
                </div>
                
            <?php else: ?>
                <div class="text-center p-4 text-muted">
                    <p class="m-0">ยังไม่มีข่าวประชาสัมพันธ์</p>
                </div>
            <?php endif; ?>
            
        </div>
        
    </div>
    
</div>

<?php include 'includes/footer.php'; ?>
