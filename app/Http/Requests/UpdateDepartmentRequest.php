<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $department = $this->route('department'); // Model

        return [
            'department_name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('departments', 'department_name')
                    ->ignore($department->id),
            ],

            'department_code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('departments', 'department_code')
                    ->ignore($department->id),
            ],

            'college_id' => [
                'required',
                'exists:colleges,id',
            ],

            'department_head_id' => [
                'nullable',
                'exists:user_accounts,id',
            ],

            'description' => [
                'nullable',
                'string',
            ],

            'office_location' => [
                'nullable',
                'string',
                'max:255',
            ],

            'contact_email' => [
                'nullable',
                'email',
                'max:255',
            ],

            'contact_phone' => [
                'nullable',
                'string',
                'max:20',
            ],
        ];
    }
}
