<?php

use App\Rules\ValidEquipmentCatalogName;
use App\Services\ImsInventoryCatalog;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

beforeEach(function () {
    config(['services.ims.inventory_token' => 'test-ims-token']);
    Cache::flush();
    Http::preventStrayRequests();
});

it('maps IMS Item fields and groups stock while caching authenticated requests', function () {
    Http::fake(['ims.upcebu.edu.ph/*' => Http::response(['data' => [
        ['id' => 1, 'Item' => 'Projector', 'Property_number' => 'private'],
        ['id' => 2, 'Item' => ' projector '],
        ['id' => 3, 'Item' => 'Chair'],
    ]])]);
    $catalog = app(ImsInventoryCatalog::class);
    expect($catalog->items())->toBe([
        ['id' => '3', 'name' => 'Chair', 'inventory_count' => 1],
        ['id' => '1', 'name' => 'Projector', 'inventory_count' => 2],
    ]);
    expect($catalog->contains('PROJECTOR'))->toBeTrue();
    expect(Validator::make(['equipment' => 'Projector'], ['equipment' => new ValidEquipmentCatalogName])->passes())->toBeTrue();
    expect(Validator::make(['equipment' => 'Unknown'], ['equipment' => new ValidEquipmentCatalogName])->fails())->toBeTrue();
    Http::assertSentCount(1);
    Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer test-ims-token'));
});

it('reports upstream outages without exposing upstream messages', function () {
    Http::fake(['ims.upcebu.edu.ph/*' => Http::response(['message' => 'sensitive upstream detail'], 401)]);
    $validator = Validator::make(['equipment' => 'Chair'], ['equipment' => new ValidEquipmentCatalogName]);
    expect($validator->errors()->first())->toBe('Inventory is temporarily unavailable. Please try again.');
});

it('rejects malformed responses instead of treating them as an empty catalog', function () {
    Http::fake(['ims.upcebu.edu.ph/*' => Http::response(['unexpected' => []])]);
    expect(fn () => app(ImsInventoryCatalog::class)->items())->toThrow(RuntimeException::class);
});

it('persists complete IMS records on room creation and update and returns them through the API', function () {
    $records = [
        ['id' => 1, 'Item' => 'Projector', 'Property_number' => 'PROP-001', 'details' => ['serial' => 'ABC'], 'cost' => null],
        ['id' => 2, 'Item' => ' projector ', 'Property_number' => 'PROP-002'],
    ];
    Http::fake(['ims.upcebu.edu.ph/*' => Http::response(['data' => $records])]);
    $controller = app(\App\Http\Controllers\RoomController::class);
    $request = Mockery::mock(\App\Http\Requests\StoreRoomRequest::class);
    $request->shouldReceive('validated')->once()->andReturn([
        'room_name' => 'Snapshot Room', 'room_code' => 'SNAP-001', 'equipments' => ['Projector'],
    ]);
    $controller->store($request);
    $room = \App\Models\Room::where('room_code', 'SNAP-001')->firstOrFail();
    expect($room->equipments[0]['inventory'])->toBe($records);
    $this->getJson('/api/v1/room/list/'.$room->id)->assertOk()
        ->assertJsonPath('data.equipments.0.inventory', $records);
    $this->getJson('/api/v1/room/list/')->assertOk()
        ->assertJsonFragment(['inventory' => $records]);

    $update = Mockery::mock(\App\Http\Requests\UpdateRoomRequest::class);
    $update->shouldReceive('validated')->once()->andReturn(['equipments' => ['PROJECTOR']]);
    $controller->update($update, $room);
    expect($room->fresh()->equipments[0]['inventory'])->toBe($records);

    $clear = Mockery::mock(\App\Http\Requests\UpdateRoomRequest::class);
    $clear->shouldReceive('validated')->once()->andReturn(['equipments' => []]);
    $controller->update($clear, $room);
    expect($room->fresh()->equipments)->toBe([]);
});
