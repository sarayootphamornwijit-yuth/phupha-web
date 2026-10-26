/**
 * assets/js/profile.js
 * จัดการการแสดงผล Popup Modal และการสร้าง QR Code เช็คอินกิจกรรม
 * รองรับทั้งการสร้าง QR Code ออฟไลน์ในเบราว์เซอร์ (QRCode.js) และ Multi-tier Online Fallback
 */

(function () {
    let modalMoved = false;

    function getModalElements() {
        const modal = document.getElementById('qrCodeModal');
        return {
            modal: modal,
            title: document.getElementById('modalActivityTitle'),
            user: document.getElementById('modalUserName'),
            container: document.getElementById('modalQrCanvasContainer'),
            image: document.getElementById('modalQrImage'),
            loading: document.getElementById('modalQrLoading'),
            codeText: document.getElementById('modalQrCodeText'),
            copyBtn: document.getElementById('copyCodeBtn')
        };
    }

    function ensureModalInBody() {
        if (modalMoved) return;
        const modal = document.getElementById('qrCodeModal');
        if (modal && modal.parentElement !== document.body) {
            document.body.appendChild(modal);
            modalMoved = true;
        }
    }

    function closeModal() {
        const modal = document.getElementById('qrCodeModal');
        if (modal) {
            modal.style.display = 'none';
            document.body.style.overflow = '';
        }
    }

    function openModal(btn) {
        ensureModalInBody();
        const elems = getModalElements();
        if (!elems.modal) return;

        const qrCodeData = btn.dataset.code || '';
        const actTitle = btn.dataset.title || 'กิจกรรมชมรมเพื่อนภูผา';
        const userName = btn.dataset.username || 'สมาชิกชมรม';

        if (elems.title) elems.title.textContent = actTitle;
        if (elems.user) elems.user.textContent = 'ผู้เข้าร่วม: ' + userName;
        if (elems.codeText) elems.codeText.textContent = qrCodeData;

        // ล็อค scroll พื้นหลังและแสดง Modal
        elems.modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';

        // การสร้าง QR Code
        renderQrCode(qrCodeData, elems);
    }

    function renderQrCode(data, elems) {
        const container = elems.container;
        const loading = elems.loading;

        if (!container) return;

        // วิธีที่ 1: ใช้ QRCode.js สร้าง Local Canvas/SVG ในเครื่องโดยตรง (เร็วที่สุด ทำงานได้แม้ออฟไลน์)
        if (typeof QRCode !== 'undefined') {
            try {
                if (loading) loading.style.display = 'none';
                container.innerHTML = '';
                new QRCode(container, {
                    text: data,
                    width: 220,
                    height: 220,
                    colorDark: '#0f172a',
                    colorLight: '#ffffff',
                    correctLevel: QRCode.CorrectLevel.M
                });

                // ตกแต่ง Canvas/Image ที่ถูกสร้างขึ้นมาให้กึ่งกลาง
                setTimeout(() => {
                    const canvasOrImg = container.querySelector('canvas, img');
                    if (canvasOrImg) {
                        canvasOrImg.style.display = 'block';
                        canvasOrImg.style.margin = '0 auto';
                        canvasOrImg.style.borderRadius = '8px';
                    }
                }, 10);
                return;
            } catch (err) {
                console.warn('QRCode.js rendering error, falling back to image APIs:', err);
            }
        }

        // วิธีที่ 2: Multi-tier Fallback ไปยัง Online API พร้อม Loading State
        if (loading) loading.style.display = 'flex';
        container.innerHTML = '';

        const img = document.createElement('img');
        img.id = 'modalQrImage';
        img.alt = 'QR Code เช็คอิน';
        img.style.width = '220px';
        img.style.height = '220px';
        img.style.display = 'block';
        img.style.margin = '0 auto';
        img.style.borderRadius = '8px';

        const providers = [
            `https://api.qrserver.com/v1/create-qr-code/?size=260x260&margin=8&data=${encodeURIComponent(data)}`,
            `https://quickchart.io/qr?size=260&text=${encodeURIComponent(data)}`,
            `https://chart.googleapis.com/chart?chs=260x260&cht=qr&chl=${encodeURIComponent(data)}`
        ];

        let providerIndex = 0;

        function tryNextProvider() {
            if (providerIndex < providers.length) {
                img.src = providers[providerIndex];
                providerIndex++;
            } else {
                // หากทุก API ไม่สามารถติดต่อได้ (เช่น ไม่มีอินเทอร์เน็ต)
                if (loading) loading.style.display = 'none';
                container.innerHTML = `
                    <div style="padding:20px; text-align:center; color:#b91c1c;">
                        <i class="fa-solid fa-triangle-exclamation" style="font-size:2.5rem; margin-bottom:10px; color:#e11d48; display:block;"></i>
                        <div style="font-weight:700; font-size:0.95rem; margin-bottom:4px;">ไม่สามารถโหลดภาพ QR Code ได้</div>
                        <div style="font-size:0.8rem; color:#64748b; line-height:1.4;">
                            สามารถใช้รหัสข้อความด้านล่างนี้แจ้งกรรมการเพื่อเช็คอินด้วยตนเองได้ทันทีครับ
                        </div>
                    </div>
                `;
            }
        }

        img.onload = function () {
            if (loading) loading.style.display = 'none';
        };

        img.onerror = function () {
            tryNextProvider();
        };

        container.appendChild(img);
        tryNextProvider();
    }

    function initQrModal() {
        ensureModalInBody();

        // 1. Delegated click สำหรับเปิด Modal
        document.addEventListener('click', function (e) {
            const btn = e.target.closest('.btn-show-qr');
            if (btn) {
                e.preventDefault();
                openModal(btn);
                return;
            }

            // ปุ่มปิด Modal
            if (e.target.closest('#closeQrModalBtn') || e.target.closest('#closeQrModalBottomBtn')) {
                e.preventDefault();
                closeModal();
                return;
            }

            // คลิกที่พื้นหลัง Backdrop
            const modal = document.getElementById('qrCodeModal');
            if (modal && e.target === modal) {
                closeModal();
                return;
            }

            // ปุ่มคัดลอกรหัส
            const copyBtn = e.target.closest('#copyCodeBtn');
            if (copyBtn) {
                e.preventDefault();
                const codeText = document.getElementById('modalQrCodeText');
                const code = codeText ? codeText.textContent.trim() : '';
                if (!code || code === '-') return;

                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(code).then(() => {
                        const originalHtml = copyBtn.innerHTML;
                        copyBtn.innerHTML = '<i class="fa-solid fa-check text-success"></i> คัดลอกแล้ว!';
                        setTimeout(() => {
                            copyBtn.innerHTML = originalHtml;
                        }, 2000);
                    }).catch(() => {
                        fallbackCopy(code, copyBtn);
                    });
                } else {
                    fallbackCopy(code, copyBtn);
                }
            }
        });

        // 2. ปิดด้วยปุ่ม Escape
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                const modal = document.getElementById('qrCodeModal');
                if (modal && modal.style.display === 'flex') {
                    closeModal();
                }
            }
        });
    }

    function fallbackCopy(text, btn) {
        const textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.style.position = 'fixed';
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);
        textarea.select();
        try {
            document.execCommand('copy');
            const originalHtml = btn.innerHTML;
            btn.innerHTML = '<i class="fa-solid fa-check text-success"></i> คัดลอกแล้ว!';
            setTimeout(() => {
                btn.innerHTML = originalHtml;
            }, 2000);
        } catch (err) {
            prompt('กด Ctrl+C เพื่อคัดลอกรหัส:', text);
        }
        document.body.removeChild(textarea);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initQrModal);
    } else {
        initQrModal();
    }
})();