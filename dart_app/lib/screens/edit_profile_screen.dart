import 'package:flutter/material.dart';
import '../constants/app_colors.dart';
import '../models/user_model.dart';
import '../services/api_service.dart';
import '../services/session_manager.dart';
import '../widgets/custom_button.dart';

// edit_profile_screen.dart
// หน้าจอแก้ไขข้อมูลส่วนตัวของสมาชิก
// สอดคล้องกับ edit_profile.php ของเว็บเดิม
class EditProfileScreen extends StatefulWidget {
  const EditProfileScreen({super.key});

  @override
  State<EditProfileScreen> createState() => _EditProfileScreenState();
}

class _EditProfileScreenState extends State<EditProfileScreen> {
  final _formKey = GlobalKey<FormState>();
  final _nameController = TextEditingController();
  final _phoneController = TextEditingController();
  final _emailController = TextEditingController();

  UserModel? _currentUser;
  bool _isLoading = true;
  bool _isSaving = false;

  @override
  void initState() {
    super.initState();
    _loadCurrentData();
  }

  @override
  void dispose() {
    _nameController.dispose();
    _phoneController.dispose();
    _emailController.dispose();
    super.dispose();
  }

  // โหลดข้อมูลเดิมมาใส่ใน TextFormField
  Future<void> _loadCurrentData() async {
    final user = await SessionManager.getUser();
    if (!mounted) return;

    if (user != null) {
      _nameController.text = user.name;
      _phoneController.text = user.phone ?? '';
      _emailController.text = user.email ?? '';
    }

    setState(() {
      _currentUser = user;
      _isLoading = false;
    });
  }

  // บันทึกการแก้ไขข้อมูลโปรไฟล์
  Future<void> _handleSave() async {
    if (!_formKey.currentState!.validate() || _currentUser == null) return;

    setState(() {
      _isSaving = true;
    });

    final newName = _nameController.text.trim();
    final newPhone = _phoneController.text.trim();
    final newEmail = _emailController.text.trim();

    final response = await ApiService.updateProfile(
      userId: _currentUser!.userId,
      name: newName,
      phone: newPhone,
      email: newEmail,
    );

    if (!mounted) return;

    setState(() {
      _isSaving = false;
    });

    if (response.isSuccess) {
      // อัปเดต Object ใน Local Session
      final updatedUser = UserModel(
        userId: _currentUser!.userId,
        username: _currentUser!.username,
        name: newName,
        role: _currentUser!.role,
        userType: _currentUser!.userType,
        studentId: _currentUser!.studentId,
        phone: newPhone,
        email: newEmail,
        points: _currentUser!.points,
        profilePicture: _currentUser!.profilePicture,
        faculty: _currentUser!.faculty,
        major: _currentUser!.major,
      );
      await SessionManager.saveUser(updatedUser);

      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(response.message), backgroundColor: AppColors.success),
      );
      Navigator.pop(context); // กลับหน้าโปรไฟล์
    } else {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(response.message), backgroundColor: AppColors.danger),
      );
    }
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
          'แก้ไขข้อมูลส่วนตัว',
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
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16),
        child: Form(
          key: _formKey,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Card(
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
                child: Padding(
                  padding: const EdgeInsets.all(16),
                  child: Column(
                    children: [
                      // ชื่อ-นามสกุล
                      TextFormField(
                        controller: _nameController,
                        decoration: InputDecoration(
                          labelText: 'ชื่อ - นามสกุล',
                          prefixIcon: const Icon(Icons.person_outline, color: AppColors.primary),
                          border: OutlineInputBorder(borderRadius: BorderRadius.circular(10)),
                        ),
                        validator: (value) {
                          if (value == null || value.trim().isEmpty) {
                            return 'กรุณากรอกชื่อ-นามสกุล';
                          }
                          return null;
                        },
                      ),
                      const SizedBox(height: 16),

                      // เบอร์โทรศัพท์
                      TextFormField(
                        controller: _phoneController,
                        keyboardType: TextInputType.phone,
                        decoration: InputDecoration(
                          labelText: 'เบอร์โทรศัพท์',
                          prefixIcon: const Icon(Icons.phone_outlined, color: AppColors.primary),
                          border: OutlineInputBorder(borderRadius: BorderRadius.circular(10)),
                        ),
                      ),
                      const SizedBox(height: 16),

                      // อีเมล
                      TextFormField(
                        controller: _emailController,
                        keyboardType: TextInputType.emailAddress,
                        decoration: InputDecoration(
                          labelText: 'อีเมล',
                          prefixIcon: const Icon(Icons.email_outlined, color: AppColors.primary),
                          border: OutlineInputBorder(borderRadius: BorderRadius.circular(10)),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
              const SizedBox(height: 24),

              CustomButton(
                text: 'บันทึกการเปลี่ยนแปลง',
                icon: Icons.save_outlined,
                onPressed: _handleSave,
                isLoading: _isSaving,
              ),
            ],
          ),
        ),
      ),
    );
  }
}
