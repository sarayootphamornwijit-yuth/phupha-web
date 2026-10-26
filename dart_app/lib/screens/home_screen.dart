import 'package:flutter/material.dart';
import '../constants/app_colors.dart';
import '../constants/app_config.dart';
import '../models/user_model.dart';
import '../models/activity_model.dart';
import '../models/news_model.dart';
import '../services/api_service.dart';
import '../services/session_manager.dart';
import '../widgets/web_app_bar.dart';
import '../widgets/web_drawer.dart';
import '../widgets/dashed_container.dart';
import 'activity_detail_screen.dart';

// home_screen.dart
// หน้าหลักของแอพพลิเคชันชมรมเพื่อนภูผา
// ออกแบบตรงตามหน้า index.php และธีมของระบบเว็บไซต์เดิม 100%
// ประกอบด้วย: แถบเมนูด้านบนสีขาว, ป้ายต้อนรับสีเขียวไล่เฉด, กล่องสถานะว่างเส้นประ (Empty state)

class HomeScreen extends StatefulWidget {
  const HomeScreen({super.key});

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  // ตัวแปรเก็บข้อมูลผู้ใช้งานที่ล็อกอินอยู่
  UserModel? _currentUser;

  // ตัวแปรเก็บรายการกิจกรรมทั้งหมด
  List<ActivityModel> _activities = [];

  // ตัวแปรเก็บรายการข่าวประชาสัมพันธ์
  List<NewsModel> _newsList = [];

  // สถานะกำลังโหลดข้อมูล
  bool _isLoading = true;

  @override
  void initState() {
    super.initState();
    // เริ่มต้นโหลดข้อมูลเมื่อเปิดหน้าจอ
    _loadData();
  }

  // ฟังก์ชันดึงข้อมูลจาก Local Session และ Backend API
  Future<void> _loadData() async {
    setState(() {
      _isLoading = true;
    });

    // 1. ตรวจสอบข้อมูลผู้ใช้งานในเครื่อง
    final user = await SessionManager.getUser();

    // 2. เรียก API รายการกิจกรรม
    final actResponse = await ApiService.getActivities();

    // 3. เรียก API ข่าวประชาสัมพันธ์
    final newsResponse = await ApiService.getNews();

    if (!mounted) return;

    setState(() {
      _currentUser = user;
      _activities = actResponse.data ?? [];
      _newsList = newsResponse.data ?? [];
      _isLoading = false;
    });
  }

  // แยกกิจกรรมเป็น "กิจกรรมใหม่" (วันที่ยังไม่ผ่านไป)
  List<ActivityModel> get _upcomingActivities {
    final now = DateTime.now();
    return _activities.where((act) {
      final start = act.startDateParsed;
      if (start == null) return true;
      final end = act.endDateParsed;
      return start.isAfter(now) || (end != null && end.isAfter(now));
    }).toList();
  }

