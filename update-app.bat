@echo off
chcp 65001 >nul
echo ==========================================
echo 🚀 BẮT ĐẦU ĐÓNG GÓI VÀ CẬP NHẬT ỨNG DỤNG
echo ==========================================

echo.
echo 📦 [1/4] Đang đóng gói Frontend (npm run build)...
cd Frontend
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
    echo ❌ Không thể copy vào container equipment_manager-app-1.
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
