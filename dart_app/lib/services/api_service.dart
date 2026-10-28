import 'dart:convert';
import 'package:http/http.dart' as http;
import 'package:encrypt/encrypt.dart' as enc;
import 'package:shared_preferences/shared_preferences.dart';
import '../constants/app_config.dart';
import '../models/user_model.dart';
import '../models/activity_model.dart';
import '../models/vehicle_model.dart';
import '../models/news_model.dart';

// api_service.dart
// คลาสสำหรับส่ง Request เชื่อมต่อไปยัง api.php บนเซิร์ฟเวอร์
// มีระบบแก้ปัญหาและข้ามการป้องกัน TestCookie (slowAES) ของ InfinityFree อัตโนมัติ

class ApiResponse<T> {
  final bool isSuccess;
  final String message;
  final T? data;

  ApiResponse({
    required this.isSuccess,
    required this.message,
    this.data,
  });
}

class ApiService {
  // กำหนดเวลา Timeout สูงสุดสำหรับการเชื่อมต่อ API (15 วินาที)
  static const Duration timeoutDuration = Duration(seconds: 15);

  // คุกกี้สำหรับผ่านระบบรักษาความปลอดภัยของ InfinityFree
  static String? _testCookie;

  // ฟังก์ชันส่วนกลางสำหรับส่ง HTTP Request พร้อมระบบแก้ Cookie Challenge อัตโนมัติ
  static Future<http.Response> _executeRequest(
    Future<http.Response> Function(Map<String, String> headers) requestFn,
  ) async {
    // โหลดคุกกี้เดิมจาก SharedPreferences หากมี
    if (_testCookie == null) {
      final prefs = await SharedPreferences.getInstance();
      _testCookie = prefs.getString('infinityfree_test_cookie');
    }

    final headers = <String, String>{
      'User-Agent':
          'Mozilla/5.0 (Linux; Android 10; Mobile) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/119.0.0.0 Mobile Safari/537.36',
    };
    if (_testCookie != null && _testCookie!.isNotEmpty) {
      headers['Cookie'] = _testCookie!;
    }

    var response = await requestFn(headers).timeout(timeoutDuration);

    // ตรวจสอบว่าติดหน้าท้าทาย Anti-Bot (aes.js) ของ InfinityFree หรือไม่
    if (response.body.contains('/aes.js') || response.body.contains('slowAES.decrypt')) {
      final aMatch = RegExp(r'a=toNumbers\("([a-f0-9]+)"\)').firstMatch(response.body);
      final bMatch = RegExp(r'b=toNumbers\("([a-f0-9]+)"\)').firstMatch(response.body);
      final cMatch = RegExp(r'c=toNumbers\("([a-f0-9]+)"\)').firstMatch(response.body);

      if (aMatch != null && bMatch != null && cMatch != null) {
        final aHex = aMatch.group(1)!;
        final bHex = bMatch.group(1)!;
        final cHex = cMatch.group(1)!;

        // ถอดรหัส AES-128 CBC ตามสูตรของ slowAES
        final key = enc.Key.fromBase16(aHex);
        final iv = enc.IV.fromBase16(bHex);
        final encrypter = enc.Encrypter(enc.AES(key, mode: enc.AESMode.cbc, padding: null));
        final decrypted = encrypter.decryptBytes(enc.Encrypted.fromBase16(cHex), iv: iv);
        final hexCookie = decrypted.map((b) => b.toRadixString(16).padLeft(2, '0')).join('');

        _testCookie = '__test=$hexCookie';

        // บันทึกคุกกี้ไว้ใช้ซ้ำ
        final prefs = await SharedPreferences.getInstance();
        await prefs.setString('infinityfree_test_cookie', _testCookie!);

        // ส่งคำขอซ้ำอีกครั้งพร้อม Cookie
        headers['Cookie'] = _testCookie!;
        response = await requestFn(headers).timeout(timeoutDuration);
      }
    }

    return response;
  }

