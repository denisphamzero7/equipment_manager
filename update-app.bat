@echo off
chcp 65001 >nul
echo ==========================================
echo 🚀 BẮT ĐẦU ĐÓNG GÓI VÀ CẬP NHẬT ỨNG DỤNG
echo ==========================================

REM Tự động thêm Node.js vào PATH nếu chưa có
where node >nul 2>nul
if %ERRORLEVEL% NEQ 0 (
    if exist "C:\Program Files\nodejs" (
        set "PATH=C:\Program Files\nodejs;%PATH%"
    )
)

echo.
echo 📦 [1/4] Đang đóng gói Frontend (npm run build)...
cd Frontend

if not exist node_modules (
    echo 📥 Thư mục node_modules chưa có. Đang tự động chạy npm install...
    call npm install
    if %ERRORLEVEL% NEQ 0 (
        echo ❌ Cài đặt dependencies thất bại!
        cd ..
        pause
        exit /b %ERRORLEVEL%
    )
)

call npm run build
if %ERRORLEVEL% NEQ 0 (
    echo ❌ Build Frontend thất bại!
    cd ..
    pause
    exit /b %ERRORLEVEL%
)
cd ..

echo.
echo 🚚 [2/4] Đang đồng bộ file Frontend vào Docker Container...
docker cp Frontend/dist/. equipment_manager-app-1:/var/www/html/public/
if %ERRORLEVEL% NEQ 0 (
    echo ❌ Không thể copy vào container equipment_manager-app-1. Kiểm tra xem container có đang chạy không?
    pause
    exit /b %ERRORLEVEL%
)

echo.
echo 🚚 [3/4] Đang đồng bộ mã nguồn Backend vào Docker Container...
docker cp Backend/app/. equipment_manager-app-1:/var/www/html/app/
docker cp Backend/routes/. equipment_manager-app-1:/var/www/html/routes/

echo.
echo ⚡ [4/4] Xóa cache Laravel và Reload Nginx...
docker exec equipment_manager-app-1 php artisan optimize:clear
docker exec equipment_manager-app-1 nginx -s reload

echo.
echo =======================================================
echo ✅ ĐÓNG GÓI ^& CẬP NHẬT THÀNH CÔNG!
echo 👉 Bạn chỉ cần mở trình duyệt và nhấn Ctrl + F5 để xem!
echo =======================================================
echo.
pause
