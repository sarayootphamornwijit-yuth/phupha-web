import 'package:flutter/material.dart';
import '../constants/app_colors.dart';
import '../models/user_model.dart';
import '../services/session_manager.dart';
import '../services/api_service.dart';
import '../widgets/profile_image.dart';
import 'login_screen.dart';
import 'edit_profile_screen.dart';
import 'certificates_screen.dart';
import 'about_screen.dart';
import 'advisor_approval_screen.dart';
import 'advisor_members_screen.dart';
import 'advisor_news_screen.dart';
import 'qr_scanner_screen.dart';
import 'erp_activities_screen.dart';

// profile_screen.dart
// หน้าจอโปรไฟล์สมาชิกชมรมเพื่อนภูผา
// สอดคล้องกับ profile.php ของเว็บเดิม
class ProfileScreen extends StatefulWidget {
  const ProfileScreen({super.key});

  @override
  State<ProfileScreen> createState() => _ProfileScreenState();
}

class _ProfileScreenState extends State<ProfileScreen> {
  UserModel? _currentUser;
  bool _isLoading = true;

  @override
  void initState() {
    super.initState();
    _loadUserProfile();
  }

  // โหลดข้อมูลโปรไฟล์จาก Local Session
  Future<void> _loadUserProfile() async {
    var user = await SessionManager.getUser();
    if (user != null) {
      final response = await ApiService.getProfile();
      if (response.isSuccess && response.data != null) {
        user = response.data;
        await SessionManager.saveUser(user!);
      } else {
        user = await SessionManager.getUser();
      }
    }
    if (!mounted) return;

    setState(() {
      _currentUser = user;
      _isLoading = false;
    });
  }