  // ส่ง HTTP GET แบบข้ามคุกกี้
  static Future<http.Response> _get(Uri uri) async {
    return await _executeRequest((headers) => http.get(uri, headers: headers));
  }

  // ส่ง HTTP POST แบบข้ามคุกกี้
  static Future<http.Response> _post(Uri uri, Map<String, String> body) async {
    return await _executeRequest((headers) => http.post(uri, headers: headers, body: body));
  }

  // 1. เข้าสู่ระบบ (Login)
  static Future<ApiResponse<UserModel>> login(String username, String password) async {
    try {
      final response = await _post(
        Uri.parse(AppConfig.apiUrl),
        {
          'action': 'login',
          'username': username,
          'password': password,
        },
      );

      if (!response.body.trim().startsWith('{')) {
        return ApiResponse(
          isSuccess: false,
          message: 'เซิร์ฟเวอร์ยังไม่พร้อมใช้งาน กรุณาลองใหม่อีกครั้งในภายหลัง',
        );
      }

      final Map<String, dynamic> json = jsonDecode(response.body);
      if (json['status'] == 'success' && json['data'] != null) {
        final user = UserModel.fromJson(json['data']);
        return ApiResponse(
          isSuccess: true,
          message: json['message'] ?? 'เข้าสู่ระบบสำเร็จ',
          data: user,
        );
      } else {
        return ApiResponse(
          isSuccess: false,
          message: json['message'] ?? 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง',
        );
      }
    } catch (e) {
      return ApiResponse(
        isSuccess: false,
        message: 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้ กรุณาตรวจสอบอินเทอร์เน็ต',
      );
    }
  }

  // 2. สมัครสมาชิกใหม่ (Register)
  // รองรับข้อมูลครบถ้วนตามแบบฟอร์ม register.php บนเว็บไซต์
  static Future<ApiResponse<void>> register({
    required String username,
    required String password,
    required String name,
    required String userType,
    String? studentId,
    String? occupation,
    int? facultyId,
    int? majorId,
    String? faculty,
    String? major,
    int? academicYear,
    String role = 'member',
    String? committeePosition,
    String? phone,
    String? email,
  }) async {
    try {
      final bodyData = <String, String>{
        'action': 'register',
        'username': username,
        'password': password,
        'name': name,
        'user_type': userType,
        'student_id': studentId ?? '',
        'occupation': occupation ?? '',
        'faculty_id': (facultyId ?? 0).toString(),
        'major_id': (majorId ?? 0).toString(),
        'faculty': faculty ?? '',
        'major': major ?? '',
        'academic_year': (academicYear ?? 0).toString(),
        'role': role,
        'committee_position': committeePosition ?? '',
      };

      final response = await _post(
        Uri.parse(AppConfig.apiUrl),
        bodyData,
      );

      if (!response.body.trim().startsWith('{')) {
        return ApiResponse(
          isSuccess: false,
          message: 'เซิร์ฟเวอร์ขัดข้อง กรุณาลองใหม่อีกครั้ง',
        );
      }

      final Map<String, dynamic> json = jsonDecode(response.body);
      if (json['status'] == 'success') {
        return ApiResponse(
          isSuccess: true,
          message: json['message'] ?? 'สมัครสมาชิกสำเร็จ เข้าสู่ระบบได้ทันที',
        );
      } else {
        return ApiResponse(
          isSuccess: false,
          message: json['message'] ?? 'สมัครสมาชิกไม่สำเร็จ',
        );
      }
    } catch (e) {
      return ApiResponse(
        isSuccess: false,
        message: 'เกิดข้อผิดพลาดในการเชื่อมต่อเซิร์ฟเวอร์',
      );
    }
  }

