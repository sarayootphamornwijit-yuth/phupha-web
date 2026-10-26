document.addEventListener('DOMContentLoaded', () => {
    // 2. Client-side File Validation
    const fileInput = document.getElementById('cover_image');
    if (fileInput) {
        fileInput.addEventListener('change', function () {
            const file = this.files[0];
            if (file) {
                // เช็คขนาดไฟล์ไม่เกิน 2MB (2 * 1024 * 1024 bytes)
                if (file.size > 2097152) {
                    alert('ไฟล์มีขนาดใหญ่เกินไป: กรุณาเลือกไฟล์ขนาดไม่เกิน 2MB');
                    this.value = ''; // เคลียร์ไฟล์ออก
                    return;
                }

                // เช็คนามสกุลไฟล์ (เพิ่มเติมจาก accept)
                const allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
                if (!allowedTypes.includes(file.type)) {
                    alert('ประเภทไฟล์ไม่ถูกต้อง: กรุณาอัปโหลดเฉพาะไฟล์รูปภาพ (JPG, PNG, GIF)');
                    this.value = '';
                }
            }
        });
    }

    // 3. จัดการ Toggle Self Check-in
    const toggles = document.querySelectorAll('.toggle-checkin');
    toggles.forEach(toggle => {
        toggle.addEventListener('change', function () {
            const activityId = this.dataset.id;
            const status = this.checked ? 1 : 0;

            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

            fetch('ajax_toggle_checkin.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `id=${activityId}&status=${status}&csrf_token=${encodeURIComponent(csrfToken)}`
            })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        alert('อัปเดตสถานะเช็คอินเรียบร้อย');
                    } else {
                        alert('ข้อผิดพลาด: ไม่สามารถอัปเดตสถานะได้');
                        this.checked = !this.checked; // Revert visually
                    }
                })
                .catch(err => {
                    console.error(err);
                    alert('ข้อผิดพลาด: เกิดข้อผิดพลาดในการเชื่อมต่อกับเซิร์ฟเวอร์');
                    this.checked = !this.checked;
                });
        });
    });
});

// ฟังก์ชันพิมพ์รายชื่อผู้เข้าร่วม
function printParticipants(activityId) {
    if (confirm('กด [OK] เพื่อพิมพ์พร้อมสถานะ\nกด [Cancel] เพื่อพิมพ์เฉพาะรายชื่อ (จะมีการถามยืนยันอีกครั้ง)')) {
        window.open(`print_participants.php?id=${activityId}&mode=with_status`, '_blank');
    } else {
        if (confirm('คุณต้องการพิมพ์เฉพาะรายชื่อใช่หรือไม่?')) {
            window.open(`print_participants.php?id=${activityId}&mode=name_only`, '_blank');
        }
    }
}
// 4. จัดการปุ่มลบกิจกรรมหลักด้วย Native Confirm
const deleteActMainButtons = document.querySelectorAll('.btn-delete-act-main');
deleteActMainButtons.forEach(btn => {
    btn.addEventListener('click', function () {
        const form = this.closest('form');
        if (confirm('ยืนยันการลบกิจกรรม?')) {
            form.submit();
        }
    });
});