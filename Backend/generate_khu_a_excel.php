<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Modules\Equipment\Models\EquipmentModel;
use App\Modules\Motel\Models\Room;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

echo "=== 1. Đảm bảo các mẫu thiết bị tồn tại trong hệ thống ===\n";

$tvModel = EquipmentModel::firstOrCreate(
    ['name' => 'TV Samsung 32 inch HD'],
    [
        'equipment_category_id' => 2,
        'brand' => 'Samsung',
        'model_code' => 'TV-SAM-32',
        'specs' => [
            'type' => 'Smart TV 32 inch',
            'resolution' => 'HD 720p',
            'screen_size' => '32 inch',
        ],
        'organization_id' => null,
    ]
);
echo "TV Model: " . $tvModel->name . "\n";

EquipmentModel::firstOrCreate(
    ['name' => 'Tivi 32 inch'],
    [
        'equipment_category_id' => 2,
        'brand' => 'Samsung',
        'model_code' => 'TV-32-GEN',
        'specs' => ['type' => 'Tivi 32 inch'],
        'organization_id' => null,
    ]
);

// Đảm bảo mẫu Điện thoại bàn
EquipmentModel::firstOrCreate(
    ['name' => 'Điện thoại bàn Panasonic KX-TS500'],
    [
        'equipment_category_id' => 1,
        'brand' => 'Panasonic',
        'model_code' => 'TEL-PANA-500',
        'specs' => ['type' => 'Điện thoại cố định / Nội bộ', 'features' => 'Gọi lễ tân, nội bộ buồng phòng'],
        'organization_id' => null,
    ]
);

EquipmentModel::firstOrCreate(
    ['name' => 'Điện thoại bàn'],
    [
        'equipment_category_id' => 1,
        'brand' => 'Panasonic',
        'model_code' => 'TEL-GEN',
        'specs' => ['type' => 'Điện thoại bàn nội bộ buồng phòng'],
        'organization_id' => null,
    ]
);

echo "=== 2. Cập nhật cấu hình WiFi dùng ké 1:1 cho phòng Block A ===\n";
$roomsMap = Room::where('block', 'Block A')->get()->keyBy('room_number');

// Cặp WiFi 1:1: [Phòng cấp WiFi => Phòng dùng ké]
$wifiPairs = [
    // Tầng 1 (122 cấp cho 123, 124 cấp cho 125, 126 độc lập)
    '122' => '123',
    '124' => '125',
    // Tầng 2
    '221' => '222',
    '223' => '224',
    '225' => '226',
    // Tầng 3
    '321' => '322',
    '323' => '324',
    '325' => '326',
    // Tầng 4
    '421' => '422',
    '423' => '424',
    '425' => '426',
    // Tầng 5
    '521' => '522',
    '523' => '524',
    '525' => '526',
    // Tầng 6
    '621' => '622',
    '623' => '624',
    '625' => '626',
    // Tầng 7
    '721' => '722',
    '723' => '724',
    '725' => '726',
];

// Danh sách phòng có bộ phát riêng (21 phòng)
$dedicatedRoomSet = array_fill_keys(array_keys($wifiPairs), true);
$dedicatedRoomSet['126'] = true; // Phòng lẻ tầng 1

// 1. Cập nhật các phòng dedicated (có router riêng)
foreach (array_keys($dedicatedRoomSet) as $roomNum) {
    $rStr = (string)$roomNum;
    if (isset($roomsMap[$rStr])) {
        $r = $roomsMap[$rStr];
        $r->wifi_mode = 'dedicated';
        $r->shared_wifi_room_id = null;
        $r->wifi_router_count = 1;
        $r->shared_wifi_note = null;
        $r->save();
    }
}

// 2. Cập nhật các phòng dùng ké (shared wifi)
foreach ($wifiPairs as $hostRoomNum => $guestRoomNum) {
    $hStr = (string)$hostRoomNum;
    $gStr = (string)$guestRoomNum;
    if (isset($roomsMap[$gStr]) && isset($roomsMap[$hStr])) {
        $guest = $roomsMap[$gStr];
        $host = $roomsMap[$hStr];
        $guest->wifi_mode = 'shared';
        $guest->shared_wifi_room_id = $host->id;
        $guest->wifi_router_count = 0;
        $guest->shared_wifi_note = "Dùng chung Wifi Router của P.{$hStr}";
        $guest->save();
    }
}

echo "Đã cập nhật cấu hình WiFi 1:1 cho 41 phòng Block A: 21 phòng dedicated, 20 phòng shared.\n";

