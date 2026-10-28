import 'package:flutter/material.dart';
import '../constants/app_colors.dart';
import '../services/api_service.dart';

// committee_directory_screen.dart
// หน้าจอ "ทำเนียบผู้นำรุ่น" ถอดแบบดีไซน์จาก committee_directory.php ของเว็บไซต์
class CommitteeDirectoryScreen extends StatefulWidget {
  const CommitteeDirectoryScreen({super.key});

  @override
  State<CommitteeDirectoryScreen> createState() => _CommitteeDirectoryScreenState();
}

class _CommitteeDirectoryScreenState extends State<CommitteeDirectoryScreen> {
  // ชั้นปีที่กำลังเลือก (1, 2, 3, 4) เริ่มต้นที่ปี 2 ตามภาพหน้าเว็บ
  int _selectedYear = 2;
  bool _isLoading = false;

  // ข้อมูลคณะกรรมการแยกตามชั้นปี
  final Map<int, List<Map<String, dynamic>>> _committeeByYear = {
    1: [],
    2: [
      {
        'name': 'นาย ธนกร แซ่ย่าง',
        'committee_position': 'ประธานชมรม',
        'faculty': 'วิทยาการจัดการ',
        'major': 'ธุรกิจระหว่างประเทศ',
        'image': '',
      },
      {
        'name': 'นางสาว ฟ้าใส แซ่ย่าง',
        'committee_position': 'รองประธานชมรม',
        'faculty': 'ครุศาสตร์',
        'major': 'ชีววิทยา',
        'image': '',
      },
      {
        'name': 'นางสาว ชนิภาพร แซ่เห้อ',
        'committee_position': 'เหรัญญิก',
        'faculty': 'ครุศาสตร์',
        'major': 'ชีววิทยา',
        'image': '',
      },
      {
        'name': 'นาย อดิศักดิ์ แซ่ลี',
        'committee_position': 'เลขาสะลวง',
        'faculty': 'ครุศาสตร์',
        'major': '',
        'image': '',
      },
      {
        'name': 'นางสาว ภัทรินทร์ ชรินทร์ภรณ์',
        'committee_position': 'เลขาเวียงบัว',
        'faculty': 'มนุษยศาสตร์และสังคมศาสตร์',
        'major': '',
        'image': '',
      },
      {
        'name': 'นางสาว เกศรินทร์ อุไรรุ่งโรจน์',
        'committee_position': 'เลขาเวียงบัว',
        'faculty': 'มนุษยศาสตร์และสังคมศาสตร์',
        'major': '',
        'image': '',
      },
    ],
    3: [],
    4: [],
  };

  // ตำแหน่งภาษาอังกฤษเป็นภาษาไทย
  final Map<String, String> _positionLabelsThai = {
    'president': 'ประธานชมรม',
    'vice_president': 'รองประธานชมรม',
    'treasurer': 'เหรัญญิก',
    'secretary_saluang': 'เลขาสะลวง',
    'secretary_wiangbua': 'เลขาเวียงบัว',
    'nurse': 'พยาบาล',
    'venue_coordinator': 'ฝ่ายสถานที่',
    'pr': 'ฝ่ายประชาสัมพันธ์',
  };

  @override
  void initState() {
    super.initState();
    _fetchCommittees();
  }

  Future<void> _fetchCommittees() async {
    setState(() => _isLoading = true);
    final res = await ApiService.getCommittees();
    if (res.isSuccess && res.data != null && res.data!.isNotEmpty) {
      final Map<int, List<Map<String, dynamic>>> grouped = {
        1: [],
        2: [],
        3: [],
        4: [],
      };
      for (var item in res.data!) {
        final year = int.tryParse(item['academic_year']?.toString() ?? '0') ?? 0;
        if (grouped.containsKey(year)) {
          final posKey = item['committee_position']?.toString() ?? '';
          item['position_thai'] = _positionLabelsThai[posKey] ?? posKey;
          grouped[year]!.add(item);
        }
      }
      setState(() {
        for (var y = 1; y <= 4; y++) {
          if (grouped[y]!.isNotEmpty) {
            _committeeByYear[y] = grouped[y]!;
          }
        }
      });
    }
    setState(() => _isLoading = false);
  }

