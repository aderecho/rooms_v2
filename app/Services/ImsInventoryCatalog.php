<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ImsInventoryCatalog
{
    private function records(): array
    {
        $token = config('services.ims.inventory_token');
        if (! $token) {
            throw new RuntimeException('Inventory is not configured.');
        }

        return Cache::remember('ims.inventory.records.v2.'.hash('sha256', $token), 60, function () use ($token) {
            $response = Http::withToken($token)->acceptJson()->asJson()
                ->connectTimeout(5)->timeout(15)->withoutRedirecting()
                ->get('https://ims.upcebu.edu.ph/api/v1/inventory');
            if (! $response->successful() || ! is_array($response->json('data'))) {
                throw new RuntimeException('Inventory is temporarily unavailable. Please try again.');
            }

            return collect($response->json('data'))
                ->filter(fn ($item) => is_array($item) && is_string($item['Item'] ?? null) && trim($item['Item']) !== '')
                ->values()->all();
        });
    }

    public function items(): array
    {
        return collect($this->records())
            ->groupBy(fn ($item) => mb_strtolower(trim($item['Item'])))
            ->map(fn ($items) => [
                'id' => (string) $items->first()['id'],
                'name' => trim($items->first()['Item']),
                'inventory_count' => $items->count(),
            ])->sortBy('name')->values()->all();
    }

    public function snapshots(array $names): array
    {
        if ($names === []) {
            return [];
        }

        $groups = collect($this->records())
            ->groupBy(fn ($item) => mb_strtolower(trim($item['Item'])));

        return collect($names)->map(function ($name) use ($groups) {
            $records = $groups->get(mb_strtolower(trim($name)));
            if (! $records || $records->isEmpty()) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'equipments' => 'Please select equipment from the inventory suggestions.',
                ]);
            }

            return [
                'id' => (string) $records->first()['id'],
                'name' => trim($records->first()['Item']),
                'inventory_count' => $records->count(),
                'inventory' => $records->values()->all(),
            ];
        })->values()->all();
    }

    public function contains(string $name): bool
    {
        return collect($this->items())->contains(fn ($item) => mb_strtolower($item['name']) === mb_strtolower(trim($name)));
    }
}
