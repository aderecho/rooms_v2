<?php

namespace App\Services;

use App\Models\Equipment;
use App\Models\Room;
use Illuminate\Support\Facades\DB;

class EquipmentService
{
    public function getEquipmentStats()
    {
        return [
            'total_equipment' => Equipment::count(),
            'total_quantity' => Equipment::sum('quantity'),
            'total_value' => Equipment::sum(DB::raw('quantity * COALESCE(purchase_price, 0)')),
            'by_status' => $this->getEquipmentByStatus(),
            'by_location' => $this->getEquipmentByLocation(),
            'recent_activity' => $this->getRecentActivity(),
        ];
    }

    public function transferEquipment($equipmentId, $newRoomId, $notes = null)
    {
        DB::beginTransaction();

        try {
            $equipment = Equipment::findOrFail($equipmentId);
            $oldRoom = $equipment->room;

            // Get the new room to update building/college/department
            $newRoom = Room::with(['building', 'college', 'department'])->findOrFail($newRoomId);

            // Update equipment location
            $equipment->update([
                'room_id' => $newRoomId,
                'building_id' => $newRoom->building_id,
                'college_id' => $newRoom->college_id,
                'department_id' => $newRoom->department_id,
                'notes' => $notes ?: "Transferred from " . ($oldRoom ? $oldRoom->room_name : 'Unknown') . " on " . now()->format('Y-m-d')
            ]);

            DB::commit();

            return [
                'equipment' => $equipment,
                'old_location' => $oldRoom ? $oldRoom->room_name : 'Unknown',
                'new_location' => $newRoom->room_name,
                'transfer_date' => now()
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    private function getEquipmentByStatus()
    {
        return Equipment::select('status', DB::raw('count(*) as count'), DB::raw('sum(quantity) as total_quantity'))
            ->groupBy('status')
            ->get()
            ->mapWithKeys(function ($item) {
                return [$item->status => [
                    'count' => $item->count,
                    'total_quantity' => $item->total_quantity
                ]];
            })->toArray();
    }

    private function getEquipmentByLocation()
    {
        return [
            'by_building' => Equipment::join('buildings', 'equipment.building_id', '=', 'buildings.id')
                ->select('buildings.building_name', DB::raw('count(*) as count'), DB::raw('sum(quantity) as total_quantity'))
                ->groupBy('buildings.id', 'buildings.building_name')
                ->get(),
            'by_room' => Equipment::join('rooms', 'equipment.room_id', '=', 'rooms.id')
                ->select('rooms.room_name', DB::raw('count(*) as count'), DB::raw('sum(quantity) as total_quantity'))
                ->groupBy('rooms.id', 'rooms.room_name')
                ->orderBy('count', 'desc')
                ->limit(10)
                ->get(),
        ];
    }

    private function getRecentActivity()
    {
        return [
            'recent_additions' => Equipment::with(['room', 'building'])
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get(),
            'recent_transfers' => Equipment::with(['room', 'building'])
                ->whereNotNull('notes')
                ->where('notes', 'like', '%Transferred%')
                ->orderBy('updated_at', 'desc')
                ->limit(5)
                ->get(),
        ];
    }
}
