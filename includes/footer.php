<?php
// includes/footer.php
// ไฟล์นี้ทำหน้าที่เป็น "ส่วนท้าย" ของทุกหน้าในระบบ
// ประกอบด้วย: การปิดแท็ก main, Footer HTML, และการโหลด JavaScript
?>

</main><!-- ปิดแท็ก .main-content ที่เปิดไว้ใน includes/header.php -->

<!-- Footer -->
<footer class="site-footer" style="padding: 15px 24px; text-align: center; border-top: 1px solid var(--border-color); background: white; margin-top: auto;">
    <p style="margin: 0; font-size: 12px; color: var(--text-muted);">
        © <?= date('Y') ?> ชมรมเพื่อนภูผา มหาวิทยาลัยราชภัฏเชียงใหม่ — Phupha Club CMRU
    </p>
</footer>

<!-- Global JavaScript -->
<!-- โหลดไฟล์ JavaScript หลักที่ใช้ร่วมกันทุกหน้า (sidebar toggle ฯลฯ) -->
<script src="assets/js/app.js"></script>

</body>

</html>