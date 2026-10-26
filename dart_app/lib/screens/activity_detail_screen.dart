import 'package:flutter/material.dart';
import '../constants/app_colors.dart';
import '../constants/app_config.dart';
import '../models/activity_model.dart';
import '../models/user_model.dart';
import '../services/api_service.dart';
import '../services/session_manager.dart';
import '../widgets/custom_button.dart';
import 'select_vehicle_screen.dart';
import 'self_checkin_screen.dart';
import 'evaluate_screen.dart';

// activity_detail_screen.dart
// หน้ารายละเอียดกิจกรรม และการลงทะเบียนเข้าร่วม
// สอดคล้องกับ activity_details.php ของเว็บเดิม
class ActivityDetailScreen extends StatefulWidget {
  final int activityId;

  const ActivityDetailScreen({super.key, required this.activityId});

  @override
  State<ActivityDetailScreen> createState() => _ActivityDetailScreenState();
}

class _ActivityDetailScreenState extends State<ActivityDetailScreen> {
  ActivityModel? _activity;
  UserModel? _currentUser;
  bool _isLoading = true;
  bool _isRegistering = false;
  bool _isJoined = false;
  String _userActivityStatus = '';

  @override
  void initState() {
    super.initState();
    _loadActivityDetail();
  }

  // ดึงข้อมูลกิจกรรมและตรวจสอบว่าผู้ใช้ลงทะเบียนหรือยัง
  Future<void> _loadActivityDetail() async {
    setState(() {
      _isLoading = true;
    });

    final user = await SessionManager.getUser();
    final response = await ApiService.getActivity(widget.activityId);

    bool joined = false;
    String status = '';

    if (user != null) {
      // ตรวจสอบว่าในกิจกรรมที่ลงทะเบียน มีกิจกรรมนี้หรือไม่
      final joinedResponse = await ApiService.getJoinedActivities(user.userId);
      if (joinedResponse.isSuccess && joinedResponse.data != null) {
        final found = joinedResponse.data!.where((a) => a.activityId == widget.activityId);
        if (found.isNotEmpty) {
          joined = true;
          status = found.first.userStatus ?? 'registered';
        }
      }
    }

    if (!mounted) return;

    setState(() {
      _currentUser = user;
      _activity = response.data;
      _isJoined = joined;
      _userActivityStatus = status;
      _isLoading = false;
    });
  }

