<?php
// committee_directory.php
// ไฟล์นี้ทำหน้าที่แสดงทำเนียบผู้นำรุ่น คณะกรรมการชมรมเพื่อนภูผา
// แสดงข้อมูลคณะกรรมการในแต่ละชั้นปี และสามารถเลือก Tab เพื่อดูข้อมูล

require_once 'config/database.php';


// ส่วนที่ 1 ดึงข้อมูลคณะกรรมการ
// เฉพาะ committee ที่เป็นนักศึกษา ปี 1-4
$committeeQueryString = "
    SELECT u.user_id, u.name, u.role, u.committee_position, u.academic_year, u.generation, 
           u.image, u.phone, u.faculty, u.major, u.student_id
    FROM users u 
    WHERE u.role IN ('committee') 
      AND u.user_type = 'student'
      AND u.academic_year IS NOT NULL
      AND u.academic_year BETWEEN 1 AND 4
    ORDER BY u.academic_year ASC, 
             FIELD(u.committee_position, 'president', 'vice_president', 'treasurer', 
                   'secretary_saluang', 'secretary_wiangbua', 'nurse', 'venue_coordinator', 'pr'),
             u.name ASC
";
$committeeQueryResult = $conn->query($committeeQueryString);

// จัดกลุ่มข้อมูลตามชั้นปีการศึกษา
$committeeListByYear = [];
if ($committeeQueryResult) {
    while ($committeeRow = $committeeQueryResult->fetch_assoc()) {
        $yearLevel = $committeeRow['academic_year'];
        $committeeListByYear[$yearLevel][] = $committeeRow;
    }
}


// ส่วนที่ 2 ตั้งค่า Labels และ Icons
// แปลงตำแหน่งภาษาอังกฤษเป็นภาษาไทย
$positionLabelsThai = [
    'president'          => 'ประธานชมรม',
    'vice_president'     => 'รองประธานชมรม',
    'treasurer'          => 'เหรัญญิก',
    'secretary_saluang'  => 'เลขาสะลวง',
    'secretary_wiangbua' => 'เลขาเวียงบัว',
    'nurse'              => 'พยาบาล',
    'venue_coordinator'  => 'ฝ่ายสถานที่',
    'pr'                 => 'ฝ่ายประชาสัมพันธ์'
];

// ตั้งค่าไอคอนสำหรับแต่ละตำแหน่ง
$positionIcons = [
    'president'          => 'fa-crown',
    'vice_president'     => 'fa-star',
    'treasurer'          => 'fa-coins',
    'secretary_saluang'  => 'fa-pen-nib',
    'secretary_wiangbua' => 'fa-pen-nib',
    'nurse'              => 'fa-kit-medical',
    'venue_coordinator'  => 'fa-map-location-dot',
    'pr'                 => 'fa-bullhorn'
];

// ตั้งค่าสีประจำตำแหน่งเพื่อเน้นความโดดเด่น
$positionColors = [
    'president'          => '#F9A825',
    'vice_president'     => '#FB8C00',
    'treasurer'          => '#43A047',
    'secretary_saluang'  => '#1976D2',
    'secretary_wiangbua' => '#7B1FA2',
    'nurse'              => '#E53935',
    'venue_coordinator'  => '#00897B',
    'pr'                 => '#FFB300'
];


// ส่วนที่ 3 โหลด Header
include 'includes/header.php';
?>

<div class="container py-4">

    <!-- ส่วนหัวหน้าธรรมเนียบ -->
    <div class="text-center mb-4">
        <h1 class="m-0 text-primary-dark" style="font-size: 28px;">ทำเนียบผู้นำรุ่น</h1>
        <p class="text-muted text-md mt-1">คณะกรรมการชมรมเพื่อนภูผา มหาวิทยาลัยราชภัฏเชียงใหม่</p>
    </div>

<!-- ส่วนที่ 4: Tabs เลือกชั้นปี -->
    <div class="d-flex gap-2 mb-4 justify-center flex-wrap">
        <?php for ($yearIndex = 1; $yearIndex <= 4; $yearIndex++): ?>
            <?php
            // ตรวจสอบว่ามีข้อมูลสำหรับชั้นปีนี้หรือไม่
            $memberCountInYear = isset($committeeListByYear[$yearIndex]) ? count($committeeListByYear[$yearIndex]) : 0;
            
            // ปี 1 ให้ใช้งานเป็น Default
            if ($yearIndex === 1) {
                $buttonClass = 'btn-primary active';
            } else {
                $buttonClass = 'btn-surface border-light text-muted';
            }
            ?>
            <button type="button" class="btn <?= $buttonClass ?> tab-btn" data-target="year-<?= $yearIndex ?>">
                ชั้นปี <?= $yearIndex ?>
            </button>
        <?php endfor; ?>
    </div>


