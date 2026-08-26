<?php

use App\Services\RoomAvailabilityService;

it('detects when schedules cover the entire operating window', function () {
    $service = new RoomAvailabilityService;

    expect($service->isFullyOccupied([
        ['start_time' => '07:00', 'end_time' => '12:00'],
        ['start_time' => '12:00', 'end_time' => '18:00'],
        ['start_time' => '17:30', 'end_time' => '21:00'],
    ]))->toBeTrue();
});

it('keeps a room available when there is a gap in the operating window', function () {
    $service = new RoomAvailabilityService;

    expect($service->isFullyOccupied([
        ['start_time' => '07:00', 'end_time' => '12:00'],
        ['start_time' => '12:30', 'end_time' => '21:00'],
    ]))->toBeFalse();
});

it('clips schedules to the operating window', function () {
    $service = new RoomAvailabilityService;

    expect($service->isFullyOccupied([
        ['start_time' => '06:00', 'end_time' => '22:00'],
    ]))->toBeTrue();
});

it('keeps a room available when it has no schedules', function () {
    $service = new RoomAvailabilityService;

    expect($service->isFullyOccupied([]))->toBeFalse();
});
