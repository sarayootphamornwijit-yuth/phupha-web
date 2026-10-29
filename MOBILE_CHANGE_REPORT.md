# รายงานการแก้เว็บและแอป Flutter สำหรับตรวจใน Antigravity IDE

วันที่ 2026-10-29 — โครงการ phupha-web

## ขอบเขตของงาน

แก้แอปให้ใช้ข้อมูลและลำดับงานตามเว็บ เพิ่มคำสั่ง PHP ที่แอปยังขาด และอธิบายโค้ดส่วนที่เพิ่มด้วยคอมเม้นภาษาไทย ใช้สี ฟอนต์ ชื่อช่อง และลำดับฟอร์มอ้างอิงเว็บ ไม่ปรับ CSS หรือ layout ของหน้าเว็บเดิม แถบสัดส่วนบนแดชบอร์ดที่เพิ่มเองถูกลบแล้วตามที่เจ้าของโครงงานแจ้ง เหลือข้อความและจำนวน

งานนี้รวมเข้ากับ main ล่าสุดที่มีอยู่ก่อนส่ง คือ `df30ac4` ซึ่งมีงานเปิดหน้าแรกก่อนล็อกอิน โหมดผู้เยี่ยมชม ปุ่มย้อนกลับ และ URL tunnel ใหม่อยู่แล้ว เก็บงานเหล่านั้นไว้ ไม่ย้อนกลับไปใช้หน้า Login เป็นหน้าแรก

ส่งเฉพาะไฟล์แอป/เว็บที่จำเป็นและรายงานนี้ ไม่ส่งรายงานเก่า ภาพหน้าจอ เครื่องมือคลาวด์ สคริปต์ทดสอบเพิ่ม ไฟล์ build ฐานข้อมูลทดสอบ หรือกุญแจลงนามจริง ไฟล์ทดสอบเดิมและ build_apk.bat ที่มีบน main ยังอยู่ตามเดิม

## ปัญหาที่แก้และพฤติกรรมหลังแก้

| ส่วน | สิ่งที่เปลี่ยน |
| --- | --- |
| เข้าสู่ระบบ | ใช้ PHP session จริง เก็บ session แยกตามเซิร์ฟเวอร์ โหลดสิทธิ์ล่าสุด และจัดการ session หมดอายุ/ออกจากระบบ |
| สมัครสมาชิก | ใช้คณะและสาขาจากฐานข้อมูล ตรวจข้อมูลนักศึกษา ผู้สมัครทุกประเภทเริ่มเป็น member ผู้ดูแลเป็นคนให้สิทธิ์ |
| สิทธิ์กรรมการ | ตรวจทั้งเมนูในแอปและคำสั่ง API ตามตำแหน่ง ป้องกันเรียก API ข้ามสิทธิ์ ลบเมนูสมาชิกที่ซ้ำ |
| กิจกรรม | เพิ่มฟอร์มสร้าง/แก้ไขครบช่อง รูปปก ผู้ประสานงาน ส่งรออนุมัติ แก้กิจกรรมที่ถูกตีกลับแล้วส่งใหม่ เปิด/ปิดเช็คอิน และรายชื่อผู้ร่วม |
| การอนุมัติ | อาจารย์เห็นกิจกรรมรออนุมัติและเปลี่ยนสถานะจริง สมาชิกจึงเห็นและสมัครกิจกรรมที่อนุมัติแล้ว |
| ยานพาหนะ | เตรียมจุดนัดพบ/รายละเอียดเดินทาง เพิ่ม/ลบรถ อ่านจำนวนที่จอง ตรวจทะเบียนซ้ำและความจุ |
| งบประมาณ | เลือกกิจกรรม เพิ่ม/ลบรายรับรายจ่าย แนบใบเสร็จ อ่านยอดคงเหลือจริง ตรวจยอดไม่ให้ติดลบ |
| ลงทะเบียน | ตรวจความจุใน transaction เลือกการเดินทางหลังสมัคร ยกเลิกแล้วสมัครใหม่ได้ตามเงื่อนไข |
| QR/เช็คอิน | ใช้ mobile_scanner เปิดกล้อง และ qr_flutter วาด QR ภายในแอป ตัวรับตรวจสิทธิ์และสถานะการลงทะเบียน |
| ประเมินและคะแนน | ประเมินหลังจบกิจกรรมและเช็คอินตามเงื่อนไข รับคะแนนครั้งเดียว รีเฟรชข้อมูลที่เกี่ยวข้อง |
| สรุปรายงาน | แสดงผู้สมัคร/เช็คอิน การเดินทาง เงิน และผลประเมินจากกิจกรรมที่เลือก ใช้เอกสารรายชื่อเดียวกับเว็บสำหรับพิมพ์/PDF ไม่มีขั้นตอนส่งรายงานให้อาจารย์เพิ่ม |
| ข่าว | ผู้มีสิทธิ์เพิ่ม แก้ ลบ และเผยแพร่ข่าวลงฐานข้อมูลจริง |
| สมาชิกและโปรไฟล์ | อ่านรายละเอียดสมาชิก แก้ข้อมูลส่วนตัวและรูป เก็บข้อมูลการศึกษาเดิมที่ไม่ได้แก้ ไม่ใช้รายชื่อกรรมการจำลอง |
| ผู้ดูแล | แดชบอร์ดข้อมูลจริง ตั้งเกณฑ์คะแนน และจัดสิทธิ์/ตำแหน่งสมาชิก ไม่ให้เปลี่ยนสิทธิ์หรือลบตัวเอง |
| เกียรติบัตร/เอกสาร | โหลด HTML ที่ต้องเข้าสู่ระบบแล้วส่งให้ระบบพิมพ์ Android/iOS เพิ่มตัวเลือกแนบไฟล์และบันทึกใบเสร็จผ่านบริการของระบบ |
| อายุหน้าจอ | ยกเลิก Timer ของ Splash และตรวจ mounted/ลำดับคำขอในจุดที่พบ เพื่อลดการอัปเดตหน้าจอที่ปิดไปแล้ว |

