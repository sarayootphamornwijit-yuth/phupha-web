import 'package:flutter/material.dart';
import '../constants/app_colors.dart';
import '../models/user_model.dart';
import '../screens/home_screen.dart';
import '../screens/activities_screen.dart';
import '../screens/joined_activities_screen.dart';
import '../screens/certificates_screen.dart';
import '../screens/self_checkin_screen.dart';

// web_drawer.dart
// เมนูด้านข้าง (Sidebar Drawer) ออกแบบตามแท็ก <aside class="sidebar"> ใน header.php ของเว็บ
// มีปุ่มปิด (X) ด้านบนขวา รายการเมนูไฮไลต์แถบเขียว และตัวคั่นแบ่งหมวดหมู่

class WebDrawer extends StatelessWidget {
  // หน้าปัจจุบันที่กำลังเปิดอยู่ เพื่อทำแถบไฮไลต์สีเขียว (เช่น 'home', 'volunteer', 'sport', 'byaniyan', 'joined', 'certificates')
  final String currentRoute;

  // ข้อมูลผู้ใช้ปัจจุบัน
  final UserModel? currentUser;

  const WebDrawer({
    super.key,
    required this.currentRoute,
    this.currentUser,
  });

  // ฟังก์ชันนำทางไปยังหน้าต่างๆ
  void _navigateTo(BuildContext context, Widget screen) {
    Navigator.pop(context); // ปิด Drawer ก่อน
    Navigator.push(
      context,
      MaterialPageRoute(builder: (context) => screen),
    );
  }

  // นำทางไปยังหน้ากิจกรรมพร้อมหมวดหมู่ที่เลือก
  void _navigateToCategory(BuildContext context, String category) {
    Navigator.pop(context);
    Navigator.push(
      context,
      MaterialPageRoute(
        builder: (context) => ActivitiesScreen(initialCategory: category),
      ),
    );
  }

