import 'package:flutter/material.dart';
import '../services/api_service.dart';
import '../services/mobile_document_service.dart';
import '../widgets/management_form_widgets.dart';

//เรียงช่องกรอกเหมือนฟอร์มร่างและแก้กิจกรรมบนเว็บ
class ActivityFormScreen extends StatefulWidget {
  final Map<String, dynamic>? activity;
  const ActivityFormScreen({super.key, this.activity});
  @override
  State<ActivityFormScreen> createState() => _ActivityFormScreenState();
}

class _ActivityFormScreenState extends State<ActivityFormScreen> {
  final _formKey = GlobalKey<FormState>();
  final _title = TextEditingController();
  final _description = TextEditingController();
  final _facebook = TextEditingController();
  final _start = TextEditingController();
  final _end = TextEditingController();
  final _location = TextEditingController();
  final _meeting = TextEditingController();
  final _meetingTime = TextEditingController();
  final _capacity = TextEditingController(text: '0');
  final _points = TextEditingController(text: '1');
  String _type = 'single_day';
  String _category = 'volunteer';
  bool _selfCheckin = false;
  bool _saving = false;
  int? _venueId;
  List<Map<String, dynamic>> _contacts = [];
  MobileSelectedFile? _cover;

  @override
  void initState() {
    super.initState();
    final old = widget.activity;
    if (old != null) {
      _title.text = old['title']?.toString() ?? '';
      _description.text = old['description']?.toString() ?? '';
      _facebook.text = old['facebook_link']?.toString() ?? '';
      _start.text = old['start_date']?.toString() ?? '';
      _end.text = old['end_date']?.toString() ?? '';
      _location.text = old['location_name']?.toString() ?? '';
      _meeting.text = old['meeting_point']?.toString() ?? '';
      _meetingTime.text = old['meeting_time']?.toString() ?? '';
      _capacity.text = old['max_participants'].toString();
      _points.text = old['points'].toString();
      _type = old['activity_type']?.toString() ?? 'single_day';
      _category = old['category']?.toString() ?? 'volunteer';
      _selfCheckin = old['self_checkin_enabled'].toString() == '1';
      _venueId = int.tryParse(old['venue_contact_user_id']?.toString() ?? '');
    }
    _loadContacts();
  }

  Future<void> _loadContacts() async {
    final result = await ApiService.managementGet('get_activity_contacts');
    if (!mounted) {
      return;
    }
    if (result.isSuccess) {
      setState(
        () => _contacts = List<Map<String, dynamic>>.from(result.data as List),
      );
    }
  }

  @override
  void dispose() {
    for (final controller in [
      _title,
      _description,
      _facebook,
      _start,
      _end,
      _location,
      _meeting,
      _meetingTime,
      _capacity,
      _points,
    ]) {
      controller.dispose();
    }
    super.dispose();
  }

  Future<void> _pickDate(TextEditingController controller) async {
    final old = DateTime.tryParse(controller.text);
    final day = await showDatePicker(
      context: context,
      initialDate: old ?? DateTime.now(),
      firstDate: DateTime(2000),
      lastDate: DateTime(2100),
    );
    if (day == null || !mounted) {
      return;
    }
    final time = await showTimePicker(
      context: context,
      initialTime: TimeOfDay.fromDateTime(old ?? DateTime.now()),
    );
    if (time == null || !mounted) {
      return;
    }
    final date = DateTime(day.year, day.month, day.day, time.hour, time.minute);
    controller.text = date
        .toIso8601String()
        .substring(0, 16)
        .replaceFirst('T', ' ');
  }

