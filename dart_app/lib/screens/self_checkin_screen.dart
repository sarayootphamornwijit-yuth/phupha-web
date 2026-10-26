import 'package:flutter/material.dart';
import '../constants/app_colors.dart';
import '../models/activity_model.dart';
import '../models/user_model.dart';
import '../services/api_service.dart';
import '../services/session_manager.dart';
import '../widgets/custom_button.dart';
import '../widgets/status_badge.dart';

// self_checkin_screen.dart
// หน้าจอเช็คอินเข้าร่วมกิจกรรมด้วยตนเอง
// สอดคล้องกับ self_checkin.php ในระบบเว็บเดิม
class SelfCheckinScreen extends StatefulWidget {
  const SelfCheckinScreen({super.key});

  @override
  State<SelfCheckinScreen> createState() => _SelfCheckinScreenState();
}

class _SelfCheckinScreenState extends State<SelfCheckinScreen> {
  UserModel? _currentUser;
  List<ActivityModel> _availableCheckinActivities = [];
  bool _isLoading = true;
  int? _checkingInActivityId;

  @override
  void initState() {
    super.initState();
    _loadCheckinData();
  }

  // โหลดรายการกิจกรรมที่เปิดเช็คอินในวันนี้
  Future<void> _loadCheckinData() async {
    setState(() {
      _isLoading = true;
    });

    final user = await SessionManager.getUser();
    List<ActivityModel> activities = [];

    if (user != null) {
      // ดึงกิจกรรมที่ผู้ใช้ลงทะเบียน
      final response = await ApiService.getJoinedActivities(user.userId);
      if (response.isSuccess && response.data != null) {
        // กรองเฉพาะกิจกรรมที่เปิดเช็คอิน
        activities = response.data!;
      }
    }

    if (!mounted) return;

    setState(() {
      _currentUser = user;
      _availableCheckinActivities = activities;
      _isLoading = false;
    });
  }

  // ดำเนินการเช็คอินกิจกรรม
  Future<void> _handleCheckin(int activityId) async {
    if (_currentUser == null) return;

    setState(() {
      _checkingInActivityId = activityId;
    });

    final response = await ApiService.selfCheckin(_currentUser!.userId, activityId);

    if (!mounted) return;

    setState(() {
      _checkingInActivityId = null;
    });

    if (response.isSuccess) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(response.message), backgroundColor: AppColors.success),
      );
      _loadCheckinData(); // อัปเดตข้อมูลใหม่
    } else {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(response.message), backgroundColor: AppColors.danger),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.bgMain,
      appBar: AppBar(
        title: const Text(
          'เช็คอินกิจกรรม',
          style: TextStyle(
            fontSize: 17,
            fontWeight: FontWeight.bold,
            color: AppColors.primaryDark,
          ),
        ),
        backgroundColor: Colors.white,
        elevation: 0,
        scrolledUnderElevation: 0,
        centerTitle: true,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back, color: AppColors.primaryDark),
          onPressed: () => Navigator.pop(context),
        ),
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator(color: AppColors.primary))
          : RefreshIndicator(
              onRefresh: _loadCheckinData,
              color: AppColors.primary,
              child: SingleChildScrollView(
                physics: const AlwaysScrollableScrollPhysics(),
                padding: const EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    // การ์ดคำแนะนำการเช็คอิน
                    Container(
                      padding: const EdgeInsets.all(16),
                      decoration: BoxDecoration(
                        color: Colors.white,
                        borderRadius: BorderRadius.circular(16),
                        border: Border.all(color: AppColors.cardBorder),
                      ),
                      child: const Row(
                        children: [
                          CircleAvatar(
                            backgroundColor: AppColors.primaryPale,
                            child: Icon(Icons.info_outline, color: AppColors.primary),
                          ),
                          SizedBox(width: 14),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  'เงื่อนไขการเช็คอิน',
                                  style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14),
                                ),
                                SizedBox(height: 4),
                                Text(
                                  'สมาชิกต้องลงทะเบียนกิจกรรมล่วงหน้า และระบบจะเปิดให้เช็คอินเฉพาะในวันจัดกิจกรรมจริงเท่านั้น',
                                  style: TextStyle(fontSize: 12, color: AppColors.textMuted),
                                ),
                              ],
                            ),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 20),

                    const Text(
                      'กิจกรรมที่ลงทะเบียนไว้',
                      style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold, color: AppColors.textMain),
                    ),
                    const SizedBox(height: 12),

                    if (_availableCheckinActivities.isEmpty)
                      Container(
                        padding: const EdgeInsets.all(32),
                        decoration: BoxDecoration(
                          color: Colors.white,
                          borderRadius: BorderRadius.circular(16),
                        ),
                        child: const Column(
                          children: [
                            Icon(Icons.event_available, size: 52, color: Colors.grey),
                            SizedBox(height: 12),
                            Text(
                              'ไม่พบกิจกรรมที่ต้องเช็คอินในขณะนี้',
                              style: TextStyle(color: AppColors.textMuted),
                            ),
                          ],
                        ),
                      )
                    else
                      ListView.builder(
                        shrinkWrap: true,
                        physics: const NeverScrollableScrollPhysics(),
                        itemCount: _availableCheckinActivities.length,
                        itemBuilder: (context, index) {
                          final act = _availableCheckinActivities[index];
                          final isCheckedIn = act.userStatus == 'checked_in' || act.userStatus == 'checked_out';
                          final isCheckingThis = _checkingInActivityId == act.activityId;

                          return Card(
                            margin: const EdgeInsets.only(bottom: 12),
                            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                            elevation: 1,
                            child: Padding(
                              padding: const EdgeInsets.all(16),
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Row(
                                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                    children: [
                                      StatusBadge(status: act.userStatus ?? 'registered'),
                                      Text(
                                        act.startDate,
                                        style: const TextStyle(fontSize: 12, color: AppColors.textMuted),
                                      ),
                                    ],
                                  ),
                                  const SizedBox(height: 8),

                                  Text(
                                    act.title,
                                    style: const TextStyle(fontSize: 15, fontWeight: FontWeight.bold),
                                  ),
                                  const SizedBox(height: 14),

                                  if (isCheckedIn)
                                    Container(
                                      padding: const EdgeInsets.all(10),
                                      decoration: BoxDecoration(
                                        color: AppColors.success.withValues(alpha: 0.1),
                                        borderRadius: BorderRadius.circular(8),
                                      ),
                                      child: const Row(
                                        mainAxisAlignment: MainAxisAlignment.center,
                                        children: [
                                          Icon(Icons.check_circle, color: AppColors.success, size: 18),
                                          SizedBox(width: 8),
                                          Text(
                                            'คุณได้เช็คอินเข้าร่วมกิจกรรมเรียบร้อยแล้ว',
                                            style: TextStyle(
                                              color: AppColors.success,
                                              fontWeight: FontWeight.bold,
                                              fontSize: 13,
                                            ),
                                          ),
                                        ],
                                      ),
                                    )
                                  else
                                    CustomButton(
                                      text: 'กดเช็คอินเข้าร่วมกิจกรรม',
                                      icon: Icons.how_to_reg,
                                      isLoading: isCheckingThis,
                                      onPressed: () => _handleCheckin(act.activityId),
                                    ),
                                ],
                              ),
                            ),
                          );
                        },
                      ),
                  ],
                ),
              ),
            ),
    );
  }
}
