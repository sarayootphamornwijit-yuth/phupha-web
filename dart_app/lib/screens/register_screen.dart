import 'package:flutter/material.dart';
import '../constants/app_colors.dart';
import '../services/api_service.dart';
import 'login_screen.dart';

// register_screen.dart
// หน้าจอสมัครสมาชิกใหม่ (Register Screen)
// ออกแบบตามโครงสร้างของไฟล์ register.php และรูปภาพหน้าเว็บจริง 100%
// เขียนโค้ดตามแนวทางของโครงงานนักศึกษา: ตั้งชื่อตัวแปรเข้าใจง่าย คอมเมนต์อธิบายละเอียดทุกส่วน

class RegisterScreen extends StatefulWidget {
  const RegisterScreen({super.key});

  @override
  State<RegisterScreen> createState() => _RegisterScreenState();
}

class _RegisterScreenState extends State<RegisterScreen> {
  // GlobalKey สำหรับตรวจสอบความถูกต้องของฟอร์ม (Form Validation)
  final _formKey = GlobalKey<FormState>();

  // ตัวควบคุมช่องกรอกข้อมูลข้อความ (TextEditingController)
  final _usernameController = TextEditingController();
  final _passwordController = TextEditingController();
  final _confirmPasswordController = TextEditingController();
  final _nameController = TextEditingController();
  final _otherPrefixController = TextEditingController();
  final _studentIdController = TextEditingController();
  final _occupationController = TextEditingController();

  // ตัวแปรเก็บสถานะการเลือกคำนำหน้า (ค่าเริ่มต้น: - เลือก -)
  String _selectedPrefix = '- เลือก -';

  // ตัวแปรประเภทผู้ใช้งาน: 'student' (นิสิต/นักศึกษา) หรือ 'outsider' (บุคคลภายนอกทั่วไป)
  // ค่าเริ่มต้นเป็น 'student' ตามหน้าเว็บในรูปภาพแคปเจอร์
  String _userType = 'student';

  // ตัวแปรรหัสคณะและชื่อคณะที่เลือก
  int? _selectedFacultyId;
  String? _selectedFacultyName;

  // ตัวแปรรหัสสาขาวิชาและชื่อสาขาวิชาที่เลือก
  int? _selectedMajorId;
  String? _selectedMajorName;

  // ตัวแปรชั้นปีการศึกษาที่เลือก (1 = ปี 1, 2 = ปี 2, 3 = ปี 3, 4 = ปี 4)
  int? _selectedAcademicYear;

  // ตัวแปรระดับผู้ใช้งาน: 'member' (สมาชิก) หรือ 'committee' (คณะกรรมการ)
  String _userLevel = 'member';

  // ตัวแปรตำแหน่งคณะกรรมการ (เช่น ประธาน, รองประธาน, เหรัญญิก ฯลฯ)
  String? _selectedCommitteePosition;

  // สถานะกำลังส่งข้อมูลไปยังเซิร์ฟเวอร์
  bool _isLoading = false;

  // ตัวแปรเก็บข้อความแจ้งเตือน Error
  String? _errorMessage;

  // ====================================================================
  // ข้อมูล Master Data คณะและสาขาวิชา มหาวิทยาลัยราชภัฏเชียงใหม่
  // สอดคล้องกับฐานข้อมูลตาราง faculties และ majors ใน init.sql
  // ====================================================================
  final List<Map<String, dynamic>> _facultiesList = [
    {'id': 1, 'name': 'คณะครุศาสตร์'},
    {'id': 2, 'name': 'คณะวิทยาศาสตร์และเทคโนโลยี'},
    {'id': 3, 'name': 'คณะวิทยาการจัดการ'},
    {'id': 4, 'name': 'คณะมนุษยศาสตร์และสังคมศาสตร์'},
    {'id': 5, 'name': 'คณะเทคโนโลยีการเกษตร'},
    {'id': 6, 'name': 'คณะพยาบาลศาสตร์'},
    {'id': 7, 'วิทยาลัยนานาชาติ': 'วิทยาลัยนานาชาติ', 'name': 'วิทยาลัยนานาชาติ', 'id': 7},
    {'id': 8, 'name': 'วิทยาลัยแม่ฮ่องสอน'},
    {'id': 9, 'name': 'วิทยาลัยพัฒนาเศรษฐกิจและเทคโนโลยีชุมชนแห่งเอเชีย (adiCET)'},
  ];

