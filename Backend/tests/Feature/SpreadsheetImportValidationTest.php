<?php

namespace Tests\Feature;

use App\Modules\Core\Models\Organization;
use App\Modules\Core\Models\User;
use App\Modules\Equipment\Imports\EquipmentImport;
use App\Modules\Equipment\Models\EquipmentCategory;
use App\Modules\Equipment\Models\EquipmentModel;
use App\Modules\Motel\Imports\RoomImport;
use App\Modules\Motel\Models\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class SpreadsheetImportValidationTest extends TestCase
{
    use RefreshDatabase;

    private function createXlsxUploadedFile(array $rows, string $filename = 'test.xlsx'): UploadedFile
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();

        foreach ($rows as $rowIndex => $row) {
            foreach (array_values($row) as $colIndex => $value) {
                $sheet->setCellValue([$colIndex + 1, $rowIndex + 1], $value);
            }
        }

        $tempPath = tempnam(sys_get_temp_dir(), 'xlsx_test_').'.xlsx';
        (new Xlsx($spreadsheet))->save($tempPath);

        return new UploadedFile(
            $tempPath,
            $filename,
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );
    }

    private function actingUserWithPermissions(array $permissions): array
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $org = Organization::create([
            'name' => 'Khách sạn Test',
            'slug' => 'khach-san-test',
            'status' => 'active',
        ]);

        $user = User::factory()->create();

        setPermissionsTeamId($org->id);
        foreach ($permissions as $permissionName) {
            $permission = Permission::firstOrCreate([
                'name' => $permissionName,
                'guard_name' => 'web',
            ]);
            $user->givePermissionTo($permission);
        }

        return [$user, $org];
    }

    public function test_room_import_rejects_equipment_template_immediately(): void
    {
        [$user, $org] = $this->actingUserWithPermissions(['motel.import']);

        $file = $this->createXlsxUploadedFile([
            ['Mã Serial', 'Mẫu thiết bị', 'Số phòng', 'Trạng thái'],
            ['TEL-101', 'Điện thoại bàn', '101', 'Hoạt động tốt'],
        ], 'thiet-bi-dien-thoai-ban.xlsx');

        $response = $this->actingAs($user, 'sanctum')
            ->withHeader('X-Organization-Id', (string) $org->id)
            ->postJson('/api/motel/import', ['file' => $file]);

        $response->assertUnprocessable()
            ->assertJsonPath('code', 'VALIDATION_ERROR')
            ->assertJsonValidationErrors('file');

        $this->assertStringContainsString('Thiết bị', (string) $response->json('message'));
        $this->assertDatabaseCount('rooms', 0);
    }

    public function test_room_import_rejects_empty_and_corrupted_files_immediately(): void
    {
        [$user, $org] = $this->actingUserWithPermissions(['motel.import']);

        $headerOnlyFile = $this->createXlsxUploadedFile([
            ['Số phòng', 'Tên phòng', 'Khu vực', 'Tầng'],
        ], 'rooms-template.xlsx');

        $this->actingAs($user, 'sanctum')
            ->withHeader('X-Organization-Id', (string) $org->id)
            ->postJson('/api/motel/import', ['file' => $headerOnlyFile])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');

        $corruptPath = tempnam(sys_get_temp_dir(), 'corrupt_').'.xlsx';
        file_put_contents($corruptPath, 'not a valid zip xlsx content');
        $corruptFile = new UploadedFile(
            $corruptPath,
            'corrupt.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );

        $this->actingAs($user, 'sanctum')
            ->withHeader('X-Organization-Id', (string) $org->id)
            ->postJson('/api/motel/import', ['file' => $corruptFile])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');
    }

    public function test_room_import_returns_422_with_row_errors_when_all_rows_fail_validation(): void
    {
        [$user, $org] = $this->actingUserWithPermissions(['motel.import']);

        $file = $this->createXlsxUploadedFile([
            ['Số phòng', 'Trạng thái', 'Chế độ WiFi'],
            ['101', 'invalid_status', 'dedicated'],
            ['', 'ready', 'invalid_wifi'],
        ], 'invalid-rooms.xlsx');

        $response = $this->actingAs($user, 'sanctum')
            ->withHeader('X-Organization-Id', (string) $org->id)
            ->postJson('/api/motel/import', ['file' => $file]);

        $response->assertUnprocessable()
            ->assertJsonPath('code', 'IMPORT_VALIDATION_ERROR')
            ->assertJsonPath('data.success_count', 0)
            ->assertJsonPath('data.failed_count', 2);

        $this->assertCount(2, $response->json('data.errors'));
        $this->assertDatabaseCount('rooms', 0);
    }

    public function test_room_import_imports_valid_rows_and_skips_trailing_empty_rows(): void
    {
        [$user, $org] = $this->actingUserWithPermissions(['motel.import']);

        $file = $this->createXlsxUploadedFile([
            ['Số phòng', 'Tên phòng', 'Khu vực', 'Tầng', 'Trạng thái'],
            ['101', '', 'Block A', 1, ''],
            ['', '', '', '', ''],
            ['102', 'Phòng VIP 102', 'Block A', 1, 'Sẵn sàng đón khách'],
        ], 'valid-rooms.xlsx');

        $response = $this->actingAs($user, 'sanctum')
            ->withHeader('X-Organization-Id', (string) $org->id)
            ->postJson('/api/motel/import', ['file' => $file]);

        $response->assertOk()
            ->assertJsonPath('data.success_count', 2)
            ->assertJsonPath('data.failed_count', 0);

        $this->assertDatabaseHas('rooms', [
            'organization_id' => $org->id,
            'room_number' => '101',
            'name' => 'Phòng 101',
            'status' => 'ready',
            'bedroom_count' => 1,
            'living_room_count' => 0,
        ]);
        $this->assertDatabaseHas('rooms', [
            'organization_id' => $org->id,
            'room_number' => '102',
            'name' => 'Phòng VIP 102',
        ]);
    }

    public function test_equipment_import_rejects_room_template_and_imports_valid_rows(): void
    {
        [$user, $org] = $this->actingUserWithPermissions(['equipment.import']);

        $roomFile = $this->createXlsxUploadedFile([
            ['Số phòng', 'Tên phòng', 'Khu vực', 'Tầng'],
            ['101', 'Phòng 101', 'Block A', 1],
        ], 'rooms.xlsx');

        $this->actingAs($user, 'sanctum')
            ->withHeader('X-Organization-Id', (string) $org->id)
            ->postJson('/api/equipments/import', ['file' => $roomFile])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');

        $category = EquipmentCategory::create([
            'name' => 'Điện thoại',
            'code' => 'dien-thoai',
            'sort_order' => 1,
        ]);
        EquipmentModel::create([
            'equipment_category_id' => $category->id,
            'name' => 'Điện thoại bàn Panasonic',
            'organization_id' => $org->id,
        ]);
        Room::create([
            'room_number' => '101',
            'name' => 'Phòng 101',
            'organization_id' => $org->id,
            'status' => 'ready',
        ]);

        $equipmentFile = $this->createXlsxUploadedFile([
            ['Mã Serial', 'Mẫu thiết bị', 'Số phòng', 'Trạng thái', 'Ghi chú tình trạng'],
            ['TEL-101', 'Điện thoại bàn Panasonic', '101', 'Hoạt động tốt', 'Mới lắp'],
        ], 'thiet-bi-dien-thoai-ban.xlsx');

        $this->actingAs($user, 'sanctum')
            ->withHeader('X-Organization-Id', (string) $org->id)
            ->postJson('/api/equipments/import', ['file' => $equipmentFile])
            ->assertOk()
            ->assertJsonPath('data.success_count', 1)
            ->assertJsonPath('data.failed_count', 0);

        $this->assertDatabaseHas('room_equipments', [
            'serial_number' => 'TEL-101',
            'organization_id' => $org->id,
            'note' => 'Mới lắp',
        ]);
    }

    public function test_direct_import_class_throws_validation_exception_for_wrong_columns(): void
    {
        $this->expectException(ValidationException::class);

        $import = new RoomImport(1);
        Excel::import($import, $this->createXlsxUploadedFile([
            ['Cột A', 'Cột B'],
            ['Giá trị 1', 'Giá trị 2'],
        ]));
    }
}
