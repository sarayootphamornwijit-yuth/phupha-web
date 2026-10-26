// รอให้หน้าเว็บโหลดองค์ประกอบ HTML ให้เสร็จสมบูรณ์ก่อนทำงาน
document.addEventListener('DOMContentLoaded', () => {
    // สร้างตัวแปรเพื่อดึงปุ่มสำหรับการลงทะเบียนทั้งหมดในหน้าเว็บ
    const regButtons = document.querySelectorAll('.btn-confirm-reg');
    // วนลูปเพื่อจัดการกับทุกปุ่มลงทะเบียน
    regButtons.forEach(btn => {
        // กำหนด Event Listener ดักจับการคลิกที่ปุ่มลงทะเบียน
        btn.addEventListener('click', function () {
            // สร้างตัวแปรดึงแบบฟอร์มที่ปุ่มนี้อาศัยอยู่
            const form = this.closest('form');
            // สร้างตัวแปรดึงช่องเลือกวิธีการเดินทางภายในฟอร์มนี้
            const travelSelect = form.querySelector('select[name="travel_method"]');

            // ตรวจสอบว่าผู้ใช้ไม่ได้เลือกวิธีการเดินทางใช่หรือไม่
            if (!travelSelect.value) {
                alert('ระบุการเดินทาง: กรุณาเลือกว่าคุณจะเดินทางไปเอง หรือไปกับรถของชมรม');
                return;
            }

            // สร้างตัวแปรดึงตัวเลือกยานพาหนะที่ผู้ใช้เลือกในปัจจุบัน
            const selectedOption = travelSelect.options[travelSelect.selectedIndex];
            // ตรวจสอบว่าตัวเลือกนั้นถูกปิดใช้งาน (ที่นั่งเต็ม) หรือไม่
            if (selectedOption.disabled) {
                alert('ที่นั่งเต็มแล้ว: ขออภัย รถคันที่คุณเลือกที่นั่งเต็มแล้ว กรุณาเลือกคันอื่น');
                return;
            }

            // เรียกใช้งาน confirm แสดงหน้าต่างยืนยันการลงทะเบียน
            if (confirm('ยืนยันการลงทะเบียน?')) {
                form.submit();
            }
        });
    });
});