@echo off
title Phupha Club - Build APK Release
echo ==========================================================
echo   ระบบสร้างไฟล์ติดตั้งแอปพลิเคชัน Android (.apk)
echo ==========================================================
echo.
echo [1/2] กำลังคอมไพล์และสร้างไฟล์ติดตั้ง (อาจใช้เวลา 1-2 นาที)...
echo.

cd /d "%~dp0dart_app"
call C:\scr\flutter\bin\flutter.bat build apk --release --no-pub

if %ERRORLEVEL% neq 0 (
    echo.
    echo [ERROR] การสร้างไฟล์ไม่สำเร็จ กรุณาตรวจสอบข้อผิดพลาดด้านบน
    pause
    exit /b
)

echo.
echo [2/2] กำลังคัดลอกไฟล์ app-release.apk ไปที่ web_code/uploads/...
copy /y "%~dp0dart_app\build\app\outputs\flutter-apk\app-release.apk" "%~dp0web_code\uploads\app-release.apk" >nul

echo.
echo ==========================================================
echo   สร้างไฟล์ติดตั้งสำเร็จเรียบร้อยแล้ว!
echo   - ไฟล์อยู่ที่: web_code\uploads\app-release.apk
echo   - สามารถส่งไฟล์นี้เข้ามือถือ หรือดาวน์โหลดผ่านหน้าเว็บได้ทันที
echo ==========================================================
echo.
pause

