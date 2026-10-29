import 'package:flutter/material.dart';
import '../services/api_service.dart';
import '../services/mobile_document_service.dart';
import '../widgets/management_form_widgets.dart';

//แยกรายรับ รายจ่าย และเงินคงเหลือของกิจกรรมที่เลือกเหมือนหน้าบัญชีเว็บ
class ErpBudgetsScreen extends StatefulWidget {
  const ErpBudgetsScreen({super.key});
  @override
  State<ErpBudgetsScreen> createState() => _ErpBudgetsScreenState();
}

class _ErpBudgetsScreenState extends State<ErpBudgetsScreen> {
  final _form = GlobalKey<FormState>();
  final _category = TextEditingController();
  final _amount = TextEditingController();
  final _description = TextEditingController();
  String _type = 'income';
  int? _activityId;
  Map<String, dynamic>? _data;
  MobileSelectedFile? _receipt;
  bool _loading = false;
  bool _saving = false;
  String? _error;
  int _loadNumber = 0;

  @override
  void dispose() {
    _category.dispose();
    _amount.dispose();
    _description.dispose();
    super.dispose();
  }

  //เลือกกิจกรรมแล้วอ่านรายการกับยอดรวมใหม่ ใช้เลขรอบกันคำตอบเก่ามาทับกิจกรรมที่เพิ่งเลือก
  Future<void> _load(int id) async {
    final number = ++_loadNumber;
    setState(() {
      _activityId = id;
      _loading = true;
      _error = null;
      _receipt = null;
    });
    final result = await ApiService.managementGet(
      'get_activity_budget',
      values: {'activity_id': id.toString()},
    );
    if (!mounted || number != _loadNumber) {
      return;
    }
    setState(() {
      _loading = false;
      if (result.isSuccess) {
        _data = Map<String, dynamic>.from(result.data);
      } else {
        _error = result.message;
      }
    });
  }

