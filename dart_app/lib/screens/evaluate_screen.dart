import 'package:flutter/material.dart';
import '../constants/app_colors.dart';
import '../services/api_service.dart';
import '../services/session_manager.dart';
import '../widgets/custom_button.dart';

// evaluate_screen.dart
// หน้าจอทำแบบประเมินความพึงพอใจในกิจกรรม 6 ข้อ (Likert Scale 1-5)
// สอดคล้องกับ evaluate.php ในระบบเว็บเดิม
class EvaluateScreen extends StatefulWidget {
  final int activityId;
  final String activityTitle;

  const EvaluateScreen({
    super.key,
    required this.activityId,
    required this.activityTitle,
  });

  @override
  State<EvaluateScreen> createState() => _EvaluateScreenState();
}

class _EvaluateScreenState extends State<EvaluateScreen> {
  // คะแนน 6 ข้อ เริ่มต้นที่ 5 คะแนน (พึงพอใจมากที่สุด)
  int _q1 = 5;
  int _q2 = 5;
  int _q3 = 5;
  int _q4 = 5;
  int _q5 = 5;
  int _q6 = 5;

  bool _isSubmitting = false;

  // ข้อคำถามทั้ง 6 ข้อ ตาม evaluate.php ของระบบเว็บเดิม
  final List<String> _questions = const [
    '1. ความเหมาะสมของสถานที่และสิ่งอำนวยความสะดวกในการจัดกิจกรรม',
    '2. การบริหารจัดการ การประสานงาน และกำหนดการของกิจกรรม',
    '3. ประโยชน์และความรู้/ประสบการณ์ที่ได้รับจากการเข้าร่วมกิจกรรม',
    '4. ความพร้อม ความเป็นกันเอง และการดูแลของคณะกรรมการชมรม',
    '5. อาหาร เครื่องดื่ม และการดูแลสวัสดิการตลอดกิจกรรม',
    '6. ความพึงพอใจในภาพรวมต่อการเข้าร่วมกิจกรรมในครั้งนี้',
  ];

  // ส่งผลการประเมิน
  Future<void> _handleSubmit() async {
    final user = await SessionManager.getUser();
    if (user == null) return;

    setState(() {
      _isSubmitting = true;
    });

    final response = await ApiService.submitEvaluation(
      userId: user.userId,
      activityId: widget.activityId,
      q1: _q1,
      q2: _q2,
      q3: _q3,
      q4: _q4,
      q5: _q5,
      q6: _q6,
    );

    if (!mounted) return;

    setState(() {
      _isSubmitting = false;
    });

    if (response.isSuccess) {
      await showDialog(
        context: context,
        builder: (context) => AlertDialog(
          title: const Row(
            children: [
              Icon(Icons.check_circle, color: AppColors.success),
              SizedBox(width: 8),
              Text('ประเมินสำเร็จ'),
            ],
          ),
          content: Text(response.message),
          actions: [
            ElevatedButton(
              style: ElevatedButton.styleFrom(backgroundColor: AppColors.primary),
              onPressed: () {
                Navigator.pop(context); // ปิด dialog
                Navigator.pop(context); // กลับหน้าก่อนหน้า
              },
              child: const Text('ตกลง', style: TextStyle(color: Colors.white)),
            ),
          ],
        ),
      );
    } else {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(response.message), backgroundColor: AppColors.danger),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.bgMain,
      appBar: AppBar(
        title: const Text(
          'แบบประเมินความพึงพอใจ',
          style: TextStyle(
            fontSize: 17,
            fontWeight: FontWeight.bold,
            color: AppColors.primaryDark,
          ),
        ),
        backgroundColor: Colors.white,
        elevation: 0,
        scrolledUnderElevation: 0,
        centerTitle: true,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back, color: AppColors.primaryDark),
          onPressed: () => Navigator.pop(context),
        ),
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            // ส่วนหัวเรื่อง
            Container(
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(16),
                border: Border.all(color: AppColors.cardBorder),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text('กิจกรรมที่ประเมิน', style: TextStyle(fontSize: 12, color: AppColors.textMuted)),
                  const SizedBox(height: 4),
                  Text(
                    widget.activityTitle,
                    style: const TextStyle(fontSize: 16, fontWeight: FontWeight.bold, color: AppColors.primaryDark),
                  ),
                  const SizedBox(height: 10),
                  const Text(
                    'เกณฑ์คะแนน: 5 = มากที่สุด, 4 = มาก, 3 = ปานกลาง, 2 = น้อย, 1 = น้อยที่สุด',
                    style: TextStyle(fontSize: 12, color: AppColors.textMuted),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 16),

            // ข้อคำถามที่ 1 ถึง 6
            _buildRatingItem(_questions[0], _q1, (val) => setState(() => _q1 = val)),
            _buildRatingItem(_questions[1], _q2, (val) => setState(() => _q2 = val)),
            _buildRatingItem(_questions[2], _q3, (val) => setState(() => _q3 = val)),
            _buildRatingItem(_questions[3], _q4, (val) => setState(() => _q4 = val)),
            _buildRatingItem(_questions[4], _q5, (val) => setState(() => _q5 = val)),
            _buildRatingItem(_questions[5], _q6, (val) => setState(() => _q6 = val)),

            const SizedBox(height: 20),

            // ปุ่มส่งแบบประเมิน
            CustomButton(
              text: 'ส่งแบบประเมินและรับแต้มจิตอาสา',
              icon: Icons.send,
              onPressed: _handleSubmit,
              isLoading: _isSubmitting,
            ),
            const SizedBox(height: 24),
          ],
        ),
      ),
    );
  }

  // วิดเจ็ตสำหรับแต่ละข้อคำถาม
  Widget _buildRatingItem(String question, int currentVal, ValueChanged<int> onChanged) {
    return Card(
      margin: const EdgeInsets.only(bottom: 12),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
      elevation: 1,
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              question,
              style: const TextStyle(fontSize: 14, fontWeight: FontWeight.bold, color: AppColors.textMain),
            ),
            const SizedBox(height: 12),
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceAround,
              children: List.generate(5, (index) {
                final score = index + 1;
                final isSelected = currentVal == score;

                return InkWell(
                  onTap: () => onChanged(score),
                  borderRadius: BorderRadius.circular(20),
                  child: Container(
                    width: 44,
                    height: 44,
                    decoration: BoxDecoration(
                      color: isSelected ? AppColors.primary : AppColors.primaryPale,
                      shape: BoxShape.circle,
                      border: Border.all(
                        color: isSelected ? AppColors.primary : AppColors.cardBorder,
                      ),
                    ),
                    alignment: Alignment.center,
                    child: Text(
                      '$score',
                      style: TextStyle(
                        color: isSelected ? Colors.white : AppColors.primaryDark,
                        fontWeight: FontWeight.bold,
                        fontSize: 16,
                      ),
                    ),
                  ),
                );
              }),
            ),
          ],
        ),
      ),
    );
  }
}
