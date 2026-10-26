import 'package:flutter/material.dart';
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

                                    // ปุ่มเลือกยานพาหนะ (เฉพาะสถานะ registered)
                                    if (act.userStatus == 'registered')
                                      ElevatedButton(
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
                                        style: ElevatedButton.styleFrom(
                                          backgroundColor: AppColors.primary,
                                          foregroundColor: Colors.white,
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
