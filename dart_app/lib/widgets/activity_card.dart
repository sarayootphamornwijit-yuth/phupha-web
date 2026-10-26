import 'package:flutter/material.dart';
import '../constants/app_colors.dart';
import '../constants/app_config.dart';
import '../models/activity_model.dart';

// activity_card.dart
// วิดเจ็ตการ์ดแสดงกิจกรรม สอดคล้องกับ UI ในหน้าแรกและหน้ากิจกรรมของเว็บเดิม
class ActivityCard extends StatelessWidget {
  final ActivityModel activity;
  final VoidCallback onTap;

  const ActivityCard({
    super.key,
    required this.activity,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    final imageUrl = AppConfig.getFullImageUrl(activity.coverImage);

    return Card(
      margin: const EdgeInsets.only(bottom: 16),
      elevation: 2,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(16),
        side: const BorderSide(color: AppColors.cardBorder, width: 0.8),
      ),
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: onTap,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // ส่วนรูปภาพปกกิจกรรม
            SizedBox(
              height: 150,
              width: double.infinity,
              child: Stack(
                fit: StackFit.expand,
                children: [
                  if (imageUrl.isNotEmpty)
                    Image.network(
                      imageUrl,
                      fit: BoxFit.cover,
                      errorBuilder: (context, error, stackTrace) => Container(
                        color: AppColors.primaryPale,
                        child: const Center(
                          child: Icon(Icons.terrain, size: 48, color: AppColors.primary),
                        ),
                      ),
                    )
                  else
                    Container(
                      color: AppColors.primaryPale,
                      child: const Center(
                        child: Icon(Icons.terrain, size: 48, color: AppColors.primary),
                      ),
                    ),
                  // ป้ายหมวดหมู่กิจกรรม
                  Positioned(
                    top: 10,
                    left: 10,
                    child: Container(
                      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                      decoration: BoxDecoration(
                        color: AppColors.primaryDark.withValues(alpha: 0.85),
                        borderRadius: BorderRadius.circular(20),
                      ),
                      child: Text(
                        activity.categoryText,
                        style: const TextStyle(
                          color: Colors.white,
                          fontSize: 12,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                    ),
                  ),
                  // แต้มจิตอาสา
                  if (activity.points > 0)
                    Positioned(
                      top: 10,
                      right: 10,
                      child: Container(
                        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                        decoration: BoxDecoration(
                          color: AppColors.warning,
                          borderRadius: BorderRadius.circular(20),
                        ),
                        child: Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            const Icon(Icons.star, color: Colors.white, size: 14),
                            const SizedBox(width: 4),
                            Text(
                              '+${activity.points} แต้ม',
                              style: const TextStyle(
                                color: Colors.white,
                                fontSize: 12,
                                fontWeight: FontWeight.bold,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                ],
              ),
            ),

            // ข้อมูลเนื้อหากิจกรรม
            Padding(
              padding: const EdgeInsets.all(14),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    activity.title,
                    style: const TextStyle(
                      fontSize: 16,
                      fontWeight: FontWeight.bold,
                      color: AppColors.textMain,
                    ),
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                  ),
                  const SizedBox(height: 8),

                  // วันที่จัดกิจกรรม
                  Row(
                    children: [
                      const Icon(Icons.calendar_today_outlined, size: 14, color: AppColors.primary),
                      const SizedBox(width: 6),
                      Expanded(
                        child: Text(
                          activity.startDate,
                          style: const TextStyle(fontSize: 13, color: AppColors.textMuted),
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 4),

                  // สถานที่จัดกิจกรรม
                  if (activity.locationName != null && activity.locationName!.isNotEmpty)
                    Row(
                      children: [
                        const Icon(Icons.location_on_outlined, size: 14, color: AppColors.danger),
                        const SizedBox(width: 6),
                        Expanded(
                          child: Text(
                            activity.locationName!,
                            style: const TextStyle(fontSize: 13, color: AppColors.textMuted),
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                          ),
                        ),
                      ],
                    ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}
