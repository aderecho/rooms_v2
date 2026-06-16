<?php

namespace App\Http\Requests\Concerns;

trait NormalizesRoomEquipments
{
    protected function prepareForValidation(): void
    {
        $equipments = $this->input('equipments');

        if ($equipments === null || $equipments === '') {
            return;
        }

        if (is_string($equipments)) {
            $trimmed = trim($equipments);

            $decoded = json_decode($trimmed, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $equipments = $decoded;
            } else {
                $equipments = preg_split('/[\r\n,;]+/', $trimmed) ?: [];
            }
        }

        if (!is_array($equipments)) {
            return;
        }

        $normalized = array_values(array_unique(array_filter(array_map(
            fn ($item) => trim((string) $item),
            $equipments
        ))));

        $this->merge([
            'equipments' => $normalized ?: null,
        ]);
    }
}
