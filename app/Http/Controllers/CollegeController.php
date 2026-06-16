<?php

namespace App\Http\Controllers;

use App\Models\College;
use App\Http\Requests\StoreCollegeRequest;
use App\Http\Requests\UpdateCollegeRequest;
use App\Models\UserAccount;
use App\Services\CollegeService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CollegeController extends Controller
{
    public function __construct(
        protected CollegeService $collegeService,
    ) {}
    public function index(Request $request)
    {
        $perPage = 10;
        $search = $request->input('search');
        $colleges = $this->collegeService->getColleges($perPage, $search);
        $dean = UserAccount::all();

        return Inertia::render('CollegeDashboard', [
            'colleges' => $colleges,
            'dean' => $dean,
        ]);
    }

    public function store(StoreCollegeRequest $request)
    {
        College::create($request->validated());

        return redirect()->back()->with('success', 'College created successfully.');
    }

    public function update(UpdateCollegeRequest $request, College $college)
    {
        $college->update($request->validated());

        return redirect()->back()->with('success', 'College updated successfully.');
    }

    public function destroy(College $college)
    {
        $college->delete();

        return redirect()
            ->back()
            ->with('success', 'College deleted successfully.');
    }
}
