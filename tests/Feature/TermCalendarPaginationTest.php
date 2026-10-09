<?php

use App\Http\Controllers\TermController;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\Term;
use Illuminate\Http\Request;

it('loads only a requested term month and a bounded calendar page', function () {
    $term = Term::factory()->create();
    $room = Room::create(['room_name' => 'Term room', 'room_code' => 'TERM-CAL']);
    $row = ['term_id' => $term->id, 'room_id' => $room->id, 'event_title' => 'Term class',
        'date' => '2026-10-15', 'start_time' => '10:00', 'end_time' => '11:00',
        'day_of_week' => 'Thursday', 'status' => 'approved'];
    \Illuminate\Support\Facades\DB::table('schedules')->insert(array_fill(0, 205, $row));
    $response = app(TermController::class)->getCalendar(Request::create('/term-calendar', 'GET', ['month' => '2026-10', 'page' => 3]), $term->id);
    $data = $response->getData(true);
    expect($data['meta']['total'])->toBe(205)->and($data['meta']['last_page'])->toBe(3)
        ->and($data['calendar']['2026-10']['schedules'])->toHaveCount(5);
    $empty = app(TermController::class)->getCalendar(Request::create('/term-calendar', 'GET', ['month' => '2026-11']), $term->id)->getData(true);
    expect($empty['meta']['total'])->toBe(0)->and($empty['calendar'])->toBe([]);
});
