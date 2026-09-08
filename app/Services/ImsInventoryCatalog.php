<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ImsInventoryCatalog
{
    public function items(): array
    {
        $token = config('services.ims.inventory_token');
        if (! $token) {
            throw new RuntimeException('Inventory is not configured.');
        }

        return Cache::remember('ims.inventory.'.hash('sha256', $token), 60, function () use ($token) {
            $response = Http::withToken($token)->acceptJson()->asJson()
                ->connectTimeout(5)->timeout(15)->withoutRedirecting()
                ->get('https://ims.upcebu.edu.ph/api/v1/inventory');
            if (! $response->successful() || ! is_array($response->json('data'))) {
                throw new RuntimeException('Inventory is temporarily unavailable. Please try again.');
            }

            return collect($response->json('data'))
                ->filter(fn ($item) => is_array($item) && is_string($item['Item'] ?? null) && trim($item['Item']) !== '')
                ->groupBy(fn ($item) => mb_strtolower(trim($item['Item'])))
                ->map(fn ($items) => [
                    'id' => (string) $items->first()['id'],
                    'name' => trim($items->first()['Item']),
                    'inventory_count' => $items->count(),
                ])->sortBy('name')->values()->all();
        });
    }

    public function contains(string $name): bool
    {
        return collect($this->items())->contains(fn ($item) => mb_strtolower($item['name']) === mb_strtolower(trim($name)));
    }
}
