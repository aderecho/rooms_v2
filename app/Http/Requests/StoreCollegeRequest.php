<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCollegeRequest extends FormRequest
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
            'college_name'   => 'required|string|max:255',
            'college_code'   => 'required|string|max:50|unique:colleges,college_code',
            'description'   => 'nullable|string',
            'dean_id'        => 'nullable|exists:user_accounts,id',
            'contact_email' => 'required|email|max:255',
            'contact_phone' => 'nullable|string|max:50',
        ];
    }
}
