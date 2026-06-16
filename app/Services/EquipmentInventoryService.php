<?php

namespace App\Services;

use App\Models\Equipment;
use Illuminate\Support\Facades\DB;

/**
 * Single source of truth for equipment stock counts shown in the UI.
 *
 * - Global catalog (room equipment picker): COUNT(*) of inventory rows per name.
 * - Room booking: SUM(quantity) for rows assigned to that room; otherwise global COUNT(*).
 */
class EquipmentInventoryService
{
    /**
     * @return array<string, int> lowercase equipment name => count
     */
    public function globalInventoryCountsByName(): array
    {
        return Equipment::query()
            ->select('equipment_name', DB::raw('COUNT(*) as total'))
            ->groupBy('equipment_name')
            ->get()
            ->mapWithKeys(fn ($row) => [
                strtolower(trim($row->equipment_name)) => (int) $row->total,
            ])
            ->all();
    }

    /**
     * @return array<string, int> lowercase equipment name => sum of quantity
     */
    public function roomQuantityTotalsByName(int $roomId): array
    {
        return Equipment::query()
            ->where('room_id', $roomId)
            ->select('equipment_name', DB::raw('SUM(quantity) as total'))
            ->groupBy('equipment_name')
            ->get()
            ->mapWithKeys(fn ($row) => [
                strtolower(trim($row->equipment_name)) => (int) $row->total,
            ])
            ->all();
    }

    public function quantityForRoomEquipment(?int $roomId, string $equipmentName): int
    {
        $key = strtolower(trim($equipmentName));
        if ($key === '') {
            return 0;
        }

        if ($roomId) {
            $roomTotals = $this->roomQuantityTotalsByName($roomId);
            if (array_key_exists($key, $roomTotals)) {
                return $roomTotals[$key];
            }
        }

        return $this->globalInventoryCountsByName()[$key] ?? 0;
    }

    /**
     * @param  list<string>  $equipmentNames
     * @return list<array{name: string, quantity: int}>
     */
    public function equipmentDetailsForRoom(?int $roomId, array $equipmentNames): array
    {
        $details = [];

        foreach ($equipmentNames as $name) {
            $name = trim((string) $name);
            if ($name === '') {
                continue;
            }

            $details[] = [
                'name' => $name,
                'quantity' => $this->quantityForRoomEquipment($roomId, $name),
            ];
        }

        return $details;
    }
}
