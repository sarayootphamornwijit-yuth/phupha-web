import 'package:flutter/material.dart';
import '../services/api_service.dart';
import '../services/mobile_document_service.dart';
import '../widgets/management_form_widgets.dart';

//แสดงผลจากข้อมูลกิจกรรมจริง ไม่เพิ่มขั้นตอนส่งรายงาน เพราะเว็บสรุปให้อัตโนมัติ
class ErpReportsScreen extends StatefulWidget {
  const ErpReportsScreen({super.key});
  @override
  State<ErpReportsScreen> createState() => _ErpReportsScreenState();
}

class _ErpReportsScreenState extends State<ErpReportsScreen> {
  Map<String, dynamic>? _data;
  int? _activityId;
  bool _loading = false;
  bool _printing = false;
  String? _error;
  int _loadNumber = 0;

  //ข้อมูลรายงานมาจากกิจกรรมที่เลือก ไม่เอาสถิติทุกกิจกรรมมาปนกัน
  Future<void> _load(int id) async {
    final number = ++_loadNumber;
    setState(() {
      _activityId = id;
      _loading = true;
      _error = null;
    });
    final result = await ApiService.managementGet(
      'get_activity_report',
      values: {'activity_id': id.toString()},
    );
    if (!mounted || number != _loadNumber) {
      return;
    }
    setState(() {
      _loading = false;
      if (result.isSuccess) {
        _data = Map<String, dynamic>.from(result.data);
      } else {
        _error = result.message;
      }
    });
  }

  //ใช้เอกสารพิมพ์หน้าเดียวกับเว็บ จึงได้คอลัมน์และสถานะเช็คอินแบบเดียวกัน
  Future<void> _print() async {
    if (_activityId == null || _printing) {
      return;
    }
    setState(() => _printing = true);
    try {
      await MobileDocumentService.printDocument(
        'print_participants.php',
        'รายชื่อผู้เข้าร่วมกิจกรรม',
        activityId: _activityId,
      );
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              'เปิดเอกสารไม่ได้: ${MobileDocumentService.errorMessage(error)}',
            ),
          ),
        );
      }
    } finally {
      if (mounted) {
        setState(() => _printing = false);
      }
    }
  }

  String _decimal(dynamic value) =>
      (num.tryParse(value.toString()) ?? 0).toStringAsFixed(2);

  @override
  Widget build(BuildContext context) {
    final participants = List<Map<String, dynamic>>.from(
      _data?['participants'] ?? [],
    );
    final checked = participants
        .where(
          (row) =>
              row['status'] == 'checked_in' || row['status'] == 'checked_out',
        )
        .length;
    final ownTravel = participants
        .where(
          (row) =>
              row['travel_method'] == 'own_travel' ||
              row['travel_method'] == 'เดินทางเอง',
        )
        .length;
    final clubTravel = participants
        .where(
          (row) =>
              row['vehicle_id'] != null ||
              row['travel_method'] == 'club_vehicle',
        )
        .length;
    final questions = [
      'การประชาสัมพันธ์กิจกรรม',
      'สถานที่และการจัดการ',
      'ความเหมาะสมของเวลา',
      'การดำเนินกิจกรรมและเนื้อหา',
      'สวัสดิการอาหารและเครื่องดื่ม',
      'ความพึงพอใจในภาพรวม',
    ];
    return Scaffold(
      backgroundColor: const Color(0xFFF1F8E9),
      appBar: AppBar(title: const Text('สรุปรายงานผล')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          ManagementActivitySelector(menu: 'reports', onChanged: _load),
          const SizedBox(height: 16),
          if (_loading) const LinearProgressIndicator(),
          if (_error != null) Text(_error!),
          if (_data != null && !_loading && _error == null) ...[
            managementCard(_data!['activity']['title'].toString(), [
              Text('วันที่ ${_data!['activity']['start_date']}'),
              Text('สถานที่ ${_data!['activity']['location_name'] ?? '-'}'),
              const SizedBox(height: 12),
              OutlinedButton.icon(
                style: managementOutlineStyle(),
                onPressed: _printing ? null : _print,
                icon: const Icon(Icons.print),
                label: Text(
                  _printing
                      ? 'กำลังเปิดเอกสาร...'
                      : 'พิมพ์รายชื่อผู้เข้าร่วม / บันทึก PDF (A4)',
                ),
              ),
            ]),
            managementCard('1. สรุปสถิติผู้เข้าร่วมกิจกรรม', [
              Text('ลงทะเบียนทั้งหมด ${participants.length} คน'),
              Text('เช็คอินแล้ว $checked คน'),
              Text('ยังไม่เช็คอิน ${participants.length - checked} คน'),
            ]),
            managementCard('2. สรุปรูปแบบการเดินทาง', [
              Text('เดินทางด้วยตนเอง $ownTravel คน'),
              Text('เดินทางโดยรถชมรม $clubTravel คน'),
              const SizedBox(height: 12),
              ...List<Map<String, dynamic>>.from(_data!['vehicles']).map(
                (row) => ListTile(
                  contentPadding: EdgeInsets.zero,
                  title: Text(
                    '${row['vehicle_type']} • ${row['license_plate']}',
                  ),
                  subtitle: Text(
                    'คนขับ ${row['driver_name']} ${row['driver_phone'] ?? ''}\nผู้โดยสาร ${row['booked_seats']}/${row['capacity']} คน',
                  ),
                ),
              ),
            ]),
            managementCard('3. สรุปผลการประเมินความพึงพอใจ', [
              Text('ผู้ประเมิน ${_data!['evaluation']['count']} คน'),
              Text(
                'คะแนนเฉลี่ย ${_decimal(_data!['evaluation']['average'])} / 5',
              ),
              for (int index = 0; index < questions.length; index++)
                Padding(
                  padding: const EdgeInsets.only(top: 8),
                  child: Text(
                    '${index + 1}. ${questions[index]}: ${_decimal(_data!['evaluation']['q${index + 1}'])}',
                  ),
                ),
            ]),
            managementCard('4. สรุปสถานะงบประมาณ', [
              Text('รายรับ ${_decimal(_data!['income'])} บาท'),
              Text('รายจ่าย ${_decimal(_data!['expense'])} บาท'),
              Text('คงเหลือ ${_decimal(_data!['balance'])} บาท'),
            ]),
            managementCard('รายชื่อผู้เข้าร่วม', [
              ...participants.map(
                (row) => ListTile(
                  contentPadding: EdgeInsets.zero,
                  title: Text('${row['student_id'] ?? '-'} ${row['name']}'),
                  subtitle: Text(
                    '${row['faculty'] ?? '-'}\n${row['major'] ?? '-'}',
                  ),
                  trailing: Text(
                    row['status'] == 'checked_in' ||
                            row['status'] == 'checked_out'
                        ? 'เช็คอินแล้ว'
                        : 'ยังไม่เช็คอิน',
                  ),
                ),
              ),
              if (participants.isEmpty) const Text('ยังไม่มีผู้ลงทะเบียน'),
            ]),
          ],
        ],
      ),
    );
  }
}
