<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBuildingRequest extends FormRequest
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
            'building_name'   => 'required|string|max:255',
            'address'         => 'required|string|max:500',
            'description'     => 'nullable|string',
            'total_floors'    => 'required|integer|min:0',
            'total_rooms'     => 'required|integer|min:0',
            'has_elevator'    => 'required|in:0,1',
            'has_parking'     => 'required|in:0,1',
            'restroom_count'  => 'required|integer|min:0',
            'ramp_count'      => 'required|integer|min:0',
            'college_id'      => 'required|exists:colleges,id',
        ];
    }
}
