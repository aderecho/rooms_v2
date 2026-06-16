<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRoomTypesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $roomTypeId = $this->route('roomtype'); 

        return [
            'room_type_name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('room_types', 'room_type_name')->ignore($roomTypeId),
            ],

            'slug' => [
                'required',
                'string',
                'max:50',
                Rule::unique('room_types', 'slug')->ignore($roomTypeId),
            ],

            'description' => 'nullable|string',

            'default_capacity' => 'required|integer|min:1',

            'features' => 'nullable|string',
        ];
    }
}
