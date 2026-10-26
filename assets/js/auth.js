// รอให้หน้าเว็บโหลดโครงสร้าง HTML ให้เสร็จสิ้น (DOMContentLoaded)
document.addEventListener('DOMContentLoaded', () => {
    // สร้างตัวแปรดึงองค์ประกอบของตัวเลือกคำนำหน้า
    const prefixSelect = document.getElementById('prefix');
    // สร้างตัวแปรดึงองค์ประกอบคอนเทนเนอร์สำหรับคำนำหน้าแบบระบุเอง
    const otherPrefixContainer = document.getElementById('otherPrefixContainer');
    // สร้างตัวแปรดึงองค์ประกอบกล่องข้อความสำหรับกรอกคำนำหน้า
    const otherPrefixInput = document.getElementById('other_prefix');

    // สร้างตัวแปรดึงกลุ่มปุ่มตัวเลือก (Radio) ประเภทผู้ใช้งานทั้งหมด
    const userTypeRadios = document.querySelectorAll('input[name="user_type"]');
    // สร้างตัวแปรดึงคอนเทนเนอร์ฟอร์มของบุคคลภายนอก
    const outsiderFields = document.getElementById('outsiderFields');
    // สร้างตัวแปรดึงคอนเทนเนอร์ฟอร์มของนักศึกษา
    const studentFields = document.getElementById('studentFields');
    // สร้างตัวแปรเก็บองค์ประกอบกล่องข้อความภายในฟอร์มบุคคลภายนอก (ถ้ามี)
    const inputsOutsider = outsiderFields ? outsiderFields.querySelectorAll('input') : [];
    // สร้างตัวแปรเก็บองค์ประกอบกล่องข้อความภายในฟอร์มนักศึกษา (ถ้ามี)
    const inputsStudent = studentFields ? studentFields.querySelectorAll('input, select') : [];
    // สร้างตัวแปรดึงองค์ประกอบแสดงข้อความแนะนำการตั้งชื่อบัญชีผู้ใช้
    const usernameHint = document.getElementById('usernameHint');

    // สร้างตัวแปรดึงองค์ประกอบของฟอร์มสมัครสมาชิกทั้งหมด
    const registerForm = document.getElementById('registerForm');
    // สร้างตัวแปรดึงองค์ประกอบกล่องข้อความรหัสผ่าน
    const password = document.getElementById('password');
    // สร้างตัวแปรดึงองค์ประกอบกล่องข้อความยืนยันรหัสผ่าน
    const confirmPassword = document.getElementById('confirm_password');
    // สร้างตัวแปรดึงองค์ประกอบสำหรับแสดงข้อผิดพลาดเมื่อรหัสผ่านไม่ตรงกัน
    const passwordError = document.getElementById('passwordError');

    // สร้างตัวแปรดึงกลุ่มปุ่มตัวเลือกระดับผู้ใช้ (member / committee)
    const userLevelRadios = document.querySelectorAll('input[name="user_level"]');
    // สร้างตัวแปรดึงคอนเทนเนอร์สำหรับเลือกตำแหน่งคณะกรรมการ
    const committeePositionField = document.getElementById('committeePositionField');

    // ตรวจสอบว่าหน้าเว็บมีส่วนของตัวเลือกคำนำหน้าหรือไม่
    if (prefixSelect) {
        // เพิ่ม Event Listener เพื่อจับเหตุการณ์เมื่อผู้ใช้เปลี่ยนค่าตัวเลือก
        prefixSelect.addEventListener('change', (e) => {
            // ตรวจสอบเงื่อนไขว่าผู้ใช้เลือกเป็น "อื่นๆ" หรือไม่
            if (e.target.value === 'other') {
                // ยกเลิกคลาส d-none เพื่อแสดงช่องกรอกคำนำหน้าแบบระบุเอง
                otherPrefixContainer.classList.remove('d-none');
                // กำหนดให้กล่องข้อความนี้จำเป็นต้องกรอก (required)
                otherPrefixInput.required = true;
            } else {
                // เพิ่มคลาส d-none เพื่อซ่อนช่องกรอกคำนำหน้าแบบระบุเอง
                otherPrefixContainer.classList.add('d-none');
                // ยกเลิกข้อบังคับในการกรอก
                otherPrefixInput.required = false;
                // ล้างค่าที่กรอกไว้เดิมให้เป็นค่าว่าง
                otherPrefixInput.value = '';
            }
        });
    }

    // สร้างฟังก์ชันสำหรับการสลับการแสดงผลประเภทผู้ใช้งาน
    function toggleUserType() {
        // ดึงองค์ประกอบของปุ่มตัวเลือก (Radio) ที่ถูกเลือกอยู่ปัจจุบัน
        const selectedType = document.querySelector('input[name="user_type"]:checked');
        // ตรวจสอบว่ามีการเลือกประเภทอยู่หรือไม่ หากไม่มีให้หยุดการทำงาน
        if (!selectedType) return;

        // ตรวจสอบเงื่อนไขว่าผู้ใช้เลือกประเภทเป็นนักศึกษา (student) หรือไม่
        if (selectedType.value === 'student') {
            // ซ่อนฟอร์มสำหรับบุคคลภายนอก
            if (outsiderFields) outsiderFields.classList.add('d-none');
            // แสดงฟอร์มสำหรับนักศึกษา
            if (studentFields) studentFields.classList.remove('d-none');
            // วนลูปเพื่อยกเลิกข้อบังคับและปิดการใช้งานช่องของบุคคลภายนอก
            inputsOutsider.forEach(input => { input.required = false; input.disabled = true; });
            // วนลูปเพื่อกำหนดข้อบังคับและเปิดการใช้งานช่องของนักศึกษา
            inputsStudent.forEach(input => { input.required = true; input.disabled = false; });
            // ตรวจสอบและแสดงข้อความแจ้งเตือนสำหรับชื่อบัญชีนักศึกษา
            if (usernameHint) {
                usernameHint.innerHTML = '<i class="fa-solid fa-info-circle text-warning"></i> สำหรับนักศึกษา: Username จะถูกเปลี่ยนเป็น<strong>รหัสนักศึกษา</strong>อัตโนมัติหลังสมัครสมาชิก';
                usernameHint.style.color = '#F57F17';
            }
        } else {
            // แสดงฟอร์มสำหรับบุคคลภายนอก
            if (outsiderFields) outsiderFields.classList.remove('d-none');
            // ซ่อนฟอร์มสำหรับนักศึกษา
            if (studentFields) studentFields.classList.add('d-none');
            // วนลูปเพื่อเปิดการใช้งานช่องของบุคคลภายนอก
            inputsOutsider.forEach(input => { input.disabled = false; });
            // วนลูปเพื่อยกเลิกข้อบังคับและปิดการใช้งานช่องของนักศึกษา
            inputsStudent.forEach(input => { input.required = false; input.disabled = true; });
            // ล้างข้อความแจ้งเตือนชื่อบัญชีออก
            if (usernameHint) {
                usernameHint.innerHTML = '';
            }
        }
        
        // Re-evaluate committee position requirement based on current user level
        if (typeof toggleUserLevel === 'function') {
            toggleUserLevel();
        }
    }

    // นำกลุ่มปุ่มตัวเลือกประเภทผู้ใช้งานมาวนลูป
    userTypeRadios.forEach(radio => {
        // กำหนด Event Listener เพื่อเรียกใช้ฟังก์ชันเมื่อมีการเปลี่ยนค่า
        radio.addEventListener('change', toggleUserType);
    });

    // หากมีปุ่มตัวเลือกอยู่ ให้ทำการเรียกใช้ฟังก์ชันจัดฟอร์มทันทีเมื่อโหลดหน้าเว็บ
    if (userTypeRadios.length > 0) toggleUserType();

    // สร้างฟังก์ชันจัดการการแสดงผลระดับผู้ใช้งาน (สมาชิกหรือกรรมการ)
    function toggleUserLevel() {
        // ดึงองค์ประกอบที่ผู้ใช้เลือกระดับปัจจุบัน
        const selectedLevel = document.querySelector('input[name="user_level"]:checked');
        const committeeSelect = document.getElementById('committee_position');
        // ตรวจสอบข้อมูลให้แน่ใจว่าเลือกได้ และมีช่องให้เลือกตำแหน่ง
        if (!selectedLevel || !committeePositionField) return;

        // ตรวจสอบว่าเป็นระดับคณะกรรมการ (committee) หรือไม่
        if (selectedLevel.value === 'committee') {
            // แสดงส่วนสำหรับเลือกตำแหน่งกรรมการ
            committeePositionField.classList.remove('d-none');
            if (committeeSelect) committeeSelect.required = true;
        } else {
            // ซ่อนส่วนสำหรับเลือกตำแหน่งกรรมการ
            committeePositionField.classList.add('d-none');
            if (committeeSelect) {
                committeeSelect.required = false;
                committeeSelect.value = '';
            }
        }
    }

    // ตรวจสอบว่ามีกลุ่มปุ่มตัวเลือกระดับผู้ใช้
    if (userLevelRadios.length > 0) {
        // วนลูปและเพิ่ม Event Listener เพื่อเรียกฟังก์ชันจัดการระดับผู้ใช้
        userLevelRadios.forEach(radio => {
            radio.addEventListener('change', toggleUserLevel);
        });
        // ทำการอัปเดตฟอร์มเมื่อหน้าเว็บโหลดทันที
        toggleUserLevel();
    }

    // ตรวจสอบว่ามีฟอร์มสมัครสมาชิกในหน้าปัจจุบันหรือไม่
    if (registerForm) {
        // เพิ่ม Event Listener เพื่อจับเหตุการณ์ตอนกดปุ่ม submit เพื่อยืนยันรหัสผ่าน
        registerForm.addEventListener('submit', (e) => {
            // ตรวจสอบเปรียบเทียบรหัสผ่านกับช่องยืนยันรหัสผ่าน
            if (password.value !== confirmPassword.value) {
                // สั่งยกเลิกการส่งฟอร์ม (Prevent Default)
                e.preventDefault();
                // แสดงข้อความแจ้งเตือนข้อผิดพลาด
                passwordError.classList.remove('d-none');
            } else {
                // ซ่อนข้อความแจ้งเตือนเมื่อรหัสผ่านตรงกัน
                passwordError.classList.add('d-none');
            }
        });
    }
});