  // ตารางข้อมูลสาขาวิชาแยกตามรหัสคณะ (faculty_id)
  final Map<int, List<Map<String, dynamic>>> _majorsMap = {
    // 1: คณะครุศาสตร์
    1: [
      {'id': 1, 'name': 'สาขาวิชาการศึกษาปฐมวัย'},
      {'id': 2, 'name': 'สาขาวิชาการประถมศึกษา'},
      {'id': 3, 'name': 'สาขาวิชาการศึกษาพิเศษ'},
      {'id': 4, 'name': 'สาขาวิชาจิตวิทยาการศึกษาและการแนะแนว'},
      {'id': 5, 'name': 'สาขาวิชาภาษาไทย'},
      {'id': 6, 'name': 'สาขาวิชาภาษาอังกฤษ'},
      {'id': 7, 'name': 'สาขาวิชาภาษาจีน'},
      {'id': 8, 'name': 'สาขาวิชาสังคมศึกษา'},
      {'id': 9, 'name': 'สาขาวิชาคณิตศาสตร์'},
      {'id': 10, 'name': 'สาขาวิชาวิทยาศาสตร์ทั่วไป'},
      {'id': 11, 'name': 'สาขาวิชาฟิสิกส์'},
      {'id': 12, 'name': 'สาขาวิชาเคมี'},
      {'id': 13, 'name': 'สาขาวิชาชีววิทยา'},
      {'id': 14, 'name': 'สาขาวิชาคอมพิวเตอร์ศึกษา'},
      {'id': 15, 'name': 'สาขาวิชาพลศึกษา'},
      {'id': 16, 'name': 'สาขาวิชาศิลปศึกษา'},
      {'id': 17, 'name': 'สาขาวิชาดนตรีศึกษา'},
      {'id': 18, 'name': 'สาขาวิชานาฏศิลป์ศึกษา'},
      {'id': 19, 'name': 'สาขาวิชาเกษตรศาสตร์ (ครุศาสตร์)'},
      {'id': 20, 'name': 'สาขาวิชาอุตสาหกรรมศิลป์'},
    ],
    // 2: คณะวิทยาศาสตร์และเทคโนโลยี
    2: [
      {'id': 21, 'name': 'สาขาวิชาวิทยาการคอมพิวเตอร์'},
      {'id': 22, 'name': 'สาขาวิชาเทคโนโลยีสารสนเทศ'},
      {'id': 23, 'name': 'สาขาวิชาเทคโนโลยีคอมพิวเตอร์และดิจิทัล'},
      {'id': 24, 'name': 'สาขาวิชาเคมี'},
      {'id': 25, 'name': 'สาขาวิชาชีววิทยา'},
      {'id': 26, 'name': 'สาขาวิชาคณิตศาสตร์'},
      {'id': 27, 'name': 'สาขาวิชาวิทยาการข้อมูลเชิงสถิติ'},
      {'id': 28, 'name': 'สาขาวิชาวิทยาศาสตร์สิ่งแวดล้อม'},
      {'id': 29, 'name': 'สาขาวิชาคหกรรมศาสตร์'},
      {'id': 30, 'name': 'สาขาวิชาการออกแบบผลิตภัณฑ์'},
      {'id': 31, 'name': 'สาขาวิชาเทคโนโลยีสถาปัตยกรรม'},
      {'id': 32, 'name': 'สาขาวิชาเทคโนโลยีวิศวกรรมการก่อสร้าง'},
      {'id': 33, 'name': 'สาขาวิชาเทคโนโลยีอุตสาหกรรม'},
      {'id': 34, 'name': 'สาขาวิชาอาชีวอนามัยและความปลอดภัย'},
      {'id': 35, 'name': 'สาขาวิชาสาธารณสุขศาสตร์'},
      {'id': 36, 'name': 'สาขาวิชาวิศวกรรมโยธา'},
    ],
    // 3: คณะวิทยาการจัดการ
    3: [
      {'id': 37, 'name': 'สาขาวิชาการจัดการ'},
      {'id': 38, 'name': 'สาขาวิชาการตลาด'},
      {'id': 39, 'name': 'สาขาวิชาการเงินและการธนาคาร'},
      {'id': 40, 'name': 'สาขาวิชาการบัญชี'},
      {'id': 41, 'name': 'สาขาวิชานิเทศศาสตร์'},
      {'id': 42, 'name': 'สาขาวิชาการจัดการธุรกิจการค้าสมัยใหม่'},
      {'id': 43, 'name': 'สาขาวิชาการจัดการทรัพยากรมนุษย์'},
      {'id': 44, 'name': 'สาขาวิชาคอมพิวเตอร์ธุรกิจ'},
      {'id': 45, 'name': 'สาขาวิชาการท่องเที่ยวและการโรงแรม'},
      {'id': 46, 'name': 'สาขาวิชาเศรษฐศาสตร์'},
    ],
    // 4: คณะมนุษยศาสตร์และสังคมศาสตร์
    4: [
      {'id': 47, 'name': 'สาขาวิชาภาษาไทย'},
      {'id': 48, 'name': 'สาขาวิชาภาษาอังกฤษ'},
      {'id': 49, 'name': 'สาขาวิชาภาษาอังกฤษธุรกิจ'},
      {'id': 50, 'name': 'สาขาวิชาภาษาจีนธุรกิจ'},
      {'id': 51, 'name': 'สาขาวิชาภาษาญี่ปุ่น'},
      {'id': 52, 'name': 'สาขาวิชาภาษาเกาหลี'},
      {'id': 53, 'name': 'สาขาวิชาการพัฒนาชุมชน'},
      {'id': 54, 'name': 'สาขาวิชาสังคมวิทยาและมานุษยวิทยา'},
      {'id': 55, 'name': 'สาขาวิชารัฐประศาสนศาสตร์'},
      {'id': 56, 'name': 'สาขาวิชาสารสนเทศศาสตร์'},
      {'id': 57, 'name': 'สาขาวิชาดนตรีสากล'},
      {'id': 58, 'name': 'สาขาวิชาศิลปะและการออกแบบ'},
    ],
    // 5: คณะเทคโนโลยีการเกษตร
    5: [
      {'id': 59, 'name': 'สาขาวิชาเกษตรศาสตร์'},
      {'id': 60, 'name': 'สาขาวิชาสัตวศาสตร์'},
      {'id': 61, 'name': 'สาขาวิชาวิทยาศาสตร์และเทคโนโลยีการอาหาร'},
      {'id': 62, 'name': 'สาขาวิชาเทคโนโลยีชีวภาพทางการเกษตร'},
    ],
    // 6: คณะพยาบาลศาสตร์
    6: [
      {'id': 63, 'name': 'หลักสูตรพยาบาลศาสตรบัณฑิต'},
    ],
    // 7: วิทยาลัยนานาชาติ
    7: [
      {'id': 64, 'name': 'สาขาวิชาภาษาอังกฤษเพื่อการสื่อสารระหว่างประเทศ'},
      {'id': 65, 'name': 'สาขาวิชาธุรกิจระหว่างประเทศ'},
      {'id': 66, 'name': 'สาขาวิชานวัตกรรมการจัดการการท่องเที่ยว'},
    ],
    // 8: วิทยาลัยแม่ฮ่องสอน
    8: [
      {'id': 67, 'name': 'สาขาวิชาการจัดการเพื่อการพัฒนาท้องถิ่น'},
      {'id': 68, 'name': 'สาขาวิชาเทคโนโลยีสารสนเทศเพื่อการพัฒนาท้องถิ่น'},
      {'id': 69, 'name': 'สาขาวิชาการท่องเที่ยวเชิงวัฒนธรรมและธรรมชาติ'},
    ],
    // 9: adiCET
    9: [
      {'id': 70, 'name': 'สาขาวิชาเทคโนโลยีพลังงานทดแทน'},
      {'id': 71, 'name': 'สาขาวิชาการจัดการสิ่งแวดล้อมชุมชน'},
    ],
  };

