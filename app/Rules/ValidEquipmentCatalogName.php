<?php

namespace App\Rules;

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

        try {
            $exists = app(\App\Services\ImsInventoryCatalog::class)->contains($name);
        } catch (\Throwable $e) {
            $fail('Inventory is temporarily unavailable. Please try again.');

            return;
        }

        if (! $exists) {
            $fail("“{$name}” is not a registered equipment type. Please select a valid item from the suggestions.");
        }
    }
}
