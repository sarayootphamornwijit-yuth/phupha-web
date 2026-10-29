import 'package:flutter/material.dart';
import '../constants/app_colors.dart';
import '../models/activity_model.dart';
import '../services/api_service.dart';
import '../widgets/management_form_widgets.dart';
import 'activity_form_screen.dart';

// erp_activities_screen.dart
// หน้าจอ "จัดการกิจกรรม" สำหรับคณะกรรมการ ถอดแบบจาก erp_activities.php
class ErpActivitiesScreen extends StatefulWidget {
  const ErpActivitiesScreen({super.key});

  @override
  State<ErpActivitiesScreen> createState() => _ErpActivitiesScreenState();
}

class _ErpActivitiesScreenState extends State<ErpActivitiesScreen> {
  bool _isLoading = false;
  List<ActivityModel> _activities = [];
  String? _error;

  @override
  void initState() {
    super.initState();
    _loadActivities();
  }

  Future<void> _loadActivities() async {
    setState(() {
      _isLoading = true;
      _error = null;
    });
    final res = await ApiService.getActivities(management: true);
    if (!mounted) {
      return;
    }
    if (res.isSuccess && res.data != null) {
      _activities = res.data!;
    } else {
      _error = res.message;
    }
    setState(() => _isLoading = false);
  }

