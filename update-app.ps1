# ==============================================================================
# Script tu dong dong goi va cap nhat code vao Docker Container
# Su dung: .\update-app.ps1
# ==============================================================================

Write-Host "==========================================" -ForegroundColor Cyan
Write-Host "[*] BAT DAU DONG GOI VA CAP NHAT UNG DUNG" -ForegroundColor Cyan
Write-Host "==========================================" -ForegroundColor Cyan

# 1. Build Frontend (Vue 3 + Vite)
Write-Host ""
Write-Host "[1/4] Dang dong goi Frontend (npm run build)..." -ForegroundColor Yellow
Set-Location -Path "$PSScriptRoot\Frontend"
npm run build
if ($LASTEXITCODE -ne 0) {
    Write-Host "[!] Build Frontend that bai! Vui long kiem tra lai loi code." -ForegroundColor Red
    Set-Location -Path $PSScriptRoot
    exit 1
}
Set-Location -Path $PSScriptRoot

# 2. Dong bo Frontend build vao Docker Container
Write-Host ""
Write-Host "[2/4] Dang dong bo file Frontend vao Docker Container..." -ForegroundColor Yellow
docker cp Frontend/dist/. equipment_manager-app-1:/var/www/html/public/
if ($LASTEXITCODE -ne 0) {
    Write-Host "[!] Khong the copy vao container equipment_manager-app-1. Kiem tra xem container co dang chay khong?" -ForegroundColor Red
    exit 1
}

# 3. Dong bo Backend code vao Docker Container
Write-Host ""
Write-Host "[3/4] Dang dong bo ma nguon Backend vao Docker Container..." -ForegroundColor Yellow
docker cp Backend/app/. equipment_manager-app-1:/var/www/html/app/
docker cp Backend/routes/. equipment_manager-app-1:/var/www/html/routes/

# 4. Xoa cache va reload Nginx
Write-Host ""
Write-Host "[4/4] Xoa cache Laravel va Reload Nginx..." -ForegroundColor Yellow
docker exec equipment_manager-app-1 php artisan optimize:clear
docker exec equipment_manager-app-1 nginx -s reload

Write-Host ""
Write-Host "=======================================================" -ForegroundColor Green
Write-Host "[OK] DONG GOI VA CAP NHAT THANH CONG!" -ForegroundColor Green
Write-Host "-> Mo trinh duyet va nhan Ctrl + F5 de xem ket qua!" -ForegroundColor Green
Write-Host "=======================================================" -ForegroundColor Green
Write-Host ""
