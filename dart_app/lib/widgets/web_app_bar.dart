import 'package:flutter/material.dart';
import '../constants/app_colors.dart';
import '../models/user_model.dart';
import '../services/session_manager.dart';
import '../screens/login_screen.dart';
import '../screens/profile_screen.dart';

// web_app_bar.dart
// แถบหัวด้านบนของแอพพลิเคชัน ออกแบบตาม mobile-header ใน header.php ของระบบเว็บ
// มีไอคอนเปิดเมนูทางซ้าย, ชื่อชมรมตรงกลาง, และ Avatar/ปุ่มเข้าสู่ระบบทางขวา

class WebAppBar extends StatelessWidget implements PreferredSizeWidget {
  // ข้อมูลผู้ใช้งานปัจจุบัน (ถ้ามี)
  final UserModel? currentUser;

  // Callback เมื่อมีการเปลี่ยนแปลงสถานะล็อกอิน (เช่น กดออกจากระบบ)
  final VoidCallback? onAuthStateChanged;

  const WebAppBar({
    super.key,
    this.currentUser,
    this.onAuthStateChanged,
  });

  @override
  Size get preferredSize => const Size.fromHeight(56.0);

  // ฟังก์ชันช่วยดึงตัวอักษรแรกของชื่อผู้ใช้สำหรับแสดงในวงกลม Avatar
  String _getUserInitial(String name) {
    if (name.trim().isEmpty) return 'U';
    // ดึงตัวอักษรแรก (รองรับภาษาไทย)
    return name.trim().characters.first;
  }

  // ฟังก์ชันออกจากระบบ
  Future<void> _handleLogout(BuildContext context) async {
    await SessionManager.clear();
    if (!context.mounted) return;

    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(
        content: Text('ออกจากระบบเรียบร้อยแล้ว'),
        backgroundColor: AppColors.info,
        duration: Duration(seconds: 2),
      ),
    );

    if (onAuthStateChanged != null) {
      onAuthStateChanged!();
    }
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: const BoxDecoration(
        color: Colors.white,
        border: Border(
          bottom: BorderSide(
            color: Color(0xFFE2E8F0), // เส้นขอบล่างบางๆ ตาม variables.css (--border-color)
            width: 1.0,
          ),
        ),
      ),
      child: AppBar(
        backgroundColor: Colors.white,
        elevation: 0,
        scrolledUnderElevation: 0,
        centerTitle: true,

        // ปุ่มแฮมเบอร์เกอร์เปิด Sidebar ทางซ้าย
        leading: Builder(
          builder: (scaffoldContext) {
            return IconButton(
              icon: const Icon(
                Icons.menu,
                color: AppColors.primaryDark, // สีเขียวเข้ม #1B5E20
                size: 24,
              ),
              onPressed: () {
                Scaffold.of(scaffoldContext).openDrawer();
              },
              tooltip: 'เปิดเมนู',
            );
          },
        ),

        // ชื่อชมรมตรงกลาง
        title: const Text(
          'ชมรมเพื่อนภูผา',
          style: TextStyle(
            fontSize: 17,
            fontWeight: FontWeight.bold,
            color: AppColors.primaryDark, // สีเขียวเข้ม #1B5E20 ตาม mobile-header-brand
            letterSpacing: 0.3,
          ),
        ),

        // เมนูผู้ใช้ทางขวา (Avatar พร้อม Popup Menu หรือปุ่มเข้าสู่ระบบ)
        actions: [
          Padding(
            padding: const EdgeInsets.only(right: 12),
            child: currentUser != null
                ? _buildUserAvatarMenu(context)
                : _buildLoginButton(context),
          ),
        ],
      ),
    );
  }

  // สร้างปุ่ม Avatar วงกลมสีเทา พร้อม Popup Menu เมื่อกด
  Widget _buildUserAvatarMenu(BuildContext context) {
    final initialChar = _getUserInitial(currentUser?.name ?? '');

    return PopupMenuButton<String>(
      offset: const Offset(0, 44),
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(8),
        side: const BorderSide(color: Color(0xFFEEEEEE)),
      ),
      color: Colors.white,
      elevation: 4,
      onSelected: (value) {
        if (value == 'profile') {
          Navigator.push(
            context,
            MaterialPageRoute(builder: (context) => const ProfileScreen()),
          ).then((_) {
            if (onAuthStateChanged != null) {
              onAuthStateChanged!();
            }
          });
        } else if (value == 'logout') {
          _handleLogout(context);
        }
      },
      itemBuilder: (BuildContext context) => [
        // เมนูโปรไฟล์
        const PopupMenuItem<String>(
          value: 'profile',
          height: 40,
          child: Row(
            children: [
              Icon(Icons.person_outline, size: 18, color: Color(0xFF555555)),
              SizedBox(width: 12),
              Text(
                'โปรไฟล์',
                style: TextStyle(fontSize: 14, color: Color(0xFF333333)),
              ),
            ],
          ),
        ),
        // เส้นคั่น
        const PopupMenuDivider(height: 1),
        // เมนูออกจากระบบ
        const PopupMenuItem<String>(
          value: 'logout',
          height: 40,
          child: Row(
            children: [
              Icon(Icons.logout, size: 18, color: Color(0xFF555555)),
              SizedBox(width: 12),
              Text(
                'ออกจากระบบ',
                style: TextStyle(fontSize: 14, color: Color(0xFF333333)),
              ),
            ],
          ),
        ),
      ],
      // ตัวปุ่ม Avatar ทรงกลมสีเทา
      child: Container(
        width: 34,
        height: 34,
        decoration: const BoxDecoration(
          color: Color(0xFF9E9E9E), // สีพื้นหลังเทาตาม mobile-avatar-btn บนเว็บ
          shape: BoxShape.circle,
        ),
        alignment: Alignment.center,
        child: Text(
          initialChar,
          style: const TextStyle(
            color: Colors.white,
            fontSize: 15,
            fontWeight: FontWeight.bold,
          ),
        ),
      ),
    );
  }

  // สร้างปุ่มสีเขียว "เข้าสู่ระบบ" สำหรับผู้ที่ยังไม่ได้ล็อกอิน
  Widget _buildLoginButton(BuildContext context) {
    return ElevatedButton(
      onPressed: () {
        Navigator.push(
          context,
          MaterialPageRoute(builder: (context) => const LoginScreen()),
        ).then((_) {
          if (onAuthStateChanged != null) {
            onAuthStateChanged!();
          }
        });
      },
      style: ElevatedButton.styleFrom(
        backgroundColor: AppColors.primary, // สีเขียว #2E7D32
        foregroundColor: Colors.white,
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 6),
        minimumSize: Size.zero,
        tapTargetSize: MaterialTapTargetSize.shrinkWrap,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(6), // ความโค้งมน 6px ตาม mobile-login-btn
        ),
        elevation: 0,
      ),
      child: const Text(
        'เข้าสู่ระบบ',
        style: TextStyle(fontSize: 13, fontWeight: FontWeight.bold),
      ),
    );
  }
}
