<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ImportScheduleRequest;
use App\Services\ScheduleImportService;

class ScheduleImportController extends Controller
{
    //
    /**
     * Import schedules from external systems.
     */
    public function import(
        ImportScheduleRequest $request,
        ScheduleImportService $service
    )
    {
        $result = $service->import(
            $request->validated()
        );

        return response()->json($result);
    }

    public function rollback(int $id, ScheduleImportService $service
    )
    {
        $result = $service->rollbackImport($id);

        return response()->json($result);
    }
}