  // ดึงข้อมูลคณะทั้งหมด
  static Future<List<Map<String, dynamic>>> getFaculties() async {
    try {
      final uri = Uri.parse('${AppConfig.apiUrl}?action=get_faculties');
      final response = await _get(uri);
      if (response.body.trim().startsWith('{')) {
        final Map<String, dynamic> json = jsonDecode(response.body);
        if (json['status'] == 'success' && json['data'] is List) {
          return List<Map<String, dynamic>>.from(json['data']);
        }
      }
    } catch (_) {}
    return [];
  }

  // ดึงข้อมูลสาขาวิชาตามรหัสคณะ
  static Future<List<Map<String, dynamic>>> getMajors(int facultyId) async {
    try {
      final uri = Uri.parse('${AppConfig.apiUrl}?action=get_majors&faculty_id=$facultyId');
      final response = await _get(uri);
      if (response.body.trim().startsWith('{')) {
        final Map<String, dynamic> json = jsonDecode(response.body);
        if (json['status'] == 'success' && json['data'] is List) {
          return List<Map<String, dynamic>>.from(json['data']);
        }
      }
    } catch (_) {}
    return [];
  }

  // 3. ดึงรายการกิจกรรมทั้งหมด (รองรับการค้นหา)
  static Future<ApiResponse<List<ActivityModel>>> getActivities({String search = ''}) async {
    try {
      final uri = Uri.parse(
        '${AppConfig.apiUrl}?action=get_activities&search=${Uri.encodeComponent(search)}',
      );
      final response = await _get(uri);

      if (!response.body.trim().startsWith('{')) {
        return ApiResponse(isSuccess: false, message: 'เซิร์ฟเวอร์ตอบกลับไม่ถูกต้อง', data: []);
      }

      final Map<String, dynamic> json = jsonDecode(response.body);
      if (json['status'] == 'success' && json['data'] != null) {
        final List<dynamic> list = json['data'];
        final activities = list.map((item) => ActivityModel.fromJson(item)).toList();
        return ApiResponse(isSuccess: true, message: json['message'] ?? 'สำเร็จ', data: activities);
      } else {
        return ApiResponse(isSuccess: false, message: json['message'] ?? 'ไม่พบข้อมูลกิจกรรม', data: []);
      }
    } catch (e) {
      return ApiResponse(isSuccess: false, message: 'ไม่สามารถโหลดข้อมูลกิจกรรมได้', data: []);
    }
  }

  // 4. ดึงรายละเอียดกิจกรรมตามรหัส (Activity Detail)
  static Future<ApiResponse<ActivityModel>> getActivity(int activityId) async {
    try {
      final uri = Uri.parse('${AppConfig.apiUrl}?action=get_activity&id=$activityId');
      final response = await _get(uri);

      if (!response.body.trim().startsWith('{')) {
        return ApiResponse(isSuccess: false, message: 'เซิร์ฟเวอร์ตอบกลับไม่ถูกต้อง');
      }

      final Map<String, dynamic> json = jsonDecode(response.body);
      if (json['status'] == 'success' && json['data'] != null) {
        final activity = ActivityModel.fromJson(json['data']);
        return ApiResponse(isSuccess: true, message: json['message'] ?? 'สำเร็จ', data: activity);
      } else {
        return ApiResponse(isSuccess: false, message: json['message'] ?? 'ไม่พบกิจกรรมนี้');
      }
    } catch (e) {
      return ApiResponse(isSuccess: false, message: 'เกิดข้อผิดพลาดในการโหลดรายละเอียดกิจกรรม');
    }
  }

  // 5. ลงทะเบียนเข้าร่วมกิจกรรม (Register Activity)
  static Future<ApiResponse<void>> registerActivity(int userId, int activityId) async {
    try {
      final response = await _post(
        Uri.parse(AppConfig.apiUrl),
        {
          'action': 'register_activity',
          'user_id': userId.toString(),
          'activity_id': activityId.toString(),
        },
      );

      final Map<String, dynamic> json = jsonDecode(response.body);
      if (json['status'] == 'success') {
        return ApiResponse(isSuccess: true, message: json['message'] ?? 'ลงทะเบียนสำเร็จ');
      } else {
        return ApiResponse(isSuccess: false, message: json['message'] ?? 'ไม่สามารถลงทะเบียนได้');
      }
    } catch (e) {
      return ApiResponse(isSuccess: false, message: 'เกิดข้อผิดพลาดในการลงทะเบียนกิจกรรม');
    }
  }

