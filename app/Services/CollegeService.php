<?php

namespace App\Services;
use App\Models\College;

class CollegeService
{
    public function getColleges(int $perPage = 10, ?string $search = null)
    {
        return College::with('dean')
            ->orderByDesc('created_at')
            ->when($search, fn($query) => $query->where('college_name', 'like', "%{$search}%"))
            ->paginate($perPage)
            ->withQueryString();
    }
}
