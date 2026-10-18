<?php

namespace App\Modules\Equipment\Requests;

use App\Modules\Equipment\Enums\EquipmentStatusEnum;
use Illuminate\Foundation\Http\FormRequest;

class ChangeEquipmentStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => 'required|string|'.EquipmentStatusEnum::rule(),
            'note' => 'nullable|string|max:1000',
        ];
    }
}
