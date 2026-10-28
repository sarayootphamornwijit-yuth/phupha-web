import 'package:flutter/material.dart';
import '../constants/app_colors.dart';

// erp_budgets_screen.dart
// หน้าจอ "บัญชีรายรับ-รายจ่าย" สำหรับคณะกรรมการ ถอดแบบจาก erp_budgets.php
class ErpBudgetsScreen extends StatelessWidget {
  const ErpBudgetsScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF1F8E9),
      appBar: AppBar(
        title: const Text(
          'บัญชีรายรับ-รายจ่าย',
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
          children: [
            // การ์ดสรุปยอดเงิน
            Row(
              children: [
                Expanded(
                  child: Container(
                    padding: const EdgeInsets.all(16),
                    decoration: BoxDecoration(
                      color: Colors.white,
                      borderRadius: BorderRadius.circular(16),
                      border: Border.all(color: const Color(0xFFE2E8F0)),
                    ),
                    child: const Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text('ยอดงบประมาณจัดสรร', style: TextStyle(fontSize: 12, color: Colors.grey)),
                        SizedBox(height: 6),
                        Text('฿15,000.00', style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold, color: AppColors.primary)),
                      ],
                    ),
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Container(
                    padding: const EdgeInsets.all(16),
                    decoration: BoxDecoration(
                      color: Colors.white,
                      borderRadius: BorderRadius.circular(16),
                      border: Border.all(color: const Color(0xFFE2E8F0)),
                    ),
                    child: const Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text('ยอดใช้จ่ายรวม', style: TextStyle(fontSize: 12, color: Colors.grey)),
                        SizedBox(height: 6),
                        Text('฿8,420.00', style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold, color: Colors.deepOrange)),
                      ],
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 16),

            // รายการบันทึกงบประมาณล่าสุด
            Container(
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(16),
                border: Border.all(color: const Color(0xFFE2E8F0)),
              ),
              child: const Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text('รายการใช้จ่ายล่าสุด', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 15)),
                  SizedBox(height: 12),
                  ListTile(
                    contentPadding: EdgeInsets.zero,
                    leading: CircleAvatar(backgroundColor: Color(0xFFDCFCE7), child: Icon(Icons.shopping_bag_outlined, color: AppColors.primary)),
                    title: Text('ค่าอุปกรณ์จิตอาสาปลูกป่า', style: TextStyle(fontSize: 13, fontWeight: FontWeight.w600)),
                    subtitle: Text('โครงการปลูกป่าชายเลน • 24 ต.ค. 2569', style: TextStyle(fontSize: 11, color: Colors.grey)),
                    trailing: Text('-฿3,200', style: TextStyle(fontWeight: FontWeight.bold, color: Colors.red)),
                  ),
                  Divider(height: 1),
                  ListTile(
                    contentPadding: EdgeInsets.zero,
                    leading: CircleAvatar(backgroundColor: Color(0xFFDCFCE7), child: Icon(Icons.fastfood_outlined, color: AppColors.primary)),
                    title: Text('ค่าน้ำดื่มและอาหารว่าง', style: TextStyle(fontSize: 13, fontWeight: FontWeight.w600)),
                    subtitle: Text('โครงการบายเนียร์ • 18 ต.ค. 2569', style: TextStyle(fontSize: 11, color: Colors.grey)),
                    trailing: Text('-฿5,220', style: TextStyle(fontWeight: FontWeight.bold, color: Colors.red)),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}
