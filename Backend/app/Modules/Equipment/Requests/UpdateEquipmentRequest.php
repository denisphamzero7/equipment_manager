<?php

namespace App\Modules\Equipment\Requests;

use App\Modules\Equipment\Enums\EquipmentStatusEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEquipmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('id') ?? $this->route('equipment');

        return [
            'equipment_model_id' => 'sometimes|required|integer|exists:equipment_models,id',
            'serial_number' => [
                'sometimes',
                'required',
                'string',
                'max:100',
                Rule::unique('room_equipments', 'serial_number')->ignore($id),
            ],
            'room_id' => 'nullable|integer|exists:rooms,id',
            'status' => 'sometimes|required|string|'.EquipmentStatusEnum::rule(),
            'position_note' => 'nullable|string|max:255',
            'note' => 'nullable|string|max:1000',
            'installed_at' => 'nullable|date',
            'photo' => 'nullable|image|max:10240',
        ];
    }
}
