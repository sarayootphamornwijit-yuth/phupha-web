document.addEventListener('DOMContentLoaded', () => {


    // สลับแท็บข้อมูล (นิสิต / บุคคลทั่วไป)
    const tabBtns = document.querySelectorAll('.tab-btn');
    const tabContents = document.querySelectorAll('.tab-content');

    tabBtns.forEach(btn => {
        btn.addEventListener('click', function () {
            // ล้างสถานะปุ่มทั้งหมด
            tabBtns.forEach(b => {
                b.classList.remove('btn-primary', 'active');
                b.classList.add('btn-surface', 'border-light', 'text-muted');
            });

            // ซ่อนเนื้อหาทั้งหมด
            tabContents.forEach(content => {
                content.classList.add('d-none');
            });

            // เปิดแท็บที่เลือก
            this.classList.remove('btn-surface', 'border-light', 'text-muted');
            this.classList.add('btn-primary', 'active');

            const targetId = this.dataset.target;
            const targetContent = document.getElementById(targetId);
            if (targetContent) {
                targetContent.classList.remove('d-none');
            }
        });
    });

});