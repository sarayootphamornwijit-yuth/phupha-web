import 'package:flutter/material.dart';
import '../constants/app_colors.dart';

// status_badge.dart
// ป้ายแสดงสถานะ (Status Badge) แบบสีสันสวยงาม เข้าใจง่าย
class StatusBadge extends StatelessWidget {
  final String status;

  const StatusBadge({super.key, required this.status});

  @override
  Widget build(BuildContext context) {
    Color bgColor;
    Color textColor;
    String label;

    switch (status) {
      case 'registered':
        bgColor = AppColors.info.withValues(alpha: 0.15);
        textColor = AppColors.info;
        label = 'ลงทะเบียนแล้ว';
        break;
      case 'checked_in':
        bgColor = AppColors.success.withValues(alpha: 0.15);
        textColor = AppColors.success;
        label = 'เช็คอินแล้ว';
        break;
      case 'checked_out':
        bgColor = AppColors.primary.withValues(alpha: 0.15);
        textColor = AppColors.primary;
        label = 'เสร็จสิ้น';
        break;
      case 'cancelled':
        bgColor = AppColors.danger.withValues(alpha: 0.15);
        textColor = AppColors.danger;
        label = 'ยกเลิกแล้ว';
        break;
      default:
        bgColor = Colors.grey.shade200;
        textColor = Colors.grey.shade700;
        label = status;
    }

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(
        color: bgColor,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: textColor.withValues(alpha: 0.3)),
      ),
      child: Text(
        label,
        style: TextStyle(
          color: textColor,
          fontSize: 12,
          fontWeight: FontWeight.w600,
        ),
      ),
    );
  }
}