  // แสดงกล่องข้อความข้อมูล เช่น เกี่ยวกับเรา หรือ ติดต่อเรา
  void _showInfoDialog(BuildContext context, String title, String content) {
    Navigator.pop(context);
    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(title, style: const TextStyle(fontWeight: FontWeight.bold)),
        content: Text(content),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('ตกลง', style: TextStyle(color: AppColors.primary)),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final isLoggedIn = currentUser != null;
    final isStudent = currentUser?.userType == 'student';

    return Drawer(
      backgroundColor: Colors.white,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.zero, // มุมตรงตามแทัก aside ของเว็บ
      ),
      child: SafeArea(
        child: Column(
          children: [
            // ส่วนหัวของ Drawer พร้อมปุ่มปิดกากบาท (X) ตาม #sidebarClose
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
              alignment: Alignment.centerRight,
              child: IconButton(
                icon: const Icon(Icons.close, color: Color(0xFF757575), size: 24),
                onPressed: () => Navigator.pop(context),
                tooltip: 'ปิดเมนู',
              ),
            ),

            // รายการเมนูนำทาง
            Expanded(
              child: ListView(
                padding: EdgeInsets.zero,
                children: [
                  // 1. หน้าแรก
                  _buildMenuItem(
                    context: context,
                    title: 'หน้าแรก',
                    isActive: currentRoute == 'home',
                    onTap: () {
                      Navigator.pop(context);
                      if (currentRoute != 'home') {
                        Navigator.pushAndRemoveUntil(
                          context,
                          MaterialPageRoute(builder: (context) => const HomeScreen()),
                          (route) => false,
                        );
                      }
                    },
                  ),

                  // 2. กิจกรรมจิตอาสา
                  _buildMenuItem(
                    context: context,
                    title: 'กิจกรรมจิตอาสา',
                    isActive: currentRoute == 'volunteer',
                    onTap: () => _navigateToCategory(context, 'volunteer'),
                  ),

                  // 3. กิจกรรมกีฬา
                  _buildMenuItem(
                    context: context,
                    title: 'กิจกรรมกีฬา',
                    isActive: currentRoute == 'sport',
                    onTap: () => _navigateToCategory(context, 'sport'),
                  ),

                  // 4. กิจกรรมบายเนียร์
                  _buildMenuItem(
                    context: context,
                    title: 'กิจกรรมบายเนียร์',
                    isActive: currentRoute == 'byaniyan',
                    onTap: () => _navigateToCategory(context, 'byaniyan'),
                  ),

                  // เมนูสำหรับผู้ที่เข้าสู่ระบบแล้ว
                  if (isLoggedIn) ...[
                    // 5. กิจกรรมที่เข้าร่วม
                    _buildMenuItem(
                      context: context,
                      title: 'กิจกรรมที่เข้าร่วม',
                      isActive: currentRoute == 'joined',
                      onTap: () => _navigateTo(context, const JoinedActivitiesScreen()),
                    ),

                    // 6. เกียรติบัตรของฉัน
                    _buildMenuItem(
                      context: context,
                      title: 'เกียรติบัตรของฉัน',
                      isActive: currentRoute == 'certificates',
                      onTap: () => _navigateTo(context, const CertificatesScreen()),
                    ),

                    // 7. เช็คอินกิจกรรม (สำหรับนักศึกษา)
                    if (isStudent)
                      _buildMenuItem(
                        context: context,
                        title: 'เช็คอินกิจกรรม',
                        isActive: currentRoute == 'self_checkin',
                        onTap: () => _navigateTo(context, const SelfCheckinScreen()),
                      ),
                  ],

                  // เส้นคั่นหมวดหมู่ตาม .nav-divider
                  const Padding(
                    padding: EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                    child: Divider(color: Color(0xFFE2E8F0), height: 1),
                  ),

                  // 8. กรรมการผู้นำรุ่น
                  _buildMenuItem(
                    context: context,
                    title: 'กรรมการผู้นำรุ่น',
                    isActive: currentRoute == 'committee_directory',
                    onTap: () => _showInfoDialog(
                      context,
                      'กรรมการผู้นำรุ่น',
                      'รายนามคณะกรรมการผู้นำรุ่น ชมรมเพื่อนภูผา มหาวิทยาลัยราชภัฏเชียงใหม่ ประจำปีการศึกษา 2569',
                    ),
                  ),

                  // 9. เกี่ยวกับเรา
                  _buildMenuItem(
                    context: context,
                    title: 'เกี่ยวกับเรา',
                    isActive: currentRoute == 'about',
                    onTap: () => _showInfoDialog(
                      context,
                      'เกี่ยวกับเรา',
                      'ชมรมเพื่อนภูผา มหาวิทยาลัยราชภัฏเชียงใหม่ มุ่งมั่นส่งเสริมกิจกรรมจิตอาสา พัฒนาศักยภาพนักศึกษา และสร้างประโยชน์สุขแก่สังคมและชุมชน',
                    ),
                  ),

                  // 10. ติดต่อเรา
                  _buildMenuItem(
                    context: context,
                    title: 'ติดต่อเรา',
                    isActive: currentRoute == 'contact',
                    onTap: () => _showInfoDialog(
                      context,
                      'ติดต่อเรา',
                      'ที่ตั้ง: อาคารกิจกรรมนักศึกษา มหาวิทยาลัยราชภัฏเชียงใหม่\nอีเมล: phuphaclub@cmru.ac.th',
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  // สร้างแถบเมนูแต่ละรายการ พร้อมขีดแนวตั้งสีเขียวด้านซ้ายเมื่อถูกเลือก
  Widget _buildMenuItem({
    required BuildContext context,
    required String title,
    required bool isActive,
    required VoidCallback onTap,
  }) {
    return InkWell(
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 14),
        decoration: BoxDecoration(
          color: isActive ? const Color(0xFFE8F5E9) : Colors.transparent, // สีพื้นหลังเขียวอ่อนตาม .nav-link.active
          border: Border(
            left: BorderSide(
              color: isActive ? AppColors.primary : Colors.transparent, // เส้นขอบซ้าย 3px ตาม border-left
              width: 3.5,
            ),
          ),
        ),
        child: Text(
          title,
          style: TextStyle(
            fontSize: 14,
            fontWeight: isActive ? FontWeight.bold : FontWeight.w500,
            color: Colors.black, // สีตัวอักษรดำตามเว็บ
          ),
        ),
      ),
    );
  }
}
