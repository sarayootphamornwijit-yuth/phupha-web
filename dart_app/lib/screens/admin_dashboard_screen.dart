import 'package:flutter/material.dart';
import '../constants/app_colors.dart';
import '../services/api_service.dart';
import '../widgets/management_form_widgets.dart';

//ใช้หัวข้อและข้อมูลชุดเดียวกับแดชบอร์ดเว็บ สำหรับผู้ดูแลระบบเท่านั้น
class AdminDashboardScreen extends StatefulWidget {
  const AdminDashboardScreen({super.key});
  @override
  State<AdminDashboardScreen> createState() => _AdminDashboardScreenState();
}

class _AdminDashboardScreenState extends State<AdminDashboardScreen> {
  final _threshold = TextEditingController();
  Map<String, dynamic>? _data;
  String? _error;
  bool _saving = false;

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _threshold.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    final result = await ApiService.managementGet('get_admin_dashboard');
    if (!mounted) return;
    setState(() {
      if (result.isSuccess) {
        _data = Map<String, dynamic>.from(result.data);
        _threshold.text = _data!['threshold'].toString();
        _error = null;
      } else {
        _error = result.message;
      }
    });
  }

  Future<void> _saveThreshold() async {
    final points = int.tryParse(_threshold.text);
    if (points == null || points < 1) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('เกณฑ์คะแนนต้องเป็นจำนวนเต็มมากกว่า 0')),
      );
      return;
    }
    if (_saving) return;
    setState(() => _saving = true);
    final result = await ApiService.managementPost(
      'update_certificate_threshold',
      {'threshold': points.toString()},
    );
    if (!mounted) return;
    setState(() => _saving = false);
    ScaffoldMessenger.of(
      context,
    ).showSnackBar(SnackBar(content: Text(result.message)));
    if (result.isSuccess) _load();
  }

  Widget _stat(String label, dynamic value) {
    return Expanded(
      child: Card(
        child: Padding(
          padding: const EdgeInsets.all(12),
          child: Column(
            children: [
              Text(
                label,
                textAlign: TextAlign.center,
                style: const TextStyle(fontSize: 12),
              ),
              const SizedBox(height: 8),
              Text(
                value.toString(),
                style: const TextStyle(
                  fontSize: 22,
                  fontWeight: FontWeight.bold,
                  color: AppColors.primaryDark,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  //แสดงชื่อและจำนวนจากฐานข้อมูล โดยไม่ต้องวาดแถบสัดส่วนเพิ่ม
  Widget _statisticText(String label, int count) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 16),
      child: Text('$label: $count'),
    );
  }

  @override
  Widget build(BuildContext context) {
    const labels = {
      'approved': 'อนุมัติแล้ว',
      'pending': 'รออนุมัติ',
      'rejected': 'ถูกตีกลับ',
    };
    return Scaffold(
      backgroundColor: const Color(0xFFF1F8E9),
      appBar: AppBar(title: const Text('แดชบอร์ดระบบ')),
      body: _error != null
          ? Center(child: Text(_error!))
          : _data == null
          ? const Center(child: CircularProgressIndicator())
          : ListView(
              padding: const EdgeInsets.all(16),
              children: [
                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    _stat('สมาชิก', _data!['users']),
                    _stat('จำนวนกิจกรรม', _data!['activities']),
                    _stat('สมาชิกใหม่ (เดือนนี้)', _data!['new_users']),
                  ],
                ),
                const SizedBox(height: 16),
                managementCard('เกณฑ์คะแนนจิตอาสาสำหรับรับเกียรติบัตร', [
                  managementInput(
                    'เกณฑ์คะแนนสะสมขั้นต่ำ',
                    _threshold,
                    number: true,
                    required: true,
                  ),
                  ElevatedButton(
                    style: managementButtonStyle(),
                    onPressed: _saving ? null : _saveThreshold,
                    child: const Text('บันทึกเกณฑ์คะแนน'),
                  ),
                ]),
                managementCard('สถิติการจัดกิจกรรมตามสถานะ', [
                  for (final row in List<Map<String, dynamic>>.from(
                    _data!['statuses'],
                  ))
                    _statisticText(
                      labels[row['status_type']] ?? 'ไม่ระบุ',
                      int.parse(row['count'].toString()),
                    ),
                ]),
                managementCard('สถิติระดับการมีส่วนร่วมของสมาชิก', [
                  _statisticText(
                    'มาก (>3 ครั้ง)',
                    int.parse(_data!['active'].toString()),
                  ),
                  _statisticText(
                    'ปานกลาง (1-3 ครั้ง)',
                    int.parse(_data!['regular'].toString()),
                  ),
                  _statisticText(
                    'น้อย (0 ครั้ง)',
                    int.parse(_data!['inactive'].toString()),
                  ),
                ]),
              ],
            ),
    );
  }
}
