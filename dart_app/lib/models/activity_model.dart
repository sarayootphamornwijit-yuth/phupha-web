// activity_model.dart
// โมเดลข้อมูลกิจกรรมชมรมเพื่อนภูผา
// สอดคล้องกับคอลัมน์ในตาราง `activities` และ `user_activities`

class ActivityModel {
  final int activityId;
  final String title;
  final String? description;
  final String? coverImage;
  final String? category;
  final String? activityType;
  final String startDate;
  final String? endDate;
  final String? locationName;
  final String? meetingPoint;
  final String? meetingTime;
  final int maxParticipants;
  final int points;
  final String approvalStatus;
  final int participantCount;
  final bool selfCheckinEnabled;

  // ฟิลด์เพิ่มเติมกรณีดึงจากกิจกรรมที่ผู้ใช้เข้าร่วม (joined_activities)
  final int? userActivityId;
  final String? userStatus; // 'registered', 'checked_in', 'checked_out', 'cancelled'
  final String? travelMethod; // 'own_travel', 'club_vehicle', etc.

  ActivityModel({
    required this.activityId,
    required this.title,
    this.description,
    this.coverImage,
    this.category,
    this.activityType,
    required this.startDate,
    this.endDate,
    this.locationName,
    this.meetingPoint,
    this.meetingTime,
    this.maxParticipants = 0,
    this.points = 0,
    this.approvalStatus = 'approved',
    this.participantCount = 0,
    this.selfCheckinEnabled = false,
    this.userActivityId,
    this.userStatus,
    this.travelMethod,
  });

  // แปลงข้อมูลจาก JSON ที่ได้รับจาก API เป็น ActivityModel Object
  factory ActivityModel.fromJson(Map<String, dynamic> json) {
    return ActivityModel(
      activityId: int.tryParse(json['activity_id']?.toString() ?? '0') ?? 0,
      title: json['title']?.toString() ?? '',
      description: json['description']?.toString(),
      coverImage: json['cover_image']?.toString(),
      category: json['category']?.toString(),
      activityType: json['activity_type']?.toString(),
      startDate: json['start_date']?.toString() ?? '',
      endDate: json['end_date']?.toString(),
      locationName: json['location_name']?.toString(),
      meetingPoint: json['meeting_point']?.toString(),
      meetingTime: json['meeting_time']?.toString(),
      maxParticipants: int.tryParse(json['max_participants']?.toString() ?? '0') ?? 0,
      points: int.tryParse(json['points']?.toString() ?? '0') ?? 0,
      approvalStatus: json['approval_status']?.toString() ?? 'approved',
      participantCount: int.tryParse(json['participant_count']?.toString() ?? '0') ?? 0,
      selfCheckinEnabled: (json['self_checkin_enabled']?.toString() == '1' || json['self_checkin_enabled'] == true),
      userActivityId: json['user_activity_id'] != null ? int.tryParse(json['user_activity_id'].toString()) : null,
      userStatus: json['status']?.toString(),
      travelMethod: json['travel_method']?.toString(),
    );
  }

  // แปลงหมวดหมู่กิจกรรมภาษาอังกฤษเป็นภาษาไทย
  String get categoryText {
    switch (category) {
      case 'volunteer':
        return 'กิจกรรมจิตอาสา';
      case 'sport':
        return 'กิจกรรมกีฬา';
      case 'byaniyan':
        return 'กิจกรรมสานสัมพันธ์/บายเนียร์';
      default:
        return 'กิจกรรมทั่วไป';
    }
  }

  // แปลงสถานะการเข้าร่วมเป็นข้อความภาษาไทย
  String get userStatusText {
    switch (userStatus) {
      case 'registered':
        return 'ลงทะเบียนแล้ว';
      case 'checked_in':
        return 'เช็คอินแล้ว';
      case 'checked_out':
        return 'เสร็จสิ้นกิจกรรม';
      case 'cancelled':
        return 'ยกเลิกแล้ว';
      default:
        return 'ยังไม่ได้ลงทะเบียน';
    }
  }

  // แปลง startDate เป็น DateTime (ถ้าทำได้)
  DateTime? get startDateParsed {
    if (startDate.isEmpty) return null;
    return DateTime.tryParse(startDate);
  }

  // แปลง endDate เป็น DateTime (ถ้าทำได้)
  DateTime? get endDateParsed {
    if (endDate == null || endDate!.isEmpty) return null;
    return DateTime.tryParse(endDate!);
  }

  // วันที่จัดกิจกรรมในรูปแบบ วัน/เดือน/ปี พ.ศ. หรือ ค.ศ.
  String get startDateFormatted {
    final parsed = startDateParsed;
    if (parsed == null) return startDate;
    final day = parsed.day.toString().padLeft(2, '0');
    final month = parsed.month.toString().padLeft(2, '0');
    final year = parsed.year;
    return '$day/$month/$year';
  }
}
