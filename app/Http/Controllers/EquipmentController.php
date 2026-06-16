<?php

namespace App\Http\Controllers;

use App\Models\Room;
use Inertia\Inertia;
use App\Models\College;
use App\Models\Building;
use App\Models\Equipment;
use App\Models\Department;
use App\Models\UserAccount;
use App\Services\EquipmentInventoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class EquipmentController extends Controller 
{
    /**
     * Display the equipment management page
     */
    public function index(Request $request)
    {
        // Get all necessary data for dropdowns
        $rooms = Room::select('id', 'room_name', 'room_code')->get();
        $buildings = Building::select('id', 'building_name')->get();
        $colleges = College::select('id', 'college_name')->get();
        $departments = Department::select('id', 'department_name', 'college_id')->get();
        $users = UserAccount::whereIn('user_type', ['faculty', 'staff', 'admin'])
            ->select('id', 'first_name', 'last_name', 'username', 'user_type')
            ->get();

        return Inertia::render('equipment', [
            'rooms' => $rooms,
            'buildings' => $buildings,
            'colleges' => $colleges,
            'departments' => $departments,
            'users' => $users,
        ]);
    }

    /**
     * Get all equipment with filters and pagination
     */
    public function getAll(Request $request)
    {
        try {
            $search = $request->query('search', '');
            $status = $request->query('status', '');
            $college_id = $request->query('college_id', '');
            $department_id = $request->query('department_id', '');
            $building_id = $request->query('building_id', '');
            $assigned_user_id = $request->query('assigned_user_id', '');
            $perPage = $request->query('per_page', 10);
            $sortBy = $request->query('sort_by', 'equipment_name');
            $sortOrder = $request->query('sort_order', 'asc');

            $query = Equipment::with([
                'room:id,room_name,room_code',
                'building:id,building_name',
                'college:id,college_name',
                'department:id,department_name',
                'assignedUser:id,first_name,last_name,username'
            ]);

            // Apply filters
            if (!empty($search)) {
                $query->where(function($q) use ($search) {
                    $q->where('equipment_name', 'like', "%{$search}%")
                      ->orWhere('inventory_id', 'like', "%{$search}%")
                      ->orWhere('property_id', 'like', "%{$search}%")
                      ->orWhere('brand', 'like', "%{$search}%")
                      ->orWhere('model', 'like', "%{$search}%")
                      ->orWhere('serial_number', 'like', "%{$search}%");
                });
            }

            if (!empty($status)) {
                $query->where('status', $status);
            }

            if (!empty($college_id)) {
                $query->where('college_id', $college_id);
            }

            if (!empty($department_id)) {
                $query->where('department_id', $department_id);
            }

            if (!empty($building_id)) {
                $query->where('building_id', $building_id);
            }

            if (!empty($assigned_user_id)) {
                $query->where('assigned_user_id', $assigned_user_id);
            }

            // Apply sorting
            $query->orderBy($sortBy, $sortOrder);

            $equipment = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $equipment->items(),
                'current_page' => $equipment->currentPage(),
                'total' => $equipment->total(),
                'per_page' => $equipment->perPage(),
                'last_page' => $equipment->lastPage(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch equipment data.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get equipment statistics for charts (respects search/status filters).
     */
    public function getStats(Request $request)
    {
        try {
            $query = $this->equipmentStatsQuery($request);

            $total = (clone $query)->count();
            $available = (clone $query)->where('status', 'available')->count();
            $inUse = (clone $query)->where('status', 'in_use')->count();
            $maintenance = (clone $query)->where('status', 'maintenance')->count();
            $damaged = (clone $query)->where('status', 'damaged')->count();
            $retired = (clone $query)->where('status', 'retired')->count();

            $totalValue = (clone $query)->sum('purchase_price');
            $avgValue = $total > 0 ? $totalValue / $total : 0;

            $statusStats = (clone $query)
                ->select('status', DB::raw('COUNT(*) as count'))
                ->groupBy('status')
                ->get()
                ->map(function ($item) {
                    return [
                        'status' => ucfirst(str_replace('_', ' ', $item->status)),
                        'count' => $item->count,
                    ];
                });

            $personStats = (clone $query)
                ->select('assigned_user_id', DB::raw('COUNT(*) as equipment_count'))
                ->groupBy('assigned_user_id')
                ->orderByDesc('equipment_count')
                ->get()
                ->map(function ($item) {
                    if (!$item->assigned_user_id) {
                        return [
                            'name' => 'Unassigned',
                            'equipmentCount' => (int) $item->equipment_count,
                        ];
                    }

                    $user = UserAccount::select('id', 'first_name', 'last_name')
                        ->find($item->assigned_user_id);

                    return [
                        'name' => $user
                            ? trim($user->first_name . ' ' . $user->last_name)
                            : 'Unknown User',
                        'equipmentCount' => (int) $item->equipment_count,
                    ];
                })
                ->values();

            $buildingStats = (clone $query)
                ->select('building_id', DB::raw('COUNT(*) as equipment_count'))
                ->groupBy('building_id')
                ->orderByDesc('equipment_count')
                ->get()
                ->map(function ($item) {
                    if (!$item->building_id) {
                        return [
                            'building' => 'Unassigned Building',
                            'equipmentCount' => (int) $item->equipment_count,
                        ];
                    }

                    $building = Building::select('id', 'building_name')->find($item->building_id);

                    return [
                        'building' => $building?->building_name ?? 'Unknown Building',
                        'equipmentCount' => (int) $item->equipment_count,
                    ];
                })
                ->values();

            $collegeStats = (clone $query)
                ->select('college_id', DB::raw('COUNT(*) as equipment_count'))
                ->groupBy('college_id')
                ->orderByDesc('equipment_count')
                ->get()
                ->map(function ($item) {
                    if (!$item->college_id) {
                        return [
                            'college' => 'Unassigned College',
                            'equipmentCount' => (int) $item->equipment_count,
                        ];
                    }

                    $college = College::select('id', 'college_name')->find($item->college_id);

                    return [
                        'college' => $college?->college_name ?? 'Unknown College',
                        'equipmentCount' => (int) $item->equipment_count,
                    ];
                })
                ->values();

            $recentActivities = (clone $query)
                ->with(['assignedUser:id,first_name,last_name'])
                ->orderBy('updated_at', 'desc')
                ->limit(10)
                ->get()
                ->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'equipment_name' => $item->equipment_name,
                        'inventory_id' => $item->inventory_id,
                        'status' => $item->status,
                        'updated_by' => $item->assignedUser
                            ? $item->assignedUser->first_name . ' ' . $item->assignedUser->last_name
                            : 'System',
                        'updated_at' => $item->updated_at->format('M d, Y H:i'),
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => [
                    'total' => $total,
                    'available' => $available,
                    'in_use' => $inUse,
                    'maintenance' => $maintenance,
                    'damaged' => $damaged,
                    'retired' => $retired,
                    'total_value' => $totalValue,
                    'average_value' => round($avgValue, 2),
                    'status_stats' => $statusStats,
                    'person_stats' => $personStats,
                    'building_stats' => $buildingStats,
                    'college_stats' => $collegeStats,
                    'recent_activities' => $recentActivities,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch equipment statistics.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Base query for equipment stats, aligned with dashboard filters.
     */
    private function equipmentStatsQuery(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $status = trim((string) $request->query('status', ''));

        $query = Equipment::query();

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('equipment_name', 'like', "%{$search}%")
                    ->orWhere('inventory_id', 'like', "%{$search}%")
                    ->orWhere('property_id', 'like', "%{$search}%")
                    ->orWhere('brand', 'like', "%{$search}%")
                    ->orWhere('model', 'like', "%{$search}%")
                    ->orWhere('serial_number', 'like', "%{$search}%")
                    ->orWhereHas('assignedUser', function ($q) use ($search) {
                        $q->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('username', 'like', "%{$search}%");
                    })
                    ->orWhereHas('building', function ($q) use ($search) {
                        $q->where('building_name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('college', function ($q) use ($search) {
                        $q->where('college_name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('room', function ($q) use ($search) {
                        $q->where('room_code', 'like', "%{$search}%")
                            ->orWhere('room_name', 'like', "%{$search}%");
                    });
            });
        }

        if ($status !== '') {
            $query->where('status', $status);
        }

        return $query;
    }

    /**
     * Store a newly created equipment
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'equipment_name' => 'required|string|max:100',
                'inventory_id' => 'required|string|max:50|unique:equipment',
                'property_id' => 'nullable|string|max:50|unique:equipment',
                'description' => 'nullable|string',
                'quantity' => 'required|integer|min:1',
                'room_id' => 'nullable|exists:rooms,id',
                'building_id' => 'nullable|exists:buildings,id',
                'college_id' => 'nullable|exists:colleges,id',
                'department_id' => 'nullable|exists:departments,id',
                'cfic_id' => 'nullable|string|max:100',
                'status' => 'required|in:available,in_use,maintenance,damaged,retired',
                'brand' => 'nullable|string|max:100',
                'model' => 'nullable|string|max:100',
                'serial_number' => 'nullable|string|max:100',
                'purchase_date' => 'nullable|date',
                'purchase_price' => 'nullable|numeric|min:0',
                'assigned_user_id' => 'nullable|exists:user_accounts,id',
                'specifications' => 'nullable|array',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $equipment = Equipment::create($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Equipment created successfully.',
                'data' => $equipment->load(['room', 'building', 'college', 'department', 'assignedUser'])
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create equipment.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified equipment
     */
    public function update(Request $request, Equipment $equipment)
    {
        try {
            $validator = Validator::make($request->all(), [
                'equipment_name' => 'sometimes|required|string|max:100',
                'inventory_id' => 'sometimes|required|string|max:50|unique:equipment,inventory_id,' . $equipment->id,
                'property_id' => 'nullable|string|max:50|unique:equipment,property_id,' . $equipment->id,
                'description' => 'nullable|string',
                'quantity' => 'sometimes|required|integer|min:1',
                'room_id' => 'nullable|exists:rooms,id',
                'building_id' => 'nullable|exists:buildings,id',
                'college_id' => 'nullable|exists:colleges,id',
                'department_id' => 'nullable|exists:departments,id',
                'cfic_id' => 'nullable|string|max:100',
                'status' => 'sometimes|required|in:available,in_use,maintenance,damaged,retired',
                'brand' => 'nullable|string|max:100',
                'model' => 'nullable|string|max:100',
                'serial_number' => 'nullable|string|max:100',
                'purchase_date' => 'nullable|date',
                'purchase_price' => 'nullable|numeric|min:0',
                'assigned_user_id' => 'nullable|exists:user_accounts,id',
                'specifications' => 'nullable|array',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $equipment->update($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Equipment updated successfully.',
                'data' => $equipment->load(['room', 'building', 'college', 'department', 'assignedUser'])
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update equipment.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified equipment
     */
    public function destroy(Equipment $equipment)
    {
        try {
            $equipment->delete();

            return response()->json([
                'success' => true,
                'message' => 'Equipment deleted successfully.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete equipment.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Transfer equipment to another user/location
     */
    public function transfer(Request $request, $id)
    {
        try {
            $equipment = Equipment::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'assigned_user_id' => 'nullable|exists:user_accounts,id',
                'room_id' => 'nullable|exists:rooms,id',
                'building_id' => 'nullable|exists:buildings,id',
                'status' => 'sometimes|in:available,in_use,maintenance,damaged,retired',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $equipment->update($request->only([
                'assigned_user_id', 'room_id', 'building_id', 'status'
            ]));

            return response()->json([
                'success' => true,
                'message' => 'Equipment transferred successfully.',
                'data' => $equipment->load(['room', 'building', 'assignedUser'])
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to transfer equipment.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get equipment usage by assigned users
     */
    public function getEquipmentUsage(Request $request)
    {
        try {
            $search = $request->get('search', '');

            $query = Equipment::with([
                'room:id,room_name,room_code',
                'building:id,building_name',
                'college:id,college_name',
                'assignedUser:id,first_name,last_name,middle_name,username'
            ])
            ->whereNotNull('assigned_user_id');

            // Apply search filter if provided
            if (!empty($search)) {
                $query->where(function($q) use ($search) {
                    $q->where('equipment_name', 'like', "%{$search}%")
                      ->orWhere('inventory_id', 'like', "%{$search}%")
                      ->orWhere('property_id', 'like', "%{$search}%")
                      ->orWhereHas('assignedUser', function($q) use ($search) {
                          $q->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%");
                      })
                      ->orWhereHas('room', function($q) use ($search) {
                          $q->where('room_code', 'like', "%{$search}%")
                            ->orWhere('room_name', 'like', "%{$search}%");
                      })
                      ->orWhereHas('building', function($q) use ($search) {
                          $q->where('building_name', 'like', "%{$search}%");
                      })
                      ->orWhereHas('college', function($q) use ($search) {
                          $q->where('college_name', 'like', "%{$search}%");
                      });
                });
            }

            // Group by assigned user to get aggregated view
            $equipmentByUser = $query->get()
                ->groupBy('assigned_user_id')
                ->map(function($equipments, $userId) {
                    $user = $equipments->first()->assignedUser;
                    $room = $equipments->first()->room;
                    $building = $equipments->first()->building;
                    $college = $equipments->first()->college;

                    return [
                        'id' => $userId,
                        'name' => $user ? $user->first_name . ' ' . $user->last_name : 'Unknown User',
                        'room' => $room ? $room->room_code : 'N/A',
                        'building' => $building ? $building->building_name : 'N/A',
                        'college' => $college ? $college->college_name : 'N/A',
                        'equipmentUsed' => $equipments->map(function($eq) {
                            return [
                                'inventory_id' => $eq->inventory_id,
                                'property_id' => $eq->property_id,
                                'name' => $eq->equipment_name,
                                'cfic' => $eq->cfic_id,
                                'status' => ucfirst(str_replace('_', ' ', $eq->status)),
                                'description' => $eq->description
                            ];
                        })->toArray()
                    ];
                })
                ->values();

            return response()->json([
                'success' => true,
                'usage_list' => $equipmentByUser,
                'total_users' => $equipmentByUser->count(),
                'total_equipment' => $equipmentByUser->sum(function($user) {
                    return count($user['equipmentUsed']);
                })
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch equipment usage data.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Autocomplete suggestions for room equipment picker (per keystroke).
     */
    public function suggestions(Request $request)
    {
        try {
            $query = trim((string) $request->query('q', ''));

            if ($query === '') {
                return response()->json([
                    'success' => true,
                    'data' => [],
                ]);
            }

            $exclude = $request->query('exclude', []);
            if (is_string($exclude)) {
                $exclude = array_filter(array_map('trim', explode(',', $exclude)));
            }
            if (!is_array($exclude)) {
                $exclude = [];
            }

            $excludeLower = array_map(fn ($name) => strtolower((string) $name), $exclude);

            // COUNT(*) per name — same rule as EquipmentInventoryService::globalInventoryCountsByName()
            $suggestions = Equipment::query()
                ->select('equipment_name', DB::raw('MIN(id) as id'), DB::raw('COUNT(*) as inventory_count'))
                ->where('equipment_name', 'like', '%' . $query . '%')
                ->groupBy('equipment_name')
                ->orderBy('equipment_name')
                ->limit(10)
                ->get()
                ->filter(function ($item) use ($excludeLower) {
                    return !in_array(strtolower($item->equipment_name), $excludeLower, true);
                })
                ->values()
                ->map(function ($item) use ($query) {
                    return [
                        'id' => (int) $item->id,
                        'name' => $item->equipment_name,
                        'inventory_count' => (int) $item->inventory_count,
                        'match' => $query,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $suggestions,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch equipment suggestions.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Validate equipment names against the inventory catalog.
     */
    public function validateNames(Request $request)
    {
        try {
            $names = $request->input('names', []);
            if (!is_array($names)) {
                $names = [$names];
            }

            $invalid = [];
            $valid = [];

            foreach ($names as $name) {
                $trimmed = trim((string) $name);
                if ($trimmed === '') {
                    continue;
                }

                $exists = Equipment::query()
                    ->whereRaw('LOWER(equipment_name) = ?', [strtolower($trimmed)])
                    ->exists();

                if ($exists) {
                    $valid[] = $trimmed;
                } else {
                    $invalid[] = $trimmed;
                }
            }

            return response()->json([
                'success' => empty($invalid),
                'valid' => array_values(array_unique($valid)),
                'invalid' => array_values(array_unique($invalid)),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to validate equipment names.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
