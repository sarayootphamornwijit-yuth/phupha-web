import 'package:flutter/material.dart';
import '../services/api_service.dart';
import '../services/session_manager.dart';
import '../services/mobile_document_service.dart';
import '../widgets/management_form_widgets.dart';
import '../widgets/profile_image.dart';
import '../models/user_model.dart';

class EditProfileScreen extends StatefulWidget {
  const EditProfileScreen({super.key});
  @override
  State<EditProfileScreen> createState() => _EditProfileScreenState();
}

class _EditProfileScreenState extends State<EditProfileScreen> {
  final _form = GlobalKey<FormState>();
  final _name = TextEditingController();
  final _phone = TextEditingController();
  final _email = TextEditingController();
  final _age = TextEditingController();
  final _location = TextEditingController();
  final _interests = TextEditingController();
  final _occupation = TextEditingController();
  final _studentId = TextEditingController();
  List<Map<String, dynamic>> _faculties = [];
  List<Map<String, dynamic>> _majors = [];
  int? _facultyId;
  int? _majorId;
  bool _student = false;
  bool _loading = true;
  bool _loadingMajors = false;
  bool _saving = false;
  String? _error;
  MobileSelectedFile? _image;
  String? _imagePath;

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    for (final controller in [
      _name,
      _phone,
      _email,
      _age,
      _location,
      _interests,
      _occupation,
      _studentId,
    ]) {
      controller.dispose();
    }
    super.dispose();
  }

  Future<void> _load() async {
    //อ่านข้อมูลสดจากเซิร์ฟเวอร์ก่อนแก้ ไม่ใช้ข้อมูลเก่าที่ค้างในเครื่อง
    final result = await ApiService.managementGet('get_profile');
    if (!mounted) {
      return;
    }
    if (!result.isSuccess) {
      setState(() {
        _error = result.message;
        _loading = false;
      });
      return;
    }
    final data = result.data;
    _imagePath = UserModel.fromJson(
      Map<String, dynamic>.from(data),
    ).profilePicture;
    _name.text = data['name']?.toString() ?? '';
    _phone.text = data['phone']?.toString() ?? '';
    _email.text = data['email']?.toString() ?? '';
    _age.text = data['age']?.toString() ?? '';
    _location.text = data['location']?.toString() ?? '';
    _interests.text = data['interests']?.toString() ?? '';
    _occupation.text = data['occupation']?.toString() ?? '';
    _studentId.text = data['student_id']?.toString() ?? '';
    _student = data['user_type'] == 'student';
    if (_student) {
      _faculties = await ApiService.getFaculties();
      _facultyId = int.tryParse(data['faculty_id']?.toString() ?? '');
      _majorId = int.tryParse(data['major_id']?.toString() ?? '');
      if (_facultyId != null) _majors = await ApiService.getMajors(_facultyId!);
    }
    if (mounted) {
      setState(() => _loading = false);
    }
  }

  Future<void> _changeFaculty(int? value) async {
    setState(() {
      _facultyId = value;
      _majorId = null;
      _majors = [];
      _loadingMajors = true;
    });
    final majors = value == null
        ? <Map<String, dynamic>>[]
        : await ApiService.getMajors(value);
    if (mounted && _facultyId == value) {
      setState(() {
        _majors = majors;
        _loadingMajors = false;
      });
    }
  }

  Future<void> _pickImage() async {
    try {
      final file = await MobileDocumentService.pickFile();
      if (mounted && file != null) {
        setState(() => _image = file);
      }
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              'เลือกรูปไม่ได้: ${MobileDocumentService.errorMessage(error)}',
            ),
          ),
        );
      }
    }
  }

  Future<void> _save() async {
    if (_saving || !_form.currentState!.validate()) {
      return;
    }
    setState(() => _saving = true);
    final result = await ApiService.managementPost(
      'update_profile_details',
      {
        'phone': _phone.text.trim(),
        'email': _email.text.trim(),
        'age': _age.text,
        'location': _location.text.trim(),
        'interests': _interests.text.trim(),
        'occupation': _occupation.text.trim(),
        'student_id': _studentId.text.trim(),
        'faculty_id': _facultyId?.toString() ?? '0',
        'major_id': _majorId?.toString() ?? '0',
      },
      fileField: _image == null ? null : 'image',
      fileName: _image?.name,
      fileBytes: _image?.bytes,
    );
    if (!mounted) {
      return;
    }
    setState(() => _saving = false);
    ScaffoldMessenger.of(
      context,
    ).showSnackBar(SnackBar(content: Text(result.message)));
    if (result.isSuccess) {
      final fresh = await ApiService.getProfile();
      if (fresh.isSuccess && fresh.data != null) {
        await SessionManager.saveUser(fresh.data!);
      }
      if (mounted) Navigator.pop(context);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF1F8E9),
      appBar: AppBar(title: const Text('แก้ไขข้อมูลส่วนตัว')),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
          ? Center(child: Text(_error!))
          : Form(
              key: _form,
              child: ListView(
                padding: const EdgeInsets.all(16),
                children: [
                  managementCard('ข้อมูลส่วนตัว', [
                    Center(
                      child: CircleAvatar(
                        radius: 60,
                        child: _image == null
                            ? ProfileImage(
                                path: _imagePath,
                                size: 120,
                                fallback: const Icon(Icons.person, size: 48),
                              )
                            : ClipOval(
                                child: Image.memory(
                                  _image!.bytes,
                                  width: 120,
                                  height: 120,
                                  fit: BoxFit.cover,
                                  errorBuilder: (context, error, stackTrace) =>
                                      const Icon(Icons.person, size: 48),
                                ),
                              ),
                      ),
                    ),
                    const SizedBox(height: 14),
                    OutlinedButton.icon(
                      style: managementOutlineStyle(),
                      onPressed: _saving ? null : _pickImage,
                      icon: const Icon(Icons.photo_camera),
                      label: Text(_image?.name ?? 'เลือกรูปโปรไฟล์'),
                    ),
                    const SizedBox(height: 14),
                    //เว็บไม่ให้แก้ชื่อจากหน้านี้ จึงแสดงอ่านอย่างเดียวให้เหมือนกัน
                    TextFormField(
                      controller: _name,
                      readOnly: true,
                      decoration: const InputDecoration(
                        labelText: 'ชื่อ-นามสกุล (ไม่สามารถแก้ไขได้)',
                        border: OutlineInputBorder(),
                      ),
                    ),
                    const SizedBox(height: 14),
                    managementInput('อายุ (ปี)', _age, number: true),
                    managementInput('เบอร์โทรศัพท์', _phone),
                    managementInput('อีเมล', _email),
                    if (_student) ...[
                      managementInput(
                        'รหัสนักศึกษา',
                        _studentId,
                        required: true,
                      ),
                      DropdownButtonFormField<int>(
                        value:
                            _faculties.any(
                              (row) =>
                                  row['faculty_id'].toString() ==
                                  _facultyId?.toString(),
                            )
                            ? _facultyId
                            : null,
                        isExpanded: true,
                        decoration: const InputDecoration(
                          labelText: 'คณะ',
                          border: OutlineInputBorder(),
                        ),
                        items: _faculties
                            .map(
                              (row) => DropdownMenuItem(
                                value: int.parse(row['faculty_id'].toString()),
                                child: Text(row['faculty_name'].toString()),
                              ),
                            )
                            .toList(),
                        onChanged: _changeFaculty,
                      ),
                      const SizedBox(height: 14),
                      if (_loadingMajors) const LinearProgressIndicator(),
                      DropdownButtonFormField<int>(
                        value:
                            _majors.any(
                              (row) =>
                                  row['major_id'].toString() ==
                                  _majorId?.toString(),
                            )
                            ? _majorId
                            : null,
                        isExpanded: true,
                        decoration: const InputDecoration(
                          labelText: 'สาขาวิชา',
                          border: OutlineInputBorder(),
                        ),
                        items: _majors
                            .map(
                              (row) => DropdownMenuItem(
                                value: int.parse(row['major_id'].toString()),
                                child: Text(row['major_name'].toString()),
                              ),
                            )
                            .toList(),
                        onChanged: _loadingMajors
                            ? null
                            : (value) => setState(() => _majorId = value),
                      ),
                      const SizedBox(height: 14),
                    ] else
                      managementInput('อาชีพ', _occupation),
                    managementInput(
                      'ที่อยู่ / สถานที่ติดต่อ',
                      _location,
                      lines: 2,
                    ),
                    managementInput('ความสนใจ', _interests, lines: 3),
                    ElevatedButton(
                      style: managementButtonStyle(),
                      onPressed: _saving || _loadingMajors ? null : _save,
                      child: Text(_saving ? 'กำลังบันทึก...' : 'บันทึกข้อมูล'),
                    ),
                  ]),
                ],
              ),
            ),
    );
  }
}
