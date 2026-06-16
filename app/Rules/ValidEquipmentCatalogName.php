<?php

namespace App\Rules;

use App\Models\Equipment;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidEquipmentCatalogName implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $name = trim((string) $value);

        if ($name === '') {
            $fail('Equipment name cannot be empty.');
            return;
        }

        $exists = Equipment::query()
            ->whereRaw('LOWER(equipment_name) = ?', [strtolower($name)])
            ->exists();

        if (!$exists) {
            $fail("“{$name}” is not a registered equipment type. Please select a valid item from the suggestions.");
        }
    }
}
