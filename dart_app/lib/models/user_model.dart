// user_model.dart
// โมเดลข้อมูลผู้ใช้งานระบบชมรมเพื่อนภูผา
// สอดคล้องกับคอลัมน์ในตาราง `users` ของฐานข้อมูล MySQL

class UserModel {
  final int userId;
  final String username;
  final String name;
  final String role;
  final String userType;
  final String? studentId;
  final String? phone;
  final String? email;
  final int points;
  final String? profilePicture;
  final String? faculty;
  final String? major;
  final String? committeePosition;

  UserModel({
    required this.userId,
    required this.username,
    required this.name,
    required this.role,
    this.userType = 'student',
    this.studentId,
    this.phone,
    this.email,
    this.points = 0,
    this.profilePicture,
    this.faculty,
    this.major,
    this.committeePosition,
  });

  // แปลงข้อมูลจาก JSON ที่ได้รับจาก API เป็น UserModel Object
  factory UserModel.fromJson(Map<String, dynamic> json) {
    //ฐานเดิมมีทั้งสองชื่อคอลัมน์ หากช่องใหม่ว่างยังต้องอ่านรูปจากเว็บเดิมได้
    String? image = json['profile_picture']?.toString();
    if (image == null || image.isEmpty) {
      image = json['image']?.toString();
    }
    return UserModel(
      userId: int.tryParse(json['user_id']?.toString() ?? '0') ?? 0,
      username: json['username']?.toString() ?? '',
      name: json['name']?.toString() ?? '',
      role: json['role']?.toString() ?? 'member',
      userType: json['user_type']?.toString() ?? 'student',
      studentId: json['student_id']?.toString(),
      phone: json['phone']?.toString(),
      email: json['email']?.toString(),
      points: int.tryParse(json['points']?.toString() ?? '0') ?? 0,
      profilePicture: image,
      faculty: json['faculty']?.toString(),
      major: json['major']?.toString(),
      committeePosition: json['committee_position']?.toString(),
    );
  }

  // แปลง UserModel Object เป็น Map JSON สำหรับบันทึกหรือส่งออก
  Map<String, dynamic> toJson() {
    return {
      'user_id': userId,
      'username': username,
      'name': name,
      'role': role,
      'user_type': userType,
      'student_id': studentId,
      'phone': phone,
      'email': email,
      'points': points,
      'profile_picture': profilePicture,
      'faculty': faculty,
      'major': major,
      'committee_position': committeePosition,
    };
  }

  //กติกาเดียวกับเมนูเว็บ การซ่อนเมนูช่วยไม่ให้กดผิด แต่ API ยังต้องตรวจสิทธิ์ซ้ำ
  bool canManage(String menu) {
    if (role == 'admin' || role == 'advisor') {
      return true;
    }
    if (role != 'committee' && role != 'leader') {
      return false;
    }
    String? position = committeePosition;
    if (role == 'leader' && (position == null || position.isEmpty)) {
      position = 'president';
    }
    const permissions = {
      'activities': [
        'president',
        'vice_president',
        'secretary_saluang',
        'secretary_wiangbua',
      ],
      'budgets': ['president', 'vice_president', 'treasurer'],
      'vehicles': ['president', 'vice_president', 'venue_coordinator'],
      'reports': [
        'president',
        'vice_president',
        'secretary_saluang',
        'secretary_wiangbua',
      ],
      'qr_scanner': [
        'president',
        'vice_president',
        'treasurer',
        'secretary_saluang',
        'secretary_wiangbua',
        'nurse',
        'venue_coordinator',
      ],
      'members': [
        'president',
        'vice_president',
        'secretary_saluang',
        'secretary_wiangbua',
      ],
    };
    return permissions[menu]?.contains(position) ?? false;
  }
}
