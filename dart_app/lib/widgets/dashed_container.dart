import 'package:flutter/material.dart';

// dashed_container.dart
// คอมโพเนนต์กล่องกรอบเส้นประ (Dashed Border Box)
// ใช้สำหรับแสดงสถานะข้อมูลว่าง (Empty State) เช่น ยังไม่มีกิจกรรมใหม่ หรือ ยังไม่มีข่าวประชาสัมพันธ์
// ดีไซน์ถอดแบบมาจากคลาส .card.border-dashed ใน components.css และ index.php ของระบบเว็บ

class DashedContainer extends StatelessWidget {
  // ข้อความที่จะแสดงตรงกลางกล่อง
  final String message;

  // ระยะห่างด้านใน (Padding)
  final EdgeInsetsGeometry padding;

  const DashedContainer({
    super.key,
    required this.message,
    this.padding = const EdgeInsets.symmetric(vertical: 24, horizontal: 16),
  });

  @override
  Widget build(BuildContext context) {
    return CustomPaint(
      painter: _DashedRectPainter(
        color: const Color(0xFFBDBDBD), // สีเทาอ่อนตามเส้นประบนเว็บ (--text-light)
        strokeWidth: 1.2,
        gap: 5.0,
        dash: 5.0,
        radius: 12.0, // ความโค้งมนของมุมกล่องตามธีมเว็บ
      ),
      child: Container(
        width: double.infinity,
        padding: padding,
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(12),
        ),
        child: Center(
          child: Text(
            message,
            textAlign: TextAlign.center,
            style: const TextStyle(
              fontSize: 14,
              color: Color(0xFF616161), // สีตัวอักษรรองตาม --text-muted
              fontWeight: FontWeight.normal,
            ),
          ),
        ),
      ),
    );
  }
}

// คลาส CustomPainter สำหรับวาดเส้นขอบประโค้งมนรอบกล่อง
class _DashedRectPainter extends CustomPainter {
  final Color color;
  final double strokeWidth;
  final double gap;
  final double dash;
  final double radius;

  _DashedRectPainter({
    required this.color,
    required this.strokeWidth,
    required this.gap,
    required this.dash,
    required this.radius,
  });

  @override
  void paint(Canvas canvas, Size size) {
    final paint = Paint()
      ..color = color
      ..strokeWidth = strokeWidth
      ..style = PaintingStyle.stroke;

    final rrect = RRect.fromRectAndRadius(
      Rect.fromLTWH(0, 0, size.width, size.height),
      Radius.circular(radius),
    );

    final path = Path()..addRRect(rrect);
    final dashedPath = _createDashedPath(path, dash, gap);

    canvas.drawPath(dashedPath, paint);
  }

  // ฟังก์ชันคำนวณแบ่งเส้น Path เป็นท่อนๆ (Dashed)
  Path _createDashedPath(Path source, double dashLength, double gapLength) {
    final dashedPath = Path();
    for (final metric in source.computeMetrics()) {
      double distance = 0.0;
      bool draw = true;
      while (distance < metric.length) {
        final length = draw ? dashLength : gapLength;
        if (distance + length > metric.length) {
          if (draw) {
            dashedPath.addPath(
              metric.extractPath(distance, metric.length),
              Offset.zero,
            );
          }
          break;
        }
        if (draw) {
          dashedPath.addPath(
            metric.extractPath(distance, distance + length),
            Offset.zero,
          );
        }
        distance += length;
        draw = !draw;
      }
    }
    return dashedPath;
  }

  @override
  bool shouldRepaint(covariant _DashedRectPainter oldDelegate) {
    return oldDelegate.color != color ||
        oldDelegate.strokeWidth != strokeWidth ||
        oldDelegate.gap != gap ||
        oldDelegate.dash != dash ||
        oldDelegate.radius != radius;
  }
}
