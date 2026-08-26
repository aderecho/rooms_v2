<?php

namespace App\Services;

use Carbon\Carbon;
use DateTimeInterface;

class RoomAvailabilityService
{
    public const OPENING_TIME = '07:00';

    public const CLOSING_TIME = '21:00';

    public function isFullyOccupied(iterable $schedules): bool
    {
        $openingMinute = $this->toMinute(self::OPENING_TIME);
        $closingMinute = $this->toMinute(self::CLOSING_TIME);
        $intervals = [];

        foreach ($schedules as $schedule) {
            $startTime = data_get($schedule, 'start_time');
            $endTime = data_get($schedule, 'end_time');

            if (! $startTime || ! $endTime) {
                continue;
            }

            $start = max($openingMinute, $this->toMinute($startTime));
            $end = min($closingMinute, $this->toMinute($endTime));

            if ($start < $end) {
                $intervals[] = [$start, $end];
            }
        }

        usort($intervals, fn (array $left, array $right) => $left[0] <=> $right[0]);

        $coveredUntil = $openingMinute;

        foreach ($intervals as [$start, $end]) {
            if ($start > $coveredUntil) {
                return false;
            }

            $coveredUntil = max($coveredUntil, $end);

            if ($coveredUntil >= $closingMinute) {
                return true;
            }
        }

        return false;
    }

    private function toMinute(string|DateTimeInterface $time): int
    {
        $parsed = $time instanceof DateTimeInterface
            ? Carbon::instance($time)
            : Carbon::parse($time);

        return ($parsed->hour * 60) + $parsed->minute;
    }
}