ป้องกันการลบข้อมูลที่มีคนใช้งานแล้ว ได้แก่ กิจกรรมที่มีผู้ลงทะเบียน รถที่มีผู้จอง และรายรับที่ลบแล้วทำให้เงินติดลบ ใช้ตารางเดิมสำหรับงานจัดการใหม่ ไม่เพิ่มระบบหรือบริการภายนอก

## ไฟล์ใหม่ที่จำเป็นต่อแอป/เว็บ

| ไฟล์ | เหตุผลที่เพิ่ม |
| --- | --- |
| dart_app/lib/screens/activity_form_screen.dart | ฟอร์มสร้าง/แก้กิจกรรมตามข้อมูลของเว็บ |
| dart_app/lib/screens/admin_dashboard_screen.dart | แดชบอร์ดผู้ดูแล ข้อความสถิติและเกณฑ์คะแนน ไม่มีแถบสัดส่วน |
| dart_app/lib/screens/admin_users_screen.dart | จัดสิทธิ์และตำแหน่งสมาชิก |
| dart_app/lib/screens/member_detail_screen.dart | รายละเอียดสมาชิก |
| dart_app/lib/services/mobile_document_service.dart | เรียกตัวเลือกไฟล์ พิมพ์ และบันทึกเอกสารของ Android/iOS |
| dart_app/lib/widgets/management_form_widgets.dart | ช่องกรอก/กรอบฟอร์มร่วมกันเพื่อให้หน้าที่เพิ่มใช้รูปแบบเดียวกัน |
| dart_app/lib/widgets/profile_image.dart | แสดงรูปโปรไฟล์และรูปสำรองเมื่อโหลดไม่ได้ |
| web_code/includes/api_management.php | อ่านข้อมูลจัดการที่แอปต้องใช้พร้อมตรวจสิทธิ์ |
| web_code/includes/api_mobile_management.php | คำสั่งจัดการใหม่ 22 action ใช้ฐานข้อมูลชุดเดิม |
| dart_app/ios/Podfile | ตั้งค่า CocoaPods สำหรับปลั๊กอิน iOS |
| dart_app/android/key.properties.example | ตัวอย่างตั้งค่าลงนาม ไม่มีรหัสผ่านหรือกุญแจจริง |
| dart_app/assets/fonts/Prompt-*.ttf และ OFL.txt | ฟอนต์ Prompt 5 น้ำหนักพร้อมใบอนุญาต ใช้ฟอนต์เดียวกับเว็บโดยไม่โหลดจากอินเทอร์เน็ต |

ไฟล์เดิมที่แก้กระจายอยู่ใน lib/screens, models, services, widgets, Android/iOS, pubspec และ API/config ของเว็บ รายชื่อไฟล์ครบอยู่ท้ายรายงาน

## สิ่งที่เพิ่มจากโครงการเดิม

