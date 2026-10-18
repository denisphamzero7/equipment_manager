<?php

namespace Database\Factories;

use App\Modules\Motel\Enums\RoomStatusEnum;
use App\Modules\Motel\Models\Room;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Modules\Motel\Models\Room>
 */
class RoomFactory extends Factory
{
    protected $model = Room::class;

    public function definition(): array
    {
        $title = fake()->unique()->sentence(3);

        return [
            'name' => $title,
            'description' => fake()->paragraph(),
            'status' => fake()->randomElement(RoomStatusEnum::values()),
            'organization_id' => null,
            'created_by' => null,
            'updated_by' => null,
        ];
    }

    public function ready(): static
    {
        return $this->state(fn (array $attributes) => ['status' => RoomStatusEnum::Ready->value]);
    }

    public function occupied(): static
    {
        return $this->state(fn (array $attributes) => ['status' => RoomStatusEnum::Occupied->value]);
    }

    public function notReady(): static
    {
        return $this->state(fn (array $attributes) => ['status' => RoomStatusEnum::NotReady->value]);
    }

    public function maintenance(): static
    {
        return $this->state(fn (array $attributes) => ['status' => RoomStatusEnum::Maintenance->value]);
    }

    public function forOrganization(int $organizationId): static
    {
        return $this->state(fn (array $attributes) => ['organization_id' => $organizationId]);
    }

    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
    }
}
