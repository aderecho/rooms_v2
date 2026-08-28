<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RejectReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $reservationRequest = $this->route('reservationRequest');

        return $reservationRequest
            && (bool) $this->user()?->can('reject', $reservationRequest);
    }

    public function rules(): array
    {
        return [
            'admin_response' => ['required', 'string', 'min:3', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'admin_response.required' => 'A rejection message is required.',
        ];
    }
}