echo "=== 3. Tạo file Excel danh_sach_thiet_bi_khu_A.xlsx (1 Sheet chuẩn để Import) ===\n";

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Danh_Sach_Thiet_Bi');

// Tiêu đề cột
$headers = [
    'A1' => 'Mã Serial',
    'B1' => 'Mẫu thiết bị',
    'C1' => 'Số phòng',
    'D1' => 'Trạng thái',
    'E1' => 'Vị trí trong phòng',
    'F1' => 'Ghi chú',
];

foreach ($headers as $cell => $text) {
    $sheet->setCellValue($cell, $text);
}

// Styling header
$headerStyle = [
    'font' => [
        'bold' => true,
        'color' => ['rgb' => 'FFFFFF'],
        'size' => 11,
    ],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => '1E40AF'], // Indigo / Blue 800
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical' => Alignment::VERTICAL_CENTER,
    ],
];
$sheet->getStyle('A1:F1')->applyFromArray($headerStyle);
$sheet->getRowDimension(1)->setRowHeight(28);

// Lấy danh sách 41 phòng Block A sắp xếp theo tầng và số phòng
$allRooms = Room::where('block', 'Block A')
    ->orderBy('floor')
    ->orderBy('room_number')
    ->get();

$rowIdx = 2;
$countTV = 0;
$countBox = 0;
$countRemote = 0;
$countPhone = 0;
$countWifi = 0;

foreach ($allRooms as $room) {
    $rNum = (string)$room->room_number;
    $floor = $room->floor;

    // 1. Tivi 32 inch (Mỗi phòng 1 cái)
    $sheet->setCellValue("A{$rowIdx}", "TV32-A{$rNum}");
    $sheet->setCellValue("B{$rowIdx}", "TV Samsung 32 inch HD");
    $sheet->setCellValue("C{$rowIdx}", $rNum);
    $sheet->setCellValue("D{$rowIdx}", "Hoạt động tốt");
    $sheet->setCellValue("E{$rowIdx}", "Treo tường đối diện giường ngủ");
    $sheet->setCellValue("F{$rowIdx}", "Trang bị mới Block A - Tầng {$floor}");
    $rowIdx++;
    $countTV++;

    // 2. TV Box (Mỗi phòng 1 cái)
    $sheet->setCellValue("A{$rowIdx}", "BOX-A{$rNum}");
    $sheet->setCellValue("B{$rowIdx}", "FPT Play Box 4K");
    $sheet->setCellValue("C{$rowIdx}", $rNum);
    $sheet->setCellValue("D{$rowIdx}", "Hoạt động tốt");
    $sheet->setCellValue("E{$rowIdx}", "Gắn sau Tivi / Kệ Tivi");
    $sheet->setCellValue("F{$rowIdx}", "Trang bị mới Block A - Tầng {$floor}");
    $rowIdx++;
    $countBox++;

    // 3. Remote Tivi (Mỗi phòng 1 cái)
    $sheet->setCellValue("A{$rowIdx}", "RMT-A{$rNum}");
    $sheet->setCellValue("B{$rowIdx}", "Remote Tivi đa năng hồng ngoại");
    $sheet->setCellValue("C{$rowIdx}", $rNum);
    $sheet->setCellValue("D{$rowIdx}", "Hoạt động tốt");
    $sheet->setCellValue("E{$rowIdx}", "Bàn đầu giường");
    $sheet->setCellValue("F{$rowIdx}", "Trang bị mới Block A - Tầng {$floor}");
    $rowIdx++;
    $countRemote++;

    // 4. Điện thoại bàn (Mỗi phòng 1 cái)
    $sheet->setCellValue("A{$rowIdx}", "TEL-A{$rNum}");
    $sheet->setCellValue("B{$rowIdx}", "Điện thoại bàn Panasonic KX-TS500");
    $sheet->setCellValue("C{$rowIdx}", $rNum);
    $sheet->setCellValue("D{$rowIdx}", "Hoạt động tốt");
    $sheet->setCellValue("E{$rowIdx}", "Bàn làm việc / Táp đầu giường");
    $sheet->setCellValue("F{$rowIdx}", "Điện thoại bàn nội bộ buồng phòng - Tầng {$floor}");
    $rowIdx++;
    $countPhone++;

    // 5. Wifi Router: Chỉ lắp ở 21 phòng có router riêng
    if (isset($dedicatedRoomSet[$rNum])) {
        $sheet->setCellValue("A{$rowIdx}", "WF-A{$rNum}");
        $sheet->setCellValue("B{$rowIdx}", "Wifi Router TP-Link Archer C6");
        $sheet->setCellValue("C{$rowIdx}", $rNum);
        $sheet->setCellValue("D{$rowIdx}", "Hoạt động tốt");
        $sheet->setCellValue("E{$rowIdx}", "Góc trên cửa ra vào / Kệ cao");
        $sheet->setCellValue("F{$rowIdx}", "Router phát sóng chính (chia sẻ 1:1)");
        $rowIdx++;
        $countWifi++;
    }
}

