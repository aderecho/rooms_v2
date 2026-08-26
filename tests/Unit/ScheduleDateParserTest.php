<?php

use App\Services\ScheduleDateParser;

it('parses a recurring academic-year schedule expression', function () {
    $result = (new ScheduleDateParser)->parse(
        'T-TH from June to May 10:am-11:am 2026-2027'
    );

    expect($result['is_recurring'])->toBeTrue()
        ->and($result['start_time'])->toBe('10:00')
        ->and($result['end_time'])->toBe('11:00')
        ->and($result['recurrence_pattern']['range_start'])->toBe('2026-06-01')
        ->and($result['recurrence_pattern']['range_end'])->toBe('2027-05-31')
        ->and($result['dates'])->toHaveCount(104)
        ->and($result['dates'][0])->toBe('2026-06-02')
        ->and($result['dates'][103])->toBe('2027-05-27')
        ->and(collect($result['dates'])->map(fn (string $date) => substr($date, 0, 7))->unique()->values()->all())
        ->toBe([
            '2026-06', '2026-07', '2026-08', '2026-09', '2026-10', '2026-11',
            '2026-12', '2027-01', '2027-02', '2027-03', '2027-04', '2027-05',
        ]);

    foreach ($result['dates'] as $date) {
        expect(Carbon\Carbon::parse($date)->englishDayOfWeek)
            ->toBeIn(['Tuesday', 'Thursday']);
    }
});

it('continues to parse a single ISO date', function () {
    $result = (new ScheduleDateParser)->parse('2026-08-27', '09:00', '10:00');

    expect($result['dates'])->toBe(['2026-08-27'])
        ->and($result['is_recurring'])->toBeFalse();
});

it('rejects an invalid recurring expression', function () {
    (new ScheduleDateParser)->parse('every other day sometime next year');
})->throws(InvalidArgumentException::class);

it('rejects an invalid ISO calendar date', function () {
    (new ScheduleDateParser)->parse('2026-02-30', '09:00', '10:00');
})->throws(InvalidArgumentException::class);
