import 'dart:convert';
import 'package:shared_preferences/shared_preferences.dart';
import '../models/user_model.dart';

// session_manager.dart
// จัดการสถานะการเข้าสู่ระบบและบันทึกข้อมูลผู้ใช้แบบ Local
class SessionManager {
  static const String _keyUser = 'logged_in_user';
  static const String _keyIsLoggedIn = 'is_logged_in';

  // บันทึกข้อมูลผู้ใช้งานเมื่อเข้าสู่ระบบสำเร็จ
  static Future<void> saveUser(UserModel user) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setBool(_keyIsLoggedIn, true);
    final userJson = jsonEncode(user.toJson());
    await prefs.setString(_keyUser, userJson);
  }

  // ดึงข้อมูลผู้ใช้งานที่กำลังเข้าสู่ระบบอยู่
  static Future<UserModel?> getUser() async {
    final prefs = await SharedPreferences.getInstance();
    final isLoggedIn = prefs.getBool(_keyIsLoggedIn) ?? false;
    if (!isLoggedIn) return null;

    final userJson = prefs.getString(_keyUser);
    if (userJson == null || userJson.isEmpty) return null;

    try {
      final Map<String, dynamic> map = jsonDecode(userJson);
      return UserModel.fromJson(map);
    } catch (e) {
      return null;
    }
  }

  // ตรวจสอบว่าผู้ใช้เข้าสู่ระบบอยู่หรือไม่
  static Future<bool> isLoggedIn() async {
    final prefs = await SharedPreferences.getInstance();
    return prefs.getBool(_keyIsLoggedIn) ?? false;
  }

  // ออกจากระบบและลบข้อมูล session
  static Future<void> logout() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(_keyIsLoggedIn);
    await prefs.remove(_keyUser);
  }

  // ล้างข้อมูล session ทั้งหมด (เหมือน logout)
  static Future<void> clear() async {
    await logout();
  }
}