  // แยกกิจกรรมเป็น "กิจกรรมที่ผ่านมา" (สิ้นสุดแล้ว)
  List<ActivityModel> get _pastActivities {
    final now = DateTime.now();
    return _activities.where((act) {
      final start = act.startDateParsed;
      if (start == null) return false;
      final checkDate = act.endDateParsed ?? start;
      return checkDate.isBefore(now);
    }).toList();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.bgMain, // สีพื้นหลังเขียวอ่อน #F1F8E9 ตาม variables.css

      // แถบด้านบนสีขาว พร้อมไอคอนแฮมเบอร์เกอร์และปุ่มโปรไฟล์/ล็อกอิน
      appBar: WebAppBar(
        currentUser: _currentUser,
        onAuthStateChanged: _loadData,
      ),

      // เมนูด้านข้าง (Sidebar Drawer) ตามดีไซน์บนเว็บ
      drawer: WebDrawer(
        currentRoute: 'home',
        currentUser: _currentUser,
      ),

      body: _isLoading
          ? const Center(
              child: CircularProgressIndicator(color: AppColors.primary),
            )
          : RefreshIndicator(
              onRefresh: _loadData,
              color: AppColors.primary,
              child: SingleChildScrollView(
                physics: const AlwaysScrollableScrollPhysics(),
                padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    // 1. ป้ายแบนเนอร์ต้อนรับสีเขียวไล่เฉด (Banner Welcome)
                    _buildWelcomeBanner(),

                    const SizedBox(height: 24),

                    // 2. หมวด: กิจกรรมใหม่
                    _buildSectionHeader('กิจกรรมใหม่'),
                    const SizedBox(height: 12),
                    _upcomingActivities.isEmpty
                        ? const DashedContainer(message: 'ยังไม่มีกิจกรรมใหม่ขณะนี้')
                        : _buildActivityList(_upcomingActivities),

                    const SizedBox(height: 28),

                    // 3. หมวด: ข่าวประชาสัมพันธ์
                    _buildSectionHeader('ข่าวประชาสัมพันธ์'),
                    const SizedBox(height: 12),
                    _newsList.isEmpty
                        ? const DashedContainer(message: 'ยังไม่มีข่าวประชาสัมพันธ์ในขณะนี้')
                        : _buildNewsList(),

                    const SizedBox(height: 28),

                    // 4. หมวด: กิจกรรมที่ผ่านมา
                    _buildSectionHeader('กิจกรรมที่ผ่านมา'),
                    const SizedBox(height: 12),
                    _pastActivities.isEmpty
                        ? const DashedContainer(message: 'ยังไม่มีกิจกรรมที่ผ่านมาขณะนี้')
                        : _buildActivityList(_pastActivities),

                    const SizedBox(height: 24),
                  ],
                ),
              ),
            ),
    );
  }

  // สร้างหัวข้อแต่ละส่วน เช่น กิจกรรมใหม่, ข่าวประชาสัมพันธ์
  Widget _buildSectionHeader(String title) {
    return Text(
      title,
      style: const TextStyle(
        fontSize: 18,
        fontWeight: FontWeight.bold,
        color: AppColors.textMain, // สีดำตามเว็บ
      ),
    );
  }

  // ป้ายแบนเนอร์ข้อความต้อนรับด้านบนสุด (ถอดแบบมาจาก .banner-welcome ใน layout.css)
  Widget _buildWelcomeBanner() {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 28),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          colors: [
            Color(0xFF1B5E20), // เขียวเข้ม Forest Green
            Color(0xFF2E7D32), // เขียวมรกต Emerald Green
            Color(0xFF43A047), // เขียวสว่าง
            Color(0xFF66BB6A),
          ],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(16), // รัศมีความโค้งมน 16px ตาม border-radius-lg
        boxShadow: const [
          BoxShadow(
            color: Color(0x1A000000),
            blurRadius: 10,
            offset: Offset(0, 4),
          ),
        ],
      ),
      child: const Column(
        children: [
          Text(
            'ยินดีต้อนรับสู่ ชมรมเพื่อนภูผา',
            textAlign: TextAlign.center,
            style: TextStyle(
              fontSize: 22,
              fontWeight: FontWeight.bold,
              color: Colors.white,
            ),
          ),
          SizedBox(height: 12),
          Text(
            'ระบบสารสนเทศเพื่อการบริหารจัดการชมรมเพื่อนภูผา มหาวิทยาลัยราชภัฏเชียงใหม่ สนับสนุนการบริหารงาน การจัดกิจกรรม การพัฒนาสมาชิก และการสืบสานอุดมการณ์จิตอาสาอย่างมีประสิทธิภาพ',
            textAlign: TextAlign.center,
            style: TextStyle(
              fontSize: 13,
              color: Colors.white,
              height: 1.6, // ระยะห่างระหว่างบรรทัดให้อ่านง่าย
            ),
          ),
        ],
      ),
    );
  }

  // แสดงการ์ดรายการกิจกรรม (ตามรูปแบบการ์ดบนหน้าเว็บ index.php)
  Widget _buildActivityList(List<ActivityModel> activities) {
    return ListView.builder(
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      itemCount: activities.length,
      itemBuilder: (context, index) {
        final act = activities[index];
        final imageUrl = AppConfig.getFullImageUrl(act.coverImage);

        return Card(
          margin: const EdgeInsets.only(bottom: 16),
          color: Colors.white,
          elevation: 0,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(12),
            side: const BorderSide(color: Color(0xFFE2E8F0)), // เส้นขอบเทาบางๆ ตาม --border-color
          ),
          clipBehavior: Clip.antiAlias,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // 1. ภาพหน้าปกกิจกรรม
              Container(
                height: 160,
                width: double.infinity,
                color: const Color(0xFFF1F5F9),
                child: imageUrl.isNotEmpty
                    ? Image.network(
                        imageUrl,
                        fit: BoxFit.cover,
                        errorBuilder: (context, error, stackTrace) => const Center(
                          child: Icon(Icons.image, size: 48, color: Colors.black26),
                        ),
                      )
                    : const Center(
                        child: Icon(Icons.image, size: 48, color: Colors.black26),
                      ),
              ),

              // 2. ข้อมูลกิจกรรม
              Padding(
                padding: const EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    // ป้ายประเภทกิจกรรม (กิจกรรม 1 วัน หรือ ค่ายหลายวัน)
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                      decoration: BoxDecoration(
                        color: act.category == 'sport'
                            ? const Color(0xFFE0E7FF)
                            : const Color(0xFFF3F4F6),
                        borderRadius: BorderRadius.circular(4),
                        border: Border.all(
                          color: act.category == 'sport'
                              ? const Color(0xFFC7D2FE)
                              : const Color(0xFFE5E7EB),
                        ),
                      ),
                      child: Text(
                        act.categoryText,
                        style: TextStyle(
                          fontSize: 11,
                          fontWeight: FontWeight.w500,
                          color: act.category == 'sport'
                              ? const Color(0xFF4338CA)
                              : const Color(0xFF374151),
                        ),
                      ),
                    ),

                    const SizedBox(height: 8),

                    // ชื่อกิจกรรม
                    Text(
                      act.title,
                      style: const TextStyle(
                        fontSize: 16,
                        fontWeight: FontWeight.bold,
                        color: Colors.black,
                      ),
                    ),

                    const SizedBox(height: 6),

                    // คำอธิบายย่อ
                    if (act.description != null && act.description!.isNotEmpty)
                      Text(
                        act.description!,
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(
                          fontSize: 13,
                          color: Color(0xFF616161),
                          height: 1.4,
                        ),
                      ),

                    const SizedBox(height: 10),

                    // วันที่จัดกิจกรรม
                    if (act.startDateFormatted.isNotEmpty)
                      Padding(
                        padding: const EdgeInsets.only(bottom: 4),
                        child: Text(
                          'วันที่: ${act.startDateFormatted}',
                          style: const TextStyle(fontSize: 13, color: Color(0xFF616161)),
                        ),
                      ),

                    // สถานที่จัดกิจกรรม
                    if (act.locationName != null && act.locationName!.isNotEmpty)
                      Padding(
                        padding: const EdgeInsets.only(bottom: 12),
                        child: Text(
                          'สถานที่: ${act.locationName!}',
                          style: const TextStyle(fontSize: 13, color: Color(0xFF616161)),
                        ),
                      ),

                    const Divider(color: Color(0xFFEEEEEE), height: 1),
                    const SizedBox(height: 12),

                    // ปุ่มกด: ดูรายละเอียดกิจกรรม
                    SizedBox(
                      width: double.infinity,
                      child: OutlinedButton(
                        onPressed: () {
                          Navigator.push(
                            context,
                            MaterialPageRoute(
                              builder: (context) => ActivityDetailScreen(activityId: act.activityId),
                            ),
                          ).then((_) => _loadData());
                        },
                        style: OutlinedButton.styleFrom(
                          foregroundColor: AppColors.primary,
                          side: const BorderSide(color: AppColors.primary),
                          padding: const EdgeInsets.symmetric(vertical: 10),
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(8),
                          ),
                        ),
                        child: const Text(
                          'รายละเอียดกิจกรรม',
                          style: TextStyle(fontWeight: FontWeight.bold),
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
        );
      },
    );
  }

  // แสดงการ์ดรายการข่าวประชาสัมพันธ์ (ตามแท็ก card ข่าวใน index.php)
  Widget _buildNewsList() {
    return ListView.builder(
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      itemCount: _newsList.length,
      itemBuilder: (context, index) {
        final news = _newsList[index];

        return Card(
          margin: const EdgeInsets.only(bottom: 14),
          color: Colors.white,
          elevation: 0,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(12),
            side: const BorderSide(color: Color(0xFFE2E8F0)),
          ),
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                // หัวข้อข่าว
                Text(
                  news.title,
                  style: const TextStyle(
                    fontSize: 15,
                    fontWeight: FontWeight.bold,
                    color: Colors.black,
                  ),
                ),

                const SizedBox(height: 6),

                // เนื้อหาข่าว
                Text(
                  news.content,
                  maxLines: 3,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    fontSize: 13,
                    color: Color(0xFF616161),
                    height: 1.4,
                  ),
                ),

                const SizedBox(height: 12),
                const Divider(color: Color(0xFFEEEEEE), height: 1),
                const SizedBox(height: 8),

                // ผู้ประกาศและวันที่
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    const Text(
                      'โดย: ชมรมเพื่อนภูผา',
                      style: TextStyle(fontSize: 11, color: Color(0xFF888888)),
                    ),
                    Text(
                      news.createdAtFormatted,
                      style: const TextStyle(fontSize: 11, color: Color(0xFF888888)),
                    ),
                  ],
                ),
              ],
            ),
          ),
        );
      },
    );
  }
}
