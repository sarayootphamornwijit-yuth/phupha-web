import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import '../constants/app_colors.dart';

// contact_screen.dart
// หน้าจอ "ติดต่อเรา" ถอดแบบดีไซน์จาก contact.php ของเว็บไซต์
class ContactScreen extends StatelessWidget {
  const ContactScreen({super.key});

  void _copyToClipboard(BuildContext context, String text, String label) {
    Clipboard.setData(ClipboardData(text: text));
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text('คัดลอก $label เรียบร้อยแล้ว: $text'),
        backgroundColor: AppColors.primary,
        behavior: SnackBarBehavior.floating,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
        duration: const Duration(seconds: 2),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF1F8E9), // พื้นหลังเขียวพาสเทลตามเว็บ
      appBar: AppBar(
        title: const Text(
          'ติดต่อเรา',
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
                  'ติดต่อเรา',
                  style: TextStyle(
                    fontSize: 26,
                    fontWeight: FontWeight.bold,
                    color: AppColors.primaryDark,
                  ),
                ),
                const SizedBox(height: 18),

                // การ์ดช่องทางการติดต่อ
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
                      // ไอคอนจดหมาย/อีเมล ตามไอคอน fa-envelope-open-text
                      Container(
                        padding: const EdgeInsets.all(18),
                        decoration: BoxDecoration(
                          color: AppColors.primaryPale,
                          shape: BoxShape.circle,
                        ),
                        child: const Icon(
                          Icons.mark_email_unread_outlined,
                          size: 54,
                          color: AppColors.primary,
                        ),
                      ),
                      const SizedBox(height: 20),

                      // หัวข้อการ์ด
                      const Text(
                        'ช่องทางการติดต่อ',
                        style: TextStyle(
                          fontSize: 22,
                          fontWeight: FontWeight.bold,
                          color: AppColors.primaryDark,
                        ),
                      ),
                      const SizedBox(height: 24),

                      // รายการช่องทางติดต่อ
                      _buildContactTile(
                        context: context,
                        icon: Icons.facebook,
                        iconColor: const Color(0xFF1877F2),
                        title: 'Facebook',
                        value: 'ชมรมเพื่อนภูผา cmru',
                        copyText: 'https://www.facebook.com/profile.php?id=100067001889511',
                      ),
                      const Divider(height: 24, color: Color(0xFFF1F5F9)),

                      _buildContactTile(
                        context: context,
                        icon: Icons.music_note,
                        iconColor: Colors.black87,
                        title: 'TikTok',
                        value: '@puean_phupa',
                        copyText: 'https://www.tiktok.com/@puean_phupa?_r=1&_t=ZS-98lCjiCv9oS',
                      ),
                      const Divider(height: 24, color: Color(0xFFF1F5F9)),

                      _buildContactTile(
                        context: context,
                        icon: Icons.phone_android,
                        iconColor: AppColors.primary,
                        title: 'เบอร์โทรศัพท์',
                        value: '093 171 0884',
                        copyText: '0931710884',
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

  Widget _buildContactTile({
    required BuildContext context,
    required IconData icon,
    required Color iconColor,
    required String title,
    required String value,
    required String copyText,
  }) {
    return InkWell(
      borderRadius: BorderRadius.circular(12),
      onTap: () => _copyToClipboard(context, copyText, title),
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
        child: Row(
          children: [
            Container(
              width: 44,
              height: 44,
              decoration: BoxDecoration(
                color: iconColor.withValues(alpha: 0.1),
                borderRadius: BorderRadius.circular(10),
              ),
              child: Icon(icon, color: iconColor, size: 26),
            ),
            const SizedBox(width: 16),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    title,
                    style: const TextStyle(
                      fontSize: 12,
                      color: Color(0xFF94A3B8),
                      fontWeight: FontWeight.w500,
                    ),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    value,
                    style: const TextStyle(
                      fontSize: 15,
                      fontWeight: FontWeight.w600,
                      color: AppColors.textMain,
                    ),
                  ),
                ],
              ),
            ),
            IconButton(
              icon: const Icon(Icons.copy_rounded, size: 18, color: AppColors.primary),
              onPressed: () => _copyToClipboard(context, copyText, title),
              tooltip: 'คัดลอก',
            ),
          ],
        ),
      ),
    );
  }
}
