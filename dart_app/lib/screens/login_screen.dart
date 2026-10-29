import 'package:flutter/material.dart';
import '../constants/app_colors.dart';
import '../services/api_service.dart';
import '../services/session_manager.dart';
import 'main_navigation.dart';
import 'register_screen.dart';

// login_screen.dart
// หน้าจอเข้าสู่ระบบ (Login Screen)
// ออกแบบตรงตามหน้า login.php บนระบบเว็บไซต์
// พื้นหลังสีเขียวอ่อน #F1F8E9, การ์ดสีขาวขอบมน, ปุ่มสีเขียวหลัก

class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key});

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  // GlobalKey สำหรับฟอร์มตรวจสอบข้อมูล
  final _formKey = GlobalKey<FormState>();

  // ตัวควบคุมช่องกรอกชื่อผู้ใช้และรหัสผ่าน
  final _usernameController = TextEditingController();
  final _passwordController = TextEditingController();

  // สถานะเปิด/ปิดการซ่อนรหัสผ่าน
  bool _obscurePassword = true;

  // สถานะกำลังส่งข้อมูลเข้าระบบ
  bool _isLoading = false;

  // ข้อความแจ้งเตือน Error
  String? _errorMessage;

  @override
  void dispose() {
    _usernameController.dispose();
    _passwordController.dispose();
    super.dispose();
  }

  // ดำเนินการเข้าสู่ระบบ
  Future<void> _handleLogin() async {
    setState(() {
      _errorMessage = null;
    });

    if (!_formKey.currentState!.validate()) {
      return;
    }

    setState(() {
      _isLoading = true;
    });

    final username = _usernameController.text.trim();
    final password = _passwordController.text;

    // เรียก API เข้าสู่ระบบ
    final response = await ApiService.login(username, password);

    if (!mounted) return;

    setState(() {
      _isLoading = false;
    });

    if (response.isSuccess && response.data != null) {
      // บันทึก Session ข้อมูลผู้ใช้
      await SessionManager.saveUser(response.data!);

      if (!mounted) return;

      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(response.message),
          backgroundColor: AppColors.success,
          duration: const Duration(seconds: 2),
        ),
      );

      // นำทางไปยังหน้าหลักของระบบ
      Navigator.pushAndRemoveUntil(
        context,
        MaterialPageRoute(builder: (context) => const MainNavigation()),
        (route) => false,
      );
    } else {
      // แสดงข้อความ error ตามที่เซิร์ฟเวอร์ส่งกลับมา
      setState(() {
        _errorMessage = response.message;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.bgMain, // สีพื้นหลังเขียวอ่อน #F1F8E9 ตาม variables.css

      // แถบหัวด้านบนสีขาว
      appBar: AppBar(
        backgroundColor: Colors.white,
        elevation: 0,
        scrolledUnderElevation: 0,
        centerTitle: true,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back, color: AppColors.primaryDark),
          onPressed: () {
            if (Navigator.canPop(context)) {
              Navigator.pop(context);
            } else {
              Navigator.pushReplacement(
                context,
                MaterialPageRoute(builder: (context) => const MainNavigation()),
              );
            }
          },
        ),
        title: const Text(
          'ชมรมเพื่อนภูผา',
          style: TextStyle(
            fontSize: 17,
            fontWeight: FontWeight.bold,
            color: AppColors.primaryDark,
          ),
        ),
      ),

      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 32),
          child: Center(
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 420),
              child: Card(
                color: Colors.white,
                elevation: 0,
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(12),
                  side: const BorderSide(color: Color(0xFFE2E8F0)), // เส้นขอบเทาบางๆ ตามเว็บ
                ),
                child: Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 28),
                  child: Form(
                    key: _formKey,
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        // หัวข้อ: เข้าสู่ระบบ
                        const Text(
                          'เข้าสู่ระบบ',
                          textAlign: TextAlign.center,
                          style: TextStyle(
                            fontSize: 22,
                            fontWeight: FontWeight.bold,
                            color: Colors.black, // สีดำตามเว็บ
                          ),
                        ),
                        const SizedBox(height: 6),
                        const Text(
                          'ลงชื่อเข้าใช้เพื่อจัดการกิจกรรมของคุณ',
                          textAlign: TextAlign.center,
                          style: TextStyle(
                            fontSize: 13,
                            color: Color(0xFF757575), // สีเทารองตาม .text-muted
                          ),
                        ),

                        const SizedBox(height: 24),

                        // กล่องแสดงข้อความ Error (ถ้ามี)
                        if (_errorMessage != null) ...[
                          Container(
                            padding: const EdgeInsets.all(12),
                            decoration: BoxDecoration(
                              color: const Color(0xFFFFEBEE),
                              borderRadius: BorderRadius.circular(8),
                              border: Border.all(color: const Color(0xFFFFCDD2)),
                            ),
                            child: Text(
                              _errorMessage!,
                              textAlign: TextAlign.center,
                              style: const TextStyle(
                                color: AppColors.danger,
                                fontSize: 13,
                              ),
                            ),
                          ),
                          const SizedBox(height: 16),
                        ],

                        // 1. ช่องกรอก: ชื่อผู้ใช้งาน (บนเว็บไม่มี placeholder)
                        _buildFieldLabel('ชื่อผู้ใช้งาน'),
                        TextFormField(
                          controller: _usernameController,
                          decoration: _inputDecoration(),
                          validator: (val) {
                            if (val == null || val.trim().isEmpty) {
                              return 'กรุณากรอกชื่อผู้ใช้งาน';
                            }
                            return null;
                          },
                        ),

                        const SizedBox(height: 16),

                        // 2. ช่องกรอก: รหัสผ่าน (บนเว็บไม่มี placeholder)
                        _buildFieldLabel('รหัสผ่าน'),
                        TextFormField(
                          controller: _passwordController,
                          obscureText: _obscurePassword,
                          decoration: _inputDecoration(
                            suffixIcon: IconButton(
                              icon: Icon(
                                _obscurePassword ? Icons.visibility_off : Icons.visibility,
                                color: const Color(0xFF757575),
                                size: 20,
                              ),
                              onPressed: () {
                                setState(() {
                                  _obscurePassword = !_obscurePassword;
                                });
                              },
                            ),
                          ),
                          validator: (val) {
                            if (val == null || val.isEmpty) {
                              return 'กรุณากรอกรหัสผ่าน';
                            }
                            return null;
                          },
                        ),

                        const SizedBox(height: 24),

                        // 3. ปุ่มกด: เข้าสู่ระบบ (ปุ่มสีเขียวทรงมนเต็มความกว้าง)
                        SizedBox(
                          height: 46,
                          child: ElevatedButton(
                            onPressed: _isLoading ? null : _handleLogin,
                            style: ElevatedButton.styleFrom(
                              backgroundColor: AppColors.primary, // #2E7D32
                              foregroundColor: Colors.white,
                              elevation: 0,
                              shape: RoundedRectangleBorder(
                                borderRadius: BorderRadius.circular(8),
                              ),
                            ),
                            child: _isLoading
                                ? const SizedBox(
                                    width: 22,
                                    height: 22,
                                    child: CircularProgressIndicator(
                                      strokeWidth: 2,
                                      color: Colors.white,
                                    ),
                                  )
                                : const Text(
                                    'เข้าสู่ระบบ',
                                    style: TextStyle(
                                      fontSize: 15,
                                      fontWeight: FontWeight.bold,
                                    ),
                                  ),
                          ),
                        ),

                        const SizedBox(height: 20),

                        // 4. ลิงก์: ยังไม่มีบัญชี? สมัครสมาชิก
                        Row(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            const Text(
                              'ยังไม่มีบัญชี? ',
                              style: TextStyle(fontSize: 14, color: Color(0xFF616161)),
                            ),
                            GestureDetector(
                              onTap: () {
                                Navigator.push(
                                  context,
                                  MaterialPageRoute(builder: (context) => const RegisterScreen()),
                                );
                              },
                              child: const Text(
                                'สมัครสมาชิก',
                                style: TextStyle(
                                  fontSize: 14,
                                  fontWeight: FontWeight.bold,
                                  color: AppColors.primary,
                                ),
                              ),
                            ),
                          ],
                        ),
                      ],
                    ),
                  ),
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }

  // ป้ายกำกับฟิลด์ข้อความสีดำ
  Widget _buildFieldLabel(String label) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 6),
      child: Text(
        label,
        style: const TextStyle(
          fontSize: 14,
          fontWeight: FontWeight.w500,
          color: Colors.black, // สีดำตามเว็บ
        ),
      ),
    );
  }

  // รูปแบบช่องกรอกข้อความ
  InputDecoration _inputDecoration({Widget? suffixIcon}) {
    return InputDecoration(
      filled: true,
      fillColor: Colors.white,
      suffixIcon: suffixIcon,
      contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
      border: OutlineInputBorder(
        borderRadius: BorderRadius.circular(8),
        borderSide: const BorderSide(color: Color(0xFFE2E8F0)),
      ),
      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(8),
        borderSide: const BorderSide(color: Color(0xFFE2E8F0)),
      ),
      focusedBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(8),
        borderSide: const BorderSide(color: AppColors.primary, width: 1.5),
      ),
    );
  }
}
