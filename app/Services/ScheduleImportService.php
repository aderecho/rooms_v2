<?php

namespace App\Services;

use App\Models\Room;
use App\Models\Schedule;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;

class ScheduleImportService
{
    public const MAX_ROWS = 500;

    public const HEADERS = [
        'room_id',
        'room_name',
        'event_title',
        'event_type',
        'date',
        'start_time',
        'end_time',
        'course_code',
        'course_name',
        'section',
        'faculty_name',
        'number_of_participants',
        'requester_name',
        'description',
        'agenda',
        'organizer',
        'equipment_needed',
        'additional_requirements',
        'cfic_id',
    ];

    public const REQUIRED_HEADERS = [
        'room_id',
        'room_name',
        'event_title',
        'event_type',
        'date',
        'start_time',
        'end_time',
    ];

    public function __construct(
        private readonly ScheduleDateParser $scheduleDateParser,
    ) {}

    public function analyze(UploadedFile $file): array
    {
        $records = $this->readRecords($file);
        $rooms = Room::query()->get()->keyBy('id');
        $roomsByName = Room::query()->get()->keyBy(fn (Room $room) => strtolower(trim($room->room_name)));
        $rows = [];
        $prepared = [];
        $fileSlots = [];

        foreach ($records as $record) {
            $result = $this->analyzeRow($record, $rooms, $roomsByName, $fileSlots);
            $rows[] = $result['preview'];

            if ($result['prepared']) {
                $prepared[] = $result['prepared'];

                foreach ($result['prepared']['dates'] as $date) {
                    $fileSlots[$result['prepared']['room']->id][$date][] = [
                        'start_time' => $result['prepared']['start_time'],
                        'end_time' => $result['prepared']['end_time'],
                        'row_number' => $record['row_number'],
                    ];
                }
            }
        }

        $invalidRows = collect($rows)->where('status', 'invalid')->count();

        return [
            'summary' => [
                'total_rows' => count($rows),
                'valid_rows' => count($rows) - $invalidRows,
                'invalid_rows' => $invalidRows,
                'schedule_occurrences' => collect($prepared)->sum(fn (array $row) => count($row['dates'])),
            ],
            'rows' => $rows,
            'prepared' => $prepared,
        ];
    }

    public function create(array $preparedRows): Collection
    {
        $created = collect();

        foreach ($preparedRows as $prepared) {
            foreach ($prepared['dates'] as $date) {
                $created->push(Schedule::create([
                    ...$prepared['schedule'],
                    'room_id' => $prepared['room']->id,
                    'date' => $date,
                    'start_time' => $prepared['start_time'],
                    'end_time' => $prepared['end_time'],
                    'day_of_week' => Carbon::parse($date)->englishDayOfWeek,
                    'status' => 'pending',
                    'is_recurring' => $prepared['is_recurring'],
                    'recurrence_pattern' => $prepared['recurrence_pattern'],
                ]));
            }
        }

        return $created;
    }

    public function exampleRows(iterable $rooms): array
    {
        $rooms = collect($rooms)->values();

        if ($rooms->isEmpty()) {
            return [];
        }

        $firstRoomName = $rooms->first()->room_name;
        $secondRoomName = ($rooms->get(1) ?? $rooms->first())->room_name;

        return [
            [
                '', $firstRoomName, 'Faculty Meeting', 'meeting', '2026-08-27', '09:00', '10:00',
                '', '', '', 'Juan Dela Cruz', '20', 'Office of the Dean', 'Monthly coordination meeting',
                'Department updates', 'UP Cebu', 'projector|microphone', 'water', 'EXT-001',
            ],
            [
                '', $secondRoomName, 'Recurring Class', 'class',
                'T-TH from June to May 10:am-11:am 2026-2027', '', '', 'CMSC 101',
                'Introduction to Computing', 'A', 'Maria Santos', '35', 'External Scheduling System',
                '', '', 'College of Science', 'projector|whiteboard', '', 'EXT-002',
            ],
        ];
    }

    private function readRecords(UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());

        if ($extension === 'csv') {
            return $this->readCsvRecords($file);
        }

        $readerType = match ($extension) {
            'xlsx' => 'Xlsx',
            'xls' => 'Xls',
            default => throw new RuntimeException('Unsupported file type. Upload a CSV, XLSX, or XLS file.'),
        };

        $reader = IOFactory::createReader($readerType);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($file->getRealPath());
        $sheet = $spreadsheet->getSheet(0);
        $highestRow = $sheet->getHighestDataRow();
        $highestColumn = $sheet->getHighestDataColumn();
        $rawHeaders = $sheet->rangeToArray("A1:{$highestColumn}1", null, true, true, false)[0] ?? [];
        $headers = array_map(fn ($header) => $this->normalizeHeader((string) $header), $rawHeaders);

        $missingHeaders = array_values(array_diff(self::REQUIRED_HEADERS, $headers));
        if ($missingHeaders !== []) {
            throw new RuntimeException('Missing required columns: '.implode(', ', $missingHeaders).'.');
        }

