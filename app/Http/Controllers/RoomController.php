<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRoomRequest;
use App\Http\Requests\UpdateRoomRequest;
use App\Http\Resources\RoomListResource;
use App\Http\Resources\RoomResource;
use App\Models\Building;
use App\Models\College;
use App\Models\Department;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\UserAccount;
use App\Services\RoomService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class RoomController extends Controller
{
    public function __construct(
        protected RoomService $roomService,
    ) {}
    public function index(Request $request)
    {
        return redirect('/BuildingDashboard?' . http_build_query([
            'active_tab' => 'rooms',
            'room_search' => $request->input('search'),
            'building_id' => $request->input('building_id'),
        ]));
    }

    public function apiIndex()
    {
        $rooms = $this->roomService->getRoomsForApi();

        return RoomResource::collection($rooms);
    }

    public function apiShow($id)
    {
        $room = $this->roomService->getRoomById($id);

        return new RoomListResource($room);
    }

    public function store(StoreRoomRequest $request)
    {
        $payload = $request->validated();
        $payload['equipments'] = $this->normalizeEquipments($payload['equipments'] ?? null);
        Room::create($payload);

        return redirect()->back()->with('success', 'Room created successfully.');
    }

    public function update(UpdateRoomRequest $request, Room $room)
    {
        $payload = $request->validated();
        $payload['equipments'] = $this->normalizeEquipments($payload['equipments'] ?? null);
        $room->update($payload);

        return redirect()->back()->with('success', 'Room updated successfully.');
    }

    public function destroy(Room $room)
    {
        $room->delete();

        return redirect()
            ->back()
            ->with('success', 'Room deleted successfully.');
    }

    private function normalizeEquipments($equipments): ?array
    {
        return app(\App\Services\ImsInventoryCatalog::class)->snapshots($equipments ?? []);
    }
}
