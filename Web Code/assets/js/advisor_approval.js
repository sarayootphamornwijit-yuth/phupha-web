document.addEventListener('DOMContentLoaded', () => {
    // 2. จัดการปุ่มอนุมัติ/ยกเลิก
    const actionButtons = document.querySelectorAll('.btn-action-approve');
    actionButtons.forEach(btn => {
        btn.addEventListener('click', function () {
            const activityId = this.dataset.id;
            const action = this.dataset.action;

            let title = 'ยืนยันอนุมัติโครงการนี้?';
            if (action === 'rejected') {
                title = 'ยืนยันไม่อนุมัติโครงการนี้?';
            }

            if (confirm(title)) {
                // สร้างฟอร์มชั่วคราวเพื่อส่ง POST
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'advisor_approval.php';

                const inputAction = document.createElement('input');
                inputAction.type = 'hidden';
                inputAction.name = 'action';
                inputAction.value = action;

                const inputId = document.createElement('input');
                inputId.type = 'hidden';
                inputId.name = 'activity_id';
                inputId.value = activityId;

                form.appendChild(inputAction);
                form.appendChild(inputId);
                document.body.appendChild(form);
                form.submit();
            }
        });
    });
});