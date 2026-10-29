import 'package:mobile_scanner/mobile_scanner.dart';
import 'package:flutter/material.dart';
import '../constants/app_colors.dart';
import '../models/activity_model.dart';
import '../services/api_service.dart';

// qr_scanner_screen.dart
// หน้าจอ "สแกนเช็คอินกิจกรรม" สำหรับคณะกรรมการ ถอดแบบจาก qr_scanner.php
class QrScannerScreen extends StatefulWidget {
  const QrScannerScreen({super.key});

  @override
  State<QrScannerScreen> createState() => _QrScannerScreenState();
}

class _QrScannerScreenState extends State<QrScannerScreen> {
  final TextEditingController _codeController = TextEditingController();
  bool _isLoading = false;
  bool _scannerBusy = false;
  final MobileScannerController _scannerController = MobileScannerController(formats: [BarcodeFormat.qrCode]);
  List<ActivityModel> _activities = [];
  int? _selectedActivityId;

  @override
  void dispose() {
    _codeController.dispose();
    _scannerController.dispose();
    super.dispose();
  }

  //กันกล้องอ่านรหัสเดิมซ้ำระหว่างที่ยังเปิดกล่องผลเช็คอินอยู่
  Future<void> _onDetect(BarcodeCapture capture) async {
    if (_scannerBusy || _isLoading || capture.barcodes.isEmpty) return;
    final code = capture.barcodes.first.rawValue;
    if (code == null || code.isEmpty) return;
    _scannerBusy = true;
    _codeController.text = code;
    try {
      await _handleManualCheckin();
    } finally {
      _scannerBusy = false;
    }
  }

  @override
  void initState() {
    super.initState();
    _loadActivities();
  }

  Future<void> _loadActivities() async {
    final res = await ApiService.getActivities();
    if (!mounted) return;
    if (res.isSuccess && res.data != null && res.data!.isNotEmpty) {
      setState(() {
        _activities = res.data!;
        _selectedActivityId = _activities.first.activityId;
      });
    }
  }

