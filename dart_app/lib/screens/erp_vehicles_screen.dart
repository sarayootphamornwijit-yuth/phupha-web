import 'package:flutter/material.dart';
import '../services/api_service.dart';
import '../widgets/management_form_widgets.dart';

//เลือกกิจกรรมก่อน แล้วเตรียมจุดนัดพบและรถได้ระหว่างรออาจารย์อนุมัติ เหมือนเว็บ
class ErpVehiclesScreen extends StatefulWidget {
  const ErpVehiclesScreen({super.key});
  @override
  State<ErpVehiclesScreen> createState() => _ErpVehiclesScreenState();
}

class _ErpVehiclesScreenState extends State<ErpVehiclesScreen> {
  final _meeting = TextEditingController();
  final _survey = TextEditingController();
  final _plate = TextEditingController();
  final _capacity = TextEditingController();
  final _driver = TextEditingController();
  final _phone = TextEditingController();
  final _form = GlobalKey<FormState>();
  String _type = 'รถตู้โดยสาร';
  int? _activityId;
  List<Map<String, dynamic>> _vehicles = [];
  bool _loading = false;
  bool _saving = false;
  String? _error;
  int _loadNumber = 0;

  @override
  void dispose() {
    for (final controller in [
      _meeting,
      _survey,
      _plate,
      _capacity,
      _driver,
      _phone,
    ]) {
      controller.dispose();
    }
    super.dispose();
  }

  Future<void> _load(int id) async {
    final loadNumber = ++_loadNumber;
    setState(() {
      _activityId = id;
      _loading = true;
      _error = null;
    });
    final result = await ApiService.managementGet(
      'get_vehicle_management',
      values: {'activity_id': id.toString()},
    );
    //หากผู้ใช้เลือกกิจกรรมใหม่แล้ว ไม่เอาคำตอบของกิจกรรมเก่ามาเขียนทับ
    if (!mounted || loadNumber != _loadNumber) {
      return;
    }
    setState(() {
      _loading = false;
      if (result.isSuccess) {
        _vehicles = List<Map<String, dynamic>>.from(result.data['vehicles']);
        _meeting.text =
            result.data['activity']['meeting_point']?.toString() ?? '';
        _survey.text =
            result.data['activity']['survey_details']?.toString() ?? '';
      } else {
        _error = result.message;
      }
    });
  }

  Future<void> _save(String action, Map<String, String> fields) async {
    if (_activityId == null || _saving) {
      return;
    }
    setState(() => _saving = true);
    final id = _activityId!;
    final result = await ApiService.managementPost(action, {
      'activity_id': id.toString(),
      ...fields,
    });
    if (!mounted) {
      return;
    }
    setState(() => _saving = false);
    ScaffoldMessenger.of(
      context,
    ).showSnackBar(SnackBar(content: Text(result.message)));
    if (result.isSuccess) {
      if (action == 'add_vehicle') {
        _plate.clear();
        _capacity.clear();
        _driver.clear();
        _phone.clear();
      }
      if (_activityId == id) _load(id);
    }
  }

  Future<void> _add() async {
    if (!_form.currentState!.validate()) {
      return;
    }
    final capacity = int.tryParse(_capacity.text);
    if (capacity == null || capacity < 1) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('ที่นั่งต้องเป็นจำนวนเต็มตั้งแต่ 1')),
      );
      return;
    }
    await _save('add_vehicle', {
      'vehicle_type': _type,
      'license_plate': _plate.text.trim(),
      'capacity': _capacity.text,
      'driver_name': _driver.text.trim(),
      'driver_phone': _phone.text.trim(),
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF1F8E9),
      appBar: AppBar(title: const Text('จัดการยานพาหนะ')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          ManagementActivitySelector(menu: 'vehicles', onChanged: _load),
          const SizedBox(height: 16),
          if (_loading) const LinearProgressIndicator(),
          if (_error != null) Text(_error!),
          if (_activityId != null && !_loading && _error == null) ...[
            managementCard('จุดนัดพบและข้อมูลสำรวจพื้นที่', [
              managementInput('จุดนัดพบ / จุดรวมพล', _meeting, required: true),
              managementInput('ข้อมูลสำรวจพื้นที่', _survey, lines: 3),
              ElevatedButton(
                style: managementButtonStyle(),
                onPressed: _saving
                    ? null
                    : () => _save('save_logistics', {
                        'meeting_point': _meeting.text.trim(),
                        'survey_details': _survey.text.trim(),
                      }),
                child: const Text('บันทึกแผนการเดินทาง'),
              ),
            ]),
            Form(
              key: _form,
              child: managementCard('เพิ่มยานพาหนะ', [
                managementDropdown('ประเภทยานพาหนะ', _type, {
                  'รถตู้โดยสาร': 'รถตู้โดยสาร',
                  'รถกระบะ (บรรทุกคน/ของ)': 'รถกระบะ (บรรทุกคน/ของ)',
                  'รถยนต์ส่วนตัว (เก๋ง)': 'รถยนต์ส่วนตัว (เก๋ง)',
                  'รถบัสมหาวิทยาลัย': 'รถบัสมหาวิทยาลัย',
                }, (value) => setState(() => _type = value!)),
                managementInput('ป้ายทะเบียน', _plate, required: true),
                managementInput(
                  'ความจุที่นั่ง',
                  _capacity,
                  required: true,
                  number: true,
                ),
                managementInput('ชื่อคนขับ', _driver, required: true),
                managementInput('เบอร์โทรคนขับ', _phone),
                ElevatedButton(
                  style: managementButtonStyle(),
                  onPressed: _saving ? null : _add,
                  child: const Text('เพิ่มรถ'),
                ),
              ]),
            ),
            managementCard('ยานพาหนะของกิจกรรม', [
              if (_vehicles.isEmpty) const Text('ยังไม่มีข้อมูลรถ'),
              ..._vehicles.map(
                (vehicle) => ListTile(
                  contentPadding: EdgeInsets.zero,
                  title: Text(
                    '${vehicle['vehicle_type']} • ${vehicle['license_plate']}',
                  ),
                  subtitle: Text(
                    'คนขับ ${vehicle['driver_name']} ${vehicle['driver_phone'] ?? ''}\nจอง ${vehicle['booked_seats']}/${vehicle['capacity']} ที่นั่ง',
                  ),
                  trailing: IconButton(
                    tooltip: 'ลบรถ',
                    icon: const Icon(Icons.delete_outline),
                    onPressed: _saving
                        ? null
                        : () async {
                            if (await confirmManagementDelete(
                              context,
                              'ลบรถทะเบียน ${vehicle['license_plate']} หรือไม่?',
                            )) {
                              await _save('delete_vehicle', {
                                'vehicle_id': vehicle['vehicle_id'].toString(),
                              });
                            }
                          },
                  ),
                ),
              ),
            ]),
          ],
        ],
      ),
    );
  }
}
