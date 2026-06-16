<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'department_name' => [
                'required',
                'string',
                'max:255',
                'unique:departments,department_name',
            ],

            'department_code' => [
                'required',
                'string',
                'max:50',
                'unique:departments,department_code',
            ],

            'college_id' => [
                'required',
                'exists:colleges,id',
            ],

            'department_head_id' => [
                'nullable',
                'exists:user_accounts,id', // ⚠️ adjust if needed
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