  // 6. ดึงกิจกรรมที่ฉันเข้าร่วม (Joined Activities)
  static Future<ApiResponse<List<ActivityModel>>> getJoinedActivities(int userId) async {
    try {
      final uri = Uri.parse('${AppConfig.apiUrl}?action=get_joined_activities&user_id=$userId');
      final response = await _get(uri);

      if (!response.body.trim().startsWith('{')) {
        return ApiResponse(isSuccess: false, message: 'เซิร์ฟเวอร์ตอบกลับไม่ถูกต้อง', data: []);
      }

      final Map<String, dynamic> json = jsonDecode(response.body);
      if (json['status'] == 'success' && json['data'] != null) {
        final List<dynamic> list = json['data'];
        final activities = list.map((item) => ActivityModel.fromJson(item)).toList();
        return ApiResponse(isSuccess: true, message: json['message'] ?? 'สำเร็จ', data: activities);
      } else {
        return ApiResponse(isSuccess: false, message: json['message'] ?? 'ไม่มีกิจกรรมที่เข้าร่วม', data: []);
      }
    } catch (e) {
      return ApiResponse(isSuccess: false, message: 'ไม่สามารถโหลดกิจกรรมที่เข้าร่วมได้', data: []);
    }
  }

  // 7. ยกเลิกการลงทะเบียนกิจกรรม (Cancel Registration)
  static Future<ApiResponse<void>> cancelRegistration(int userId, int activityId) async {
    try {
      final response = await _post(
        Uri.parse(AppConfig.apiUrl),
        {
          'action': 'cancel_registration',
          'user_id': userId.toString(),
          'activity_id': activityId.toString(),
        },
      );

      final Map<String, dynamic> json = jsonDecode(response.body);
      if (json['status'] == 'success') {
        return ApiResponse(isSuccess: true, message: json['message'] ?? 'ยกเลิกการลงทะเบียนเรียบร้อย');
      } else {
        return ApiResponse(isSuccess: false, message: json['message'] ?? 'ไม่สามารถยกเลิกได้');
      }
    } catch (e) {
      return ApiResponse(isSuccess: false, message: 'เกิดข้อผิดพลาดในการยกเลิกการลงทะเบียน');
    }
  }

  // 8. ดึงรายการยานพาหนะของกิจกรรม (Get Vehicles)
  static Future<ApiResponse<List<VehicleModel>>> getVehicles(int activityId) async {
    try {
      final uri = Uri.parse('${AppConfig.apiUrl}?action=get_vehicles&activity_id=$activityId');
      final response = await _get(uri);

      if (!response.body.trim().startsWith('{')) {
        return ApiResponse(isSuccess: false, message: 'เซิร์ฟเวอร์ตอบกลับไม่ถูกต้อง', data: []);
      }

      final Map<String, dynamic> json = jsonDecode(response.body);
      if (json['status'] == 'success' && json['data'] != null) {
        final List<dynamic> list = json['data'];
        final vehicles = list.map((item) => VehicleModel.fromJson(item)).toList();
        return ApiResponse(isSuccess: true, message: json['message'] ?? 'สำเร็จ', data: vehicles);
      } else {
        return ApiResponse(isSuccess: false, message: json['message'] ?? 'ไม่มียานพาหนะ', data: []);
      }
    } catch (e) {
      return ApiResponse(isSuccess: false, message: 'ไม่สามารถโหลดยานพาหนะได้', data: []);
    }
  }

