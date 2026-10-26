import 'package:flutter/material.dart';
import '../constants/app_colors.dart';
import '../models/user_model.dart';
import '../services/session_manager.dart';
import 'login_screen.dart';
import 'edit_profile_screen.dart';
import 'certificates_screen.dart';

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
    final user = await SessionManager.getUser();
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
            child: const Text('ออกจากระบบ', style: TextStyle(color: Colors.white)),
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
        body: Center(child: CircularProgressIndicator(color: AppColors.primary)),
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
                MaterialPageRoute(builder: (context) => const EditProfileScreen()),
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
                    child: const Icon(Icons.person, size: 48, color: AppColors.primary),
                  ),
                  const SizedBox(height: 12),
                  Text(
                    _currentUser?.name ?? 'สมาชิกชมรม',
                    style: const TextStyle(fontSize: 18, fontWeight: FontWeight.bold, color: AppColors.textMain),
                  ),
                  const SizedBox(height: 4),
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
                    decoration: BoxDecoration(
                      color: AppColors.primaryPale,
                      borderRadius: BorderRadius.circular(20),
                    ),
                    child: Text(
                      _currentUser?.role == 'admin'
                          ? 'ผู้ดูแลระบบ (Admin)'
                          : _currentUser?.role == 'committee'
                              ? 'กรรมการชมรม'
                              : 'สมาชิกชมรม',
                      style: const TextStyle(color: AppColors.primaryDark, fontSize: 12, fontWeight: FontWeight.bold),
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
                  _buildProfileRow(Icons.badge_outlined, 'รหัสนักศึกษา / ชื่อผู้ใช้', _currentUser?.studentId ?? _currentUser?.username ?? '-'),
                  const Divider(height: 20, color: AppColors.cardBorder),
                  _buildProfileRow(Icons.phone_outlined, 'เบอร์โทรศัพท์', _currentUser?.phone ?? '-'),
                  const Divider(height: 20, color: AppColors.cardBorder),
                  _buildProfileRow(Icons.email_outlined, 'อีเมล', _currentUser?.email ?? '-'),
                  const Divider(height: 20, color: AppColors.cardBorder),
                  _buildProfileRow(Icons.stars_outlined, 'คะแนนจิตอาสาสะสม', '${_currentUser?.points ?? 0} แต้ม', valueColor: AppColors.warning),
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
                  ListTile(
                    leading: const Icon(Icons.workspace_premium, color: Colors.amber),
                    title: const Text('เกียรติบัตรและคะแนนสะสม', style: TextStyle(fontSize: 14, fontWeight: FontWeight.w600)),
                    trailing: const Icon(Icons.chevron_right),
                    onTap: () {
                      Navigator.push(
                        context,
                        MaterialPageRoute(builder: (context) => const CertificatesScreen()),
                      );
                    },
                  ),
                  const Divider(height: 1, indent: 56),
                  ListTile(
                    leading: const Icon(Icons.info_outline, color: AppColors.primary),
                    title: const Text('เกี่ยวกับชมรมเพื่อนภูผา', style: TextStyle(fontSize: 14, fontWeight: FontWeight.w600)),
                    trailing: const Icon(Icons.chevron_right),
                    onTap: () {
                      showAboutDialog(
                        context: context,
                        applicationName: 'ชมรมเพื่อนภูผา',
                        applicationVersion: '1.0.0',
                        applicationLegalese: 'มหาวิทยาลัยราชภัฏเชียงใหม่',
                        children: [
                          const SizedBox(height: 12),
                          const Text('ระบบบริหารจัดการกิจกรรมและจิตอาสา ชมรมเพื่อนภูผา มหาวิทยาลัยราชภัฏเชียงใหม่'),
                        ],
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
              label: const Text('ออกจากระบบ', style: TextStyle(color: AppColors.danger, fontWeight: FontWeight.bold)),
              style: OutlinedButton.styleFrom(
                side: const BorderSide(color: AppColors.danger),
                minimumSize: const Size.fromHeight(48),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
              ),
              onPressed: _handleLogout,
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildProfileRow(IconData icon, String label, String value, {Color? valueColor}) {
    return Row(
      children: [
        Icon(icon, size: 20, color: AppColors.primary),
        const SizedBox(width: 12),
        Text(label, style: const TextStyle(fontSize: 13, color: AppColors.textMuted)),
        const Spacer(),
        Text(
          value,
          style: TextStyle(fontSize: 13, fontWeight: FontWeight.w600, color: valueColor ?? AppColors.textMain),
        ),
      ],
    );
  }
}
