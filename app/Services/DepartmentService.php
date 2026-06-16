<?php

namespace App\Services;

use App\Models\Department;
use Illuminate\Support\Facades\DB;

class DepartmentService
{
   public function getDepartment(int $perPage = 10, ?string $search = null)
    {
        return Department::with('college')
            ->orderByDesc('created_at')
            ->when($search, fn($query) => $query->where('department_name', 'like', "%{$search}%"))
            ->paginate($perPage)
            ->withQueryString();
    }
}
