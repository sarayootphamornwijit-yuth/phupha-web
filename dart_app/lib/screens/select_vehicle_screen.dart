import 'package:flutter/material.dart';
import '../constants/app_colors.dart';
import '../models/vehicle_model.dart';
import '../services/api_service.dart';
import '../services/session_manager.dart';
import '../widgets/custom_button.dart';

// select_vehicle_screen.dart
// หน้าจอเลือกรูปแบบการเดินทางและยานพาหนะของชมรม
// สอดคล้องกับ select_vehicle.php ของเว็บเดิม
class SelectVehicleScreen extends StatefulWidget {
  final int activityId;
  final String activityTitle;

  const SelectVehicleScreen({
    super.key,
    required this.activityId,
    required this.activityTitle,
  });

  @override
  State<SelectVehicleScreen> createState() => _SelectVehicleScreenState();
}

class _SelectVehicleScreenState extends State<SelectVehicleScreen> {
  String _travelType = 'own_travel'; // 'own_travel' หรือ 'club_vehicle'
  int? _selectedVehicleId;
  List<VehicleModel> _vehicles = [];
  bool _isLoading = true;
  bool _isSaving = false;

  @override
  void initState() {
    super.initState();
    _fetchVehicles();
  }

  // ดึงรายการรถของกิจกรรมจาก API
  Future<void> _fetchVehicles() async {
    setState(() {
      _isLoading = true;
    });

    final response = await ApiService.getVehicles(widget.activityId);

    if (!mounted) return;

    setState(() {
      _vehicles = response.data ?? [];
      _isLoading = false;
    });
  }

