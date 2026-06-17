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
        $perPage = 10;
        $search = $request->input('search');
        $rooms = $this->roomService->getRooms($perPage, $search);
        $buildings = Building::all();
        $colleges = College::all();
        $roomTypes = RoomType::all();
        $departments = Department::all();
        $users = UserAccount::all();

        return Inertia::render('Rooms', [
            'rooms' => $rooms,
            'buildings' => $buildings,
            'colleges' => $colleges,
            'departments' => $departments,
            'roomTypes' => $roomTypes,
            'users' => $users,
        ]);
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
        if (is_array($equipments)) {
            $list = array_values(array_filter(array_map(
                fn($item) => trim((string) $item),
                $equipments
            )));
            return $list ?: null;
        }

        if (!is_string($equipments) || trim($equipments) === '') {
            return null;
        }

        $trimmed = trim($equipments);

        // Accept JSON array input if provided.
        $decoded = json_decode($trimmed, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $list = array_values(array_filter(array_map(
                fn($item) => trim((string) $item),
                $decoded
            )));
            return $list ?: null;
        }

        // Fallback to comma/newline/semicolon separated text.
        $list = preg_split('/[\r\n,;]+/', $trimmed) ?: [];
        $list = array_values(array_filter(array_map(
            fn($item) => trim((string) $item),
            $list
        )));

        return $list ?: null;
    }
}
