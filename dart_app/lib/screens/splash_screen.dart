import 'package:flutter/material.dart';
import '../constants/app_colors.dart';
import '../services/session_manager.dart';
import 'login_screen.dart';
import 'main_navigation.dart';

// splash_screen.dart
// หน้าจอเริ่มต้น ทำการตรวจสอบว่ามี Session ของผู้ใช้อยู่แล้วหรือไม่
class SplashScreen extends StatefulWidget {
  const SplashScreen({super.key});

  @override
  State<SplashScreen> createState() => _SplashScreenState();
}

class _SplashScreenState extends State<SplashScreen> {
  @override
  void initState() {
    super.initState();
    _checkLoginStatus();
  }

  // ตรวจสอบสถานะและนำทางไปยังหน้าหลัก
  Future<void> _checkLoginStatus() async {
    await Future.delayed(const Duration(milliseconds: 1200));

    if (!mounted) return;

    // เข้าแอพแล้วแสดงหน้าหลักเสมอ (ผู้ใช้สามารถดูกิจกรรมก่อน แล้วค่อยสมัครหรือล็อกอินทีหลังได้)
    Navigator.pushReplacement(
      context,
      MaterialPageRoute(builder: (context) => const MainNavigation()),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.primaryDark,
      body: Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            // ไอคอนภูเขา/ธรรมชาติ สัญลักษณ์ชมรมเพื่อนภูผา
            Container(
              padding: const EdgeInsets.all(24),
              decoration: BoxDecoration(
                color: Colors.white.withValues(alpha: 0.15),
                shape: BoxShape.circle,
              ),
              child: const Icon(
                Icons.terrain,
                size: 72,
                color: Colors.white,
              ),
            ),
            const SizedBox(height: 24),

            // ชื่อชมรม
            const Text(
              'ชมรมเพื่อนภูผา',
              style: TextStyle(
                color: Colors.white,
                fontSize: 26,
                fontWeight: FontWeight.bold,
                letterSpacing: 0.5,
              ),
            ),
            const SizedBox(height: 8),

            // มหาวิทยาลัยราชภัฏเชียงใหม่
            Text(
              'มหาวิทยาลัยราชภัฏเชียงใหม่',
              style: TextStyle(
                color: Colors.white.withValues(alpha: 0.8),
                fontSize: 14,
              ),
            ),
            const SizedBox(height: 40),

            // สัญลักษณ์กำลังโหลด
            const CircularProgressIndicator(
              valueColor: AlwaysStoppedAnimation<Color>(Colors.white),
              strokeWidth: 2.5,
            ),
          ],
        ),
      ),
    );
  }
}
