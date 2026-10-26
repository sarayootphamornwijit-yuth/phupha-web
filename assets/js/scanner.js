document.addEventListener('DOMContentLoaded', () => {

    /* ตัวจัดการกล้องสำหรับสแกน QR Code */
    const readerElement = document.getElementById('reader');

    if (readerElement) {
        let isProcessing = false;
        const html5QrCode = new Html5Qrcode("reader");

        /* ทำงานเมื่อสแกนเจอ QR Code */
        const onScanSuccess = (decodedText, decodedResult) => {
            if (isProcessing) return;
            isProcessing = true;

            /* สั่งปิดกล้องเมื่อสแกนติดแล้ว */
            html5QrCode.stop().then(() => {
                html5QrCode.clear();
            }).catch(e => {
                console.warn('Stop scanner error:', e);
            });

            /* ส่งรหัสที่สแกนได้ผ่านฟอร์ม */
            const cameraForm = document.getElementById('qr-camera-form');
            const qrCameraData = document.getElementById('qr_camera_data');

            if (cameraForm && qrCameraData) {
                qrCameraData.value = decodedText;
                cameraForm.submit();
            } else {
                /* กรณีหาฟอร์มไม่พบ */
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'qr_scanner.php';

                const csrfInput = document.querySelector('input[name="csrf_token"]');
                if (csrfInput) {
                    const tokenField = document.createElement('input');
                    tokenField.type = 'hidden';
                    tokenField.name = 'csrf_token';
                    tokenField.value = csrfInput.value;
                    form.appendChild(tokenField);
                }

                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'qr_data';
                input.value = decodedText;

                form.appendChild(input);
                document.body.appendChild(form);
                form.submit();
            }
        };

        const onScanFailure = (error) => {
            /* ปล่อยผ่านขณะที่กล้องยังมองไม่เห็น QR Code */
        };

        const config = {
            fps: 10,
            qrbox: { width: 250, height: 250 },
            aspectRatio: 1.0
        };

        /* เปิดกล้องหลังเป็นค่าเริ่มต้น */
        html5QrCode.start(
            { facingMode: "environment" },
            config,
            onScanSuccess,
            onScanFailure
        ).catch(err => {
            /* ถ้าไม่มีกล้องหลัง ให้เปิดกล้องหน้าแทน */
            html5QrCode.start(
                { facingMode: "user" },
                config,
                onScanSuccess,
                onScanFailure
            ).catch(errFallback => {
                console.error("Unable to start camera:", errFallback);
            });
        });
    }
});