  Future<void> _handleManualCheckin() async {
    final code = _codeController.text.trim();
    if (code.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('กรุณากรอกรหัสนักศึกษา หรือรหัส QR Code')),
      );
      return;
    }

    setState(() => _isLoading = true);
    final res = await ApiService.checkinQr(
      qrData: code,
      activityId: _selectedActivityId,
    );
    if (!mounted) return;
    setState(() => _isLoading = false);

    if (res.isSuccess) {
      _codeController.clear();
      final data = res.data;
      final userName = data?['user_name'] ?? '-';
      final studentId = data?['student_id'] ?? code;
      final actTitle = data?['activity_title'] ?? 'กิจกรรมชมรม';

      await showDialog(
        context: context,
        builder: (context) => AlertDialog(
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
          title: const Row(
            children: [
              Icon(Icons.check_circle, color: AppColors.primary, size: 28),
              SizedBox(width: 8),
              Text('เช็คอินสำเร็จ', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 18)),
            ],
          ),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text('ผู้เข้าร่วม: $userName', style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 15)),
              const SizedBox(height: 6),
              Text('รหัสนักศึกษา: $studentId', style: const TextStyle(color: Color(0xFF64748B), fontSize: 13)),
              const SizedBox(height: 6),
              Text('กิจกรรม: $actTitle', style: const TextStyle(color: Color(0xFF64748B), fontSize: 13)),
              const SizedBox(height: 8),
              Text('สถานะ: ${res.message}', style: const TextStyle(color: AppColors.primary, fontSize: 13, fontWeight: FontWeight.w500)),
            ],
          ),
          actions: [
            ElevatedButton(
              style: ElevatedButton.styleFrom(
                backgroundColor: AppColors.primary,
                foregroundColor: Colors.white,
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
              ),
              onPressed: () => Navigator.pop(context),
              child: const Text('ตกลง'),
            ),
          ],
        ),
      );
    } else {
      await showDialog(
        context: context,
        builder: (context) => AlertDialog(
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
          title: const Row(
            children: [
              Icon(Icons.error_outline, color: AppColors.danger, size: 28),
              SizedBox(width: 8),
              Text('ไม่สามารถเช็คอินได้', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 18)),
            ],
          ),
          content: Text(res.message, style: const TextStyle(fontSize: 14)),
          actions: [
            ElevatedButton(
              style: ElevatedButton.styleFrom(
                backgroundColor: AppColors.danger,
                foregroundColor: Colors.white,
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
              ),
              onPressed: () => Navigator.pop(context),
              child: const Text('ปิด'),
            ),
          ],
        ),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF1F8E9),
      appBar: AppBar(
        title: const Text(
          'สแกนเช็คอินกิจกรรม',
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
        padding: const EdgeInsets.all(20),
        child: Center(
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 500),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                // กล่องเลือกกิจกรรม
                Container(
                  padding: const EdgeInsets.all(16),
                  decoration: BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.circular(16),
                    border: Border.all(color: const Color(0xFFE2E8F0)),
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text(
                        'เลือกกิจกรรมที่ต้องการเช็คอิน',
                        style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14, color: AppColors.primaryDark),
                      ),
                      const SizedBox(height: 8),
                      DropdownButtonFormField<int>(
                        value: _selectedActivityId,
                        isExpanded: true,
                        decoration: InputDecoration(
                          contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
                          border: OutlineInputBorder(borderRadius: BorderRadius.circular(10)),
                        ),
                        items: _activities.map((a) {
                          return DropdownMenuItem<int>(
                            value: a.activityId,
                            child: Text(a.title, maxLines: 1, overflow: TextOverflow.ellipsis),
                          );
                        }).toList(),
                        onChanged: (val) => setState(() => _selectedActivityId = val),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 18),

                //ใช้กล้องจริงในกรอบเดิมของหน้าสแกน QR
                Container(
                  padding: const EdgeInsets.symmetric(vertical: 36, horizontal: 20),
                  decoration: BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.circular(20),
                    border: Border.all(color: const Color(0xFFE2E8F0)),
                    boxShadow: [
                      BoxShadow(
                        color: Colors.black.withValues(alpha: 0.04),
                        blurRadius: 10,
                        offset: const Offset(0, 3),
                      ),
                    ],
                  ),
                  child: Column(
                    children: [
                      Container(
                        width: 180,
                        height: 180,
                        decoration: BoxDecoration(
                          color: const Color(0xFFF8FAFC),
                          borderRadius: BorderRadius.circular(16),
                          border: Border.all(color: AppColors.primary, width: 2),
                        ),
                        child: ClipRRect(
                          borderRadius: BorderRadius.circular(14),
                          child: MobileScanner(
                            controller: _scannerController,
                            onDetect: _onDetect,
                            errorBuilder: (context, error) => const Center(
                              child: Text('เปิดกล้องไม่ได้ กรุณาอนุญาตกล้อง\nหรือกรอกรหัสด้านล่าง', textAlign: TextAlign.center),
                            ),
                          ),
                        ),
                      ),
                      const SizedBox(height: 18),
                      const Text(
                        'นำกล้องไปส่อง QR Code ที่บัตรเข้าร่วมของสมาชิก',
                        textAlign: TextAlign.center,
                        style: TextStyle(fontSize: 13, color: Color(0xFF64748B)),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 18),

                // คีย์รหัสนักศึกษาเช็คอินด้วยตนเอง
                Container(
                  padding: const EdgeInsets.all(16),
                  decoration: BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.circular(16),
                    border: Border.all(color: const Color(0xFFE2E8F0)),
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text(
                        'หรือกรอกรหัสนักศึกษาโดยตรง',
                        style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13.5),
                      ),
                      const SizedBox(height: 10),
                      Row(
                        children: [
                          Expanded(
                            child: TextField(
                              controller: _codeController,
                              keyboardType: TextInputType.number,
                              decoration: InputDecoration(
                                hintText: 'เช่น 66143520...',
                                hintStyle: const TextStyle(fontSize: 13),
                                contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
                                border: OutlineInputBorder(borderRadius: BorderRadius.circular(10)),
                              ),
                            ),
                          ),
                          const SizedBox(width: 10),
                          ElevatedButton(
                            style: ElevatedButton.styleFrom(
                              backgroundColor: AppColors.primary,
                              foregroundColor: Colors.white,
                              padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 14),
                              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                            ),
                            onPressed: _isLoading ? null : _handleManualCheckin,
                            child: _isLoading
                                ? const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2))
                                : const Text('เช็คอิน'),
                          ),
                        ],
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
