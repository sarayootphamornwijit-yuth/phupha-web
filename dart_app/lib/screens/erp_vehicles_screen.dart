import 'package:flutter/material.dart';
import '../constants/app_colors.dart';

// erp_vehicles_screen.dart
// หน้าจอ "จัดการยานพาหนะ" สำหรับคณะกรรมการ ถอดแบบจาก erp_vehicles.php
class ErpVehiclesScreen extends StatelessWidget {
  const ErpVehiclesScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF1F8E9),
      appBar: AppBar(
        title: const Text(
          'จัดการยานพาหนะ',
          style: TextStyle(
            color: AppColors.primaryDark,
            fontWeight: FontWeight.bold,
            fontSize: 20,
          ),
        ),
        backgroundColor: Colors.white,
        elevation: 0.5,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_ios_new, color: AppColors.primaryDark, size: 20),
          onPressed: () => Navigator.pop(context),
        ),
      ),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          _buildVehicleCard('รถบัสปรับอากาศ คันที่ 1', 'ทะเบียน 30-1234 เชียงใหม่', '40/45 ที่นั่ง', 'เต็มแล้ว', Colors.red),
          _buildVehicleCard('รถตู้ชมรม คันที่ 1', 'ทะเบียน นข-5678 เชียงใหม่', '10/14 ที่นั่ง', 'ว่าง 4 ที่', AppColors.primary),
          _buildVehicleCard('รถสองแถวบริการ', 'ทะเบียน 10-9999 เชียงใหม่', '12/20 ที่นั่ง', 'ว่าง 8 ที่', AppColors.primary),
        ],
      ),
    );
  }

  Widget _buildVehicleCard(String title, String plate, String seats, String status, Color statusColor) {
    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: const Color(0xFFE2E8F0)),
      ),
      child: Row(
        children: [
          Container(
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(
              color: AppColors.primaryPale,
              borderRadius: BorderRadius.circular(12),
            ),
            child: const Icon(Icons.directions_bus_filled, color: AppColors.primary, size: 28),
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(title, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14)),
                const SizedBox(height: 2),
                Text(plate, style: const TextStyle(fontSize: 12, color: Colors.grey)),
                const SizedBox(height: 4),
                Text('ที่นั่ง: $seats', style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w500)),
              ],
            ),
          ),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
            decoration: BoxDecoration(
              color: statusColor.withValues(alpha: 0.1),
              borderRadius: BorderRadius.circular(8),
            ),
            child: Text(
              status,
              style: TextStyle(color: statusColor, fontSize: 11, fontWeight: FontWeight.bold),
            ),
          ),
        ],
      ),
    );
  }
}