  // กดลงทะเบียนกิจกรรม
  Future<void> _handleRegister() async {
    if (_currentUser == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('กรุณาเข้าสู่ระบบก่อนลงทะเบียนกิจกรรม')),
      );
      return;
    }

    setState(() {
      _isRegistering = true;
    });

    final response = await ApiService.registerActivity(_currentUser!.userId, widget.activityId);

    if (!mounted) return;

    setState(() {
      _isRegistering = false;
    });

    if (response.isSuccess) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(response.message), backgroundColor: AppColors.success),
      );
      _loadActivityDetail(); // โหลดข้อมูลใหม่เพื่ออัปเดตสถานะ
    } else {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(response.message), backgroundColor: AppColors.danger),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_isLoading) {
      return Scaffold(
        appBar: AppBar(backgroundColor: AppColors.primary, foregroundColor: Colors.white),
        body: const Center(child: CircularProgressIndicator(color: AppColors.primary)),
      );
    }

    if (_activity == null) {
      return Scaffold(
        appBar: AppBar(backgroundColor: AppColors.primary, foregroundColor: Colors.white),
        body: const Center(child: Text('ไม่พบข้อมูลกิจกรรมนี้')),
      );
    }

    final imageUrl = AppConfig.getFullImageUrl(_activity!.coverImage);

    return Scaffold(
      backgroundColor: AppColors.bgMain,
      appBar: AppBar(
        title: Text(
          _activity!.title,
          style: const TextStyle(
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
      body: SingleChildScrollView(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // ภาพปกกิจกรรม
            SizedBox(
              height: 220,
              width: double.infinity,
              child: Stack(
                fit: StackFit.expand,
                children: [
                  if (imageUrl.isNotEmpty)
                    Image.network(
                      imageUrl,
                      fit: BoxFit.cover,
                      errorBuilder: (context, error, stackTrace) => Container(
                        color: AppColors.primaryPale,
                        child: const Icon(Icons.terrain, size: 64, color: AppColors.primary),
                      ),
                    )
                  else
                    Container(
                      color: AppColors.primaryPale,
                      child: const Icon(Icons.terrain, size: 64, color: AppColors.primary),
                    ),
                  // หมวดหมู่กิจกรรม
                  Positioned(
                    top: 16,
                    left: 16,
                    child: Container(
                      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                      decoration: BoxDecoration(
                        color: AppColors.primaryDark.withValues(alpha: 0.9),
                        borderRadius: BorderRadius.circular(20),
                      ),
                      child: Text(
                        _activity!.categoryText,
                        style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 13),
                      ),
                    ),
                  ),
                ],
              ),
            ),

            // ข้อมูลเนื้อหาหลัก
            Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  // ชื่อกิจกรรม
                  Text(
                    _activity!.title,
                    style: const TextStyle(fontSize: 20, fontWeight: FontWeight.bold, color: AppColors.textMain),
                  ),
                  const SizedBox(height: 12),

                  // ข้อมูลกล่องสรุป
                  Container(
                    padding: const EdgeInsets.all(14),
                    decoration: BoxDecoration(
                      color: Colors.white,
                      borderRadius: BorderRadius.circular(14),
                      border: Border.all(color: AppColors.cardBorder),
                    ),
                    child: Column(
                      children: [
                        _buildInfoRow(Icons.calendar_today, 'วันจัดกิจกรรม', _activity!.startDate),
                        if (_activity!.endDate != null && _activity!.endDate!.isNotEmpty)
                          _buildInfoRow(Icons.event_busy, 'สิ้นสุดกิจกรรม', _activity!.endDate!),
                        if (_activity!.locationName != null)
                          _buildInfoRow(Icons.location_on, 'สถานที่', _activity!.locationName!),
                        if (_activity!.meetingPoint != null)
                          _buildInfoRow(Icons.flag, 'จุดนัดพบ', _activity!.meetingPoint!),
                        if (_activity!.meetingTime != null)
                          _buildInfoRow(Icons.access_time, 'เวลานัดหมาย', _activity!.meetingTime!),
                        _buildInfoRow(Icons.people, 'จำนวนผู้เข้าร่วม', '${_activity!.participantCount} / ${_activity!.maxParticipants > 0 ? _activity!.maxParticipants : "ไม่จำกัด"} คน'),
                        if (_activity!.points > 0)
                          _buildInfoRow(Icons.military_tech, 'แต้มจิตอาสาที่จะได้รับ', '+${_activity!.points} แต้ม', valueColor: AppColors.warning),
                      ],
                    ),
                  ),
                  const SizedBox(height: 16),

                  // รายละเอียดกิจกรรม
                  if (_activity!.description != null && _activity!.description!.isNotEmpty) ...[
                    const Text(
                      'รายละเอียดกิจกรรม',
                      style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold, color: AppColors.textMain),
                    ),
                    const SizedBox(height: 8),
                    Container(
                      width: double.infinity,
                      padding: const EdgeInsets.all(14),
                      decoration: BoxDecoration(
                        color: Colors.white,
                        borderRadius: BorderRadius.circular(14),
                        border: Border.all(color: AppColors.cardBorder),
                      ),
                      child: Text(
                        _activity!.description!,
                        style: const TextStyle(fontSize: 14, color: AppColors.textMain, height: 1.6),
                      ),
                    ),
                    const SizedBox(height: 20),
                  ],

                  // ส่วนการดำเนินการตามสถานะของผู้ใช้
                  if (_isJoined) ...[
                    Container(
                      padding: const EdgeInsets.all(16),
                      decoration: BoxDecoration(
                        color: AppColors.primaryPale,
                        borderRadius: BorderRadius.circular(14),
                        border: Border.all(color: AppColors.primary.withValues(alpha: 0.3)),
                      ),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: [
                          Row(
                            children: [
                              const Icon(Icons.check_circle, color: AppColors.success),
                              const SizedBox(width: 8),
                              Text(
                                'คุณลงทะเบียนกิจกรรมนี้แล้ว (${_activity!.userStatusText})',
                                style: const TextStyle(fontWeight: FontWeight.bold, color: AppColors.primaryDark),
                              ),
                            ],
                          ),
                          const SizedBox(height: 14),

                          // ปุ่มเลือกวิธีเดินทาง
                          CustomButton(
                            text: 'เลือกรูปแบบการเดินทาง / รถชมรม',
                            icon: Icons.directions_bus,
                            backgroundColor: AppColors.secondary,
                            onPressed: () {
                              Navigator.push(
                                context,
                                MaterialPageRoute(
                                  builder: (context) => SelectVehicleScreen(
                                    activityId: _activity!.activityId,
                                    activityTitle: _activity!.title,
                                  ),
                                ),
                              ).then((_) => _loadActivityDetail());
                            },
                          ),
                          const SizedBox(height: 8),

                          // ปุ่มเช็คอิน (หากเปิดเช็คอิน)
                          if (_activity!.selfCheckinEnabled && _userActivityStatus == 'registered')
                            CustomButton(
                              text: 'เช็คอินด้วยตนเอง (Self Check-in)',
                              icon: Icons.qr_code_scanner,
                              backgroundColor: AppColors.primary,
                              onPressed: () {
                                Navigator.push(
                                  context,
                                  MaterialPageRoute(builder: (context) => const SelfCheckinScreen()),
                                ).then((_) => _loadActivityDetail());
                              },
                            ),

                          // ปุ่มทำแบบประเมิน (หากเช็คอินแล้ว)
                          if (_userActivityStatus == 'checked_in') ...[
                            const SizedBox(height: 8),
                            CustomButton(
                              text: 'ทำแบบประเมินความพึงพอใจ',
                              icon: Icons.rate_review,
                              backgroundColor: AppColors.warning,
                              textColor: Colors.white,
                              onPressed: () {
                                Navigator.push(
                                  context,
                                  MaterialPageRoute(
                                    builder: (context) => EvaluateScreen(
                                      activityId: _activity!.activityId,
                                      activityTitle: _activity!.title,
                                    ),
                                  ),
                                ).then((_) => _loadActivityDetail());
                              },
                            ),
                          ],
                        ],
                      ),
                    ),
                  ] else ...[
                    // ปุ่มลงทะเบียน
                    CustomButton(
                      text: 'ลงทะเบียนเข้าร่วมกิจกรรม',
                      icon: Icons.how_to_reg,
                      onPressed: _handleRegister,
                      isLoading: _isRegistering,
                    ),
                  ],
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildInfoRow(IconData icon, String label, String value, {Color? valueColor}) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 6),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, size: 16, color: AppColors.primary),
          const SizedBox(width: 8),
          SizedBox(
            width: 120,
            child: Text(label, style: const TextStyle(fontSize: 13, color: AppColors.textMuted)),
          ),
          Expanded(
            child: Text(
              value,
              style: TextStyle(
                fontSize: 13,
                fontWeight: FontWeight.w600,
                color: valueColor ?? AppColors.textMain,
              ),
            ),
          ),
        ],
      ),
    );
  }
}
