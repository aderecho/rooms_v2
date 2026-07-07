<?php

namespace App\Services;

use App\Models\Building;

class BuildingService
{
   public function getBuildings(int $perPage = 10, ?string $search = null, string $pageName = 'page')
    {
        return Building::with('college')
            ->orderByDesc('created_at')
            ->when($search, fn($query) => $query->where('building_name', 'like', "%{$search}%"))
            ->paginate($perPage, ['*'], $pageName)
            ->withQueryString();
    }
}
