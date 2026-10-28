import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import '../constants/app_colors.dart';
import '../models/activity_model.dart';
import '../models/user_model.dart';
import '../services/api_service.dart';
import '../services/session_manager.dart';
import '../widgets/status_badge.dart';
import '../widgets/web_app_bar.dart';
import '../widgets/web_drawer.dart';
import '../widgets/dashed_container.dart';
import 'select_vehicle_screen.dart';
import 'activity_detail_screen.dart';
import 'evaluate_screen.dart';

// joined_activities_screen.dart
// หน้ารายการกิจกรรมที่ผู้ใช้ลงทะเบียนเข้าร่วมไว้
// สอดคล้องกับ joined_activities.php ของระบบเว็บ

class JoinedActivitiesScreen extends StatefulWidget {
  const JoinedActivitiesScreen({super.key});

  @override
  State<JoinedActivitiesScreen> createState() => _JoinedActivitiesScreenState();
}

class _JoinedActivitiesScreenState extends State<JoinedActivitiesScreen> {
  UserModel? _currentUser;
  List<ActivityModel> _joinedList = [];
  bool _isLoading = true;

  @override
  void initState() {
    super.initState();
    _fetchJoinedActivities();
  }

  // ดึงรายการกิจกรรมที่ลงทะเบียนจาก API
  Future<void> _fetchJoinedActivities() async {
    setState(() {
      _isLoading = true;
    });

    final user = await SessionManager.getUser();
    List<ActivityModel> list = [];

    if (user != null) {
      final response = await ApiService.getJoinedActivities(user.userId);
      list = response.data ?? [];
    }

    if (!mounted) return;

    setState(() {
      _currentUser = user;
      _joinedList = list;
      _isLoading = false;
    });
  }