  // 9. บันทึกการเลือกยานพาหนะ/วิธีการเดินทาง (Select Vehicle)
  static Future<ApiResponse<void>> selectVehicle({
    required int userId,
    required int activityId,
    required String travelType,
    int vehicleId = 0,
  }) async {
    try {
      final response = await _post(
        Uri.parse(AppConfig.apiUrl),
        {
          'action': 'select_vehicle',
          'user_id': userId.toString(),
          'activity_id': activityId.toString(),
          'travel_type': travelType,
          'vehicle_id': vehicleId.toString(),
        },
      );

      final Map<String, dynamic> json = jsonDecode(response.body);
      if (json['status'] == 'success') {
        return ApiResponse(isSuccess: true, message: json['message'] ?? 'บันทึกสำเร็จ');
      } else {
        return ApiResponse(isSuccess: false, message: json['message'] ?? 'ไม่สามารถบันทึกได้');
      }
    } catch (e) {
      return ApiResponse(isSuccess: false, message: 'เกิดข้อผิดพลาดในการบันทึกวิธีการเดินทาง');
    }
  }

  // 10. เช็คอินกิจกรรมด้วยตนเอง (Self Check-in)
  static Future<ApiResponse<void>> selfCheckin(int userId, int activityId) async {
    try {
      final response = await _post(
        Uri.parse(AppConfig.apiUrl),
        {
          'action': 'self_checkin',
          'user_id': userId.toString(),
          'activity_id': activityId.toString(),
        },
      );

      final Map<String, dynamic> json = jsonDecode(response.body);
      if (json['status'] == 'success') {
        return ApiResponse(isSuccess: true, message: json['message'] ?? 'เช็คอินสำเร็จ!');
      } else {
        return ApiResponse(isSuccess: false, message: json['message'] ?? 'ไม่สามารถเช็คอินได้');
      }
    } catch (e) {
      return ApiResponse(isSuccess: false, message: 'เกิดข้อผิดพลาดในการเช็คอิน');
    }
  }

  // 11. ส่งแบบประเมินความพึงพอใจ 6 ข้อ (Submit Evaluation)
  static Future<ApiResponse<int>> submitEvaluation({
    required int userId,
    required int activityId,
    required int q1,
    required int q2,
    required int q3,
    required int q4,
    required int q5,
    required int q6,
  }) async {
    try {
      final response = await _post(
        Uri.parse(AppConfig.apiUrl),
        {
          'action': 'submit_evaluation',
          'user_id': userId.toString(),
          'activity_id': activityId.toString(),
          'q1': q1.toString(),
          'q2': q2.toString(),
          'q3': q3.toString(),
          'q4': q4.toString(),
          'q5': q5.toString(),
          'q6': q6.toString(),
        },
      );

      final Map<String, dynamic> json = jsonDecode(response.body);
      if (json['status'] == 'success') {
        final earned =
            json['data'] != null ? int.tryParse(json['data']['points_earned']?.toString() ?? '0') ?? 0 : 0;
        return ApiResponse(isSuccess: true, message: json['message'] ?? 'ประเมินสำเร็จ', data: earned);
      } else {
        return ApiResponse(isSuccess: false, message: json['message'] ?? 'ไม่สามารถประเมินได้');
      }
    } catch (e) {
      return ApiResponse(isSuccess: false, message: 'เกิดข้อผิดพลาดในการส่งแบบประเมิน');
    }
  }

