<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImportScheduleRequest extends FormRequest
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
            //
            'schedules' => 'required|array|min:1',

        'schedules.*.class_id' => 'required|integer',

        'schedules.*.room_id' => 'required|integer',

        'schedules.*.event_title' => 'required|string|max:255',

        'schedules.*.event_type' => 'required|in:class,meeting,event,other',

        'schedules.*.course_code' => 'nullable|string|max:255',

        'schedules.*.course_name' => 'nullable|string|max:255',

        'schedules.*.section' => 'nullable|string|max:255',

        'schedules.*.faculty_name' => 'nullable|string|max:255',

        'schedules.*.faculty_id' => 'nullable|integer',

        'schedules.*.date' => 'required|date',

        'schedules.*.start_time' => 'required|date_format:H:i:s',

        'schedules.*.end_time' => 'required|date_format:H:i:s|after:schedules.*.start_time',

        'schedules.*.day_of_week' => 'required|string',

        'schedules.*.number_of_participants' => 'nullable|integer',

        'schedules.*.requester_id' => 'nullable|integer',

        'schedules.*.requester_name' => 'nullable|string|max:255',

        'schedules.*.description' => 'nullable|string',

        'schedules.*.agenda' => 'nullable|string|max:255',

        'schedules.*.organizer' => 'nullable|string|max:255',

        'schedules.*.equipment_needed' => 'nullable|array',

        'schedules.*.additional_requirements' => 'nullable|array',

        'schedules.*.status' => 'nullable|in:pending,in_progress,approved,completed,rejected,cancelled',

        'schedules.*.is_recurring' => 'boolean',

        'schedules.*.recurrence_pattern' => 'nullable|array',

        'schedules.*.term_id' => 'nullable|integer',

        'schedules.*.cfic_id' => 'nullable|string|max:100',

        ];
    }
}
