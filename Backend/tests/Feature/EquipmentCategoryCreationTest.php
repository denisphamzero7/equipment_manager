<?php

namespace Tests\Feature;

use App\Modules\Core\Models\User;
use App\Modules\Equipment\Models\EquipmentCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EquipmentCategoryCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_create_an_equipment_category(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/equipments/categories', [
            'name' => '  Điện thoại bàn  ',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.name', 'Điện thoại bàn')
            ->assertJsonPath('data.code', 'dien-thoai-ban');

        $this->assertDatabaseHas('equipment_categories', [
            'name' => 'Điện thoại bàn',
            'code' => 'dien-thoai-ban',
        ]);
    }

    public function test_duplicate_equipment_category_name_is_rejected(): void
    {
        EquipmentCategory::create([
            'name' => 'Điện thoại bàn',
            'code' => 'dien-thoai-ban',
            'sort_order' => 1,
        ]);

        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/equipments/categories', ['name' => 'Điện thoại bàn'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');
    }
}