  //โหลดข้อมูลครบทุกช่องก่อนแก้ เพื่อไม่ทำช่องเดิมหายตอนบันทึก
  Future<void> _openForm([ActivityModel? activity]) async {
    Map<String, dynamic>? data;
    if (activity != null) {
      final result = await ApiService.managementGet(
        'get_management_activities',
      );
      if (!mounted) {
        return;
      }
      if (!result.isSuccess) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(result.message)));
        return;
      }
      final matches = List<Map<String, dynamic>>.from(result.data as List)
          .where(
            (row) =>
                row['activity_id'].toString() == activity.activityId.toString(),
          )
          .toList();
      if (matches.isEmpty) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('กิจกรรมนี้ถูกลบแล้ว กรุณาโหลดรายการใหม่'),
          ),
        );
        _loadActivities();
        return;
      }
      data = matches.first;
    }
    if (!mounted) {
      return;
    }
    final saved = await Navigator.push<bool>(
      context,
      MaterialPageRoute(
        builder: (context) => ActivityFormScreen(activity: data),
      ),
    );
    if (saved == true && mounted) _loadActivities();
  }

  Future<void> _delete(ActivityModel activity) async {
    if (!await confirmManagementDelete(
      context,
      'ลบกิจกรรม ${activity.title} หรือไม่?',
    )) {
      return;
    }
    final result = await ApiService.managementPost('delete_activity', {
      'activity_id': activity.activityId.toString(),
    });
    if (!mounted) {
      return;
    }
    ScaffoldMessenger.of(
      context,
    ).showSnackBar(SnackBar(content: Text(result.message)));
    if (result.isSuccess) _loadActivities();
  }

  void _showParticipantsDialog(ActivityModel activity) {
    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(
          'รายชื่อผู้เข้าร่วม: ${activity.title}',
          style: const TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
        ),
        content: SizedBox(
          width: double.maxFinite,
          child: FutureBuilder<ApiResponse<List<Map<String, dynamic>>>>(
            future: ApiService.getActivityParticipants(activity.activityId),
            builder: (context, snapshot) {
              if (snapshot.connectionState == ConnectionState.waiting) {
                return const Padding(
                  padding: EdgeInsets.all(24),
                  child: Center(
                    child: CircularProgressIndicator(color: AppColors.primary),
                  ),
                );
              }

              final participants = snapshot.data?.data ?? [];

              return Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Container(
                    padding: const EdgeInsets.all(12),
                    decoration: BoxDecoration(
                      color: AppColors.primaryPale,
                      borderRadius: BorderRadius.circular(10),
                    ),
                    child: Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        const Text(
                          'ยอดลงทะเบียนทั้งหมด:',
                          style: TextStyle(
                            fontWeight: FontWeight.bold,
                            fontSize: 13,
                          ),
                        ),
                        Text(
                          '${participants.length} คน',
                          style: const TextStyle(
                            color: AppColors.primaryDark,
                            fontWeight: FontWeight.bold,
                            fontSize: 14,
                          ),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 12),
                  if (participants.isEmpty)
                    const Padding(
                      padding: EdgeInsets.all(16),
                      child: Center(
                        child: Text(
                          'ยังไม่มีผู้ลงทะเบียนกิจกรรมนี้',
                          style: TextStyle(color: Colors.grey, fontSize: 13),
                        ),
                      ),
                    )
                  else
                    ConstrainedBox(
                      constraints: const BoxConstraints(maxHeight: 280),
                      child: ListView.separated(
                        shrinkWrap: true,
                        itemCount: participants.length,
                        separatorBuilder: (context, i) =>
                            const Divider(height: 1),
                        itemBuilder: (context, i) {
                          final p = participants[i];
                          final isCheckedIn =
                              (p['status'] == 'checked_in' ||
                              p['status'] == 'checked_out');
                          final name = p['name'] ?? '-';
                          final stdId =
                              p['student_id'] != null &&
                                  p['student_id'].toString().isNotEmpty
                              ? 'รหัส ${p['student_id']}'
                              : '';
                          final statusText = isCheckedIn
                              ? 'เช็คอินแล้ว'
                              : 'รอเช็คอิน';

                          return ListTile(
                            dense: true,
                            contentPadding: EdgeInsets.zero,
                            leading: CircleAvatar(
                              radius: 14,
                              backgroundColor: isCheckedIn
                                  ? AppColors.primaryPale
                                  : const Color(0xFFF1F5F9),
                              child: Text(
                                '${i + 1}',
                                style: TextStyle(
                                  fontSize: 11,
                                  fontWeight: FontWeight.bold,
                                  color: isCheckedIn
                                      ? AppColors.primaryDark
                                      : Colors.grey,
                                ),
                              ),
                            ),
                            title: Text(
                              name,
                              style: const TextStyle(
                                fontSize: 13,
                                fontWeight: FontWeight.bold,
                              ),
                            ),
                            subtitle: Text(
                              stdId.isNotEmpty
                                  ? '$stdId ($statusText)'
                                  : '($statusText)',
                              style: TextStyle(
                                fontSize: 11,
                                color: isCheckedIn
                                    ? AppColors.primary
                                    : Colors.orange,
                              ),
                            ),
                          );
                        },
                      ),
                    ),
                ],
              );
            },
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text(
              'ปิด',
              style: TextStyle(color: AppColors.primary),
            ),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF1F8E9),
      appBar: AppBar(
        title: const Text(
          'จัดการกิจกรรม (ERP)',
          style: TextStyle(
            color: AppColors.primaryDark,
            fontWeight: FontWeight.bold,
            fontSize: 20,
          ),
        ),
        backgroundColor: Colors.white,
        elevation: 0.5,
        leading: IconButton(
          icon: const Icon(
            Icons.arrow_back_ios_new,
            color: AppColors.primaryDark,
            size: 20,
          ),
          onPressed: () => Navigator.pop(context),
        ),
      ),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () => _openForm(),
        icon: const Icon(Icons.add),
        label: const Text('ร่างกิจกรรมใหม่'),
      ),
      body: _isLoading
          ? const Center(
              child: CircularProgressIndicator(color: AppColors.primary),
            )
          : _error != null
          ? Center(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text(_error!),
                  TextButton(
                    onPressed: _loadActivities,
                    child: const Text('ลองใหม่'),
                  ),
                ],
              ),
            )
          : _activities.isEmpty
          ? const Center(
              child: Text(
                'ไม่มีกิจกรรมในระบบ',
                style: TextStyle(color: Colors.grey),
              ),
            )
          : ListView.builder(
              padding: const EdgeInsets.all(16),
              itemCount: _activities.length,
              itemBuilder: (context, index) {
                final item = _activities[index];
                return Container(
                  margin: const EdgeInsets.only(bottom: 12),
                  padding: const EdgeInsets.all(16),
                  decoration: BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.circular(16),
                    border: Border.all(color: const Color(0xFFE2E8F0)),
                    boxShadow: [
                      BoxShadow(
                        color: Colors.black.withValues(alpha: 0.04),
                        blurRadius: 8,
                        offset: const Offset(0, 3),
                      ),
                    ],
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          Container(
                            padding: const EdgeInsets.symmetric(
                              horizontal: 8,
                              vertical: 3,
                            ),
                            decoration: BoxDecoration(
                              color: AppColors.primaryPale,
                              borderRadius: BorderRadius.circular(6),
                            ),
                            child: Text(
                              item.category ?? 'ทั่วไป',
                              style: const TextStyle(
                                fontSize: 11,
                                color: AppColors.primaryDark,
                                fontWeight: FontWeight.bold,
                              ),
                            ),
                          ),
                          const Spacer(),
                          Text(
                            item.approvalStatus == 'approved'
                                ? 'อนุมัติแล้ว'
                                : item.approvalStatus == 'pending'
                                ? 'รออนุมัติ'
                                : 'ไม่อนุมัติ',
                            style: TextStyle(
                              color: AppColors.primary,
                              fontSize: 12,
                              fontWeight: FontWeight.bold,
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 8),
                      Text(
                        item.title,
                        style: const TextStyle(
                          fontWeight: FontWeight.bold,
                          fontSize: 15,
                        ),
                      ),
                      const SizedBox(height: 6),
                      Row(
                        children: [
                          const Icon(
                            Icons.location_on_outlined,
                            size: 14,
                            color: Colors.grey,
                          ),
                          const SizedBox(width: 4),
                          Expanded(
                            child: Text(
                              item.locationName ?? 'ไม่ระบุสถานที่',
                              style: const TextStyle(
                                fontSize: 12,
                                color: Colors.grey,
                              ),
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                            ),
                          ),
                        ],
                      ),
                      Wrap(
                        spacing: 8,
                        children: [
                          OutlinedButton.icon(
                            onPressed: () => _openForm(item),
                            icon: const Icon(Icons.edit, size: 16),
                            label: const Text('แก้ไข'),
                          ),
                          TextButton.icon(
                            onPressed: () => _delete(item),
                            icon: const Icon(Icons.delete, size: 16),
                            label: const Text('ลบ'),
                          ),
                        ],
                      ),
                      SwitchListTile(
                        contentPadding: EdgeInsets.zero,
                        title: const Text('เปิดเช็คอินด้วยตนเอง'),
                        value: item.selfCheckinEnabled,
                        onChanged: (enabled) async {
                          final result = await ApiService.managementPost(
                            'toggle_activity_checkin',
                            {
                              'activity_id': item.activityId.toString(),
                              'enabled': enabled ? '1' : '0',
                            },
                          );
                          if (!mounted) {
                            return;
                          }
                          if (result.isSuccess) _loadActivities();
                          ScaffoldMessenger.of(this.context).showSnackBar(
                            SnackBar(content: Text(result.message)),
                          );
                        },
                      ),
                      const SizedBox(height: 12),
                      Row(
                        children: [
                          Expanded(
                            child: OutlinedButton.icon(
                              style: OutlinedButton.styleFrom(
                                foregroundColor: AppColors.primary,
                                side: const BorderSide(
                                  color: AppColors.primary,
                                ),
                                shape: RoundedRectangleBorder(
                                  borderRadius: BorderRadius.circular(10),
                                ),
                              ),
                              icon: const Icon(Icons.people_outline, size: 16),
                              label: const Text(
                                'ดูผู้เข้าร่วม',
                                style: TextStyle(fontSize: 12),
                              ),
                              onPressed: () => _showParticipantsDialog(item),
                            ),
                          ),
                        ],
                      ),
                    ],
                  ),
                );
              },
            ),
    );
  }
}
