import 'package:flutter/material.dart';
import '../constants/app_colors.dart';
import '../constants/app_config.dart';

// about_screen.dart
// หน้าจอ "เกี่ยวกับเรา" ถอดแบบดีไซน์จาก about.php ของเว็บไซต์
class AboutScreen extends StatelessWidget {
  const AboutScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF1F8E9), // พื้นหลังสีเขียวพาสเทลตามเว็บ
      appBar: AppBar(
        title: const Text(
          'เกี่ยวกับเรา',
          style: TextStyle(
            color: AppColors.primaryDark,
            fontWeight: FontWeight.bold,
            fontSize: 20,
          ),
        ),
        backgroundColor: Colors.white,
        elevation: 0.5,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_ios_new, color: AppColors.primaryDark, size: 20),
          onPressed: () => Navigator.pop(context),
        ),
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 24),
        child: Center(
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 600),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.center,
              children: [
                // หัวข้อหลัก
                const Text(
                  'เกี่ยวกับเรา',
                  style: TextStyle(
                    fontSize: 26,
                    fontWeight: FontWeight.bold,
                    color: AppColors.primaryDark,
                  ),
                ),
                const SizedBox(height: 18),

                // การ์ดแสดงข้อมูลชมรม
                Container(
                  width: double.infinity,
                  padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 32),
                  decoration: BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.circular(20),
                    border: Border.all(color: const Color(0xFFE2E8F0)),
                    boxShadow: [
                      BoxShadow(
                        color: Colors.black.withValues(alpha: 0.04),
                        blurRadius: 15,
                        offset: const Offset(0, 4),
                      ),
                    ],
                  ),
                  child: Column(
                    children: [
                      // โลโก้ชมรม
                      ClipRRect(
                        borderRadius: BorderRadius.circular(60),
                        child: Image.asset(
                          'assets/images/logo.png',
                          width: 120,
                          height: 120,
                          fit: BoxFit.contain,
                          errorBuilder: (context, error, stackTrace) {
                            return Image.network(
                              '${AppConfig.baseUrl}/assets/images/logo.png',
                              width: 120,
                              height: 120,
                              fit: BoxFit.contain,
                              errorBuilder: (_, __, ___) => Container(
                                width: 120,
                                height: 120,
                                decoration: BoxDecoration(
                                  color: AppColors.primaryPale,
                                  shape: BoxShape.circle,
                                ),
                                child: const Icon(
                                  Icons.forest,
                                  size: 60,
                                  color: AppColors.primary,
                                ),
                              ),
                            );
                          },
                        ),
                      ),
                      const SizedBox(height: 20),

                      // ชื่อชมรม
                      const Text(
                        'ชมรมเพื่อนภูผา',
                        style: TextStyle(
                          fontSize: 22,
                          fontWeight: FontWeight.bold,
                          color: AppColors.primaryDark,
                        ),
                      ),
                      const SizedBox(height: 16),

                      // รายละเอียดเกี่ยวกับชมรม ตรงตาม about.php
                      const Text(
                        'เราคือองค์กรที่มุ่งเน้นการสร้างสรรค์กิจกรรมจิตอาสาเพื่อสังคม '
                        'ไม่ว่าจะเป็นการพัฒนาชุมชน, การอนุรักษ์สิ่งแวดล้อม, และการศึกษา\n\n'
                        'แพลตฟอร์มนี้สร้างขึ้นเพื่ออำนวยความสะดวกในการจัดการสมาชิก '
                        'การลงทะเบียนกิจกรรม และการสะสมแต้มจิตอาสา\n\n'
                        'เพื่อให้ทุกคนสามารถเข้าถึงและร่วมเป็นส่วนหนึ่งในการทำความดีได้อย่างง่ายดาย',
                        textAlign: TextAlign.center,
                        style: TextStyle(
                          fontSize: 14.5,
                          height: 1.8,
                          color: Color(0xFF64748B),
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