  // 12. ดึงข้อมูลเกียรติบัตรและคะแนนจิตอาสา (Get Certificates)
  static Future<ApiResponse<Map<String, dynamic>>> getCertificates(int userId) async {
    try {
      final uri = Uri.parse('${AppConfig.apiUrl}?action=get_certificates&user_id=$userId');
      final response = await _get(uri);

      if (!response.body.trim().startsWith('{')) {
        return ApiResponse(isSuccess: false, message: 'เซิร์ฟเวอร์ตอบกลับไม่ถูกต้อง');
      }

      final Map<String, dynamic> json = jsonDecode(response.body);
      if (json['status'] == 'success' && json['data'] != null) {
        return ApiResponse(isSuccess: true, message: json['message'] ?? 'สำเร็จ', data: json['data']);
      } else {
        return ApiResponse(isSuccess: false, message: json['message'] ?? 'ไม่พบข้อมูลเกียรติบัตร');
      }
    } catch (e) {
      return ApiResponse(isSuccess: false, message: 'เกิดข้อผิดพลาดในการโหลดข้อมูลเกียรติบัตร');
    }
  }

  // 13. ดึงข่าวสารชมรม (Get News)
  static Future<ApiResponse<List<NewsModel>>> getNews() async {
    try {
      final uri = Uri.parse('${AppConfig.apiUrl}?action=get_news');
      final response = await _get(uri);

      if (!response.body.trim().startsWith('{')) {
        return ApiResponse(isSuccess: false, message: 'เซิร์ฟเวอร์ตอบกลับไม่ถูกต้อง', data: []);
      }

      final Map<String, dynamic> json = jsonDecode(response.body);
      if (json['status'] == 'success' && json['data'] != null) {
        final List<dynamic> list = json['data'];
        final newsList = list.map((item) => NewsModel.fromJson(item)).toList();
        return ApiResponse(isSuccess: true, message: json['message'] ?? 'สำเร็จ', data: newsList);
      } else {
        return ApiResponse(isSuccess: false, message: json['message'] ?? 'ไม่มีข่าวสาร', data: []);
      }
    } catch (e) {
      return ApiResponse(isSuccess: false, message: 'ไม่สามารถโหลดข่าวสารได้', data: []);
    }
  }

  // 14. บันทึกแก้ไขโปรไฟล์ (Update Profile)
  static Future<ApiResponse<void>> updateProfile({
    required int userId,
    required String name,
    required String phone,
    required String email,
  }) async {
    try {
      final response = await _post(
        Uri.parse(AppConfig.apiUrl),
        {
          'action': 'update_profile',
          'user_id': userId.toString(),
          'name': name,
          'phone': phone,
          'email': email,
        },
      );

      final Map<String, dynamic> json = jsonDecode(response.body);
      if (json['status'] == 'success') {
        return ApiResponse(isSuccess: true, message: json['message'] ?? 'บันทึกข้อมูลเรียบร้อยแล้ว');
      } else {
        return ApiResponse(isSuccess: false, message: json['message'] ?? 'ไม่สามารถบันทึกได้');
      }
    } catch (e) {
      return ApiResponse(isSuccess: false, message: 'เกิดข้อผิดพลาดในการบันทึกข้อมูล');
    }
  }

  // 15. ดึงรายชื่อทำเนียบผู้นำรุ่น คณะกรรมการชมรม (Get Committees)
  static Future<ApiResponse<List<Map<String, dynamic>>>> getCommittees() async {
    try {
      final uri = Uri.parse('${AppConfig.apiUrl}?action=get_committees');
      final response = await _get(uri);

      if (!response.body.trim().startsWith('{')) {
        return ApiResponse(isSuccess: false, message: 'เซิร์ฟเวอร์ตอบกลับไม่ถูกต้อง', data: []);
      }

      final Map<String, dynamic> json = jsonDecode(response.body);
      if (json['status'] == 'success' && json['data'] != null) {
        final List<dynamic> list = json['data'];
        final committees = list.map((item) => Map<String, dynamic>.from(item)).toList();
        return ApiResponse(isSuccess: true, message: json['message'] ?? 'สำเร็จ', data: committees);
      } else {
        return ApiResponse(isSuccess: false, message: json['message'] ?? 'ไม่พบข้อมูลคณะกรรมการ', data: []);
      }
    } catch (e) {
      return ApiResponse(isSuccess: false, message: 'เกิดข้อผิดพลาดในการดึงข้อมูลคณะกรรมการ', data: []);
    }
  }

