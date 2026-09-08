<?php

use App\Models\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns only the equipment associated with each room in list and detail responses', function () {
    $room = Room::create([
        'room_name' => 'Equipment Room',
        'room_code' => 'EQUIP-001',
        'equipments' => ['Projector', 'Chair'],
    ]);
    $emptyRoom = Room::create([
        'room_name' => 'Empty Room',
        'room_code' => 'EQUIP-002',
    ]);

    $response = $this->getJson('/api/v1/room/list/')->assertOk();
    $rooms = collect($response->json('data'))->keyBy('id');
    expect($rooms[$room->id]['equipments'])->toBe(['Projector', 'Chair']);
    expect($rooms[$emptyRoom->id]['equipments'])->toBe([]);

    $this->getJson('/api/v1/room/list/'.$room->id)
        ->assertOk()
        ->assertJsonPath('data.equipments', ['Projector', 'Chair']);
    $this->getJson('/api/v1/room/list/'.$emptyRoom->id)
        ->assertOk()
        ->assertJsonPath('data.equipments', []);
});
