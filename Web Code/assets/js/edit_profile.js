document.addEventListener('DOMContentLoaded', () => {
    // 2. แสดงตัวอย่างรูปภาพและตรวจสอบไฟล์ (Client-side Validation)
    const imageUpload = document.getElementById('imageUpload');
    const imgPreview = document.getElementById('imgPreview');
    const iconPreview = document.getElementById('iconPreview');

    if (imageUpload) {
        imageUpload.addEventListener('change', function () {
            const file = this.files[0];
            if (file) {
                // ตรวจสอบขนาดไฟล์ไม่เกิน 2MB
                if (file.size > 2097152) {
                    alert('ไฟล์มีขนาดใหญ่เกินไป: กรุณาเลือกไฟล์ขนาดไม่เกิน 2MB');
                    this.value = '';
                    return;
                }

                // ตรวจสอบชนิดไฟล์
                const allowedTypes = ['image/jpeg', 'image/png'];
                if (!allowedTypes.includes(file.type)) {
                    alert('ประเภทไฟล์ไม่ถูกต้อง: กรุณาอัปโหลดไฟล์รูปภาพ (JPG, PNG) เท่านั้น');
                    this.value = '';
                    return;
                }

                // แสดงรูปตัวอย่าง
                const reader = new FileReader();
                reader.onload = function (e) {
                    imgPreview.src = e.target.result;
                    imgPreview.classList.remove('d-none');
                    if (iconPreview) iconPreview.classList.add('d-none');
                }
                reader.readAsDataURL(file);
            }
        });
    }
});