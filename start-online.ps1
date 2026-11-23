[Console]::OutputEncoding = [System.Text.Encoding]::UTF8
$Host.UI.RawUI.WindowTitle = "Khởi động Equipment Manager Online"

Write-Host "====================================================================" -ForegroundColor Cyan
Write-Host "   🏨 EQUIPMENT MANAGER - KHỞI ĐỘNG HỆ THỐNG ONLINE" -ForegroundColor Cyan
Write-Host "====================================================================" -ForegroundColor Cyan
Write-Host ""

# 1. Kiểm tra Docker Desktop
Write-Host "[1/3] Đang kiểm tra Docker..." -ForegroundColor Yellow
$dockerReady = $false
try {
    $null = docker ps 2>&1
    if ($LASTEXITCODE -eq 0) { $dockerReady = $true }
} catch {}

if (-not $dockerReady) {
    Write-Host "[*] Docker Desktop chưa mở. Đang khởi động Docker Desktop..." -ForegroundColor Yellow
    Start-Process "C:\Program Files\Docker\Docker\Docker Desktop.exe"
    Write-Host "[*] Đang đợi Docker engine sẵn sàng..." -ForegroundColor Yellow
    while (-not $dockerReady) {
        Start-Sleep -Seconds 5
        try {
            $null = docker ps 2>&1
            if ($LASTEXITCODE -eq 0) { $dockerReady = $true }
        } catch {}
    }
}
Write-Host "[V] Docker đã sẵn sàng!" -ForegroundColor Green
Write-Host ""

# 2. Khởi chạy hệ thống Container
Write-Host "[2/3] Đang khởi chạy các container (Web, API, MySQL, Redis)..." -ForegroundColor Yellow
docker compose -f docker-compose.prod.yml --env-file .env.docker up -d
Write-Host "[V] Hệ thống container đang chạy!" -ForegroundColor Green
Write-Host ""

# 3. Mở Ngrok với Tên miền cố định
Write-Host "[3/3] Đang kết nối đường truyền Internet với Tên miền cố định..." -ForegroundColor Yellow
Write-Host "====================================================================" -ForegroundColor Green
Write-Host "  🌐 TÊN MIỀN CỐ ĐỊNH (TRUY CẬP TỪ XA BẤT KỲ ĐÂU):" -ForegroundColor Green
Write-Host "     👉 https://vesta-preornamental-bothersomely.ngrok-free.dev" -ForegroundColor White
Write-Host ""
Write-Host "  🏠 MẠNG NỘI BỘ MÁY TÍNH:" -ForegroundColor Cyan
Write-Host "     👉 http://localhost:8088" -ForegroundColor White
Write-Host "====================================================================" -ForegroundColor Green
Write-Host ""
Write-Host "(Cửa sổ này đang duy trì kết nối Internet. Để dừng lại, bấm Ctrl + C)" -ForegroundColor Gray
Write-Host ""

$ngrokBin = "$env:LOCALAPPDATA\ngrok\bin\ngrok.exe"
if (-not (Test-Path $ngrokBin)) { $ngrokBin = "ngrok" }

& $ngrokBin start equipment-manager
