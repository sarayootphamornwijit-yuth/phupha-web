document.addEventListener('DOMContentLoaded', () => {
    // 1. Sidebar Toggle Logic
    const sidebar = document.getElementById('sidebar');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebarClose = document.getElementById('sidebarClose');
    const sidebarOverlay = document.getElementById('sidebarOverlay');

    function openSidebar() {
        if (sidebar) sidebar.classList.add('active');
        if (sidebarOverlay) sidebarOverlay.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeSidebar() {
        if (sidebar) sidebar.classList.remove('active');
        if (sidebarOverlay) sidebarOverlay.classList.remove('active');
        document.body.style.overflow = '';
    }

    if (sidebarToggle) sidebarToggle.addEventListener('click', openSidebar);
    if (sidebarClose) sidebarClose.addEventListener('click', closeSidebar);
    if (sidebarOverlay) sidebarOverlay.addEventListener('click', closeSidebar);

    // Close on ESC key
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') closeSidebar();
    });

    // 2. Prevent Double Form Submission
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function (e) {
            if (this.method && this.method.toLowerCase() === 'get') {
                // อย่าล็อคปุ่มสำหรับฟอร์มแบบ GET เพราะส่วนใหญ่เป็นการค้นหา/กรองข้อมูล และอาจไม่โหลดหน้าใหม่
                return;
            }

            if (this.classList.contains('is-submitting')) {
                e.preventDefault();
                return;
            }
            
            // Check HTML5 validity
            if (!this.checkValidity()) {
                return;
            }

            // Wait for other submit handlers (like custom JS validation) to run
            setTimeout(() => {
                if (!e.defaultPrevented) {
                    this.classList.add('is-submitting');
                    const submitBtn = this.querySelector('button[type="submit"]');
                    if (submitBtn) {
                        // Store original text just in case
                        submitBtn.dataset.originalText = submitBtn.innerHTML;
                        submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> กำลังประมวลผล...';
                        submitBtn.disabled = true;
                    }
                }
            }, 0);
        });
    });

    // 3. Flash Messages (Native Alert)
    const flashMsg = document.getElementById('flash-message');
    if (flashMsg) {
        const title = flashMsg.dataset.title || '';
        const text = flashMsg.dataset.text || '';
        if (title || text) {
            // ใช้ setTimeout เพื่อให้แน่ใจว่าหน้าเว็บโหลดเสร็จก่อนแสดง alert
            setTimeout(() => {
                let alertMessage = '';
                if (title) alertMessage += title + '\n';
                if (text) alertMessage += text;
                alert(alertMessage.trim());
            }, 100);
        }
    }

    // 4. Smooth page entrance animation
    const mainContent = document.querySelector('.main-content');
    if (mainContent) {
        mainContent.style.opacity = '0';
        mainContent.style.transform = 'translateY(8px)';
        requestAnimationFrame(() => {
            mainContent.style.transition = 'opacity 0.4s ease, transform 0.4s ease';
            mainContent.style.opacity = '1';
            mainContent.style.transform = 'translateY(0)';
            // ล้าง transform เมื่อแอนิเมชันเสร็จ เพื่อไม่ให้กระทบ position: fixed ของ Modal ต่างๆ ในหน้าเว็บ
            setTimeout(() => {
                mainContent.style.transform = '';
                mainContent.style.transition = '';
            }, 450);
        });
    }
});