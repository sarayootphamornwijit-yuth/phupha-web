import 'package:flutter/material.dart';
import '../constants/app_colors.dart';
import '../models/activity_model.dart';
import '../models/user_model.dart';
import '../services/api_service.dart';
import '../services/session_manager.dart';
import '../widgets/activity_card.dart';
import '../widgets/web_app_bar.dart';
import '../widgets/web_drawer.dart';
import '../widgets/dashed_container.dart';
import 'activity_detail_screen.dart';

// activities_screen.dart
// หน้ารายการกิจกรรมทั้งหมด ค้นหา และกรองตามหมวดหมู่กิจกรรม
// สอดคล้องกับ activities_by_category.php ในระบบเว็บ

class ActivitiesScreen extends StatefulWidget {
  // หมวดหมู่เริ่มต้น (เช่น 'all', 'volunteer', 'sport', 'byaniyan')
  final String initialCategory;

  const ActivitiesScreen({
    super.key,
    this.initialCategory = 'all',
  });

  @override
  State<ActivitiesScreen> createState() => _ActivitiesScreenState();
}

class _ActivitiesScreenState extends State<ActivitiesScreen> {
  final _searchController = TextEditingController();
  List<ActivityModel> _allActivities = [];
  List<ActivityModel> _filteredActivities = [];
  UserModel? _currentUser;
  bool _isLoading = true;
  late String _selectedCategory;

  // รายการหมวดหมู่สำหรับกรองกิจกรรม
  final List<Map<String, String>> _categories = [
    {'key': 'all', 'label': 'ทั้งหมด'},
    {'key': 'volunteer', 'label': 'จิตอาสา'},
    {'key': 'sport', 'label': 'กีฬา'},
    {'key': 'byaniyan', 'label': 'บายเนียร์'},
    {'key': 'other', 'label': 'ทั่วไป'},
  ];

  @override
  void initState() {
    super.initState();
    _selectedCategory = widget.initialCategory;
    _loadInitialData();
  }

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  // โหลดข้อมูลผู้ใช้และรายการกิจกรรม
  Future<void> _loadInitialData() async {
    final user = await SessionManager.getUser();
    if (mounted) {
      setState(() {
        _currentUser = user;
      });
    }
    await _fetchActivities();
  }

  // เรียกดึงข้อมูลกิจกรรมจาก API
  Future<void> _fetchActivities() async {
    setState(() {
      _isLoading = true;
    });

    final response = await ApiService.getActivities(search: _searchController.text.trim());

    if (!mounted) return;

    setState(() {
      _allActivities = response.data ?? [];
      _applyCategoryFilter();
      _isLoading = false;
    });
  }

  // กรองกิจกรรมตามหมวดหมู่ที่เลือก
  void _applyCategoryFilter() {
    if (_selectedCategory == 'all') {
      _filteredActivities = _allActivities;
    } else {
      _filteredActivities = _allActivities
          .where((act) => act.category == _selectedCategory)
          .toList();
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.bgMain, // #F1F8E9

      // แถบด้านบนตามเว็บ
      appBar: WebAppBar(
        currentUser: _currentUser,
        onAuthStateChanged: _loadInitialData,
      ),

      // เมนูด้านข้างตามเว็บ
      drawer: WebDrawer(
        currentRoute: _selectedCategory,
        currentUser: _currentUser,
      ),

      body: Column(
        children: [
          // ช่องค้นหากิจกรรม
          Container(
            color: Colors.white,
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
            child: TextField(
              controller: _searchController,
              decoration: InputDecoration(
                hintText: 'ค้นหากิจกรรม, สถานที่...',
                hintStyle: const TextStyle(color: Color(0xFF9E9E9E), fontSize: 14),
                prefixIcon: const Icon(Icons.search, color: AppColors.primary),
                suffixIcon: _searchController.text.isNotEmpty
                    ? IconButton(
                        icon: const Icon(Icons.clear, color: Color(0xFF757575)),
                        onPressed: () {
                          _searchController.clear();
                          _fetchActivities();
                        },
                      )
                    : null,
                contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
                border: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(10),
                  borderSide: const BorderSide(color: Color(0xFFE2E8F0)),
                ),
                enabledBorder: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(10),
                  borderSide: const BorderSide(color: Color(0xFFE2E8F0)),
                ),
                focusedBorder: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(10),
                  borderSide: const BorderSide(color: AppColors.primary, width: 1.5),
                ),
                filled: true,
                fillColor: AppColors.bgMain,
              ),
              onSubmitted: (_) => _fetchActivities(),
            ),
          ),

          // แถบเลือกหมวดหมู่ (Filter Chips)
          Container(
            color: Colors.white,
            height: 48,
            child: ListView.builder(
              scrollDirection: Axis.horizontal,
              padding: const EdgeInsets.symmetric(horizontal: 16),
              itemCount: _categories.length,
              itemBuilder: (context, index) {
                final cat = _categories[index];
                final isSelected = _selectedCategory == cat['key'];

                return Padding(
                  padding: const EdgeInsets.only(right: 8),
                  child: ChoiceChip(
                    label: Text(cat['label']!),
                    selected: isSelected,
                    selectedColor: AppColors.primary,
                    labelStyle: TextStyle(
                      color: isSelected ? Colors.white : AppColors.textMain,
                      fontSize: 12,
                      fontWeight: isSelected ? FontWeight.bold : FontWeight.normal,
                    ),
                    backgroundColor: AppColors.bgMain,
                    side: BorderSide(
                      color: isSelected ? AppColors.primary : const Color(0xFFE2E8F0),
                    ),
                    onSelected: (selected) {
                      if (selected) {
                        setState(() {
                          _selectedCategory = cat['key']!;
                          _applyCategoryFilter();
                        });
                      }
                    },
                  ),
                );
              },
            ),
          ),
          const Divider(height: 1, thickness: 1, color: Color(0xFFE2E8F0)),

          // รายการผลลัพธ์กิจกรรม
          Expanded(
            child: _isLoading
                ? const Center(child: CircularProgressIndicator(color: AppColors.primary))
                : RefreshIndicator(
                    onRefresh: _fetchActivities,
                    color: AppColors.primary,
                    child: _filteredActivities.isEmpty
                        ? ListView(
                            padding: const EdgeInsets.all(16),
                            children: const [
                              SizedBox(height: 30),
                              DashedContainer(message: 'ยังไม่มีกิจกรรมในหมวดหมู่นี้ขณะนี้'),
                            ],
                          )
                        : ListView.builder(
                            padding: const EdgeInsets.all(16),
                            itemCount: _filteredActivities.length,
                            itemBuilder: (context, index) {
                              final act = _filteredActivities[index];
                              return ActivityCard(
                                activity: act,
                                onTap: () {
                                  Navigator.push(
                                    context,
                                    MaterialPageRoute(
                                      builder: (context) => ActivityDetailScreen(activityId: act.activityId),
                                    ),
                                  ).then((_) => _fetchActivities());
                                },
                              );
                            },
                          ),
                  ),
          ),
        ],
      ),
    );
  }
}
