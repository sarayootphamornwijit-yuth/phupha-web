import 'package:flutter/material.dart';
import '../services/api_service.dart';
import '../widgets/management_form_widgets.dart';

//ผู้สมัครทุกคนเป็น member ก่อน ผู้ดูแลเปลี่ยนสิทธิ์จากหน้านี้เหมือนเว็บ
class AdminUsersScreen extends StatefulWidget {
  const AdminUsersScreen({super.key});
  @override
  State<AdminUsersScreen> createState() => _AdminUsersScreenState();
}

class _AdminUsersScreenState extends State<AdminUsersScreen> {
  final _search = TextEditingController();
  int? _currentUserId;
  List<Map<String, dynamic>> _users = [];
  bool _loading = true;
  String? _error;
  bool _saving = false;
  static const roles = {
    'member': 'สมาชิก',
    'committee': 'คณะกรรมการ',
    'advisor': 'อาจารย์ที่ปรึกษา',
    'admin': 'ผู้ดูแลระบบ',
  };
  static const positions = {
    '': 'ไม่มีตำแหน่ง',
    'president': 'ประธาน',
    'vice_president': 'รองประธาน',
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
    _load();
  }

  @override
  void dispose() {
    _search.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    final result = await ApiService.managementGet('get_admin_users');
    if (!mounted) {
      return;
    }
    setState(() {
      _loading = false;
      if (result.isSuccess) {
        _users = List<Map<String, dynamic>>.from(result.data['users']);
        _currentUserId = int.parse(result.data['current_user_id'].toString());
        _error = null;
      } else {
        _error = result.message;
      }
    });
  }

  //ซ่อนปุ่มระหว่างรอคำตอบ และอ่านสิทธิ์จากฐานข้อมูลกลับมาเมื่อบันทึกได้
  Future<bool> _save(String action, Map<String, String> values) async {
    if (_saving) {
      return false;
    }
    setState(() => _saving = true);
    final result = await ApiService.managementPost(action, values);
    if (!mounted) {
      return false;
    }
    setState(() => _saving = false);
    ScaffoldMessenger.of(
      context,
    ).showSnackBar(SnackBar(content: Text(result.message)));
    if (result.isSuccess) await _load();
    return result.isSuccess;
  }

  Future<void> _edit(Map<String, dynamic> user) async {
    String role = user['role'].toString();
    if (!roles.containsKey(role)) role = 'committee';
    String position = user['committee_position']?.toString() ?? '';
    String year = user['academic_year']?.toString() ?? '0';
    await showDialog<void>(
      context: context,
      builder: (dialogContext) => StatefulBuilder(
        builder: (dialogContext, setDialogState) {
          return AlertDialog(
            title: Text('สิทธิ์ของ ${user['name']}'),
            content: SingleChildScrollView(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  managementDropdown(
                    'สิทธิ์ผู้ใช้',
                    role,
                    roles,
                    (value) => setDialogState(() => role = value!),
                  ),
                  managementDropdown(
                    'ตำแหน่งคณะกรรมการ',
                    position,
                    positions,
                    (value) => setDialogState(() => position = value!),
                  ),
                  if (user['user_type'] == 'student')
                    managementDropdown('ชั้นปี', year, {
                      '0': 'ไม่ระบุ',
                      '1': 'ปี 1',
                      '2': 'ปี 2',
                      '3': 'ปี 3',
                      '4': 'ปี 4',
                    }, (value) => setDialogState(() => year = value!)),
                ],
              ),
            ),
            actions: [
              TextButton(
                onPressed: () => Navigator.pop(dialogContext),
                child: const Text('ยกเลิก'),
              ),
              ElevatedButton(
                style: managementButtonStyle(),
                onPressed: () async {
                  Navigator.pop(dialogContext);
                  await _save('update_member_role', {
                    'target_id': user['user_id'].toString(),
                    'role': role,
                    'committee_position': position,
                    'academic_year': year,
                  });
                },
                child: const Text('บันทึก'),
              ),
            ],
          );
        },
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final query = _search.text.trim().toLowerCase();
    final users = _users
        .where(
          (user) => '${user['name']} ${user['username']}'
              .toLowerCase()
              .contains(query),
        )
        .toList();
    return Scaffold(
      backgroundColor: const Color(0xFFF1F8E9),
      appBar: AppBar(title: const Text('จัดการสมาชิกและสิทธิ์')),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : ListView(
              padding: const EdgeInsets.all(16),
              children: [
                if (_error != null) Text(_error!),
                if (_error == null) ...[
                  TextField(
                    controller: _search,
                    onChanged: (value) => setState(() {}),
                    decoration: const InputDecoration(
                      labelText: 'ค้นหาชื่อ / รหัสนักศึกษา / ชื่อผู้ใช้',
                      border: OutlineInputBorder(),
                    ),
                  ),
                  const SizedBox(height: 16),
                  ...users.map(
                    (user) => managementCard(user['name'].toString(), [
                      Text(
                        '${user['username']} • ${roles[user['role']] ?? user['role']}',
                      ),
                      Text(positions[user['committee_position']] ?? ''),
                      Wrap(
                        spacing: 8,
                        children: [
                          OutlinedButton(
                            style: managementOutlineStyle(),
                            onPressed:
                                _saving ||
                                    user['user_id'].toString() ==
                                        _currentUserId.toString()
                                ? null
                                : () => _edit(user),
                            child: const Text('แก้ไขสิทธิ์'),
                          ),
                          TextButton(
                            onPressed:
                                _saving ||
                                    user['user_id'].toString() ==
                                        _currentUserId.toString()
                                ? null
                                : () async {
                                    if (await confirmManagementDelete(
                                      context,
                                      'ลบบัญชี ${user['name']} และข้อมูลที่เกี่ยวข้องหรือไม่?',
                                    )) {
                                      await _save('delete_member', {
                                        'target_id': user['user_id'].toString(),
                                      });
                                    }
                                  },
                            child: const Text('ลบสมาชิก'),
                          ),
                        ],
                      ),
                    ]),
                  ),
                ],
              ],
            ),
    );
  }
}