- แพ็กเกจ runtime ที่เพิ่มคือ mobile_scanner และ qr_flutter รวม qr ที่ติดตั้งตามมา ส่วน http/shared_preferences/encrypt เป็นของเดิม
- ค่าตอน build ชื่อ API_BASE_URL ทำให้เปลี่ยนเซิร์ฟเวอร์ได้ ค่าเริ่มต้นยึด URL ล่าสุดบน main: `https://cricket-sensitivity-tracy-driven.trycloudflare.com/phupha-club/web_code` ลิงก์ quick tunnel เปลี่ยนได้ทุกครั้งที่เปิดใหม่ จึงต้องใช้ลิงก์ที่เปิดอยู่จริง
- PHP รองรับ PHUPHA_DB_* สำหรับฐานทดสอบ แต่ค่า XAMPP เดิมยังใช้ได้ ตรวจและเพิ่มคอลัมน์ email หากฐานเก่ายังไม่มี โดยไม่ลบตาราง/ข้อมูลเดิม init.sql เพิ่ม email สำหรับการติดตั้งฐานใหม่
- เพิ่มคำสั่ง API/การตรวจสิทธิ์และ Origin ของคำขอเขียนจากเบราว์เซอร์ งาน native ที่ไม่มี Origin ใช้ session ตรวจเหมือนเดิม
- เพิ่มบริการเอกสารใน MainActivity.kt และ AppDelegate.swift ใช้ตัวเลือกไฟล์ของระบบ ไม่ขออ่านพื้นที่เก็บไฟล์ทั้งหมด ส่ง HTML/ไฟล์ให้ native โดยไม่ส่งรหัสผ่านหรือ cookie ไปหน้าพิมพ์
- ตั้ง application ID/bundle ID เป็น `io.github.sarayootphamornwijityuth.phupha` และรองรับ release key ส่วนตัว ถ้ายังไม่มี key ใช้ debug key เพื่อทดสอบตามเดิม การเปลี่ยน ID อาจทำให้ติดตั้งเป็นอีกแอปหนึ่งเมื่อเครื่องยังมีแอป ID เดิม

## อ่านโค้ดตามลำดับเพื่ออธิบายตอนสอบ

ตัวอย่างบันทึกกิจกรรม: ปุ่มใน activity_form_screen.dart เรียกฟังก์ชันบันทึก → ตรวจช่องกรอก → ApiService.managementPost ส่งข้อมูลพร้อม PHP session → api.php ตรวจว่าล็อกอินและมีสิทธิ์ → api_mobile_management.php ตรวจข้อมูลแล้วบันทึก MySQL → ส่งผลกลับมาให้หน้าจอโหลดรายการใหม่

SessionManager ดูแลข้อมูลล็อกอินที่เก็บในเครื่อง แต่สิทธิ์จริงตัดสินที่ PHP อีกครั้ง UserModel.canManage ใช้เลือกเมนูที่ควรแสดง ไม่ได้ใช้แทนการตรวจสิทธิ์ของเซิร์ฟเวอร์ งานจอง/เงินใช้ transaction เพื่อไม่ให้ข้อมูลเปลี่ยนครึ่งเดียว ส่วน mobile_document_service.dart เป็นสะพานเรียกฟังก์ชันของ Android/iOS ไม่ใช่ตัวฐานข้อมูล

## ผลทดสอบและขอบเขตที่ยืนยันได้

ใช้ PHP/MySQL จริงในฐานแยก phupha_api_test ไม่ได้แก้ข้อมูล XAMPP ของเจ้าของโครงงาน

| การตรวจ | ผล |
| --- | --- |
| ชุด unit/widget เพิ่มที่รันในคลาวด์ก่อนรวม main | ผ่าน 29 เคส ข้าม 20 เคสที่ต้องเปิดใช้แยก ไม่นับเคสข้ามว่าผ่าน |
| ชุดขั้นตอนจัดการผ่าน HTTP จริง | ผ่าน 393 จุดตรวจใน 1 workflow ไม่ใช่ 393 หน้าจอ |
| ชุดกดฟอร์มตามสิทธิ์กับ HTTP จริง | ผ่าน 12 เคส widget |
| อัปโหลดไฟล์/โปรไฟล์/ภาพหน้าจอ | ผ่าน 3 เคส |
| PHP API regression | ผ่าน 61 จุดตรวจ |
| PHP syntax รอบเดิม | ผ่าน 43 ไฟล์ |
| PHP syntax ไฟล์ที่จะส่ง | ตรวจไฟล์ PHP ที่เปลี่ยนครบ 5 ไฟล์ผ่าน API ที่รวมจาก main เหมือนชุดที่ทดสอบแล้วเมื่อไม่นับช่องว่าง |
| หลังรวม main: flutter analyze | ไม่มี error/warning มีคำแนะนำ info 19 รายการ |
| หลังรวม main: ชุดทดสอบเดิมใน repository | ผ่าน 1 smoke test โดยไม่ได้ส่งชุดทดสอบ/เครื่องมือใหม่เพิ่ม |
| หลังรวม main: Flutter run แยกโปรเซส | ผ่านครบ 12 บัญชี รวมกรรมการ 8 ตำแหน่ง เปิดเมนูตามสิทธิ์ 35 ครั้ง |
| หลังรวม main: ปิดเปิดโปรเซสใหม่ | จำ session ได้ เข้าแดชบอร์ดได้ ออกจากระบบแล้วเปิดใหม่เป็นผู้เยี่ยมชมและเข้าหน้าล็อกอินได้ ลบบัญชีทดสอบ 12 บัญชีแล้ว |