  // บันทึกการเลือกเดินทาง
  Future<void> _handleSaveTravel() async {
    final user = await SessionManager.getUser();
    if (!mounted || user == null) return;

    if (_travelType == 'club_vehicle' && (_selectedVehicleId == null || _selectedVehicleId! <= 0)) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('กรุณาเลือกรถของชมรมที่ต้องการโดยสาร'), backgroundColor: AppColors.danger),
      );
      return;
    }

    setState(() {
      _isSaving = true;
    });

    final response = await ApiService.selectVehicle(
      userId: user.userId,
      activityId: widget.activityId,
      travelType: _travelType,
      vehicleId: _selectedVehicleId ?? 0,
    );

    if (!mounted) return;

    setState(() {
      _isSaving = false;
    });

    if (response.isSuccess) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(response.message), backgroundColor: AppColors.success),
      );
      Navigator.pop(context); // ย้อนกลับไปยังหน้าก่อนหน้า
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
          'เลือกการเดินทาง',
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
          : SingleChildScrollView(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  // กล่องข้อมูลกิจกรรม
                  Container(
                    padding: const EdgeInsets.all(16),
                    decoration: BoxDecoration(
                      color: Colors.white,
                      borderRadius: BorderRadius.circular(14),
                      border: Border.all(color: AppColors.cardBorder),
                    ),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Text(
                          'กิจกรรม',
                          style: TextStyle(fontSize: 12, color: AppColors.textMuted),
                        ),
                        const SizedBox(height: 4),
                        Text(
                          widget.activityTitle,
                          style: const TextStyle(fontSize: 16, fontWeight: FontWeight.bold, color: AppColors.primaryDark),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 16),

                  // ตัวเลือกที่ 1: เดินทางด้วยตนเอง
                  Card(
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(14),
                      side: BorderSide(
                        color: _travelType == 'own_travel' ? AppColors.primary : AppColors.cardBorder,
                        width: _travelType == 'own_travel' ? 2 : 1,
                      ),
                    ),
                    child: ListTile(
                      leading: Icon(
                        _travelType == 'own_travel' ? Icons.radio_button_checked : Icons.radio_button_off,
                        color: _travelType == 'own_travel' ? AppColors.primary : AppColors.textMuted,
                      ),
                      title: const Text(
                        'เดินทางด้วยตนเอง',
                        style: TextStyle(fontWeight: FontWeight.bold, fontSize: 15),
                      ),
                      subtitle: const Text(
                        'ใช้รถจักรยานยนต์ รถยนต์ส่วนบุคคล หรือเดินทางไปพบ ณ จุดนัดพบด้วยตนเอง',
                        style: TextStyle(fontSize: 12, color: AppColors.textMuted),
                      ),
                      onTap: () {
                        setState(() {
                          _travelType = 'own_travel';
                        });
                      },
                    ),
                  ),
                  const SizedBox(height: 12),

                  // ตัวเลือกที่ 2: เดินทางโดยรถของชมรม
                  Card(
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(14),
                      side: BorderSide(
                        color: _travelType == 'club_vehicle' ? AppColors.primary : AppColors.cardBorder,
                        width: _travelType == 'club_vehicle' ? 2 : 1,
                      ),
                    ),
                    child: Column(
                      children: [
                        ListTile(
                          leading: Icon(
                            _travelType == 'club_vehicle' ? Icons.radio_button_checked : Icons.radio_button_off,
                            color: _travelType == 'club_vehicle' ? AppColors.primary : AppColors.textMuted,
                          ),
                          title: const Text(
                            'เดินทางโดยรถของชมรม',
                            style: TextStyle(fontWeight: FontWeight.bold, fontSize: 15),
                          ),
                          subtitle: const Text(
                            'โดยสารรถตู้หรือรถบัสที่ชมรมจัดเตรียมไว้ให้',
                            style: TextStyle(fontSize: 12, color: AppColors.textMuted),
                          ),
                          onTap: () {
                            setState(() {
                              _travelType = 'club_vehicle';
                            });
                          },
                        ),

                        // หากเลือกเดินทางโดยรถชมรม ให้แสดงรายการรถ
                        if (_travelType == 'club_vehicle') ...[
                          const Divider(height: 1),
                          if (_vehicles.isEmpty)
                            const Padding(
                              padding: EdgeInsets.all(16),
                              child: Text(
                                'กิจกรรมนี้ไม่มีรถของชมรมจัดเตรียมไว้',
                                style: TextStyle(color: AppColors.danger, fontSize: 13),
                              ),
                            )
                          else
                            ListView.builder(
                              shrinkWrap: true,
                              physics: const NeverScrollableScrollPhysics(),
                              itemCount: _vehicles.length,
                              itemBuilder: (context, index) {
                                final v = _vehicles[index];
                                final isFull = v.availableSeats <= 0;
                                final isSelected = _selectedVehicleId == v.vehicleId;

                                return Container(
                                  margin: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                                  decoration: BoxDecoration(
                                    color: isSelected ? AppColors.primaryPale : Colors.white,
                                    borderRadius: BorderRadius.circular(10),
                                    border: Border.all(
                                      color: isSelected ? AppColors.primary : AppColors.cardBorder,
                                    ),
                                  ),
                                  child: ListTile(
                                    leading: Icon(
                                      isSelected ? Icons.radio_button_checked : Icons.radio_button_off,
                                      color: isSelected ? AppColors.primary : (isFull ? Colors.grey : AppColors.textMuted),
                                    ),
                                    onTap: isFull
                                        ? null
                                        : () {
                                            setState(() {
                                              _selectedVehicleId = v.vehicleId;
                                            });
                                          },
                                    title: Row(
                                      children: [
                                        Text(
                                          '${v.vehicleType} (${v.licensePlate})',
                                          style: TextStyle(
                                            fontWeight: FontWeight.bold,
                                            fontSize: 14,
                                            color: isFull ? Colors.grey : AppColors.textMain,
                                          ),
                                        ),
                                        const Spacer(),
                                        Container(
                                          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                                          decoration: BoxDecoration(
                                            color: isFull ? AppColors.danger.withValues(alpha: 0.1) : AppColors.success.withValues(alpha: 0.1),
                                            borderRadius: BorderRadius.circular(12),
                                          ),
                                          child: Text(
                                            isFull ? 'ที่นั่งเต็ม' : 'ว่าง ${v.availableSeats}/${v.capacity}',
                                            style: TextStyle(
                                              fontSize: 11,
                                              fontWeight: FontWeight.bold,
                                              color: isFull ? AppColors.danger : AppColors.success,
                                            ),
                                          ),
                                        ),
                                      ],
                                    ),
                                    subtitle: Text(
                                      'คนขับ: ${v.driverName} ${v.driverPhone != null ? "(${v.driverPhone})" : ""}',
                                      style: const TextStyle(fontSize: 12, color: AppColors.textMuted),
                                    ),
                                  ),
                                );
                              },
                            ),
                          const SizedBox(height: 12),
                        ],
                      ],
                    ),
                  ),
                  const SizedBox(height: 28),

                  // ปุ่มบันทึกการเดินทาง
                  CustomButton(
                    text: 'บันทึกรูปแบบการเดินทาง',
                    icon: Icons.check,
                    onPressed: _handleSaveTravel,
                    isLoading: _isSaving,
                  ),
                ],
              ),
            ),
    );
  }
}
