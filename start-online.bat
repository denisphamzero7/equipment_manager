@echo off
chcp 65001 >nul
title Khởi động Equipment Manager Online (Tên miền cố định)
echo ====================================================================
echo    🏨 EQUIPMENT MANAGER - KHỞI ĐỘNG HỆ THỐNG ONLINE
echo ====================================================================
echo.

:: 1. Kiểm tra Docker Desktop
echo [1/3] Đang kiểm tra Docker...
docker ps >nul 2>&1
if %ERRORLEVEL% NEQ 0 (
    echo [*] Docker Desktop chưa mở. Đang khởi động Docker Desktop...
    start "" "C:\Program Files\Docker\Docker\Docker Desktop.exe"
    echo [*] Đang đợi Docker Desktop nạp trong giây lát...
    :wait_docker
    timeout /t 5 /nobreak >nul
    docker ps >nul 2>&1
    if %ERRORLEVEL% NEQ 0 (
        echo   ...đang khởi tạo engine Docker, vui lòng đợi...
        goto wait_docker
    )
)
echo [V] Docker đã sẵn sàng!
echo.

:: 2. Khởi chạy hệ thống Container
echo [2/3] Đang khởi chạy các container (Web, API, MySQL, Redis)...
docker compose -f docker-compose.prod.yml --env-file .env.docker up -d
echo [V] Hệ thống container đang chạy!
echo.

:: 3. Mở Ngrok với Tên miền cố định
echo [3/3] Đang kết nối đường truyền Internet với Tên miền cố định...
echo ====================================================================
echo   🌐 TÊN MIỀN CỐ ĐỊNH (TRUY CẬP TỪ XA BẤT KỲ ĐÂU):
echo      👉 https://vesta-preornamental-bothersomely.ngrok-free.dev
echo.
echo   🏠 MẠNG NỘI BỘ MÁY TÍNH:
echo      👉 http://localhost:8088
echo ====================================================================
echo.
echo (Cửa sổ này đang duy trì kết nối Internet. Để dừng lại, bấm Ctrl + C)
echo.

set "NGROK_BIN=%LOCALAPPDATA%\ngrok\bin\ngrok.exe"
if not exist "%NGROK_BIN%" set "NGROK_BIN=ngrok"

"%NGROK_BIN%" start equipment-manager
pause
