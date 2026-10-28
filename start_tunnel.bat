@echo off
title Phupha Club - Cloudflare Tunnel
echo ==========================================================
echo   Starting Cloudflare Tunnel for XAMPP (Port 80)
echo ==========================================================
echo.

if not exist "%~dp0cloudflared.exe" (
    echo [INFO] Downloading cloudflared.exe from Cloudflare...
    powershell -NoProfile -ExecutionPolicy Bypass -Command "[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12; Invoke-WebRequest -Uri 'https://github.com/cloudflare/cloudflared/releases/latest/download/cloudflared-windows-amd64.exe' -OutFile '%~dp0cloudflared.exe' -UseBasicParsing"
    echo [INFO] Download complete!
    echo.
)

echo Make sure Apache and MySQL are running in XAMPP!
echo Please wait for your https://...trycloudflare.com link below...
echo (Do not close this window while using the server)
echo ==========================================================
echo.

"%~dp0cloudflared.exe" tunnel --url http://localhost:80

pause