  Future<void> _pickReceipt() async {
    try {
      final file = await MobileDocumentService.pickFile(allowPdf: true);
      if (mounted && file != null) {
        setState(() => _receipt = file);
      }
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              'เลือกใบเสร็จไม่ได้: ${MobileDocumentService.errorMessage(error)}',
            ),
          ),
        );
      }
    }
  }

  //ตรวจจำนวนเงินก่อนส่ง ส่วนเซิร์ฟเวอร์ตรวจยอดคงเหลือซ้ำก่อนยอมให้เบิก
  Future<void> _save() async {
    if (!_form.currentState!.validate() || _saving || _activityId == null) {
      return;
    }
    if (!RegExp(r'^\d{1,8}(\.\d{1,2})?$').hasMatch(_amount.text) ||
        (double.tryParse(_amount.text) ?? 0) <= 0) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text(
            'กรุณากรอกจำนวนเงินมากกว่า 0 และทศนิยมไม่เกิน 2 ตำแหน่ง',
          ),
        ),
      );
      return;
    }
    final id = _activityId!;
    setState(() => _saving = true);
    final result = await ApiService.managementPost(
      'add_budget',
      {
        'activity_id': id.toString(),
        'type': _type,
        'category': _category.text.trim(),
        'amount': _amount.text,
        'description': _description.text.trim(),
      },
      fileField: _receipt == null ? null : 'receipt_image',
      fileName: _receipt?.name,
      fileBytes: _receipt?.bytes,
    );
    if (!mounted) {
      return;
    }
    setState(() => _saving = false);
    ScaffoldMessenger.of(
      context,
    ).showSnackBar(SnackBar(content: Text(result.message)));
    if (result.isSuccess) {
      _category.clear();
      _amount.clear();
      _description.clear();
      if (_activityId == id) _load(id);
    }
  }

  //ถามยืนยันก่อนลบ แล้วโหลดบัญชีใหม่เพื่อคำนวณยอดคงเหลือจากฐานข้อมูล
  Future<void> _delete(Map<String, dynamic> row) async {
    if (!await confirmManagementDelete(
      context,
      'ลบรายการ ${row['category']} และคำนวณยอดใหม่หรือไม่?',
    )) {
      return;
    }
    final result = await ApiService.managementPost('delete_budget', {
      'activity_id': _activityId.toString(),
      'transaction_id': row['transaction_id'].toString(),
    });
    if (!mounted) {
      return;
    }
    ScaffoldMessenger.of(
      context,
    ).showSnackBar(SnackBar(content: Text(result.message)));
    if (result.isSuccess) _load(_activityId!);
  }

  Future<void> _saveReceipt(String path) async {
    try {
      await MobileDocumentService.saveReceipt(path);
    } catch (error) {
      if (!mounted) {
        return;
      }
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            'เปิดใบเสร็จไม่ได้: ${MobileDocumentService.errorMessage(error)}',
          ),
        ),
      );
    }
  }

  String _money(dynamic value) =>
      (num.tryParse(value.toString()) ?? 0).toStringAsFixed(2);

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF1F8E9),
      appBar: AppBar(title: const Text('บัญชีรายรับ-รายจ่าย')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          ManagementActivitySelector(menu: 'budgets', onChanged: _load),
          const SizedBox(height: 16),
          if (_loading) const LinearProgressIndicator(),
          if (_error != null) Text(_error!),
          if (_data != null && !_loading && _error == null) ...[
            managementCard('สรุปงบประมาณ', [
              Text('รายรับ ${_money(_data!['income'])} บาท'),
              Text('รายจ่าย ${_money(_data!['expense'])} บาท'),
              Text(
                'คงเหลือ ${_money(_data!['balance'])} บาท',
                style: const TextStyle(fontWeight: FontWeight.bold),
              ),
            ]),
            Form(
              key: _form,
              child: managementCard('บันทึกรายรับ-รายจ่าย', [
                managementDropdown('ประเภทรายการ', _type, {
                  'income': 'รายรับ (+)',
                  'expense': 'รายจ่าย (-)',
                }, (value) => setState(() => _type = value!)),
                managementInput(
                  'หมวดหมู่ / ชื่อรายการ',
                  _category,
                  required: true,
                ),
                managementInput(
                  'จำนวนเงิน (บาท)',
                  _amount,
                  required: true,
                  number: true,
                ),
                OutlinedButton.icon(
                  style: managementOutlineStyle(),
                  onPressed: _saving ? null : _pickReceipt,
                  icon: const Icon(Icons.attach_file),
                  label: Text(_receipt?.name ?? 'แนบใบเสร็จ (รูปภาพ หรือ PDF)'),
                ),
                const SizedBox(height: 14),
                managementInput('รายละเอียด', _description, lines: 2),
                ElevatedButton(
                  style: managementButtonStyle(),
                  onPressed: _saving ? null : _save,
                  child: Text(_saving ? 'กำลังบันทึก...' : 'บันทึกรายการ'),
                ),
              ]),
            ),
            managementCard('รายการบัญชี', [
              if ((_data!['transactions'] as List).isEmpty)
                const Text('ยังไม่มีรายการบัญชี'),
              ...List<Map<String, dynamic>>.from(_data!['transactions']).map(
                (row) => ListTile(
                  contentPadding: EdgeInsets.zero,
                  title: Text(
                    '${row['category']} • ${row['transaction_type'] == 'expense' ? '-' : '+'}${_money(row['amount'])} บาท',
                  ),
                  subtitle: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text('${row['description']}\n${row['created_at']}'),
                      if (row['receipt_file'] != null &&
                          row['receipt_file'].toString().isNotEmpty)
                        TextButton.icon(
                          onPressed: () =>
                              _saveReceipt(row['receipt_file'].toString()),
                          icon: const Icon(Icons.download),
                          label: const Text('บันทึกใบเสร็จ'),
                        ),
                    ],
                  ),
                  trailing: IconButton(
                    tooltip: 'ลบรายการ',
                    onPressed: _saving ? null : () => _delete(row),
                    icon: const Icon(Icons.delete_outline),
                  ),
                ),
              ),
            ]),
          ],
        ],
      ),
    );
  }
}
