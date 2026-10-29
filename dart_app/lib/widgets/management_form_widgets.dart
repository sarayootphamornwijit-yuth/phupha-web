import 'package:flutter/material.dart';
import '../constants/app_colors.dart';
import '../services/api_service.dart';

//ใช้สีและกรอบแบบฟอร์มเดียวกับเว็บ เพื่อให้หน้าใหม่ยังดูเป็นระบบเดียวกัน
//ปุ่มบันทึกใช้สีเขียวและมุมโค้งแบบเว็บ ไม่ใช้สีเริ่มต้นของ Material
ButtonStyle managementButtonStyle() {
  return ElevatedButton.styleFrom(
    backgroundColor: AppColors.primary,
    foregroundColor: Colors.white,
    minimumSize: const Size(64, 44),
    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
  );
}

ButtonStyle managementOutlineStyle() {
  return OutlinedButton.styleFrom(
    foregroundColor: AppColors.primary,
    side: const BorderSide(color: AppColors.primary),
    minimumSize: const Size(64, 44),
    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
  );
}

Widget managementCard(String title, List<Widget> children) {
  return Container(
    margin: const EdgeInsets.only(bottom: 16),
    padding: const EdgeInsets.all(16),
    decoration: BoxDecoration(
      color: Colors.white,
      borderRadius: BorderRadius.circular(12),
      border: Border.all(color: AppColors.cardBorder),
    ),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Text(
          title,
          style: const TextStyle(
            fontSize: 17,
            fontWeight: FontWeight.bold,
            color: AppColors.primaryDark,
          ),
        ),
        const Divider(height: 24),
        ...children,
      ],
    ),
  );
}

Widget managementInput(
  String label,
  TextEditingController controller, {
  bool required = false,
  bool number = false,
  int lines = 1,
}) {
  return Padding(
    padding: const EdgeInsets.only(bottom: 14),
    child: TextFormField(
      controller: controller,
      maxLines: lines,
      keyboardType: number
          ? const TextInputType.numberWithOptions(decimal: true)
          : TextInputType.text,
      decoration: InputDecoration(
        labelText: label,
        border: const OutlineInputBorder(),
      ),
      validator: (value) {
        if (required && (value == null || value.trim().isEmpty)) {
          return 'กรุณากรอก$label';
        }
        return null;
      },
    ),
  );
}

Widget managementDropdown(
  String label,
  String value,
  Map<String, String> options,
  ValueChanged<String?> onChanged,
) {
  return Padding(
    padding: const EdgeInsets.only(bottom: 14),
    child: DropdownButtonFormField<String>(
      value: value,
      isExpanded: true,
      decoration: InputDecoration(
        labelText: label,
        border: const OutlineInputBorder(),
      ),
      items: options.entries
          .map(
            (item) =>
                DropdownMenuItem(value: item.key, child: Text(item.value)),
          )
          .toList(),
      onChanged: onChanged,
    ),
  );
}

Future<bool> confirmManagementDelete(
  BuildContext context,
  String message,
) async {
  final answer = await showDialog<bool>(
    context: context,
    builder: (dialogContext) {
      return AlertDialog(
        title: const Text('ยืนยันการลบ'),
        content: Text(message),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext, false),
            child: const Text('ยกเลิก'),
          ),
          TextButton(
            onPressed: () => Navigator.pop(dialogContext, true),
            child: const Text('ลบ', style: TextStyle(color: Colors.red)),
          ),
        ],
      );
    },
  );
  return answer == true;
}

//โหลดกิจกรรมตามสิทธิ์ของหน้านั้น เหรัญญิกจึงเลือกกิจกรรมทำบัญชีได้โดยไม่ต้องมีสิทธิ์แก้กิจกรรม
class ManagementActivitySelector extends StatefulWidget {
  final String menu;
  final ValueChanged<int> onChanged;
  const ManagementActivitySelector({
    super.key,
    required this.menu,
    required this.onChanged,
  });

  @override
  State<ManagementActivitySelector> createState() =>
      _ManagementActivitySelectorState();
}

class _ManagementActivitySelectorState
    extends State<ManagementActivitySelector> {
  List<Map<String, dynamic>> _activities = [];
  String? _error;
  int? _selected;
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    final result = await ApiService.managementGet(
      'get_management_options',
      values: {'menu': widget.menu},
    );
    if (!mounted) {
      return;
    }
    setState(() {
      _loading = false;
      if (result.isSuccess) {
        _activities = List<Map<String, dynamic>>.from(result.data as List);
        _error = null;
      } else {
        _error = result.message;
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    if (_loading) {
      return const LinearProgressIndicator();
    }
    if (_error != null) {
      return Column(
        children: [
          Text(_error!),
          TextButton(onPressed: _load, child: const Text('ลองใหม่')),
        ],
      );
    }
    return DropdownButtonFormField<int>(
      value: _selected,
      isExpanded: true,
      decoration: const InputDecoration(
        labelText: 'เลือกโครงการกิจกรรม',
        border: OutlineInputBorder(),
      ),
      items: _activities.map((activity) {
        final id = int.parse(activity['activity_id'].toString());
        return DropdownMenuItem(
          value: id,
          child: Text(
            activity['title'].toString(),
            overflow: TextOverflow.ellipsis,
          ),
        );
      }).toList(),
      onChanged: (value) {
        if (value == null) {
          return;
        }
        setState(() => _selected = value);
        widget.onChanged(value);
      },
    );
  }
}