  @override
  Widget build(BuildContext context) {
    final currentList = _committeeByYear[_selectedYear] ?? [];

    return Scaffold(
      backgroundColor: const Color(0xFFF1F8E9), // พื้นหลังเขียวพาสเทลตามเว็บ
      appBar: AppBar(
        title: const Text(
          'ทำเนียบผู้นำรุ่น',
          style: TextStyle(
            color: AppColors.primaryDark,
            fontWeight: FontWeight.bold,
            fontSize: 20,
          ),
        ),
        backgroundColor: Colors.white,
        elevation: 0.5,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_ios_new, color: AppColors.primaryDark, size: 20),
          onPressed: () => Navigator.pop(context),
        ),
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 20),
        child: Center(
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 800),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.center,
              children: [
                // หัวข้อทำเนียบผู้นำรุ่น
                const Text(
                  'ทำเนียบผู้นำรุ่น',
                  style: TextStyle(
                    fontSize: 26,
                    fontWeight: FontWeight.bold,
                    color: AppColors.primaryDark,
                  ),
                ),
                const SizedBox(height: 4),
                const Text(
                  'คณะกรรมการชมรมเพื่อนภูผา มหาวิทยาลัยราชภัฏเชียงใหม่',
                  textAlign: TextAlign.center,
                  style: TextStyle(
                    fontSize: 13.5,
                    color: Color(0xFF64748B),
                  ),
                ),
                const SizedBox(height: 20),

                // แท็บปุ่มเลือกชั้นปี 1 - 4
                Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [1, 2, 3, 4].map((year) {
                    final isSelected = _selectedYear == year;
                    return Padding(
                      padding: const EdgeInsets.symmetric(horizontal: 4),
                      child: InkWell(
                        onTap: () => setState(() => _selectedYear = year),
                        borderRadius: BorderRadius.circular(10),
                        child: AnimatedContainer(
                          duration: const Duration(milliseconds: 200),
                          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                          decoration: BoxDecoration(
                            color: isSelected ? AppColors.primary : Colors.white,
                            borderRadius: BorderRadius.circular(10),
                            border: Border.all(
                              color: isSelected ? AppColors.primary : const Color(0xFFCBD5E1),
                            ),
                            boxShadow: isSelected
                                ? [
                                    BoxShadow(
                                      color: AppColors.primary.withValues(alpha: 0.3),
                                      blurRadius: 8,
                                      offset: const Offset(0, 3),
                                    ),
                                  ]
                                : [],
                          ),
                          child: Text(
                            'ชั้นปี $year',
                            style: TextStyle(
                              fontSize: 13.5,
                              fontWeight: isSelected ? FontWeight.bold : FontWeight.w500,
                              color: isSelected ? Colors.white : const Color(0xFF475569),
                            ),
                          ),
                        ),
                      ),
                    );
                  }).toList(),
                ),
                const SizedBox(height: 24),

                // รายการการ์ดกรรมการ
                if (_isLoading)
                  const Padding(
                    padding: EdgeInsets.symmetric(vertical: 40),
                    child: CircularProgressIndicator(color: AppColors.primary),
                  )
                else if (currentList.isEmpty)
                  Container(
                    width: double.infinity,
                    padding: const EdgeInsets.all(32),
                    decoration: BoxDecoration(
                      color: Colors.white,
                      borderRadius: BorderRadius.circular(16),
                      border: Border.all(color: const Color(0xFFCBD5E1), style: BorderStyle.solid),
                    ),
                    child: Text(
                      'ยังไม่มีข้อมูลคณะกรรมการชั้นปี $_selectedYear',
                      textAlign: TextAlign.center,
                      style: const TextStyle(color: Color(0xFF94A3B8), fontSize: 14),
                    ),
                  )
                else
                  LayoutBuilder(
                    builder: (context, constraints) {
                      // หน้าจอเล็กแสดง 1 คอลัมน์ หรือ 2 คอลัมน์
                      final crossAxisCount = constraints.maxWidth > 500 ? 3 : 2;
                      return GridView.builder(
                        shrinkWrap: true,
                        physics: const NeverScrollableScrollPhysics(),
                        gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
                          crossAxisCount: crossAxisCount,
                          crossAxisSpacing: 12,
                          mainAxisSpacing: 12,
                          childAspectRatio: 0.76,
                        ),
                        itemCount: currentList.length,
                        itemBuilder: (context, index) {
                          final member = currentList[index];
                          return _buildCommitteeCard(member);
                        },
                      );
                    },
                  ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  // การ์ดกรรมการแต่ละคน
  Widget _buildCommitteeCard(Map<String, dynamic> member) {
    final name = member['name']?.toString() ?? '-';
    final position = member['position_thai']?.toString() ??
        member['committee_position']?.toString() ??
        'คณะกรรมการ';
    final faculty = member['faculty']?.toString() ?? '';
    final major = member['major']?.toString() ?? '';
    final imageUrl = member['image']?.toString() ?? '';

    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: const Color(0xFFE2E8F0)),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.04),
            blurRadius: 10,
            offset: const Offset(0, 3),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // รูปโปรไฟล์กลม มีเงาตามแบบหน้าเว็บ
          Center(
            child: Container(
              width: 72,
              height: 72,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                border: Border.all(color: const Color(0xFFE2E8F0), width: 1.5),
                boxShadow: [
                  BoxShadow(
                    color: Colors.black.withValues(alpha: 0.08),
                    blurRadius: 8,
                    offset: const Offset(0, 3),
                  ),
                ],
              ),
              child: ClipOval(
                child: imageUrl.isNotEmpty
                    ? Image.network(
                        imageUrl,
                        width: 72,
                        height: 72,
                        fit: BoxFit.cover,
                        errorBuilder: (_, __, ___) => _buildAvatarPlaceholder(),
                      )
                    : _buildAvatarPlaceholder(),
              ),
            ),
          ),
          const SizedBox(height: 12),

          // ชื่อ-นามสกุล
          Text(
            name,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: const TextStyle(
              fontSize: 13.5,
              fontWeight: FontWeight.bold,
              color: AppColors.primaryDark,
            ),
          ),
          const SizedBox(height: 2),

          // ตำแหน่ง
          Text(
            position,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: const TextStyle(
              fontSize: 12,
              color: Color(0xFF64748B),
              fontWeight: FontWeight.w500,
            ),
          ),
          const Spacer(),

          // คณะ
          if (faculty.isNotEmpty)
            Text(
              faculty,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(
                fontSize: 11,
                color: Color(0xFF94A3B8),
              ),
            ),

          // สาขาวิชา
          if (major.isNotEmpty)
            Text(
              major,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(
                fontSize: 11,
                color: Color(0xFF94A3B8),
              ),
            ),
        ],
      ),
    );
  }

  Widget _buildAvatarPlaceholder() {
    return Container(
      color: const Color(0xFFF1F5F9),
      child: const Center(
        child: Icon(
          Icons.person,
          size: 42,
          color: Color(0xFF475569),
        ),
      ),
    );
  }
}
