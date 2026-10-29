import 'package:flutter/material.dart';
import '../constants/app_colors.dart';
import 'home_screen.dart';
import 'activities_screen.dart';
import 'joined_activities_screen.dart';
import 'profile_screen.dart';

// main_navigation.dart
// เมนูนำทางหลักด้านล่างของแอพพลิเคชัน (Bottom Navigation Bar)
class MainNavigation extends StatefulWidget {
  final int initialIndex;
  const MainNavigation({super.key, this.initialIndex = 0});

  @override
  State<MainNavigation> createState() => _MainNavigationState();
}

class _MainNavigationState extends State<MainNavigation> {
  late int _currentIndex;

  // หน้าย่อยทั้ง 4 แท็บ
  final List<Widget> _screens = [
    HomeScreen(),
    ActivitiesScreen(),
    JoinedActivitiesScreen(),
    ProfileScreen(),
  ];

  @override
  void initState() {
    super.initState();
    _currentIndex = widget.initialIndex;
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: IndexedStack(
        index: _currentIndex,
        children: _screens,
      ),
      bottomNavigationBar: Container(
        decoration: BoxDecoration(
          boxShadow: [
            BoxShadow(
              color: Colors.black.withValues(alpha: 0.08),
              blurRadius: 10,
              offset: const Offset(0, -2),
            ),
          ],
        ),
        child: BottomNavigationBar(
          currentIndex: _currentIndex,
          onTap: (index) {
            setState(() {
              //เปิดแท็บด้วยข้อมูลใหม่หลังสมัครกิจกรรม เช็คอิน หรือได้รับคะแนน
              if (index != _currentIndex) {
                switch (index) {
                  case 0:
                    _screens[index] = HomeScreen(key: UniqueKey());
                    break;
                  case 1:
                    _screens[index] = ActivitiesScreen(key: UniqueKey());
                    break;
                  case 2:
                    _screens[index] = JoinedActivitiesScreen(key: UniqueKey());
                    break;
                  case 3:
                    _screens[index] = ProfileScreen(key: UniqueKey());
                    break;
                }
              }
              _currentIndex = index;
            });
          },
          type: BottomNavigationBarType.fixed,
          backgroundColor: Colors.white,
          selectedItemColor: AppColors.primary,
          unselectedItemColor: AppColors.textMuted,
          selectedFontSize: 12,
          unselectedFontSize: 12,
          selectedLabelStyle: const TextStyle(fontWeight: FontWeight.bold),
          items: const [
            BottomNavigationBarItem(
              icon: Icon(Icons.home_outlined),
              activeIcon: Icon(Icons.home),
              label: 'หน้าหลัก',
            ),
            BottomNavigationBarItem(
              icon: Icon(Icons.explore_outlined),
              activeIcon: Icon(Icons.explore),
              label: 'กิจกรรม',
            ),
            BottomNavigationBarItem(
              icon: Icon(Icons.event_note_outlined),
              activeIcon: Icon(Icons.event_note),
              label: 'กิจกรรมของฉัน',
            ),
            BottomNavigationBarItem(
              icon: Icon(Icons.person_outline),
              activeIcon: Icon(Icons.person),
              label: 'โปรไฟล์',
            ),
          ],
        ),
      ),
    );
  }
}
