<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\NormalizesRoomEquipments;
use App\Rules\ValidEquipmentCatalogName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRoomRequest extends FormRequest
{
    use NormalizesRoomEquipments;
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
        $roomId = $this->route('room');
        
        return [
            'room_name'         => 'required|string|max:255',
            'room_code' => [
                'required',
                'string',
                'max:255',
                Rule::unique('rooms', 'room_code')->ignore($roomId),
            ],
            'building_id'       => 'required|exists:buildings,id',
            'college_id'        => 'required|exists:colleges,id',
            'department_id'     => 'nullable|exists:departments,id',
            'room_type_id'      => 'required|exists:room_types,id',
            'assigned_user_id'  => 'nullable|exists:user_accounts,id',
            'floor_number'      => 'required|integer|min:0',
            'location'          => 'nullable|string|max:255',
            'capacity'          => 'required|integer|min:1',
            'description'       => 'nullable|string',
            'equipments'   => 'nullable|array',
            'equipments.*' => ['required', 'string', 'max:100', new ValidEquipmentCatalogName()],
        ];
    }
}