<!-- ส่วนที่ 5: เนื้อหาคณะกรรมการแต่ละชั้นปี -->
    <?php for ($yearIndex = 1; $yearIndex <= 4; $yearIndex++): ?>
        
        <?php
        $tabVisibilityClass = ($yearIndex !== 1) ? 'd-none' : '';
        ?>
        <div id="year-<?= $yearIndex ?>" class="tab-content <?= $tabVisibilityClass ?>">
            
            <?php if (isset($committeeListByYear[$yearIndex]) && count($committeeListByYear[$yearIndex]) > 0): ?>
                
                <div class="grid-3">
                    <?php foreach ($committeeListByYear[$yearIndex] as $committeeMember): ?>
                        
                        <?php
                        // แปลงข้อมูลตำแหน่งเพื่อแสดงผล
                        $positionKey   = $committeeMember['committee_position'] ?? '';
                        $positionText  = $positionLabelsThai[$positionKey] ?? 'คณะกรรมการ';
                        
                        // จัดการเว้นวรรคคำนำหน้าชื่อ
                        $displayName = htmlspecialchars($committeeMember['name']);
                        $displayName = preg_replace('/^(นาย|นางสาว|นาง)(?!\s)/', '$1 ', $displayName);
                        ?>
                        
                        <div class="card text-left">
                            
                            <!-- รูปโปรไฟล์ -->
                            <div class="mb-3" style="width: 90px; height: 90px; border-radius: 50%; overflow: hidden; border: 1px solid #ccc; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
                                <?php if (!empty($committeeMember['image'])): ?>
                                    <img src="<?= htmlspecialchars($committeeMember['image']) ?>" style="width: 100%; height: 100%; object-fit: cover;" alt="<?= htmlspecialchars($committeeMember['name']) ?>">
                                <?php else: ?>
                                    <div class="d-flex justify-center align-center h-100" style="background: #f0f0f0; color: #000;">
                                        <i class="fa-solid fa-user fa-2x"></i>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- ข้อมูลชื่อและตำแหน่ง -->
                            <h3 class="m-0 text-md font-weight-bold text-main mb-1">
                                <?= $displayName ?>
                            </h3>
                            
                            <div class="text-sm text-muted mb-1">
                                <?= $positionText ?>
                            </div>

                            <!-- ข้อมูลการศึกษาและการติดต่อ -->
                            <div class="text-sm text-muted">
                                <?php if (!empty($committeeMember['faculty'])): ?>
                                    <div class="mb-1">
                                        <?= htmlspecialchars($committeeMember['faculty']) ?>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($committeeMember['major'])): ?>
                                    <div class="mb-1">
                                        <?= htmlspecialchars($committeeMember['major']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                        </div>
                    <?php endforeach; ?>
                </div>
                
            <?php else: ?>
                <!-- กรณีไม่มีข้อมูลคณะกรรมการในชั้นปีนี้ -->
                <div class="card p-4 text-center text-muted" style="border: 1px dashed var(--border-color);">
                    <p class="m-0">ยังไม่มีข้อมูลคณะกรรมการชั้นปี <?= $yearIndex ?></p>
                </div>
            <?php endif; ?>
            
        </div>
    <?php endfor; ?>
    
</div>

<!-- JavaScript สำหรับการเปลี่ยน Tab -->
<script>
document.querySelectorAll('.tab-btn').forEach(buttonElement => {
    buttonElement.addEventListener('click', function() {
        
        // ลบคลาส active จากทุกปุ่ม
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.classList.remove('active', 'btn-primary');
            btn.classList.add('btn-surface', 'border-light', 'text-muted');
        });
        
        // เพิ่มคลาส active ให้กับปุ่มที่ถูกคลิก
        this.classList.add('active', 'btn-primary');
        this.classList.remove('btn-surface', 'border-light', 'text-muted');
        
        // ซ่อนเนื้อหาทั้งหมด
        document.querySelectorAll('.tab-content').forEach(content => {
            content.classList.add('d-none');
        });
        
        // แสดงเนื้อหาของเป้าหมายที่เลือก
        const targetId = this.dataset.target;
        const targetContentElement = document.getElementById(targetId);
        
        if (targetContentElement) {
            targetContentElement.classList.remove('d-none');
            targetContentElement.style.animation = 'fadeIn 0.3s ease';
        }
        
    });
});
</script>

<?php include 'includes/footer.php'; ?>