ชุดทดสอบ widget เดิมใช้ HTTP จริงแต่จำลอง SharedPreferences และจุดเรียก native สำหรับเลือกไฟล์/พิมพ์ ส่วน Flutter run แยกโปรเซสใช้ HTTP และที่เก็บข้อมูลจริงของ Linux ใช้ Driver จากอีกโปรเซสกดหน้าจอ การป้อนตัวอักษรยังใช้ Driver แทนคีย์บอร์ดจริง

ยังไม่มี APK ใหม่ที่ build สำเร็จในคลาวด์ เพราะ Gradle โหลด Flutter Android artifacts จาก storage.googleapis.com ไม่ได้ตามนโยบายเครือข่าย ตรวจ Kotlin source กับ Android SDK ได้และเคยสร้าง debug Dart/assets bundle ได้ แต่สองอย่างนี้ไม่ใช่ APK ที่ติดตั้งได้

ยังไม่ได้ทดสอบกล้อง สิทธิ์กล้อง ตัวเลือกไฟล์ การพิมพ์/บันทึกไฟล์ บน Android/iPhone จริง ไม่มี Mac/Xcode สำหรับ build/sign iOS โค้ด native และวงจรติดตั้งยังต้องตรวจบนเครื่องที่รองรับ ไม่อ้างว่ารองรับทุกฟังก์ชันบนโทรศัพท์ทุกรุ่นแล้ว

## เปิดตรวจต่อใน Antigravity IDE

1. ดึง main ล่าสุดแล้วเปิดโฟลเดอร์ repository หากมีงานในเครื่องที่ยังไม่บันทึก ให้เก็บงานนั้นก่อนดึง main
2. รัน flutter pub get ใน dart_app ใช้ pubspec.lock ที่ส่งมาด้วยเพื่อยึดรุ่นแพ็กเกจ
3. เปิด Apache และ MySQL ใน XAMPP อัปเดตไฟล์ API/config ที่เปลี่ยนพร้อมกัน อย่าอัปเดตเฉพาะ Flutter เพราะฟอร์มใหม่ต้องใช้ API ใหม่
4. ใช้ฐานเดิม ไม่ import init.sql ทับฐานที่มีข้อมูล ตรวจว่า Apache เขียน uploads ได้ หากแนบไฟล์ไม่ผ่านให้ดู upload_max_filesize/post_max_size ของ PHP
5. เปิด tunnel ของ XAMPP แล้วใช้ URL HTTPS ปัจจุบันในคำสั่งด้านล่าง

```bash
cd dart_app
flutter pub get
flutter analyze --no-fatal-infos
flutter test
flutter devices
flutter run -d DEVICE_ID --dart-define=API_BASE_URL=https://YOUR-TUNNEL/phupha-club/web_code
```

แทน DEVICE_ID ด้วยรหัสเครื่องจาก flutter devices เมื่อต้องสร้าง APK ใช้:

```bash
flutter build apk --release --dart-define=API_BASE_URL=https://YOUR-TUNNEL/phupha-club/web_code
```

จากนั้นนำ build/app/outputs/flutter-apk/app-release.apk ไปไว้ที่ web_code/uploads/app-release.apk ของ XAMPP เพื่อให้ดาวน์โหลดผ่านหน้าเดิมได้ ขณะยังไม่มีไฟล์ APK จริง download_app.php จะแจ้งว่าไม่มีไฟล์ แทนการให้ดาวน์โหลดไฟล์เสีย