$lastRow = $rowIdx - 1;

// Borders & alignment
$borderStyle = [
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
            'color' => ['rgb' => 'CBD5E1'],
        ],
    ],
];
$sheet->getStyle("A1:F{$lastRow}")->applyFromArray($borderStyle);

$sheet->getStyle("A2:A{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle("C2:C{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle("D2:D{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

foreach (range('A', 'F') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

$writer = new Xlsx($spreadsheet);
$outputFile = __DIR__ . '/public/danh_sach_thiet_bi_khu_A.xlsx';
$writer->save($outputFile);

echo "Tạo thành công {$outputFile} với tổng cộng " . ($lastRow - 1) . " dòng thiết bị:\n";
echo "- Tivi 32 inch: {$countTV} cái (41 phòng)\n";
echo "- TV Box 4K: {$countBox} cái (41 phòng)\n";
echo "- Remote Tivi: {$countRemote} cái (41 phòng)\n";
echo "- Điện thoại bàn: {$countPhone} cái (41 phòng)\n";
echo "- Wifi Router: {$countWifi} cái (21 phòng có router, 20 phòng dùng ké)\n";
echo "Tổng cộng = " . ($countTV + $countBox + $countRemote + $countPhone + $countWifi) . " thiết bị.\n";

// Cập nhật thêm file tham chiếu cấu hình WiFi: cau_hinh_wifi_khu_A.xlsx
$ssWifi = new Spreadsheet();
$sWifi = $ssWifi->getActiveSheet();
$sWifi->setTitle('Cau_Hinh_WiFi_1_1');

$sWifi->setCellValue('A1', 'Số phòng');
$sWifi->setCellValue('B1', 'Tầng');
$sWifi->setCellValue('C1', 'Chế độ WiFi');
$sWifi->setCellValue('D1', 'Số bộ phát (Router)');
$sWifi->setCellValue('E1', 'Dùng chung với phòng');
$sWifi->setCellValue('F1', 'Ghi chú kết nối');

$sWifi->getStyle('A1:F1')->applyFromArray($headerStyle);
$sWifi->getRowDimension(1)->setRowHeight(28);

$r2Idx = 2;
foreach ($allRooms as $room) {
    $rNum = (string)$room->room_number;
    $sWifi->setCellValue("A{$r2Idx}", $rNum);
    $sWifi->setCellValue("B{$r2Idx}", "Tầng " . $room->floor);
    $sWifi->setCellValue("C{$r2Idx}", $room->wifi_mode === 'dedicated' ? 'Riêng biệt (Có router)' : 'Dùng ké (Shared)');
    $sWifi->setCellValue("D{$r2Idx}", $room->wifi_mode === 'dedicated' ? 1 : 0);
    $hostNum = '';
    if ($room->wifi_mode === 'shared' && $room->shared_wifi_room_id) {
        $hostRoom = Room::find($room->shared_wifi_room_id);
        $hostNum = $hostRoom ? $hostRoom->room_number : '';
    }
    $sWifi->setCellValue("E{$r2Idx}", $hostNum ? "P.{$hostNum}" : '-');
    $sWifi->setCellValue("F{$r2Idx}", $room->shared_wifi_note ?: 'Phòng chính trang bị Router');
    $r2Idx++;
}

$lastR2 = $r2Idx - 1;
$sWifi->getStyle("A1:F{$lastR2}")->applyFromArray($borderStyle);
$sWifi->getStyle("A2:E{$lastR2}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
foreach (range('A', 'F') as $col) {
    $sWifi->getColumnDimension($col)->setAutoSize(true);
}

$writerWifi = new Xlsx($ssWifi);
$outputWifi = __DIR__ . '/public/cau_hinh_wifi_khu_A.xlsx';
$writerWifi->save($outputWifi);
echo "Tạo thành công file tham chiếu cấu hình WiFi: {$outputWifi}\n";