  // รายชื่อตัวเลือกตำแหน่งคณะกรรมการ (ตรงตามภาพหน้าจอที่ 1 และ 4)
  final List<Map<String, String>> _committeePositions = const [
    {'value': 'president', 'label': 'ประธาน'},
    {'value': 'vice_president', 'label': 'รองประธาน'},
    {'value': 'treasurer', 'label': 'เหรัญญิก'},
    {'value': 'secretary_saluang', 'label': 'เลขาสะลวง'},
    {'value': 'secretary_wiangbua', 'label': 'เลขาเวียงบัว'},
    {'value': 'nurse', 'label': 'พยาบาล'},
    {'value': 'venue_coordinator', 'label': 'ฝ่ายสถานที่'},
    {'value': 'pr', 'label': 'ฝ่ายประชาสัมพันธ์'},
  ];

  @override
  void dispose() {
    _usernameController.dispose();
    _passwordController.dispose();
    _confirmPasswordController.dispose();
    _nameController.dispose();
    _otherPrefixController.dispose();
    _studentIdController.dispose();
    _occupationController.dispose();
    super.dispose();
  }

  // ฟังก์ชันดำเนินการส่งข้อมูลสมัครสมาชิก
  Future<void> _handleRegister() async {
    setState(() {
      _errorMessage = null;
    });

    // 1. ตรวจสอบการกรอกข้อมูลพื้นฐานในฟอร์ม
    if (!_formKey.currentState!.validate()) {
      return;
    }

    // 2. ตรวจสอบความถูกต้องของรหัสผ่าน
    if (_passwordController.text != _confirmPasswordController.text) {
      setState(() {
        _errorMessage = 'รหัสผ่านและการยืนยันรหัสผ่านไม่ตรงกัน';
      });
      return;
    }

    // 3. ตรวจสอบเงื่อนไขของนักศึกษา
    if (_userType == 'student') {
      if (_studentIdController.text.trim().length != 8) {
        setState(() {
          _errorMessage = 'กรุณากรอกรหัสนักศึกษา 8 หลักให้ถูกต้อง';
        });
        return;
      }
      if (_selectedFacultyId == null) {
        setState(() {
          _errorMessage = 'กรุณาเลือกคณะ';
        });
        return;
      }
      if (_selectedAcademicYear == null) {
        setState(() {
          _errorMessage = 'กรุณาเลือกชั้นปี';
        });
        return;
      }
      if (_userLevel == 'committee' && _selectedCommitteePosition == null) {
        setState(() {
          _errorMessage = 'กรุณาเลือกตำแหน่งคณะกรรมการ';
        });
        return;
      }
    }

    // รวมคำนำหน้าและชื่อ-นามสกุล
    String prefixText = _selectedPrefix;
    if (_selectedPrefix == '- เลือก -') {
      prefixText = '';
    } else if (_selectedPrefix == 'อื่นๆ (ระบุ)') {
      prefixText = _otherPrefixController.text.trim();
    }
    final fullName = prefixText.isNotEmpty
        ? '$prefixText${_nameController.text.trim()}'
        : _nameController.text.trim();

    setState(() {
      _isLoading = true;
    });

    // เรียก API สมัครสมาชิก
    final response = await ApiService.register(
      username: _usernameController.text.trim(),
      password: _passwordController.text,
      name: fullName,
      userType: _userType,
      studentId: _userType == 'student' ? _studentIdController.text.trim() : null,
      occupation: _userType == 'outsider' ? _occupationController.text.trim() : null,
      facultyId: _userType == 'student' ? _selectedFacultyId : null,
      majorId: _userType == 'student' ? _selectedMajorId : null,
      faculty: _userType == 'student' ? _selectedFacultyName : null,
      major: _userType == 'student' ? _selectedMajorName : null,
      academicYear: _userType == 'student' ? _selectedAcademicYear : null,
      // บังคับให้ผู้สมัครใหม่เริ่มต้นด้วยสิทธิ์ 'member' เสมอ เพื่อความปลอดภัย
      role: 'member',
      committeePosition: (_userType == 'student' && _userLevel == 'committee')
          ? _selectedCommitteePosition
          : null,
    );


    if (!mounted) return;

    setState(() {
      _isLoading = false;
    });

    if (response.isSuccess) {
      // แสดงข้อความแจ้งเตือนสำเร็จ
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(response.message),
          backgroundColor: AppColors.success,
          duration: const Duration(seconds: 3),
        ),
      );
      // นำทางไปยังหน้าเข้าสู่ระบบ
      Navigator.pushReplacement(
        context,
        MaterialPageRoute(builder: (context) => const LoginScreen()),
      );
    } else {
      setState(() {
        _errorMessage = response.message;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.bgMain, // พื้นหลังสีเขียวอ่อน #F1F8E9 ตาม variables.css

      // แถบหัวด้านบนสีขาว
      appBar: AppBar(
        backgroundColor: Colors.white,
        elevation: 0,
        scrolledUnderElevation: 0,
        centerTitle: true,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back, color: AppColors.primaryDark),
          onPressed: () => Navigator.pop(context),
        ),
        title: const Text(
          'ชมรมเพื่อนภูผา',
          style: TextStyle(
            fontSize: 17,
            fontWeight: FontWeight.bold,
            color: AppColors.primaryDark,
          ),
        ),
      ),

      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 24),
          child: Center(
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 500),
              child: Card(
                color: Colors.white,
                elevation: 0,
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(12),
                  side: const BorderSide(color: Color(0xFFE2E8F0)), // เส้นขอบเทาบางๆ ตามเว็บ
                ),
                child: Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 24),
                  child: Form(
                    key: _formKey,
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        // หัวข้อ: สมัครสมาชิก
                        const Text(
                          'สมัครสมาชิก',
                          textAlign: TextAlign.center,
                          style: TextStyle(
                            fontSize: 22,
                            fontWeight: FontWeight.bold,
                            color: Colors.black, // สีดำตามแท็ก h2 ใน register.php
                          ),
                        ),

                        const SizedBox(height: 20),

                        // แสดงข้อความ Error (ถ้ามี)
                        if (_errorMessage != null) ...[
                          Container(
                            padding: const EdgeInsets.all(12),
                            decoration: BoxDecoration(
                              color: const Color(0xFFFFEBEE),
                              borderRadius: BorderRadius.circular(8),
                              border: Border.all(color: const Color(0xFFFFCDD2)),
                            ),
                            child: Text(
                              _errorMessage!,
                              textAlign: TextAlign.center,
                              style: const TextStyle(
                                color: AppColors.danger,
                                fontSize: 13,
                              ),
                            ),
                          ),
                          const SizedBox(height: 16),
                        ],

                        // 1. ช่องกรอก: ชื่อผู้ใช้ (บนเว็บไม่มี placeholder)
                        _buildFieldLabel('ชื่อผู้ใช้'),
                        TextFormField(
                          controller: _usernameController,
                          decoration: _inputDecoration(),
                          validator: (val) {
                            if (val == null || val.trim().isEmpty) {
                              return 'กรุณากรอกชื่อผู้ใช้';
                            }
                            return null;
                          },
                        ),
                        const SizedBox(height: 16),

                        // 2. ช่องกรอก: รหัสผ่าน (บนเว็บไม่มี placeholder)
                        _buildFieldLabel('รหัสผ่าน'),
                        TextFormField(
                          controller: _passwordController,
                          obscureText: true,
                          decoration: _inputDecoration(),
                          validator: (val) {
                            if (val == null || val.length < 6) {
                              return 'รหัสผ่านต้องมีความยาวอย่างน้อย 6 ตัวอักษร';
                            }
                            return null;
                          },
                        ),
                        const SizedBox(height: 16),

                        // 3. ช่องกรอก: ยืนยันรหัสผ่าน (บนเว็บไม่มี placeholder)
                        _buildFieldLabel('ยืนยันรหัสผ่าน'),
                        TextFormField(
                          controller: _confirmPasswordController,
                          obscureText: true,
                          decoration: _inputDecoration(),
                          validator: (val) {
                            if (val == null || val.isEmpty) {
                              return 'กรุณายืนยันรหัสผ่าน';
                            }
                            return null;
                          },
                        ),
                        const SizedBox(height: 16),

                        // 4. แถวคำนำหน้า และ ชื่อ-นามสกุล
                        Row(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            // คำนำหน้า (Dropdown)
                            SizedBox(
                              width: 130,
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  _buildFieldLabel('คำนำหน้า'),
                                  DropdownButtonFormField<String>(
                                    value: _selectedPrefix,
                                    decoration: _inputDecoration(),
                                    items: const [
                                      DropdownMenuItem(value: '- เลือก -', child: Text('- เลือก -')),
                                      DropdownMenuItem(value: 'นาย', child: Text('นาย')),
                                      DropdownMenuItem(value: 'นางสาว', child: Text('นางสาว')),
                                      DropdownMenuItem(value: 'นาง', child: Text('นาง')),
                                      DropdownMenuItem(value: 'อื่นๆ (ระบุ)', child: Text('อื่นๆ (ระบุ)')),
                                    ],
                                    onChanged: (val) {
                                      if (val != null) {
                                        setState(() {
                                          _selectedPrefix = val;
                                        });
                                      }
                                    },
                                  ),
                                ],
                              ),
                            ),
                            const SizedBox(width: 12),
                            // ชื่อ-นามสกุล (บนเว็บไม่มี placeholder)
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  _buildFieldLabel('ชื่อ-นามสกุล'),
                                  TextFormField(
                                    controller: _nameController,
                                    decoration: _inputDecoration(),
                                    validator: (val) {
                                      if (val == null || val.trim().isEmpty) {
                                        return 'กรุณากรอกชื่อ-นามสกุล';
                                      }
                                      return null;
                                    },
                                  ),
                                ],
                              ),
                            ),
                          ],
                        ),

                        // กรณีเลือกคำนำหน้า "อื่นๆ" ให้แสดงช่องกรอกเพิ่มเติม
                        if (_selectedPrefix == 'อื่นๆ (ระบุ)') ...[
                          const SizedBox(height: 16),
                          _buildFieldLabel('ระบุคำนำหน้าชื่อ'),
                          TextFormField(
                            controller: _otherPrefixController,
                            decoration: _inputDecoration(),
                            validator: (val) {
                              if (_selectedPrefix == 'อื่นๆ (ระบุ)' && (val == null || val.trim().isEmpty)) {
                                return 'กรุณาระบุคำนำหน้าชื่อ';
                              }
                              return null;
                            },
                          ),
                        ],

                        const SizedBox(height: 16),

                        // 5. กล่องเลือกสถานะผู้ใช้งาน (.bg-main บนเว็บ: สีเขียวอ่อนละมุน)
                        Container(
                          padding: const EdgeInsets.all(12),
                          decoration: BoxDecoration(
                            color: AppColors.bgMain, // #F1F8E9
                            borderRadius: BorderRadius.circular(8),
                          ),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              _buildFieldLabel('สถานะผู้ใช้งาน'),
                              Row(
                                children: [
                                  Radio<String>(
                                    value: 'outsider',
                                    groupValue: _userType,
                                    activeColor: AppColors.primary,
                                    onChanged: (val) {
                                      if (val != null) setState(() => _userType = val);
                                    },
                                  ),
                                  const Text('บุคคลภายนอกทั่วไป', style: TextStyle(fontSize: 14)),
                                  const SizedBox(width: 8),
                                  Radio<String>(
                                    value: 'student',
                                    groupValue: _userType,
                                    activeColor: AppColors.primary,
                                    onChanged: (val) {
                                      if (val != null) setState(() => _userType = val);
                                    },
                                  ),
                                  const Text('นิสิต / นักศึกษา', style: TextStyle(fontSize: 14)),
                                ],
                              ),
                            ],
                          ),
                        ),

                        const SizedBox(height: 16),

                        // 6. ฟิลด์เฉพาะบุคคลภายนอกทั่วไป
                        if (_userType == 'outsider') ...[
                          _buildFieldLabel('อาชีพ'),
                          TextFormField(
                            controller: _occupationController,
                            // บนเว็บมี placeholder: "เช่น พนักงานบริษัท, ธุรกิจส่วนตัว"
                            decoration: _inputDecoration(hintText: 'เช่น พนักงานบริษัท, ธุรกิจส่วนตัว'),
                          ),
                          const SizedBox(height: 16),
                        ],

                        // 7. ฟิลด์เฉพาะนักศึกษา (ตรงตามภาพหน้าจอที่ 4 เป๊ะ)
                        if (_userType == 'student') ...[
                          Container(
                            padding: const EdgeInsets.all(14),
                            decoration: BoxDecoration(
                              color: AppColors.bgMain, // พื้นหลังสีเขียวอ่อนละมุนตาม screenshot 4
                              borderRadius: BorderRadius.circular(8),
                            ),
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                // รหัสนักศึกษา (บนเว็บมี placeholder: "8 หลัก")
                                _buildGreenFieldLabel('รหัสนักศึกษา'),
                                TextFormField(
                                  controller: _studentIdController,
                                  keyboardType: TextInputType.number,
                                  maxLength: 8,
                                  decoration: _whiteInputDecoration(hintText: '8 หลัก'),
                                  validator: (val) {
                                    if (_userType == 'student' && (val == null || val.length != 8)) {
                                      return 'กรุณากรอกรหัสนักศึกษา 8 หลัก';
                                    }
                                    return null;
                                  },
                                ),
                                const SizedBox(height: 12),

                                // คณะ (Dropdown: -- กรุณาเลือกคณะ --)
                                _buildGreenFieldLabel('คณะ'),
                                DropdownButtonFormField<int>(
                                  value: _selectedFacultyId,
                                  isExpanded: true,
                                  decoration: _whiteInputDecoration(),
                                  hint: const Text('-- กรุณาเลือกคณะ --', style: TextStyle(fontSize: 14)),
                                  items: _facultiesList.map((fac) {
                                    return DropdownMenuItem<int>(
                                      value: fac['id'] as int,
                                      child: Text(fac['name'] as String, overflow: TextOverflow.ellipsis),
                                    );
                                  }).toList(),
                                  onChanged: (val) {
                                    setState(() {
                                      _selectedFacultyId = val;
                                      final found = _facultiesList.firstWhere(
                                        (element) => element['id'] == val,
                                        orElse: () => {'name': ''},
                                      );
                                      _selectedFacultyName = found['name'];
                                      // เมื่อเปลี่ยนคณะ ให้รีเซ็ตสาขาวิชา
                                      _selectedMajorId = null;
                                      _selectedMajorName = null;
                                    });
                                  },
                                ),
                                const SizedBox(height: 12),

                                // สาขาวิชา (Dropdown: ถ้ายังไม่เลือกคณะแสดง -- กรุณาเลือกคณะก่อน --)
                                _buildGreenFieldLabel('สาขาวิชา'),
                                DropdownButtonFormField<int>(
                                  value: _selectedMajorId,
                                  isExpanded: true,
                                  decoration: _whiteInputDecoration(),
                                  hint: Text(
                                    _selectedFacultyId == null
                                        ? '-- กรุณาเลือกคณะก่อน --'
                                        : '-- กรุณาเลือกสาขาวิชา --',
                                    style: const TextStyle(fontSize: 14),
                                  ),
                                  items: _selectedFacultyId == null
                                      ? null
                                      : (_majorsMap[_selectedFacultyId] ?? []).map((m) {
                                          return DropdownMenuItem<int>(
                                            value: m['id'] as int,
                                            child: Text(m['name'] as String, overflow: TextOverflow.ellipsis),
                                          );
                                        }).toList(),
                                  onChanged: _selectedFacultyId == null
                                      ? null
                                      : (val) {
                                          setState(() {
                                            _selectedMajorId = val;
                                            final mList = _majorsMap[_selectedFacultyId] ?? [];
                                            final found = mList.firstWhere(
                                              (element) => element['id'] == val,
                                              orElse: () => {'name': ''},
                                            );
                                            _selectedMajorName = found['name'];
                                          });
                                        },
                                ),
                                const SizedBox(height: 12),

                                // ชั้นปี (Dropdown: -- เลือกชั้นปี --)
                                _buildGreenFieldLabel('ชั้นปี'),
                                DropdownButtonFormField<int>(
                                  value: _selectedAcademicYear,
                                  isExpanded: true,
                                  decoration: _whiteInputDecoration(),
                                  hint: const Text('-- เลือกชั้นปี --', style: TextStyle(fontSize: 14)),
                                  items: const [
                                    DropdownMenuItem(value: 1, child: Text('ปี 1')),
                                    DropdownMenuItem(value: 2, child: Text('ปี 2')),
                                    DropdownMenuItem(value: 3, child: Text('ปี 3')),
                                    DropdownMenuItem(value: 4, child: Text('ปี 4')),
                                  ],
                                  onChanged: (val) {
                                    setState(() {
                                      _selectedAcademicYear = val;
                                    });
                                  },
                                ),
                                const SizedBox(height: 16),

                                // ระดับผู้ใช้ (สมาชิก / คณะกรรมการ)
                                Container(
                                  padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
                                  decoration: BoxDecoration(
                                    color: Colors.white,
                                    borderRadius: BorderRadius.circular(8),
                                    border: Border.all(color: const Color(0xFFE2E8F0)),
                                  ),
                                  child: Column(
                                    crossAxisAlignment: CrossAxisAlignment.start,
                                    children: [
                                      _buildFieldLabel('ระดับผู้ใช้'),
                                      Row(
                                        children: [
                                          Radio<String>(
                                            value: 'member',
                                            groupValue: _userLevel,
                                            activeColor: AppColors.primary,
                                            onChanged: (val) {
                                              if (val != null) setState(() => _userLevel = val);
                                            },
                                          ),
                                          const Text('สมาชิก', style: TextStyle(fontSize: 14)),
                                          const SizedBox(width: 12),
                                          Radio<String>(
                                            value: 'committee',
                                            groupValue: _userLevel,
                                            activeColor: AppColors.primary,
                                            onChanged: (val) {
                                              if (val != null) setState(() => _userLevel = val);
                                            },
                                          ),
                                          const Text('คณะกรรมการ', style: TextStyle(fontSize: 14)),
                                        ],
                                      ),
                                    ],
                                  ),
                                ),

                                // ตำแหน่งคณะกรรมการ (แสดงเมื่อเลือกเป็น คณะกรรมการ ตามภาพหน้าจอที่ 1 และ 4)
                                if (_userLevel == 'committee') ...[
                                  const SizedBox(height: 12),
                                  _buildFieldLabel('ตำแหน่งคณะกรรมการ'),
                                  DropdownButtonFormField<String>(
                                    value: _selectedCommitteePosition,
                                    isExpanded: true,
                                    decoration: _whiteInputDecoration(),
                                    hint: const Text('-- เลือกตำแหน่ง --', style: TextStyle(fontSize: 14)),
                                    items: _committeePositions.map((item) {
                                      return DropdownMenuItem<String>(
                                        value: item['value'],
                                        child: Text(item['label']!),
                                      );
                                    }).toList(),
                                    onChanged: (val) {
                                      setState(() {
                                        _selectedCommitteePosition = val;
                                      });
                                    },
                                  ),
                                  const SizedBox(height: 6),
                                  const Text(
                                    '* บัญชีใหม่จะได้รับสิทธิ์เป็นสมาชิกทั่วไปก่อน โดยผู้ดูแลระบบจะเป็นผู้อนุมัติสิทธิ์คณะกรรมการตามตำแหน่งให้ในภายหลัง',
                                    style: TextStyle(fontSize: 11.5, color: Color(0xFFD97706), fontStyle: FontStyle.italic),
                                  ),
                                ],
                              ],
                            ),
                          ),

                          const SizedBox(height: 20),
                        ],

                        // 8. ปุ่มกด: สมัครสมาชิก (ปุ่มสีเขียวทรงมนเต็มความกว้าง)
                        SizedBox(
                          height: 46,
                          child: ElevatedButton(
                            onPressed: _isLoading ? null : _handleRegister,
                            style: ElevatedButton.styleFrom(
                              backgroundColor: AppColors.primary, // #2E7D32
                              foregroundColor: Colors.white,
                              elevation: 0,
                              shape: RoundedRectangleBorder(
                                borderRadius: BorderRadius.circular(8), // ความโค้งมน 8px ตาม .btn
                              ),
                            ),
                            child: _isLoading
                                ? const SizedBox(
                                    width: 22,
                                    height: 22,
                                    child: CircularProgressIndicator(
                                      strokeWidth: 2,
                                      color: Colors.white,
                                    ),
                                  )
                                : const Text(
                                    'สมัครสมาชิก',
                                    style: TextStyle(
                                      fontSize: 15,
                                      fontWeight: FontWeight.bold,
                                    ),
                                  ),
                          ),
                        ),

                        const SizedBox(height: 20),

                        // 9. ลิงก์: มีบัญชีอยู่แล้ว? เข้าสู่ระบบ
                        Row(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            const Text(
                              'มีบัญชีอยู่แล้ว? ',
                              style: TextStyle(fontSize: 14, color: Color(0xFF616161)),
                            ),
                            GestureDetector(
                              onTap: () {
                                Navigator.pushReplacement(
                                  context,
                                  MaterialPageRoute(builder: (context) => const LoginScreen()),
                                );
                              },
                              child: const Text(
                                'เข้าสู่ระบบ',
                                style: TextStyle(
                                  fontSize: 14,
                                  fontWeight: FontWeight.bold,
                                  color: AppColors.primary, // สีเขียว #2E7D32
                                ),
                              ),
                            ),
                          ],
                        ),
                      ],
                    ),
                  ),
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }

  // ป้ายกำกับช่องกรอกข้อมูลสีดำทั่วไป
  Widget _buildFieldLabel(String label) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 6),
      child: Text(
        label,
        style: const TextStyle(
          fontSize: 14,
          fontWeight: FontWeight.w500,
          color: Colors.black, // สีดำตาม .form-label ในเว็บ
        ),
      ),
    );
  }

  // ป้ายกำกับช่องกรอกข้อมูลสีเขียวเข้มสำหรับหมวดนักศึกษา (ตาม screenshot 4)
  Widget _buildGreenFieldLabel(String label) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 6),
      child: Text(
        label,
        style: const TextStyle(
          fontSize: 14,
          fontWeight: FontWeight.bold,
          color: AppColors.primaryDark, // สีเขียวเข้ม #1B5E20
        ),
      ),
    );
  }

  // ตกแต่งช่องกรอกพื้นฐาน
  InputDecoration _inputDecoration({String? hintText}) {
    return InputDecoration(
      hintText: hintText,
      hintStyle: const TextStyle(color: Color(0xFF9E9E9E), fontSize: 14),
      filled: true,
      fillColor: Colors.white,
      counterText: '',
      contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
      border: OutlineInputBorder(
        borderRadius: BorderRadius.circular(8),
        borderSide: const BorderSide(color: Color(0xFFE2E8F0)),
      ),
      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(8),
        borderSide: const BorderSide(color: Color(0xFFE2E8F0)),
      ),
      focusedBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(8),
        borderSide: const BorderSide(color: AppColors.primary, width: 1.5),
      ),
    );
  }

  // ตกแต่งช่องกรอกและ Dropdown พื้นหลังสีขาว
  InputDecoration _whiteInputDecoration({String? hintText}) {
    return InputDecoration(
      hintText: hintText,
      hintStyle: const TextStyle(color: Color(0xFF9E9E9E), fontSize: 14),
      filled: true,
      fillColor: Colors.white,
      counterText: '',
      contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
      border: OutlineInputBorder(
        borderRadius: BorderRadius.circular(8),
        borderSide: const BorderSide(color: Color(0xFFCBD5E1)),
      ),
      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(8),
        borderSide: const BorderSide(color: Color(0xFFCBD5E1)),
      ),
      focusedBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(8),
        borderSide: const BorderSide(color: AppColors.primary, width: 1.5),
      ),
    );
  }
}
