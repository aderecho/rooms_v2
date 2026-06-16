<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRoomTypesRequest;
use App\Http\Requests\UpdateRoomTypesRequest;
use App\Models\RoomType;
use App\Services\RoomTypesService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class RoomTypeController extends Controller
{
    public function __construct(
        protected RoomTypesService $roomTypesService,
    ) {}
    public function index(Request $request)
    {
        $perPage = 10;
        $search = $request->input('search');
        $roomtypes = $this->roomTypesService->getRoomTypes($perPage, $search);

        return Inertia::render('RoomTypes', [
            'roomtypes' => $roomtypes,
        ]);
    }

    public function store(StoreRoomTypesRequest $request)
    {
        RoomType::create($request->validated());

        return redirect()->back()->with('success', 'Room type created successfully.');
    }

    public function update(UpdateRoomTypesRequest $request, RoomType $roomtype)
    {
        $roomtype->update($request->validated());

        return redirect()->back()->with('success', 'Room type updated successfully.');
    }

    public function destroy(RoomType $roomtype)
    {
        $roomtype->delete();

        return redirect()
            ->back()
            ->with('success', 'Room Type deleted successfully.');
    }
}