  // ขอยกเลิกการลงทะเบียน
  Future<void> _handleCancelRegistration(int activityId, String activityTitle) async {
    final confirm = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('ยืนยันการยกเลิก'),
        content: Text('คุณต้องการยกเลิกการลงทะเบียนกิจกรรม "$activityTitle" หรือไม่?'),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('ไม่ยกเลิก', style: TextStyle(color: AppColors.textMuted)),
          ),
          ElevatedButton(
            onPressed: () => Navigator.pop(context, true),
            style: ElevatedButton.styleFrom(backgroundColor: AppColors.danger),
            child: const Text('ยืนยันยกเลิก', style: TextStyle(color: Colors.white)),
          ),
        ],
      ),
    );

    if (confirm != true || _currentUser == null) return;

    final response = await ApiService.cancelRegistration(
      _currentUser!.userId,
      activityId,
    );

    if (!mounted) return;

    if (response.isSuccess) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(response.message), backgroundColor: AppColors.success),
      );
      _fetchJoinedActivities(); // โหลดรายการใหม่
    } else {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(response.message), backgroundColor: AppColors.danger),
      );
    }
  }

  // แสดง Modal คิวอาร์โค้ดเช็คอินกิจกรรม (ขนาดพอดีขอบเขต รหัสกระชับ ไม่ล้น)
  void _showQrModal(ActivityModel activity) {
    if (_currentUser == null) return;
    final userId = _currentUser!.userId;
    final rawQrData = 'checkin-$userId-${activity.activityId}';
    final shortDisplayCode = 'CHK-ACT${activity.activityId}-U$userId';
    final qrImageUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&margin=4&data=$rawQrData';

    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        contentPadding: const EdgeInsets.all(20),
        titlePadding: const EdgeInsets.fromLTRB(20, 20, 20, 0),
        title: Row(
          children: [
            Container(
              padding: const EdgeInsets.all(6),
              decoration: BoxDecoration(
                color: AppColors.primaryPale,
                borderRadius: BorderRadius.circular(8),
              ),
              child: const Icon(Icons.qr_code_2, color: AppColors.primary, size: 22),
            ),
            const SizedBox(width: 10),
            const Expanded(
              child: Text(
                'คิวอาร์โค้ดเช็คอิน',
                style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold, color: Colors.black87),
              ),
            ),
          ],
        ),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.center,
          children: [
            // ชื่อกิจกรรมและผู้เข้าร่วม (ชิดซ้าย)
            Align(
              alignment: Alignment.centerLeft,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    activity.title,
                    style: const TextStyle(fontSize: 14, fontWeight: FontWeight.bold, color: Colors.black87),
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                  ),
                  const SizedBox(height: 2),
                  Text(
                    'ผู้เข้าร่วม: ${_currentUser?.name ?? "สมาชิกชมรม"}',
                    style: const TextStyle(fontSize: 12, color: AppColors.textMuted),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 16),

            // กล่อง QR Code ที่กำหนดขอบเขตแน่นอน (190x190) ไม่ล้นกรอบ
            Container(
              width: 190,
              height: 190,
              padding: const EdgeInsets.all(10),
              decoration: BoxDecoration(
                color: const Color(0xFFF8FAFC),
                borderRadius: BorderRadius.circular(12),
                border: Border.all(color: const Color(0xFFCBD5E1), style: BorderStyle.solid, width: 1.5),
              ),
              child: ClipRRect(
                borderRadius: BorderRadius.circular(8),
                child: Image.network(
                  qrImageUrl,
                  width: 170,
                  height: 170,
                  fit: BoxFit.contain,
                  loadingBuilder: (context, child, loadingProgress) {
                    if (loadingProgress == null) return child;
                    return const Center(
                      child: CircularProgressIndicator(color: AppColors.primary, strokeWidth: 2),
                    );
                  },
                  errorBuilder: (context, error, stackTrace) => const Center(
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Icon(Icons.broken_image, color: Colors.grey, size: 36),
                        SizedBox(height: 6),
                        Text('โหลด QR ไม่สำเร็จ', style: TextStyle(fontSize: 11, color: Colors.grey)),
                      ],
                    ),
                  ),
                ),
              ),
            ),
            const SizedBox(height: 14),

            // รหัสเช็คอินแบบกระชับ และปุ่มคัดลอก
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
              decoration: BoxDecoration(
                color: const Color(0xFFF1F5F9),
                borderRadius: BorderRadius.circular(8),
              ),
              child: Row(
                children: [
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Text('รหัสเช็คอิน:', style: TextStyle(fontSize: 10, color: Color(0xFF64748B))),
                        Text(
                          shortDisplayCode,
                          style: const TextStyle(
                            fontSize: 12,
                            fontWeight: FontWeight.bold,
                            color: Color(0xFF1E293B),
                          ),
                        ),
                      ],
                    ),
                  ),
                  OutlinedButton.icon(
                    onPressed: () {
                      Clipboard.setData(ClipboardData(text: rawQrData));
                      ScaffoldMessenger.of(context).showSnackBar(
                        const SnackBar(
                          content: Text('คัดลอกรหัสเช็คอินเรียบร้อยแล้ว'),
                          duration: Duration(seconds: 2),
                          backgroundColor: AppColors.primary,
                        ),
                      );
                    },
                    icon: const Icon(Icons.copy, size: 14),
                    label: const Text('คัดลอก', style: TextStyle(fontSize: 11)),
                    style: OutlinedButton.styleFrom(
                      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                      minimumSize: Size.zero,
                      tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                      foregroundColor: AppColors.primary,
                      side: const BorderSide(color: AppColors.primary),
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(6)),
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
        actions: [
          SizedBox(
            width: double.infinity,
            child: ElevatedButton(
              onPressed: () => Navigator.pop(context),
              style: ElevatedButton.styleFrom(
                backgroundColor: const Color(0xFF64748B),
                foregroundColor: Colors.white,
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
              ),
              child: const Text('ปิดหน้าต่าง'),
            ),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.bgMain, // #F1F8E9
      appBar: WebAppBar(
        currentUser: _currentUser,
        onAuthStateChanged: _fetchJoinedActivities,
      ),
      drawer: WebDrawer(
        currentRoute: 'joined',
        currentUser: _currentUser,
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator(color: AppColors.primary))
          : RefreshIndicator(
              onRefresh: _fetchJoinedActivities,
              color: AppColors.primary,
              child: _joinedList.isEmpty
                  ? ListView(
                      padding: const EdgeInsets.all(16),
                      children: const [
                        SizedBox(height: 30),
                        DashedContainer(message: 'คุณยังไม่ได้ลงทะเบียนเข้าร่วมกิจกรรมใดๆ'),
                      ],
                    )
                  : ListView.builder(
                      padding: const EdgeInsets.all(16),
                      itemCount: _joinedList.length,
                      itemBuilder: (context, index) {
                        final act = _joinedList[index];
                        final travelMethod = act.travelMethod;

                        return Card(
                          margin: const EdgeInsets.only(bottom: 16),
                          color: Colors.white,
                          elevation: 0,
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(12),
                            side: const BorderSide(color: Color(0xFFE2E8F0)),
                          ),
                          child: Padding(
                            padding: const EdgeInsets.all(16),
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                // หัวการ์ด: สถานะ และ วันที่
                                Row(
                                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                  children: [
                                    StatusBadge(status: act.userStatus ?? 'registered'),
                                    Text(
                                      act.startDateFormatted,
                                      style: const TextStyle(fontSize: 12, color: AppColors.textMuted),
                                    ),
                                  ],
                                ),
                                const SizedBox(height: 12),

                                // ชื่อกิจกรรม
                                Text(
                                  act.title,
                                  style: const TextStyle(
                                    fontSize: 16,
                                    fontWeight: FontWeight.bold,
                                    color: Colors.black,
                                  ),
                                ),
                                const SizedBox(height: 8),

                                // สถานที่
                                if (act.locationName != null && act.locationName!.isNotEmpty)
                                  Row(
                                    children: [
                                      const Icon(Icons.location_on, size: 14, color: AppColors.danger),
                                      const SizedBox(width: 4),
                                      Expanded(
                                        child: Text(
                                          act.locationName!,
                                          style: const TextStyle(fontSize: 13, color: AppColors.textMuted),
                                        ),
                                      ),
                                    ],
                                  ),
                                const SizedBox(height: 8),

                                // ข้อมูลการเดินทาง
                                Container(
                                  padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                                  decoration: BoxDecoration(
                                    color: AppColors.primaryPale,
                                    borderRadius: BorderRadius.circular(6),
                                  ),
                                  child: Row(
                                    mainAxisSize: MainAxisSize.min,
                                    children: [
                                      const Icon(Icons.directions_bus, size: 16, color: AppColors.primary),
                                      const SizedBox(width: 6),
                                      Text(
                                        travelMethod != null && travelMethod.isNotEmpty
                                            ? travelMethod
                                            : 'ยังไม่ได้เลือกการเดินทาง',
                                        style: const TextStyle(
                                          fontSize: 12,
                                          fontWeight: FontWeight.w600,
                                          color: AppColors.primaryDark,
                                        ),
                                      ),
                                    ],
                                  ),
                                ),

                                const SizedBox(height: 14),
                                const Divider(color: Color(0xFFEEEEEE), height: 1),
                                const SizedBox(height: 12),

                                // ปุ่มการทำงานต่างๆ
                                Wrap(
                                  spacing: 8,
                                  runSpacing: 8,
                                  children: [
                                    // ปุ่มดูรายละเอียด
                                    OutlinedButton(
                                      onPressed: () {
                                        Navigator.push(
                                          context,
                                          MaterialPageRoute(
                                            builder: (context) => ActivityDetailScreen(activityId: act.activityId),
                                          ),
                                        ).then((_) => _fetchJoinedActivities());
                                      },
                                      style: OutlinedButton.styleFrom(
                                        foregroundColor: AppColors.primary,
                                        side: const BorderSide(color: AppColors.primary),
                                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                                      ),
                                      child: const Text('รายละเอียด'),
                                    ),

                                    // ปุ่มแสดง QR Code เช็คอิน (เฉพาะสถานะ registered)
                                    if (act.userStatus == 'registered')
                                      ElevatedButton.icon(
                                        onPressed: () => _showQrModal(act),
                                        icon: const Icon(Icons.qr_code, size: 16),
                                        label: const Text('คิวอาร์โค้ดเช็คอิน'),
                                        style: ElevatedButton.styleFrom(
                                          backgroundColor: AppColors.primary,
                                          foregroundColor: Colors.white,
                                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                                        ),
                                      ),

                                    // ปุ่มเลือกยานพาหนะ (เฉพาะสถานะ registered)
                                    if (act.userStatus == 'registered')
                                      OutlinedButton(
                                        onPressed: () {
                                          Navigator.push(
                                            context,
                                            MaterialPageRoute(
                                              builder: (context) => SelectVehicleScreen(
                                                activityId: act.activityId,
                                                activityTitle: act.title,
                                              ),
                                            ),
                                          ).then((_) => _fetchJoinedActivities());
                                        },
                                        style: OutlinedButton.styleFrom(
                                          foregroundColor: AppColors.primary,
                                          side: const BorderSide(color: AppColors.primary),
                                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                                        ),
                                        child: const Text('เลือกการเดินทาง'),
                                      ),

                                    // ปุ่มประเมินกิจกรรม (เมื่อเช็คอินแล้ว)
                                    if (act.userStatus == 'checked_in' || act.userStatus == 'checked_out')
                                      ElevatedButton(
                                        onPressed: () {
                                          Navigator.push(
                                            context,
                                            MaterialPageRoute(
                                              builder: (context) => EvaluateScreen(
                                                activityId: act.activityId,
                                                activityTitle: act.title,
                                              ),
                                            ),
                                          ).then((_) => _fetchJoinedActivities());
                                        },
                                        style: ElevatedButton.styleFrom(
                                          backgroundColor: AppColors.warning,
                                          foregroundColor: Colors.white,
                                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                                        ),
                                        child: const Text('ประเมินรับแต้ม'),
                                      ),

                                    // ปุ่มยกเลิกการลงทะเบียน
                                    if (act.userStatus == 'registered')
                                      TextButton(
                                        onPressed: () => _handleCancelRegistration(act.activityId, act.title),
                                        style: TextButton.styleFrom(foregroundColor: AppColors.danger),
                                        child: const Text('ยกเลิก'),
                                      ),
                                  ],
                                ),
                              ],
                            ),
                          ),
                        );
                      },
                    ),
            ),
    );
  }
}
