import 'package:flutter/material.dart';
import '../constants/app_colors.dart';
import '../models/user_model.dart';
import '../services/api_service.dart';
import '../services/session_manager.dart';
import '../widgets/web_app_bar.dart';
import '../widgets/web_drawer.dart';
import '../widgets/dashed_container.dart';

// certificates_screen.dart
// หน้าจอตรวจสอบเกณฑ์และประวัติเกียรติบัตรชมรมเพื่อนภูผา
// สอดคล้องกับ certificates.php บนเว็บไซต์

class CertificatesScreen extends StatefulWidget {
  const CertificatesScreen({super.key});

  @override
  State<CertificatesScreen> createState() => _CertificatesScreenState();
}

class _CertificatesScreenState extends State<CertificatesScreen> {
  UserModel? _currentUser;
  int _points = 0;
  int _threshold = 50;
  bool _isQualified = false;
  List<dynamic> _certificates = [];
  bool _isLoading = true;

  @override
  void initState() {
    super.initState();
    _fetchCertificateData();
  }

  // ดึงข้อมูลเกียรติบัตรและคะแนนสะสมจาก API
  Future<void> _fetchCertificateData() async {
    setState(() {
      _isLoading = true;
    });

    final user = await SessionManager.getUser();

    if (user != null) {
      final response = await ApiService.getCertificates(user.userId);
      if (response.isSuccess && response.data != null) {
        final data = response.data!;
        setState(() {
          _currentUser = user;
          _points = data['points'] ?? 0;
          _threshold = data['threshold'] ?? 50;
          _isQualified = data['is_qualified'] ?? false;
          _certificates = data['certificates'] ?? [];
          _isLoading = false;
        });
        return;
      }
    }

    if (!mounted) return;

    setState(() {
      _currentUser = user;
      _isLoading = false;
    });
  }

  @override
  Widget build(BuildContext context) {
    final progress = _threshold > 0 ? (_points / _threshold).clamp(0.0, 1.0) : 0.0;
    final remaining = (_threshold - _points) > 0 ? (_threshold - _points) : 0;

    return Scaffold(
      backgroundColor: AppColors.bgMain, // #F1F8E9
      appBar: WebAppBar(
        currentUser: _currentUser,
        onAuthStateChanged: _fetchCertificateData,
      ),
      drawer: WebDrawer(
        currentRoute: 'certificates',
        currentUser: _currentUser,
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator(color: AppColors.primary))
          : SingleChildScrollView(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  // การ์ดแสดงสถานะเกียรติบัตรและแต้มสะสม
                  Container(
                    padding: const EdgeInsets.all(20),
                    decoration: BoxDecoration(
                      gradient: LinearGradient(
                        colors: _isQualified
                            ? [const Color(0xFFF57F17), const Color(0xFFFBC02D)]
                            : [AppColors.primaryDark, AppColors.primary],
                        begin: Alignment.topLeft,
                        end: Alignment.bottomRight,
                      ),
                      borderRadius: BorderRadius.circular(16),
                      boxShadow: const [
                        BoxShadow(
                          color: Color(0x1A000000),
                          blurRadius: 10,
                          offset: Offset(0, 4),
                        ),
                      ],
                    ),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            Text(
                              _currentUser?.name ?? 'สมาชิกชมรม',
                              style: const TextStyle(
                                color: Colors.white,
                                fontSize: 18,
                                fontWeight: FontWeight.bold,
                              ),
                            ),
                            Container(
                              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                              decoration: BoxDecoration(
                                color: Colors.white24,
                                borderRadius: BorderRadius.circular(20),
                              ),
                              child: Text(
                                _isQualified ? 'ผ่านเกณฑ์แล้ว' : 'ยังไม่ผ่านเกณฑ์',
                                style: const TextStyle(color: Colors.white, fontSize: 12, fontWeight: FontWeight.bold),
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 16),

                        // คะแนนสะสม
                        Row(
                          crossAxisAlignment: CrossAxisAlignment.baseline,
                          textBaseline: TextBaseline.alphabetic,
                          children: [
                            Text(
                              '$_points',
                              style: const TextStyle(
                                color: Colors.white,
                                fontSize: 36,
                                fontWeight: FontWeight.bold,
                              ),
                            ),
                            const SizedBox(width: 8),
                            Text(
                              '/ $_threshold แต้มจิตอาสา',
                              style: const TextStyle(color: Colors.white70, fontSize: 16),
                            ),
                          ],
                        ),

                        const SizedBox(height: 12),

                        // แถบความคืบหน้า (Progress Bar)
                        ClipRRect(
                          borderRadius: BorderRadius.circular(8),
                          child: LinearProgressIndicator(
                            value: progress,
                            minHeight: 10,
                            backgroundColor: Colors.white24,
                            valueColor: AlwaysStoppedAnimation<Color>(
                              _isQualified ? Colors.white : Colors.lightGreenAccent,
                            ),
                          ),
                        ),

                        const SizedBox(height: 12),
                        Text(
                          _isQualified
                              ? 'ยินดีด้วย! คุณมีแต้มจิตอาสาครบตามเกณฑ์สำหรับออกเกียรติบัตรแล้ว'
                              : 'ต้องการอีก $remaining แต้มเพื่อปลดล็อกเกียรติบัตรประจำปีการศึกษา',
                          style: const TextStyle(color: Colors.white, fontSize: 13),
                        ),
                      ],
                    ),
                  ),

                  const SizedBox(height: 24),

                  // หัวข้อ: ประวัติเกียรติบัตร
                  const Text(
                    'เกียรติบัตรของฉัน',
                    style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold, color: Colors.black),
                  ),
                  const SizedBox(height: 12),

                  _certificates.isEmpty
                      ? const DashedContainer(message: 'ยังไม่มีประวัติเกียรติบัตรที่ออกให้ในระบบขณะนี้')
                      : ListView.builder(
                          shrinkWrap: true,
                          physics: const NeverScrollableScrollPhysics(),
                          itemCount: _certificates.length,
                          itemBuilder: (context, index) {
                            final cert = _certificates[index];
                            return Card(
                              margin: const EdgeInsets.only(bottom: 12),
                              color: Colors.white,
                              elevation: 0,
                              shape: RoundedRectangleBorder(
                                borderRadius: BorderRadius.circular(12),
                                side: const BorderSide(color: Color(0xFFE2E8F0)),
                              ),
                              child: ListTile(
                                leading: const CircleAvatar(
                                  backgroundColor: Color(0xFFFFF8E1),
                                  child: Icon(Icons.workspace_premium, color: Color(0xFFFFA000)),
                                ),
                                title: Text(
                                  cert['certificate_no'] ?? 'เกียรติบัตรจิตอาสา',
                                  style: const TextStyle(fontWeight: FontWeight.bold),
                                ),
                                subtitle: Text(
                                  'วันที่ออก: ${cert['date_issued'] ?? '-'}',
                                  style: const TextStyle(fontSize: 12, color: AppColors.textMuted),
                                ),
                              ),
                            );
                          },
                        ),
                ],
              ),
            ),
    );
  }
}