  Widget _dateInput(
    String label,
    TextEditingController controller, {
    bool required = false,
  }) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 14),
      child: TextFormField(
        controller: controller,
        readOnly: true,
        onTap: () => _pickDate(controller),
        decoration: InputDecoration(
          labelText: label,
          border: const OutlineInputBorder(),
          suffixIcon: IconButton(
            icon: const Icon(Icons.calendar_month),
            onPressed: () => _pickDate(controller),
          ),
        ),
        validator: (value) => required && (value == null || value.isEmpty)
            ? 'กรุณาเลือก$label'
            : null,
      ),
    );
  }

  Future<void> _pickCover() async {
    try {
      final file = await MobileDocumentService.pickFile();
      if (!mounted || file == null) {
        return;
      }
      setState(() => _cover = file);
    } catch (error) {
      if (!mounted) {
        return;
      }
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            'เลือกไฟล์ไม่ได้: ${MobileDocumentService.errorMessage(error)}',
          ),
        ),
      );
    }
  }

  //ส่งข้อมูลตามช่องเว็บ ถ้าเป็นการแก้จะส่งรหัสเดิมไปด้วย ไม่สร้างกิจกรรมซ้ำ
  Future<void> _save() async {
    if (!_formKey.currentState!.validate() || _saving) {
      return;
    }
    if (int.tryParse(_capacity.text) == null ||
        int.parse(_capacity.text) < 0 ||
        int.tryParse(_points.text) == null ||
        int.parse(_points.text) < 1) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text(
            'จำนวนคนต้องไม่ติดลบ และแต้มต้องเป็นจำนวนเต็มตั้งแต่ 1',
          ),
        ),
      );
      return;
    }
    setState(() => _saving = true);
    final result = await ApiService.managementPost(
      'save_activity',
      {
        'activity_id': widget.activity?['activity_id']?.toString() ?? '0',
        'title': _title.text.trim(),
        'description': _description.text.trim(),
        'facebook_link': _facebook.text.trim(),
        'activity_type': _type,
        'category': _category,
        'start_date': _start.text,
        'end_date': _end.text,
        'location_name': _location.text.trim(),
        'meeting_point': _meeting.text.trim(),
        'meeting_time': _meetingTime.text,
        'max_participants': _capacity.text,
        'points': _points.text,
        'self_checkin_enabled': _selfCheckin ? '1' : '0',
        'venue_contact_user_id': _venueId?.toString() ?? '0',
      },
      fileField: _cover == null ? null : 'cover_image',
      fileName: _cover?.name,
      fileBytes: _cover?.bytes,
    );
    if (!mounted) {
      return;
    }
    setState(() => _saving = false);
    ScaffoldMessenger.of(
      context,
    ).showSnackBar(SnackBar(content: Text(result.message)));
    if (result.isSuccess) Navigator.pop(context, true);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF1F8E9),
      appBar: AppBar(
        title: Text(
          widget.activity == null
              ? 'ร่างกิจกรรมใหม่ (ส่งขออนุมัติ)'
              : 'แก้ไขกิจกรรม',
        ),
      ),
      body: Form(
        key: _formKey,
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            managementCard('ข้อมูลกิจกรรม', [
              managementInput('ชื่อกิจกรรม', _title, required: true),
              managementInput('รายละเอียดกิจกรรม', _description, lines: 3),
              OutlinedButton.icon(
                style: managementOutlineStyle(),
                onPressed: _saving ? null : _pickCover,
                icon: const Icon(Icons.image),
                label: Text(_cover?.name ?? 'เลือกรูปปกกิจกรรม (ถ้ามี)'),
              ),
              const SizedBox(height: 14),
              managementInput('ลิงก์ Facebook ของกิจกรรม (ถ้ามี)', _facebook),
              managementDropdown('รูปแบบกิจกรรม', _type, {
                'single_day': 'กิจกรรม 1 วัน',
                'multi_day': 'กิจกรรมหลายวัน',
              }, (value) => setState(() => _type = value!)),
              managementDropdown(
                'หมวดหมู่กิจกรรม',
                _category,
                {
                  'volunteer': 'กิจกรรมจิตอาสา',
                  'sport': 'กิจกรรมกีฬา',
                  'byaniyan': 'กิจกรรมบายเนียร์',
                  'other': 'กิจกรรมทั่วไป',
                },
                (value) => setState(() => _category = value!),
              ),
              _dateInput('วันที่และเวลาเริ่มกิจกรรม', _start, required: true),
              _dateInput('วันที่และเวลาสิ้นสุดกิจกรรม', _end),
              managementInput('สถานที่จัดกิจกรรม', _location, required: true),
              managementInput('จุดนัดพบ / จุดรวมพล', _meeting),
              _dateInput('เวลานัดหมายออกเดินทาง', _meetingTime),
              managementInput(
                'จำนวนที่รับ (0 = ไม่จำกัด)',
                _capacity,
                required: true,
                number: true,
              ),
              managementInput(
                'แต้มจิตอาสา',
                _points,
                required: true,
                number: true,
              ),
              DropdownButtonFormField<int>(
                value:
                    _contacts.any(
                      (person) =>
                          person['user_id'].toString() == _venueId?.toString(),
                    )
                    ? _venueId
                    : null,
                isExpanded: true,
                decoration: const InputDecoration(
                  labelText: 'ผู้ประสานงานสถานที่',
                  border: OutlineInputBorder(),
                ),
                items: [
                  const DropdownMenuItem<int>(value: 0, child: Text('ไม่ระบุ')),
                  ..._contacts.map(
                    (person) => DropdownMenuItem(
                      value: int.parse(person['user_id'].toString()),
                      child: Text(person['name'].toString()),
                    ),
                  ),
                ],
                onChanged: (value) => setState(() => _venueId = value),
              ),
              SwitchListTile(
                contentPadding: EdgeInsets.zero,
                title: const Text('เปิดเช็คอินด้วยตนเอง'),
                value: _selfCheckin,
                onChanged: (value) => setState(() => _selfCheckin = value),
              ),
              ElevatedButton(
                style: managementButtonStyle(),
                onPressed: _saving ? null : _save,
                child: Text(
                  _saving
                      ? 'กำลังบันทึก...'
                      : widget.activity == null
                      ? 'ส่งขออนุมัติ'
                      : 'บันทึกการแก้ไข',
                ),
              ),
            ]),
          ],
        ),
      ),
    );
  }
}
