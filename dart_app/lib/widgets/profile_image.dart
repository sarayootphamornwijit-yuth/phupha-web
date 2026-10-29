import 'package:flutter/material.dart';
import '../constants/app_config.dart';

//ใช้รูปที่บันทึกจากเว็บหรือแอพ ถ้าไฟล์หายยังแสดงไอคอนเดิมได้
class ProfileImage extends StatelessWidget {
  final String? path;
  final double size;
  final Widget fallback;
  const ProfileImage({
    super.key,
    required this.path,
    required this.size,
    required this.fallback,
  });

  @override
  Widget build(BuildContext context) {
    if (path == null || path!.isEmpty) {
      return fallback;
    }
    return ClipOval(
      child: Image.network(
        AppConfig.getFullImageUrl(path),
        width: size,
        height: size,
        fit: BoxFit.cover,
        errorBuilder: (context, error, stackTrace) => fallback,
      ),
    );
  }
}
