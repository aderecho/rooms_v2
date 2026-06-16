<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDepartmentRequest;
use App\Http\Requests\UpdateDepartmentRequest;
use App\Models\Building;
use App\Models\Department;
use App\Models\College;
use App\Models\UserAccount;
use App\Services\DepartmentService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DepartmentController extends Controller
{
    public function __construct(
        protected DepartmentService $DepartmentService,
    ) {}
    public function index(Request $request)
    {
        $perPage = 10;
        $search = $request->input('search');
        $departments = $this->DepartmentService->getDepartment($perPage, $search);
        $colleges = College::all();
        $users = UserAccount::all();

        return Inertia::render('Departments', [
            'departments' => $departments,
            'colleges' => $colleges,
            'users' => $users,
        ]);
    }

    public function store(StoreDepartmentRequest $request)
    {
        Department::create($request->validated());

        return redirect()->back()->with('success', 'Department created successfully.');
    }

    public function update(UpdateDepartmentRequest $request, Department $department)
    {
        $department->update($request->validated());

        return redirect()->back()->with('success', 'Department updated successfully.');
    }

    public function destroy(Building $building)
    {
        $building->delete();

        return redirect()
            ->back()
            ->with('success', 'Building deleted successfully.');
    }
}
