// news_model.dart
// โมเดลข้อมูลข่าวประชาสัมพันธ์ชมรมเพื่อนภูผา
// สอดคล้องกับคอลัมน์ในตาราง `news`

class NewsModel {
  final int newsId;
  final String title;
  final String content;
  final String? imageUrl;
  final String createdAt;

  NewsModel({
    required this.newsId,
    required this.title,
    required this.content,
    this.imageUrl,
    required this.createdAt,
  });

  // แปลงข้อมูลจาก JSON เป็น NewsModel Object
  factory NewsModel.fromJson(Map<String, dynamic> json) {
    return NewsModel(
      newsId: int.tryParse(json['news_id']?.toString() ?? '0') ?? 0,
      title: json['title']?.toString() ?? '',
      content: json['content']?.toString() ?? '',
      imageUrl: json['image_url']?.toString(),
      createdAt: json['created_at']?.toString() ?? '',
    );
  }

  // แปลงวันที่สร้างข่าวในรูปแบบ วัน/เดือน/ปี
  String get createdAtFormatted {
    if (createdAt.isEmpty) return '';
    final parsed = DateTime.tryParse(createdAt);
    if (parsed == null) return createdAt;
    final day = parsed.day.toString().padLeft(2, '0');
    final month = parsed.month.toString().padLeft(2, '0');
    final year = parsed.year;
    return '$day/$month/$year';
  }
}
