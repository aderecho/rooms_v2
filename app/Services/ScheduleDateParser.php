<?php

namespace App\Services;

use Carbon\Carbon;
use Carbon\CarbonPeriod;
use InvalidArgumentException;

class ScheduleDateParser
{
    private const DAY_MAP = [
        'M' => Carbon::MONDAY,
        'MON' => Carbon::MONDAY,
        'MONDAY' => Carbon::MONDAY,
        'T' => Carbon::TUESDAY,
        'TU' => Carbon::TUESDAY,
        'TUE' => Carbon::TUESDAY,
        'TUESDAY' => Carbon::TUESDAY,
        'W' => Carbon::WEDNESDAY,
        'WED' => Carbon::WEDNESDAY,
        'WEDNESDAY' => Carbon::WEDNESDAY,
        'TH' => Carbon::THURSDAY,
        'THU' => Carbon::THURSDAY,
        'THURSDAY' => Carbon::THURSDAY,
        'F' => Carbon::FRIDAY,
        'FRI' => Carbon::FRIDAY,
        'FRIDAY' => Carbon::FRIDAY,
        'SAT' => Carbon::SATURDAY,
        'SATURDAY' => Carbon::SATURDAY,
        'SUN' => Carbon::SUNDAY,
        'SUNDAY' => Carbon::SUNDAY,
    ];

    private const MONTH_MAP = [
        'january' => 1,
        'jan' => 1,
        'february' => 2,
        'feb' => 2,
        'march' => 3,
        'mar' => 3,
        'april' => 4,
        'apr' => 4,
        'may' => 5,
        'june' => 6,
        'jun' => 6,
        'july' => 7,
        'jul' => 7,
        'august' => 8,
        'aug' => 8,
        'september' => 9,
        'sep' => 9,
        'sept' => 9,
        'october' => 10,
        'oct' => 10,
        'november' => 11,
        'nov' => 11,
        'december' => 12,
        'dec' => 12,
    ];

    public function parse(string $date, ?string $startTime = null, ?string $endTime = null): array
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($date))) {
            if (! $startTime || ! $endTime) {
                throw new InvalidArgumentException('start_time and end_time are required for a single-date schedule.');
            }

            $parsedDate = Carbon::createFromFormat('Y-m-d', trim($date));

            if ($parsedDate->format('Y-m-d') !== trim($date)) {
                throw new InvalidArgumentException('The schedule date must be a valid calendar date.');
            }

            return [
                'dates' => [$parsedDate->format('Y-m-d')],
                'start_time' => $startTime,
                'end_time' => $endTime,
                'is_recurring' => false,
                'recurrence_pattern' => null,
            ];
        }

        $pattern = '/^(?<days>.+?)\s+from\s+(?<start_month>[a-z]+)\s+to\s+(?<end_month>[a-z]+)\s+(?<start_time>\d{1,2}(?::\d{0,2})?\s*(?:am|pm))\s*-\s*(?<end_time>\d{1,2}(?::\d{0,2})?\s*(?:am|pm))\s+(?<start_year>\d{4})\s*-\s*(?<end_year>\d{4})$/i';

        if (! preg_match($pattern, trim($date), $matches)) {
            throw new InvalidArgumentException('Use YYYY-MM-DD with start_time and end_time, or a recurring expression such as "T-TH from June to May 10:am-11:am 2026-2027".');
        }

        $days = $this->parseDays($matches['days']);
        $startMonth = $this->parseMonth($matches['start_month']);
        $endMonth = $this->parseMonth($matches['end_month']);
        $startYear = (int) $matches['start_year'];
        $endYear = (int) $matches['end_year'];
        $normalizedStartTime = $this->parseTime($matches['start_time']);
        $normalizedEndTime = $this->parseTime($matches['end_time']);

        if ($normalizedEndTime <= $normalizedStartTime) {
            throw new InvalidArgumentException('The schedule end time must be after the start time.');
        }

        $rangeStart = Carbon::create($startYear, $startMonth, 1)->startOfDay();
        $rangeEnd = Carbon::create($endYear, $endMonth, 1)->endOfMonth()->startOfDay();

        if ($rangeEnd->lt($rangeStart)) {
            throw new InvalidArgumentException('The recurring schedule end month must be after its start month.');
        }

        $dates = collect(CarbonPeriod::create($rangeStart, $rangeEnd))
            ->filter(fn (Carbon $day) => in_array($day->dayOfWeek, $days, true))
            ->map(fn (Carbon $day) => $day->format('Y-m-d'))
            ->values()
            ->all();

        if ($dates === []) {
            throw new InvalidArgumentException('The recurring schedule did not produce any dates.');
        }

        return [
            'dates' => $dates,
            'start_time' => $normalizedStartTime,
            'end_time' => $normalizedEndTime,
            'is_recurring' => true,
            'recurrence_pattern' => [
                'expression' => trim($date),
                'days' => array_values(array_unique($days)),
                'range_start' => $rangeStart->format('Y-m-d'),
                'range_end' => $rangeEnd->format('Y-m-d'),
            ],
        ];
    }

    private function parseDays(string $value): array
    {
        $tokens = preg_split('/\s*(?:-|\/|,|&)\s*/', strtoupper(trim($value))) ?: [];
        $days = [];

        foreach ($tokens as $token) {
            if (! isset(self::DAY_MAP[$token])) {
                throw new InvalidArgumentException("Unsupported schedule day: {$token}.");
            }

            $days[] = self::DAY_MAP[$token];
        }

        return array_values(array_unique($days));
    }

    private function parseMonth(string $value): int
    {
        $month = self::MONTH_MAP[strtolower(trim($value))] ?? null;

        if (! $month) {
            throw new InvalidArgumentException("Unsupported schedule month: {$value}.");
        }

        return $month;
    }

    private function parseTime(string $value): string
    {
        $normalized = strtolower(preg_replace('/\s+/', '', trim($value)));

        if (! preg_match('/^(?<hour>\d{1,2})(?::(?<minute>\d{0,2}))?(?<period>am|pm)$/', $normalized, $matches)) {
            throw new InvalidArgumentException("Unsupported schedule time: {$value}.");
        }

        $hour = (int) $matches['hour'];
        $minute = ($matches['minute'] ?? '') === '' ? 0 : (int) $matches['minute'];

        if ($hour < 1 || $hour > 12 || $minute > 59) {
            throw new InvalidArgumentException("Unsupported schedule time: {$value}.");
        }

        if ($matches['period'] === 'pm' && $hour !== 12) {
            $hour += 12;
        } elseif ($matches['period'] === 'am' && $hour === 12) {
            $hour = 0;
        }

        return sprintf('%02d:%02d', $hour, $minute);
    }
}
