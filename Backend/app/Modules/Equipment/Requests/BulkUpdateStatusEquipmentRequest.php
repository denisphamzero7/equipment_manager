<?php

namespace App\Modules\Equipment\Requests;

use App\Modules\Equipment\Enums\EquipmentStatusEnum;
use Illuminate\Foundation\Http\FormRequest;

class BulkUpdateStatusEquipmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ids' => 'required|array|min:1',
            'ids.*' => 'required|integer|exists:room_equipments,id',
            'status' => 'required|string|'.EquipmentStatusEnum::rule(),
            'note' => 'nullable|string|max:1000',
        ];
    }
}
