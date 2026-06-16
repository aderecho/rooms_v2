<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TermRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'term_name' => ['required', 'string', 'max:100'],
            'term_code' => ['required', 'string', 'max:50'],
            'term_type' => ['required', 'in:semester,trimester,quarter,summer,special'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'enrollment_start' => ['nullable', 'date', 'before:classes_start'],
            'enrollment_end' => ['nullable', 'date', 'after:enrollment_start', 'before:classes_end'],
            'classes_start' => ['required', 'date', 'after_or_equal:start_date'],
            'classes_end' => ['required', 'date', 'before_or_equal:end_date'],
            'examination_start' => ['nullable', 'date', 'after:classes_end'],
            'examination_end' => ['nullable', 'date', 'after:examination_start', 'before_or_equal:end_date'],
            'is_current' => ['boolean'],
            'status' => ['required', 'in:upcoming,active,completed,cancelled'],
            'academic_year' => ['required', 'string', 'max:20'],
            'notes' => ['nullable', 'string'],
        ];

        // For update, ignore current record
        if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
            $term = $this->route('term');
            $rules['term_name'][] = Rule::unique('terms')->ignore($term->id);
            $rules['term_code'][] = Rule::unique('terms')->ignore($term->id);
        } else {
            $rules['term_name'][] = 'unique:terms';
            $rules['term_code'][] = 'unique:terms';
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'term_name.unique' => 'This term name already exists.',
            'term_code.unique' => 'This term code already exists.',
            'end_date.after' => 'End date must be after start date.',
            'classes_end.before_or_equal' => 'Classes end date must be before or equal to term end date.',
        ];
    }
}