  // 16. อนุมัติหรือปฏิเสธกิจกรรมสำหรับอาจารย์ที่ปรึกษา (Advisor Approve Activity)
  static Future<ApiResponse<void>> advisorApproveActivity({
    required int activityId,
    required int advisorId,
    required String status, // 'approved' หรือ 'rejected'
  }) async {
    try {
      final response = await _post(
        Uri.parse(AppConfig.apiUrl),
        {
          'action': 'advisor_approve_activity',
          'activity_id': activityId.toString(),
          'advisor_id': advisorId.toString(),
          'status': status,
        },
      );

      final Map<String, dynamic> json = jsonDecode(response.body);
      if (json['status'] == 'success') {
        return ApiResponse(isSuccess: true, message: json['message'] ?? 'บันทึกสถานะเรียบร้อยแล้ว');
      } else {
        return ApiResponse(isSuccess: false, message: json['message'] ?? 'ไม่สามารถบันทึกได้');
      }
    } catch (e) {
      return ApiResponse(isSuccess: false, message: 'เกิดข้อผิดพลาดในการเชื่อมต่อเซิร์ฟเวอร์');
    }
  }

  // 17. ดึงรายชื่อผู้ลงทะเบียนในกิจกรรม (Get Activity Participants)
  static Future<ApiResponse<List<Map<String, dynamic>>>> getActivityParticipants(int activityId) async {
    try {
      final uri = Uri.parse('${AppConfig.apiUrl}?action=get_activity_participants&activity_id=$activityId');
      final response = await _get(uri);

      if (!response.body.trim().startsWith('{')) {
        return ApiResponse(isSuccess: false, message: 'เซิร์ฟเวอร์ตอบกลับไม่ถูกต้อง', data: []);
      }

      final Map<String, dynamic> json = jsonDecode(response.body);
      if (json['status'] == 'success' && json['data'] != null) {
        final List<dynamic> list = json['data'];
        final participants = list.map((item) => Map<String, dynamic>.from(item)).toList();
        return ApiResponse(isSuccess: true, message: json['message'] ?? 'สำเร็จ', data: participants);
      } else {
        return ApiResponse(isSuccess: false, message: json['message'] ?? 'ไม่พบผู้ลงทะเบียน', data: []);
      }
    } catch (e) {
      return ApiResponse(isSuccess: false, message: 'เกิดข้อผิดพลาดในการดึงรายชื่อผู้ลงทะเบียน', data: []);
    }
  }

  // 18. เช็คอินกิจกรรมด้วยรหัส QR หรือรหัสนักศึกษา (Check-in QR)
  static Future<ApiResponse<Map<String, dynamic>>> checkinQr({
    required String qrData,
    int? activityId,
  }) async {
    try {
      final body = <String, String>{
        'action': 'checkin_qr',
        'qr_data': qrData,
      };
      if (activityId != null && activityId > 0) {
        body['activity_id'] = activityId.toString();
      }

      final response = await _post(Uri.parse(AppConfig.apiUrl), body);

      if (!response.body.trim().startsWith('{')) {
        return ApiResponse(isSuccess: false, message: 'เซิร์ฟเวอร์ตอบกลับไม่ถูกต้อง');
      }

      final Map<String, dynamic> json = jsonDecode(response.body);
      final isSuccess = json['status'] == 'success' || json['status'] == 'info';
      final message = json['message'] ?? 'ดำเนินการเรียบร้อยแล้ว';
      final data = json['data'] != null ? Map<String, dynamic>.from(json['data']) : null;

      return ApiResponse(isSuccess: isSuccess, message: message, data: data);
    } catch (e) {
      return ApiResponse(isSuccess: false, message: 'เกิดข้อผิดพลาดในการเชื่อมต่อเซิร์ฟเวอร์');
    }
  }
}


