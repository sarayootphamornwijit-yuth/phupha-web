import 'package:flutter/material.dart';
import '../services/api_service.dart';
import '../widgets/management_form_widgets.dart';

class MemberDetailScreen extends StatefulWidget {
  final int userId;
  const MemberDetailScreen({super.key, required this.userId});
  @override
  State<MemberDetailScreen> createState() => _MemberDetailScreenState();
}

class _MemberDetailScreenState extends State<MemberDetailScreen> {
  Map<String, dynamic>? _member;
  String? _error;
  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    final result = await ApiService.managementGet(
      'get_member_detail',
      values: {'target_id': widget.userId.toString()},
    );
    if (!mounted) {
      return;
    }
    setState(() {
      if (result.isSuccess && result.data != null) {
        _member = Map<String, dynamic>.from(result.data);
      } else {
        _error = result.message;
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    const labels = {
      'name': 'ชื่อ-นามสกุล',
      'student_id': 'รหัสนักศึกษา',
      'faculty': 'คณะ',
      'major': 'สาขาวิชา',
      'phone': 'เบอร์โทรศัพท์',
      'occupation': 'อาชีพ',
      'academic_year': 'ชั้นปี',
      'points': 'แต้มจิตอาสา',
    };
    return Scaffold(
      backgroundColor: const Color(0xFFF1F8E9),
      appBar: AppBar(title: const Text('ข้อมูลสมาชิก')),
      body: _error != null
          ? Center(child: Text(_error!))
          : _member == null
          ? const Center(child: CircularProgressIndicator())
          : ListView(
              padding: const EdgeInsets.all(16),
              children: [
                managementCard(_member!['name'].toString(), [
                  for (final entry in labels.entries)
                    Padding(
                      padding: const EdgeInsets.only(bottom: 12),
                      child: Text(
                        '${entry.value}: ${_member![entry.key] ?? '-'}',
                      ),
                    ),
                ]),
              ],
            ),
    );
  }
}
