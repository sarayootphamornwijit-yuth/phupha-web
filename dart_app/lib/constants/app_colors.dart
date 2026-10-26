import 'package:flutter/material.dart';

// app_colors.dart
// กำหนดชุดสีมาตรฐานของแอพพลิเคชันชมรมเพื่อนภูผา
// อ้างอิงตาม variables.css ของระบบเว็บเดิม
class AppColors {
  // สีหลักของชมรมเพื่อนภูผา (ธีมสีเขียวธรรมชาติ)
  static const Color primary = Color(0xFF2E7D32);       // เขียวมรกต (Emerald Green)
  static const Color primaryDark = Color(0xFF1B5E20);   // เขียวเข้ม (Forest Green)
  static const Color primaryLight = Color(0xFF4CAF50);  // เขียวสดใส
  static const Color primaryPale = Color(0xFFE8F5E9);   // เขียวอ่อนมาก (สำหรับพื้นหลังการ์ด/ไอคอน)

  // สีรองและสีเสริม
  static const Color secondary = Color(0xFF00695C);     // เขียวอมฟ้า (Teal)
  static const Color secondaryLight = Color(0xFF26A69A);

  // สีสถานะ
  static const Color success = Color(0xFF43A047);       // สำเร็จ / ผ่าน
  static const Color warning = Color(0xFFF9A825);       // รอตรวจสอบ / เตือน
  static const Color danger = Color(0xFFE53935);        // ยกเลิก / อันตราย
  static const Color info = Color(0xFF0288D1);          // ข้อมูล

  // สีพื้นหลัง
  static const Color bgMain = Color(0xFFF1F8E9);         // พื้นหลังหลัก (เขียวอ่อนละมุน)
  static const Color surface = Color(0xFFFFFFFF);        // พื้นหลังการ์ด (สีขาว)
  static const Color cardBorder = Color(0xFFE0E0E0);     // เส้นขอบกล่อง

  // สีตัวอักษร
  static const Color textMain = Color(0xFF212121);       // ข้อความหลักสีเข้ม
  static const Color textMuted = Color(0xFF757575);      // ข้อความรองสีเทา
  static const Color textWhite = Color(0xFFFFFFFF);      // ข้อความสีขาว
}
