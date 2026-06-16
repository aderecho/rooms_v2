<?php

namespace App\Http\Controllers;

use App\Models\Building;
use App\Http\Requests\StoreBuildingRequest;
use App\Http\Requests\UpdateBuildingRequest;
use App\Models\College;
use App\Services\BuildingService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BuildingController extends Controller
{
    public function __construct(
        protected BuildingService $buildingService,
    ) {}
    public function index(Request $request)
    {
        $perPage = 10;
        $search = $request->input('search');
        $buildings = $this->buildingService->getBuildings($perPage, $search);
        $colleges = College::all();

        return Inertia::render('BuildingDashboard', [
            'buildings' => $buildings,
            'colleges' => $colleges,
        ]);
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
