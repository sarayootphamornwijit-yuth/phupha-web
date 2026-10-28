import 'package:flutter/material.dart';
import '../constants/app_colors.dart';

// erp_reports_screen.dart
// หน้าจอ "สรุปรายงานผล" สำหรับคณะกรรมการ ถอดแบบจาก erp_reports.php
class ErpReportsScreen extends StatelessWidget {
  const ErpReportsScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF1F8E9),
      appBar: AppBar(
        title: const Text(
          'สรุปรายงานผล (ERP)',
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
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Container(
              padding: const EdgeInsets.all(20),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(16),
                border: Border.all(color: const Color(0xFFE2E8F0)),
              ),
              child: const Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text('สถิติผลการดำเนินงานชมรม', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
                  SizedBox(height: 14),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceAround,
                    children: [
                      _StatColumn('กิจกรรมที่จัด', '6', AppColors.primary),
                      _StatColumn('ผู้เข้าร่วมสะสม', '180 คน', Colors.blue),
                      _StatColumn('คะแนนพึงพอใจ', '4.85 / 5', Colors.amber),
                    ],
                  ),
                ],
              ),
            ),
            const SizedBox(height: 16),
            const Text('รายงานประเมินความพึงพอใจล่าสุด', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 15)),
            const SizedBox(height: 10),
            _buildReportCard('โครงการจิตอาสาพัฒนาชุมชนดอยสะเก็ด', 'ผู้ประเมิน: 48 คน', 'ความพึงพอใจ: 97.5%'),
            _buildReportCard('โครงการแข่งขันกีฬาเชื่อมสัมพันธ์ชมรม', 'ผู้ประเมิน: 64 คน', 'ความพึงพอใจ: 96.0%'),
            _buildReportCard('กิจกรรมบายเนียร์สานสัมพันธ์เพื่อนภูผา', 'ผู้ประเมิน: 68 คน', 'ความพึงพอใจ: 98.2%'),
          ],
        ),
      ),
    );
  }

  Widget _buildReportCard(String title, String participants, String score) {
    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: const Color(0xFFE2E8F0)),
      ),
      child: Row(
        children: [
          const Icon(Icons.analytics_outlined, color: AppColors.primary, size: 30),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(title, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13.5)),
                const SizedBox(height: 4),
                Text('$participants • $score', style: const TextStyle(fontSize: 12, color: Colors.grey)),
              ],
            ),
          ),
          const Icon(Icons.chevron_right, color: Colors.grey),
        ],
      ),
    );
  }
}

class _StatColumn extends StatelessWidget {
  final String label;
  final String value;
  final Color color;

  const _StatColumn(this.label, this.value, this.color);

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        Text(value, style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold, color: color)),
        const SizedBox(height: 4),
        Text(label, style: const TextStyle(fontSize: 11, color: Colors.grey)),
      ],
    );
  }
}
