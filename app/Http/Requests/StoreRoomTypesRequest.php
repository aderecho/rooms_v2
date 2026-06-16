<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRoomTypesRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'room_type_name' => 'required|string|max:255|unique:room_types,room_type_name',
            'slug' => 'required|string|max:50|unique:room_types,slug',
            'description' => 'nullable|string',
            'default_capacity' => 'required|integer|min:1',
            'features' => 'nullable|string',
        ];
    }
}