ทดสอบตามลำดับ: สมัครทุกประเภท → ทุกคนเป็น member → แอดมินให้สิทธิ์ → กรรมการสร้างงาน/เตรียมรถและงบ → อาจารย์อนุมัติ → สมาชิกสมัครและเลือกการเดินทาง → สมาชิกแสดง QR ให้กรรมการใช้กล้องสแกน → จบงานและประเมิน → รับคะแนนครั้งเดียว → ตรวจสรุปรายงาน/PDF และเกียรติบัตร

ตรวจเพิ่มบนอุปกรณ์จริง: มือถือเปิดหน้าแรกแบบผู้เยี่ยมชมได้ ล็อกอิน/ปิดเปิดใหม่/ออกจากระบบถูกต้อง ลองกล้องจริง ไฟล์รูป/ใบเสร็จ การพิมพ์ บัญชีและรายงานทุกสิทธิ์ และทดสอบ iOS บน Mac/iPhone ไม่มีสคริปต์คลาวด์ติดมากับชุดแอปนี้

## รายชื่อไฟล์ที่ส่ง

- `dart_app/android/app/build.gradle.kts`
- `dart_app/android/app/src/main/AndroidManifest.xml`
- `dart_app/android/app/src/main/kotlin/com/example/dart_app/MainActivity.kt`
- `dart_app/android/key.properties.example`
- `dart_app/assets/fonts/OFL.txt`
- `dart_app/assets/fonts/Prompt-Bold.ttf`
- `dart_app/assets/fonts/Prompt-Light.ttf`
- `dart_app/assets/fonts/Prompt-Medium.ttf`
- `dart_app/assets/fonts/Prompt-Regular.ttf`
- `dart_app/assets/fonts/Prompt-SemiBold.ttf`
- `dart_app/ios/Flutter/Debug.xcconfig`
- `dart_app/ios/Flutter/Release.xcconfig`
- `dart_app/ios/Podfile`
- `dart_app/ios/Runner.xcodeproj/project.pbxproj`
- `dart_app/ios/Runner/AppDelegate.swift`
- `dart_app/ios/Runner/Info.plist`
- `dart_app/lib/constants/app_config.dart`
- `dart_app/lib/main.dart`
- `dart_app/lib/models/user_model.dart`
- `dart_app/lib/screens/about_screen.dart`
- `dart_app/lib/screens/activity_form_screen.dart`
- `dart_app/lib/screens/admin_dashboard_screen.dart`
- `dart_app/lib/screens/admin_users_screen.dart`
- `dart_app/lib/screens/advisor_approval_screen.dart`
- `dart_app/lib/screens/advisor_members_screen.dart`
- `dart_app/lib/screens/advisor_news_screen.dart`
- `dart_app/lib/screens/certificates_screen.dart`
- `dart_app/lib/screens/committee_directory_screen.dart`
- `dart_app/lib/screens/edit_profile_screen.dart`
- `dart_app/lib/screens/erp_activities_screen.dart`
- `dart_app/lib/screens/erp_budgets_screen.dart`
- `dart_app/lib/screens/erp_reports_screen.dart`
- `dart_app/lib/screens/erp_vehicles_screen.dart`
- `dart_app/lib/screens/joined_activities_screen.dart`
- `dart_app/lib/screens/login_screen.dart`
- `dart_app/lib/screens/main_navigation.dart`
- `dart_app/lib/screens/member_detail_screen.dart`
- `dart_app/lib/screens/profile_screen.dart`
- `dart_app/lib/screens/qr_scanner_screen.dart`
- `dart_app/lib/screens/register_screen.dart`
- `dart_app/lib/screens/self_checkin_screen.dart`
- `dart_app/lib/screens/splash_screen.dart`
- `dart_app/lib/services/api_service.dart`
- `dart_app/lib/services/mobile_document_service.dart`
- `dart_app/lib/services/session_manager.dart`
- `dart_app/lib/widgets/management_form_widgets.dart`
- `dart_app/lib/widgets/profile_image.dart`
- `dart_app/lib/widgets/web_app_bar.dart`
- `dart_app/lib/widgets/web_drawer.dart`
- `dart_app/pubspec.lock`
- `dart_app/pubspec.yaml`
- `web_code/api.php`
- `web_code/config/database.php`
- `web_code/config/init.sql`
- `web_code/download_app.php`
- `web_code/includes/api_management.php`
- `web_code/includes/api_mobile_management.php`

รวมไฟล์เว็บ/แอป 57 ไฟล์ และรายงานนี้อีก 1 ไฟล์
