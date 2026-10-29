import 'package:flutter/services.dart';
import '../constants/app_config.dart';
import 'api_service.dart';

//เก็บเฉพาะไฟล์ที่ผู้ใช้เลือก ไม่ขออ่านไฟล์ทั้งหมดในโทรศัพท์
class MobileSelectedFile {
  final String name;
  final Uint8List bytes;

  MobileSelectedFile({required this.name, required this.bytes});
}

class MobileDocumentService {
  static const MethodChannel channel = MethodChannel('phupha/documents');

  static Future<MobileSelectedFile?> pickFile({bool allowPdf = false}) async {
    final result = await channel.invokeMapMethod<String, dynamic>('pickFile', {
      'allowPdf': allowPdf,
    });
    if (result == null) {
      return null;
    }
    return MobileSelectedFile(
      name: result['name'] as String,
      bytes: result['bytes'] as Uint8List,
    );
  }

  static Future<void> printDocument(
    String page,
    String title, {
    int? activityId,
  }) async {
    final values = <String, String>{};
    if (activityId != null) {
      values['id'] = activityId.toString();
    }
    final html = await ApiService.getDocumentHtml(page, values: values);
    //ส่งเนื้อหาเอกสารไปพิมพ์ ไม่ส่งรหัสผ่านหรือ session ให้แอพอื่น
    await channel.invokeMethod<void>('printHtml', {
      'html': html,
      'title': title,
      'baseUrl': '${AppConfig.baseUrl}/',
      'landscape': page == 'generate_certificate.php',
    });
  }

  static Future<void> saveReceipt(String path) async {
    final file = await ApiService.getReceiptFile(path);
    //ให้เจ้าของมือถือเลือกที่บันทึกผ่านหน้าของระบบ ไม่เขียนลงโฟลเดอร์ส่วนตัวเอง
    await channel.invokeMethod<bool>('saveFile', file);
  }

  //แสดงข้อความที่คนใช้อ่านรู้เรื่อง ไม่แสดงชื่อ exception หรือรายละเอียดเครื่องมือในแอพ
  static String errorMessage(Object error) {
    if (error is PlatformException) {
      return error.message ?? 'ระบบมือถือทำรายการนี้ไม่ได้ กรุณาลองใหม่';
    }
    if (error is StateError) {
      return error.message.toString();
    }
    return 'ทำรายการไม่สำเร็จ กรุณาตรวจการเชื่อมต่อแล้วลองใหม่';
  }
}
