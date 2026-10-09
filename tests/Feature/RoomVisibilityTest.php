<?php

use App\Models\Building;
use App\Models\College;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\UserAccount;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    $admin = UserAccount::factory()->create(['user_type' => 'admin', 'account_status' => 'active']);
    $this->actingAs($admin)->withSession(['user' => [
        'id' => $admin->id,
        'username' => $admin->username,
        'email' => $admin->email,
        'name' => $admin->full_name,
        'role' => 'admin',
        'permissions' => [],
    ]]);
    $this->payload = [
        'room_name' => 'Visibility Room',
        'room_code' => 'VIS-001',
        'building_id' => Building::factory()->create()->id,
        'college_id' => College::factory()->create()->id,
        'room_type_id' => RoomType::factory()->create()->id,
        'floor_number' => 1,
        'capacity' => 30,
    ];
});

it('adds a non-null visibility column with a database private default', function () {
    expect(Schema::hasColumn('rooms', 'is_public'))->toBeTrue();
    $id = DB::table('rooms')->insertGetId(['room_name' => 'Default', 'room_code' => 'DEFAULT']);
    expect((int) DB::table('rooms')->where('id', $id)->value('is_public'))->toBe(0);
    expect(fn () => DB::table('rooms')->where('id', $id)->update(['is_public' => null]))->toThrow(QueryException::class);
});

it('migrates legacy rows and resets every existing room without changing other data', function () {
    $schema = require database_path('migrations/2026_10_09_000001_add_is_public_to_rooms_table.php');
    $schema->down();
    $id = DB::table('rooms')->insertGetId($this->payload);
    $schema->up();
    expect((int) DB::table('rooms')->where('id', $id)->value('is_public'))->toBe(0);
    DB::table('rooms')->where('id', $id)->update(['is_public' => 1]);
    Room::create([...$this->payload, 'room_code' => 'VIS-002', 'is_public' => 1]);
    $before = DB::table('rooms')->get()->map(fn ($row) => collect($row)->except('is_public')->all())->all();
    $data = require database_path('migrations/2026_10_09_000002_set_existing_rooms_to_private.php');
    $data->up();
    $data->up();
    $data->down();
    expect(DB::table('rooms')->where('is_public', '!=', 0)->count())->toBe(0);
    expect(DB::table('rooms')->get()->map(fn ($row) => collect($row)->except('is_public')->all())->all())->toBe($before);
});

it('creates private by default and persists explicit visibility', function ($visibility) {
    $payload = $this->payload;
    if ($visibility !== 'omitted') {
        $payload['is_public'] = $visibility;
    }
    $this->post('/Rooms', $payload)->assertRedirect()->assertSessionHasNoErrors();
    expect(Room::where('room_code', 'VIS-001')->firstOrFail()->is_public)->toBe($visibility === 'omitted' ? 0 : (int) $visibility);
})->with(['omitted', 0, 1, '0', '1']);

it('changes visibility in either direction and preserves it when omitted', function ($initial) {
    $room = Room::create([...$this->payload, 'is_public' => $initial]);
    $this->put('/Rooms/'.$room->id, [...$this->payload, 'is_public' => 1 - $initial])
        ->assertRedirect()->assertSessionHasNoErrors();
    expect($room->fresh()->is_public)->toBe(1 - $initial);
    $this->put('/Rooms/'.$room->id, [...$this->payload, 'room_name' => 'Renamed Room'])
        ->assertRedirect()->assertSessionHasNoErrors();
    expect($room->fresh()->is_public)->toBe(1 - $initial);
    expect($room->fresh()->room_name)->toBe('Renamed Room');
    expect($room->fresh()->building_id)->toBe($this->payload['building_id']);
})->with([0, 1]);

it('rejects invalid visibility on create and update', function ($invalid) {
    $this->postJson('/Rooms', [...$this->payload, 'is_public' => $invalid])
        ->assertUnprocessable()->assertJsonValidationErrors('is_public');
    expect(Room::count())->toBe(0);
    $room = Room::create([...$this->payload, 'is_public' => 0]);
    $this->putJson('/Rooms/'.$room->id, [...$this->payload, 'is_public' => $invalid])
        ->assertUnprocessable()->assertJsonValidationErrors('is_public');
    expect($room->fresh()->is_public)->toBe(0);
})->with([null, '', 2, -1, 'public', 'private', [[1]]]);

it('returns numeric visibility in list detail and model responses', function ($visibility) {
    $room = Room::create([...$this->payload, 'is_public' => $visibility]);
    $this->getJson('/api/v1/room/list')->assertOk()->assertJsonPath('data.0.is_public', $visibility);
    $this->getJson('/api/v1/room/list/'.$room->id)->assertOk()->assertJsonPath('data.is_public', $visibility);
    expect($room->fresh()->toArray()['is_public'])->toBe($visibility);
})->with([0, 1]);