  // ดำเนินการออกจากระบบ
  Future<void> _handleLogout() async {
    final confirm = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('ออกจากระบบ'),
        content: const Text('คุณต้องการออกจากระบบชมรมเพื่อนภูผาใช่หรือไม่?'),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('ยกเลิก'),
          ),
          ElevatedButton(
            style: ElevatedButton.styleFrom(backgroundColor: AppColors.danger),
            onPressed: () => Navigator.pop(context, true),
            child: const Text(
              'ออกจากระบบ',
              style: TextStyle(color: Colors.white),
            ),
          ),
        ],
      ),
    );

    if (confirm != true) return;

    await SessionManager.logout();

    if (!mounted) return;

    Navigator.pushAndRemoveUntil(
      context,
      MaterialPageRoute(builder: (context) => const LoginScreen()),
      (route) => false,
    );
  }

  @override
  Widget build(BuildContext context) {
    if (_isLoading) {
      return const Scaffold(
        backgroundColor: AppColors.bgMain,
        body: Center(
          child: CircularProgressIndicator(color: AppColors.primary),
        ),
      );
    }

    // หากยังไม่ได้เข้าสู่ระบบ แสดงหน้าเชิญชวนให้เข้าสู่ระบบหรือสมัครสมาชิก
    if (_currentUser == null) {
      return Scaffold(
        backgroundColor: AppColors.bgMain,
        appBar: AppBar(
          title: const Text(
            'โปรไฟล์ของฉัน',
            style: TextStyle(
              fontSize: 17,
              fontWeight: FontWeight.bold,
              color: AppColors.primaryDark,
            ),
          ),
          backgroundColor: Colors.white,
          elevation: 0,
          scrolledUnderElevation: 0,
          centerTitle: true,
          automaticallyImplyLeading: false,
        ),
        body: Center(
          child: Padding(
            padding: const EdgeInsets.all(24),
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Container(
                  padding: const EdgeInsets.all(24),
                  decoration: const BoxDecoration(
                    color: Colors.white,
                    shape: BoxShape.circle,
                  ),
                  child: const Icon(
                    Icons.person_outline,
                    size: 64,
                    color: AppColors.textMuted,
                  ),
                ),
                const SizedBox(height: 20),
                const Text(
                  'ยังไม่ได้เข้าสู่ระบบ',
                  style: TextStyle(
                    fontSize: 20,
                    fontWeight: FontWeight.bold,
                    color: AppColors.textMain,
                  ),
                ),
                const SizedBox(height: 8),
                const Text(
                  'กรุณาเข้าสู่ระบบเพื่อดูข้อมูลส่วนตัว ประวัติการเข้าร่วมกิจกรรม และเกียรติบัตรของคุณ',
                  textAlign: TextAlign.center,
                  style: TextStyle(fontSize: 14, color: AppColors.textMuted, height: 1.5),
                ),
                const SizedBox(height: 28),
                SizedBox(
                  width: double.infinity,
                  child: ElevatedButton(
                    onPressed: () {
                      Navigator.push(
                        context,
                        MaterialPageRoute(builder: (context) => const LoginScreen()),
                      ).then((_) => _loadUserProfile());
                    },
                    style: ElevatedButton.styleFrom(
                      backgroundColor: AppColors.primary,
                      foregroundColor: Colors.white,
                      padding: const EdgeInsets.symmetric(vertical: 14),
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(10),
                      ),
                      elevation: 0,
                    ),
                    child: const Text(
                      'เข้าสู่ระบบ / สมัครสมาชิก',
                      style: TextStyle(fontSize: 15, fontWeight: FontWeight.bold),
                    ),
                  ),
                ),
              ],
            ),
          ),
        ),
      );
    }

    return Scaffold(
      backgroundColor: AppColors.bgMain,
      appBar: AppBar(
        title: const Text(
          'โปรไฟล์ของฉัน',
          style: TextStyle(
            fontSize: 17,
            fontWeight: FontWeight.bold,
            color: AppColors.primaryDark,
          ),
        ),
        backgroundColor: Colors.white,
        elevation: 0,
        scrolledUnderElevation: 0,
        centerTitle: true,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back, color: AppColors.primaryDark),
          onPressed: () => Navigator.pop(context),
        ),
        actions: [
          IconButton(
            icon: const Icon(Icons.edit_outlined, color: AppColors.primaryDark),
            tooltip: 'แก้ไขข้อมูล',
            onPressed: () {
              Navigator.push(
                context,
                MaterialPageRoute(
                  builder: (context) => const EditProfileScreen(),
                ),
              ).then((_) => _loadUserProfile());
            },
          ),
        ],
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16),
        child: Column(
          children: [
            // ส่วนหัวโปรไฟล์ (Avatar & Name)
            Container(
              padding: const EdgeInsets.all(20),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(18),
                border: Border.all(color: AppColors.cardBorder),
              ),
              child: Column(
                children: [
                  CircleAvatar(
                    radius: 40,
                    backgroundColor: AppColors.primaryPale,
                    child: ProfileImage(
                      path: _currentUser?.profilePicture,
                      size: 80,
                      fallback: const Icon(
                        Icons.person,
                        size: 48,
                        color: AppColors.primary,
                      ),
                    ),
                  ),
                  const SizedBox(height: 12),
                  Text(
                    _currentUser?.name ?? 'สมาชิกชมรม',
                    style: const TextStyle(
                      fontSize: 18,
                      fontWeight: FontWeight.bold,
                      color: AppColors.textMain,
                    ),
                  ),
                  const SizedBox(height: 4),
                  Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 14,
                      vertical: 5,
                    ),
                    decoration: BoxDecoration(
                      color: _currentUser?.role == 'advisor'
                          ? AppColors.advisorBadgeBg
                          : AppColors.primaryPale,
                      borderRadius: BorderRadius.circular(20),
                    ),
                    child: Text(
                      _currentUser?.role == 'admin'
                          ? 'ผู้ดูแลระบบ (Admin)'
                          : _currentUser?.role == 'advisor'
                          ? 'อาจารย์ที่ปรึกษา'
                          : _currentUser?.role == 'committee'
                          ? 'กรรมการชมรม'
                          : 'สมาชิกชมรม',
                      style: TextStyle(
                        color: _currentUser?.role == 'advisor'
                            ? AppColors.advisorBadgeText
                            : AppColors.primaryDark,
                        fontSize: 12,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 16),

            // กล่องข้อมูลส่วนตัว
            Container(
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(18),
                border: Border.all(color: AppColors.cardBorder),
              ),
              child: Column(
                children: [
                  _buildProfileRow(
                    Icons.badge_outlined,
                    _currentUser?.role == 'advisor'
                        ? 'ตำแหน่ง / สถานะ'
                        : 'รหัสนักศึกษา / ชื่อผู้ใช้',
                    _currentUser?.role == 'advisor'
                        ? 'อาจารย์ที่ปรึกษา'
                        : (_currentUser?.studentId ??
                              _currentUser?.username ??
                              '-'),
                  ),
                  if (_currentUser?.role == 'advisor') ...[
                    const Divider(height: 20, color: AppColors.cardBorder),
                    _buildProfileRow(
                      Icons.school_outlined,
                      'คณะที่สังกัด',
                      _currentUser?.faculty ?? '-',
                    ),
                  ],
                  const Divider(height: 20, color: AppColors.cardBorder),
                  _buildProfileRow(
                    Icons.phone_outlined,
                    'เบอร์โทรศัพท์',
                    _currentUser?.phone ?? 'ไม่ได้ระบุเบอร์โทร',
                  ),
                  const Divider(height: 20, color: AppColors.cardBorder),
                  _buildProfileRow(
                    Icons.email_outlined,
                    'อีเมล',
                    _currentUser?.email ?? '-',
                  ),
                  const Divider(height: 20, color: AppColors.cardBorder),
                  _buildProfileRow(
                    Icons.stars_outlined,
                    'คะแนนจิตอาสาสะสม',
                    '${_currentUser?.points ?? 0} แต้ม',
                    valueColor: AppColors.warning,
                  ),
                ],
              ),
            ),
            const SizedBox(height: 16),

            // เมนูทางลัด
            Container(
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(18),
                border: Border.all(color: AppColors.cardBorder),
              ),
              child: Column(
                children: [
                  // เมนูเฉพาะสำหรับอาจารย์ที่ปรึกษา
                  if (_currentUser?.role == 'advisor') ...[
                    ListTile(
                      leading: const Icon(
                        Icons.people_alt_outlined,
                        color: Color(0xFF6D28D9),
                      ),
                      title: const Text(
                        'ดูข้อมูลสมาชิกชมรม',
                        style: TextStyle(
                          fontSize: 14,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                      trailing: const Icon(Icons.chevron_right),
                      onTap: () {
                        Navigator.push(
                          context,
                          MaterialPageRoute(
                            builder: (context) => const AdvisorMembersScreen(),
                          ),
                        );
                      },
                    ),
                    const Divider(height: 1, indent: 56),
                    ListTile(
                      leading: const Icon(
                        Icons.fact_check_outlined,
                        color: AppColors.primary,
                      ),
                      title: const Text(
                        'อนุมัติโครงการและกิจกรรม',
                        style: TextStyle(
                          fontSize: 14,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                      trailing: const Icon(Icons.chevron_right),
                      onTap: () {
                        Navigator.push(
                          context,
                          MaterialPageRoute(
                            builder: (context) => const AdvisorApprovalScreen(),
                          ),
                        );
                      },
                    ),
                    const Divider(height: 1, indent: 56),
                    ListTile(
                      leading: const Icon(
                        Icons.campaign_outlined,
                        color: Color(0xFFE65100),
                      ),
                      title: const Text(
                        'จัดการข่าวประชาสัมพันธ์',
                        style: TextStyle(
                          fontSize: 14,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                      trailing: const Icon(Icons.chevron_right),
                      onTap: () {
                        Navigator.push(
                          context,
                          MaterialPageRoute(
                            builder: (context) => const AdvisorNewsScreen(),
                          ),
                        );
                      },
                    ),
                    const Divider(height: 1, indent: 56),
                  ],

                  // เมนูทางลัดสำหรับคณะกรรมการ
                  if (_currentUser?.role == 'committee' ||
                      _currentUser?.role == 'leader') ...[
                    if (_currentUser?.canManage('qr_scanner') == true)
                      ListTile(
                        leading: const Icon(
                          Icons.qr_code_scanner,
                          color: AppColors.primary,
                        ),
                        title: const Text(
                          'สแกนเช็คอินกิจกรรม',
                          style: TextStyle(
                            fontSize: 14,
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                        trailing: const Icon(Icons.chevron_right),
                        onTap: () {
                          Navigator.push(
                            context,
                            MaterialPageRoute(
                              builder: (context) => const QrScannerScreen(),
                            ),
                          );
                        },
                      ),
                    const Divider(height: 1, indent: 56),
                    if (_currentUser?.canManage('activities') == true)
                      ListTile(
                        leading: const Icon(
                          Icons.event_available,
                          color: AppColors.primary,
                        ),
                        title: const Text(
                          'จัดการกิจกรรม (ERP)',
                          style: TextStyle(
                            fontSize: 14,
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                        trailing: const Icon(Icons.chevron_right),
                        onTap: () {
                          Navigator.push(
                            context,
                            MaterialPageRoute(
                              builder: (context) => const ErpActivitiesScreen(),
                            ),
                          );
                        },
                      ),
                    const Divider(height: 1, indent: 56),
                  ],

                  if (_currentUser?.role != 'advisor') ...[
                    ListTile(
                      leading: const Icon(
                        Icons.workspace_premium,
                        color: Colors.amber,
                      ),
                      title: const Text(
                        'เกียรติบัตรและคะแนนสะสม',
                        style: TextStyle(
                          fontSize: 14,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                      trailing: const Icon(Icons.chevron_right),
                      onTap: () {
                        Navigator.push(
                          context,
                          MaterialPageRoute(
                            builder: (context) => const CertificatesScreen(),
                          ),
                        );
                      },
                    ),
                    const Divider(height: 1, indent: 56),
                  ],

                  ListTile(
                    leading: const Icon(
                      Icons.info_outline,
                      color: AppColors.primary,
                    ),
                    title: const Text(
                      'เกี่ยวกับชมรมเพื่อนภูผา',
                      style: TextStyle(
                        fontSize: 14,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                    trailing: const Icon(Icons.chevron_right),
                    onTap: () {
                      Navigator.push(
                        context,
                        MaterialPageRoute(
                          builder: (context) => const AboutScreen(),
                        ),
                      );
                    },
                  ),
                ],
              ),
            ),

            const SizedBox(height: 24),

            // ปุ่มออกจากระบบ
            OutlinedButton.icon(
              icon: const Icon(Icons.logout, color: AppColors.danger),
              label: const Text(
                'ออกจากระบบ',
                style: TextStyle(
                  color: AppColors.danger,
                  fontWeight: FontWeight.bold,
                ),
              ),
              style: OutlinedButton.styleFrom(
                side: const BorderSide(color: AppColors.danger),
                minimumSize: const Size.fromHeight(48),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(12),
                ),
              ),
              onPressed: _handleLogout,
            ),
          ],
        ),
      ),
    );
  }

  //ข้อความยาวให้ขึ้นบรรทัดได้ โดยยังคงหัวข้อซ้ายและข้อมูลขวาแบบเดิม
  Widget _buildProfileRow(
    IconData icon,
    String label,
    String value, {
    Color? valueColor,
  }) {
    return Row(
      children: [
        Icon(icon, size: 20, color: AppColors.primary),
        const SizedBox(width: 12),
        Expanded(
          flex: 4,
          child: Text(
            label,
            style: const TextStyle(fontSize: 13, color: AppColors.textMuted),
          ),
        ),
        const SizedBox(width: 12),
        Expanded(
          flex: 5,
          child: Text(
            value,
            textAlign: TextAlign.right,
            style: TextStyle(
              fontSize: 13,
              fontWeight: FontWeight.w600,
              color: valueColor ?? AppColors.textMain,
            ),
          ),
        ),
      ],
    );
  }
}
