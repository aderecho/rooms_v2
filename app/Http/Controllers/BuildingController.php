<?php

namespace App\Http\Controllers;

use App\Models\Building;
use App\Http\Requests\StoreBuildingRequest;
use App\Http\Requests\UpdateBuildingRequest;
use App\Models\College;
use App\Models\Department;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\UserAccount;
use App\Services\BuildingService;
use App\Services\RoomService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BuildingController extends Controller
{
    public function __construct(
        protected BuildingService $buildingService,
        protected RoomService $roomService,
    ) {}
    public function index(Request $request)
    {
        $buildingPerPage = $this->perPage($request->input('building_per_page', 10));
        $roomPerPage = $this->perPage($request->input('room_per_page', 10));
        $buildingSearch = $request->input('building_search', $request->input('search'));
        $roomSearch = $request->input('room_search');
        $buildingId = $request->integer('building_id') ?: null;
        $buildings = $this->buildingService->getBuildings($buildingPerPage, $buildingSearch, 'buildings_page');
        $rooms = $this->roomService->getRooms($roomPerPage, $roomSearch, 'rooms_page', $buildingId);
        $colleges = College::all();
        $allBuildings = Building::orderBy('building_name')->get();
        $roomTypes = RoomType::all();
        $departments = Department::all();
        $users = UserAccount::all();

        return Inertia::render('BuildingDashboard', [
            'buildings' => $buildings,
            'rooms' => $rooms,
            'colleges' => $colleges,
            'allBuildings' => $allBuildings,
            'departments' => $departments,
            'roomTypes' => $roomTypes,
            'users' => $users,
            'filters' => [
                'active_tab' => $request->input('active_tab', 'buildings'),
                'building_search' => $buildingSearch,
                'room_search' => $roomSearch,
                'building_id' => $buildingId,
                'building_per_page' => $buildingPerPage,
                'room_per_page' => $roomPerPage,
            ],
            'stats' => [
                'buildings' => Building::count(),
                'rooms' => Room::count(),
                'filtered_rooms' => $rooms->total(),
            ],
        ]);
    }

    private function perPage(mixed $value): int
    {
        $perPage = (int) $value;

        return in_array($perPage, [5, 10, 20, 50], true) ? $perPage : 10;
    }

    public function store(StoreBuildingRequest $request)
    {
        Building::create($request->validated());

        return redirect()->back()->with('success', 'Building created successfully.');
    }

    public function update(UpdateBuildingRequest $request, Building $building)
    {
        $building->update($request->validated());

        return redirect()->back()->with('success', 'Building updated successfully.');
    }

    public function destroy(Building $building)
    {
        $building->delete();

        return redirect()
            ->back()
            ->with('success', 'Building deleted successfully.');
    }
}