        if (($highestRow - 1) > self::MAX_ROWS) {
            throw new RuntimeException('The file exceeds the maximum of '.self::MAX_ROWS.' schedule rows.');
        }

        $records = [];

        for ($rowNumber = 2; $rowNumber <= $highestRow; $rowNumber++) {
            $values = [];

            foreach ($headers as $columnIndex => $header) {
                if ($header === '') {
                    continue;
                }

                $cell = $sheet->getCell([$columnIndex + 1, $rowNumber]);
                $values[$header] = $this->normalizeCellValue($sheet, $cell->getValue(), $columnIndex + 1, $rowNumber, $header);
            }

            if (collect($values)->filter(fn ($value) => $value !== null && $value !== '')->isEmpty()) {
                continue;
            }

            $records[] = [
                'row_number' => $rowNumber,
                'data' => $values,
            ];
        }

        if ($records === []) {
            throw new RuntimeException('The spreadsheet does not contain any schedule rows.');
        }

        $spreadsheet->disconnectWorksheets();

        return $records;
    }

    private function readCsvRecords(UploadedFile $file): array
    {
        $stream = fopen($file->getRealPath(), 'r');

        if ($stream === false) {
            throw new RuntimeException('The CSV file could not be opened.');
        }

        try {
            $rawHeaders = fgetcsv($stream, null, ',', '"', '');

            if ($rawHeaders === false) {
                throw new RuntimeException('The CSV file does not contain a header row.');
            }

            $headers = array_map(fn ($header) => $this->normalizeHeader((string) $header), $rawHeaders);
            $missingHeaders = array_values(array_diff(self::REQUIRED_HEADERS, $headers));

            if ($missingHeaders !== []) {
                throw new RuntimeException('Missing required columns: '.implode(', ', $missingHeaders).'.');
            }

            $records = [];
            $rowNumber = 1;

            while (($row = fgetcsv($stream, null, ',', '"', '')) !== false) {
                $rowNumber++;
                $values = [];

                foreach ($headers as $columnIndex => $header) {
                    if ($header === '') {
                        continue;
                    }

                    $value = $row[$columnIndex] ?? null;
                    $values[$header] = is_string($value) ? trim($value) : $value;
                }

                if (collect($values)->filter(fn ($value) => $value !== null && $value !== '')->isEmpty()) {
                    continue;
                }

                $records[] = [
                    'row_number' => $rowNumber,
                    'data' => $values,
                ];

                if (count($records) > self::MAX_ROWS) {
                    throw new RuntimeException('The file exceeds the maximum of '.self::MAX_ROWS.' schedule rows.');
                }
            }
        } finally {
            fclose($stream);
        }

        if ($records === []) {
            throw new RuntimeException('The spreadsheet does not contain any schedule rows.');
        }

        return $records;
    }

    private function analyzeRow(array $record, Collection $rooms, Collection $roomsByName, array $fileSlots): array
    {
        $data = $record['data'];
        $errors = [];

        $validator = Validator::make($data, [
            'room_id' => ['nullable', 'integer'],
            'room_name' => ['nullable', 'string'],
            'event_title' => ['required', 'string', 'max:255'],
            'event_type' => ['nullable', 'string', 'in:class,meeting,event,other'],
            'date' => ['required', 'string'],
            'start_time' => ['nullable', 'string'],
            'end_time' => ['nullable', 'string'],
            'number_of_participants' => ['nullable', 'integer', 'min:1'],
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors()->all();
        }

        $hasRoomId = filled($data['room_id'] ?? null);
        $hasRoomName = filled($data['room_name'] ?? null);
        if ($hasRoomId === $hasRoomName) {
            $errors[] = 'Provide exactly one of room_id or room_name.';
        }

        $room = null;
        if ($hasRoomId) {
            $room = $rooms->get((int) $data['room_id']);
        } elseif ($hasRoomName) {
            $room = $roomsByName->get(strtolower(trim((string) $data['room_name'])));
        }

        if (($hasRoomId || $hasRoomName) && ! $room) {
            $roomReference = $hasRoomName
                ? '"'.trim((string) $data['room_name']).'"'
                : 'ID '.(string) $data['room_id'];

            $errors[] = "Room {$roomReference} was not found. Select a current room or download a fresh template.";
        }

        $parsedDate = null;
        if (($data['date'] ?? '') !== '') {
            try {
                $parsedDate = $this->scheduleDateParser->parse(
                    (string) $data['date'],
                    filled($data['start_time'] ?? null) ? (string) $data['start_time'] : null,
                    filled($data['end_time'] ?? null) ? (string) $data['end_time'] : null,
                );
            } catch (\InvalidArgumentException $exception) {
                $errors[] = $exception->getMessage();
            }
        }

        if ($room && $parsedDate) {
            $conflict = Schedule::query()
                ->where('room_id', $room->id)
                ->whereIn(DB::raw('DATE(date)'), $parsedDate['dates'])
                ->whereNotIn('status', ['cancelled', 'completed', 'rejected'])
                ->where('start_time', '<', $parsedDate['end_time'])
                ->where('end_time', '>', $parsedDate['start_time'])
                ->orderBy('date')
                ->orderBy('start_time')
                ->first();

            if ($conflict) {
                $errors[] = sprintf(
                    'Conflicts with "%s" on %s from %s to %s.',
                    $conflict->event_title,
                    $conflict->date->format('Y-m-d'),
                    $conflict->start_time->format('H:i'),
                    $conflict->end_time->format('H:i'),
                );
            }

            foreach ($parsedDate['dates'] as $date) {
                foreach ($fileSlots[$room->id][$date] ?? [] as $slot) {
                    if ($parsedDate['start_time'] < $slot['end_time'] && $parsedDate['end_time'] > $slot['start_time']) {
                        $errors[] = "Overlaps row {$slot['row_number']} in this file on {$date}.";
                        break 2;
                    }
                }
            }
        }

        $errors = array_values(array_unique($errors));
        $prepared = null;

        if ($errors === [] && $room && $parsedDate) {
            $schedule = collect($data)
                ->only([
                    'event_title', 'event_type', 'course_code', 'course_name', 'section', 'faculty_name',
                    'number_of_participants', 'requester_name', 'description', 'agenda', 'organizer', 'cfic_id',
                ])
                ->map(fn ($value) => $value === '' ? null : $value)
                ->all();
            $schedule['event_type'] = $schedule['event_type'] ?: 'other';
            $schedule['equipment_needed'] = $this->parseList($data['equipment_needed'] ?? null);
            $schedule['additional_requirements'] = $this->parseList($data['additional_requirements'] ?? null);

            $prepared = [
                'room' => $room,
                'schedule' => $schedule,
                'dates' => $parsedDate['dates'],
                'start_time' => $parsedDate['start_time'],
                'end_time' => $parsedDate['end_time'],
                'is_recurring' => $parsedDate['is_recurring'],
                'recurrence_pattern' => $parsedDate['recurrence_pattern'],
            ];
        }

        return [
            'preview' => [
                'row_number' => $record['row_number'],
                'room' => $room?->room_name ?: ($data['room_name'] ?? $data['room_id'] ?? 'Unknown'),
                'event_title' => $data['event_title'] ?? '',
                'date' => $data['date'] ?? '',
                'start_time' => $parsedDate['start_time'] ?? ($data['start_time'] ?? ''),
                'end_time' => $parsedDate['end_time'] ?? ($data['end_time'] ?? ''),
                'occurrences' => $parsedDate ? count($parsedDate['dates']) : 0,
                'is_recurring' => $parsedDate['is_recurring'] ?? false,
                'days' => ($parsedDate['is_recurring'] ?? false)
                    ? collect($parsedDate['recurrence_pattern']['days'])
                        ->map(fn (int $day) => [
                            Carbon::SUNDAY => 'Sunday',
                            Carbon::MONDAY => 'Monday',
                            Carbon::TUESDAY => 'Tuesday',
                            Carbon::WEDNESDAY => 'Wednesday',
                            Carbon::THURSDAY => 'Thursday',
                            Carbon::FRIDAY => 'Friday',
                            Carbon::SATURDAY => 'Saturday',
                        ][$day])
                        ->values()
                        ->all()
                    : [],
                'range_start' => $parsedDate['recurrence_pattern']['range_start'] ?? null,
                'range_end' => $parsedDate['recurrence_pattern']['range_end'] ?? null,
                'status' => $errors === [] ? 'valid' : 'invalid',
                'errors' => $errors,
            ],
            'prepared' => $prepared,
        ];
    }

    private function normalizeHeader(string $header): string
    {
        $header = preg_replace('/^\xEF\xBB\xBF/', '', trim($header));

        return strtolower((string) preg_replace('/[^a-zA-Z0-9]+/', '_', $header));
    }

    private function normalizeCellValue(Worksheet $sheet, mixed $value, int $column, int $row, string $header): mixed
    {
        if ($value === null) {
            return null;
        }

        $cell = $sheet->getCell([$column, $row]);

        if (is_numeric($value) && in_array($header, ['date', 'start_time', 'end_time'], true) && ExcelDate::isDateTime($cell)) {
            $date = ExcelDate::excelToDateTimeObject((float) $value);

            return $header === 'date' ? $date->format('Y-m-d') : $date->format('H:i');
        }

        return is_string($value) ? trim($value) : $value;
    }

    private function parseList(mixed $value): ?array
    {
        if (! filled($value)) {
            return null;
        }

        $items = preg_split('/[|,;]+/', (string) $value) ?: [];
        $items = array_values(array_filter(array_map('trim', $items)));

        return $items ?: null;
    }
}
