<?php

use App\Models\Room;
use App\Models\Schedule;
use App\Models\UserAccount;
use App\Services\ScheduleImportService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

function scheduleImportAdmin(): UserAccount
{
    return UserAccount::create([
        'username' => 'schedule-import-admin',
        'email' => 'schedule-import@example.test',
        'password' => Hash::make('password'),
        'first_name' => 'Schedule',
        'last_name' => 'Administrator',
        'user_type' => 'admin',
        'account_status' => 'active',
    ]);
}

function scheduleImportSession(UserAccount $admin): array
{
    return ['user' => [
        'id' => $admin->id,
        'username' => $admin->username,
        'email' => $admin->email,
        'name' => trim("{$admin->first_name} {$admin->last_name}"),
        'role' => $admin->user_type,
        'permissions' => [],
    ]];
}

function scheduleImportCsv(array $rows): UploadedFile
{
    $stream = fopen('php://temp', 'w+');
    fputcsv($stream, ScheduleImportService::HEADERS);

    foreach ($rows as $row) {
        fputcsv($stream, $row);
    }

    rewind($stream);
    $contents = stream_get_contents($stream);
    fclose($stream);

    return UploadedFile::fake()->createWithContent('schedule-import.csv', $contents);
}

function scheduleImportRow(array $overrides = []): array
{
    $values = array_combine(ScheduleImportService::HEADERS, [
        '', 'Test Import Room', 'Imported Meeting', 'meeting', '2026-09-10', '09:00', '10:00',
        '', '', '', 'Test Faculty', '20', 'Test Requester', '', '', 'UP Cebu', '', '', 'IMPORT-001',
    ]);

    return array_values(array_replace($values, $overrides));
}

function scheduleImportXlsx(array $rows): UploadedFile
{
    $spreadsheet = new Spreadsheet;
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->fromArray(ScheduleImportService::HEADERS, null, 'A1');
    $sheet->fromArray($rows, null, 'A2');
    $path = tempnam(sys_get_temp_dir(), 'schedule-import-test-');
    (new Xlsx($spreadsheet))->save($path);
    $spreadsheet->disconnectWorksheets();

    return new UploadedFile($path, 'schedule-import.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
}

it('renders the manual schedule import page', function () {
    $admin = scheduleImportAdmin();

    $this->actingAs($admin)
        ->withSession(scheduleImportSession($admin))
        ->get(route('schedules.import.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ScheduleImport')
            ->has('supportedFormats', 3)
            ->where('maxRows', 500));
});

it('downloads valid CSV and Excel templates', function () {
    $admin = scheduleImportAdmin();
    Room::create([
        'room_name' => 'Actual Campus Room',
        'room_code' => 'ACTUAL-ROOM-1',
        'status' => 'available',
    ]);

    $csv = $this->actingAs($admin)
        ->withSession(scheduleImportSession($admin))
        ->get(route('schedules.import.template.csv'));
    $csv->assertOk()->assertDownload('schedule-import-template.csv');
    $csvContent = $csv->streamedContent();
    $csvLines = array_values(array_filter(preg_split('/\R/', trim($csvContent))));
    $csvRows = array_map('str_getcsv', $csvLines);
    expect($csvLines)->toHaveCount(3)
        ->and($csvLines[0])->toContain('room_id,room_name,event_title,event_type,date,start_time,end_time')
        ->and($csvRows[1][1])->toBe('Actual Campus Room')
        ->and($csvRows[1][2])->toBe('Faculty Meeting')
        ->and($csvRows[1][4])->toBe('2026-08-27')
        ->and($csvRows[2][1])->toBe('Actual Campus Room')
        ->and($csvRows[2][2])->toBe('Recurring Class')
        ->and($csvRows[2][4])->toBe('T-TH from June to May 10:am-11:am 2026-2027');

    $excel = $this->actingAs($admin)
        ->withSession(scheduleImportSession($admin))
        ->get(route('schedules.import.template.excel'));
    $excel->assertOk()->assertDownload('schedule-import-template.xlsx');

    $workbook = IOFactory::load($excel->baseResponse->getFile()->getPathname());
    expect($workbook->getSheetNames())->toBe(['Schedule Import', 'Instructions'])
        ->and($workbook->getSheet(0)->getCell('A1')->getValue())->toBe('room_id')
        ->and($workbook->getSheet(0)->getCell('C1')->getValue())->toBe('event_title')
        ->and($workbook->getSheet(0)->getHighestDataRow())->toBe(3)
        ->and($workbook->getSheet(0)->getCell('B2')->getValue())->toBe('Actual Campus Room')
        ->and($workbook->getSheet(0)->getCell('B3')->getValue())->toBe('Actual Campus Room')
        ->and($workbook->getSheet(0)->getComment('E3')->getText()->getPlainText())->toContain('Tuesday and Thursday')
        ->and($workbook->getSheet(1)->getCell('A1')->getValue())->toBe('Schedule Import Instructions')
        ->and($workbook->getSheet(1)->getCell('A2')->getValue())->toContain('headers unchanged')
        ->and($workbook->getSheet(1)->getCell('A10')->getValue())->toBe('Recurring Schedule Breakdown')
        ->and($workbook->getSheet(1)->getCell('C17')->getValue())->toBe('Enter this entire value in the date column');
    $workbook->disconnectWorksheets();

    $this->actingAs($admin)
        ->withSession(scheduleImportSession($admin))
        ->post(route('schedules.import.preview'), [
            'file' => UploadedFile::fake()->createWithContent('schedule-import-template.csv', $csvContent),
        ])
        ->assertOk()
        ->assertJsonPath('summary.total_rows', 2)
        ->assertJsonPath('summary.valid_rows', 2)
        ->assertJsonPath('summary.invalid_rows', 0)
        ->assertJsonPath('summary.schedule_occurrences', 105);

    $this->actingAs($admin)
        ->withSession(scheduleImportSession($admin))
        ->post(route('schedules.import.preview'), [
            'file' => new UploadedFile(
                $excel->baseResponse->getFile()->getPathname(),
                'schedule-import-template.xlsx',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                null,
                true,
            ),
        ])
        ->assertOk()
        ->assertJsonPath('summary.total_rows', 2)
        ->assertJsonPath('summary.valid_rows', 2)
        ->assertJsonPath('summary.invalid_rows', 0)
        ->assertJsonPath('summary.schedule_occurrences', 105);
});

it('previews and imports a valid CSV file', function () {
    $admin = scheduleImportAdmin();
    $room = Room::create([
        'room_name' => 'Test Import Room',
        'room_code' => 'IMPORT-ROOM-1',
        'status' => 'available',
    ]);

    $this->actingAs($admin)
        ->withSession(scheduleImportSession($admin))
        ->post(route('schedules.import.preview'), ['file' => scheduleImportCsv([scheduleImportRow()])])
        ->assertOk()
        ->assertJsonPath('summary.valid_rows', 1)
        ->assertJsonPath('summary.invalid_rows', 0)
        ->assertJsonPath('rows.0.is_recurring', false)
        ->assertJsonPath('rows.0.status', 'valid');

    $this->actingAs($admin)
        ->withSession(scheduleImportSession($admin))
        ->post(route('schedules.import.store'), ['file' => scheduleImportCsv([scheduleImportRow()])])
        ->assertCreated()
        ->assertJsonPath('imported_rows', 1)
        ->assertJsonPath('schedule_count', 1);

    $this->assertDatabaseHas('schedules', [
        'room_id' => $room->id,
        'event_title' => 'Imported Meeting',
        'status' => 'pending',
    ]);
});

it('returns a detailed recurring schedule preview', function () {
    $admin = scheduleImportAdmin();
    Room::create([
        'room_name' => 'Test Import Room',
        'room_code' => 'IMPORT-ROOM-RECURRING',
        'status' => 'available',
    ]);

    $this->actingAs($admin)
        ->withSession(scheduleImportSession($admin))
        ->post(route('schedules.import.preview'), ['file' => scheduleImportCsv([scheduleImportRow([
            'event_title' => 'Recurring Class',
            'event_type' => 'class',
            'date' => 'T-TH from June to May 10:am-11:am 2026-2027',
            'start_time' => '',
            'end_time' => '',
        ])])])
        ->assertOk()
        ->assertJsonPath('rows.0.is_recurring', true)
        ->assertJsonPath('rows.0.days.0', 'Tuesday')
        ->assertJsonPath('rows.0.days.1', 'Thursday')
        ->assertJsonPath('rows.0.range_start', '2026-06-01')
        ->assertJsonPath('rows.0.range_end', '2027-05-31')
        ->assertJsonPath('rows.0.start_time', '10:00')
        ->assertJsonPath('rows.0.end_time', '11:00')
        ->assertJsonPath('rows.0.occurrences', 104);
});

it('imports and exposes every month in an academic-year recurring schedule', function () {
    $admin = scheduleImportAdmin();
    $room = Room::create([
        'room_name' => 'Test Import Room',
        'room_code' => 'IMPORT-ROOM-FULL-YEAR',
        'status' => 'available',
    ]);
    $recurringRow = scheduleImportRow([
        'event_title' => 'Academic Year Class',
        'event_type' => 'class',
        'date' => 'T-TH from June to May 10:am-11:am 2026-2027',
        'start_time' => '',
        'end_time' => '',
    ]);

    $this->actingAs($admin)
        ->withSession(scheduleImportSession($admin))
        ->post(route('schedules.import.store'), ['file' => scheduleImportCsv([$recurringRow])])
        ->assertCreated()
        ->assertJsonPath('schedule_count', 104);

    $months = Schedule::query()
        ->where('room_id', $room->id)
        ->orderBy('date')
        ->get()
        ->map(fn (Schedule $schedule) => $schedule->date->format('Y-m'))
        ->unique()
        ->values()
        ->all();

    expect($months)->toBe([
        '2026-06', '2026-07', '2026-08', '2026-09', '2026-10', '2026-11',
        '2026-12', '2027-01', '2027-02', '2027-03', '2027-04', '2027-05',
    ]);

    $this->withSession(scheduleImportSession($admin))
        ->get(route('main.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('allRooms', 1)
            ->has('allRooms.0.schedules', 104)
            ->where('allRooms.0.schedules.0.date', '2026-06-02')
            ->where('allRooms.0.schedules.103.date', '2027-05-27'));
});

it('supports importing an Excel workbook', function () {
    $admin = scheduleImportAdmin();
    Room::create([
        'room_name' => 'Test Import Room',
        'room_code' => 'IMPORT-ROOM-2',
        'status' => 'available',
    ]);

    $this->actingAs($admin)
        ->withSession(scheduleImportSession($admin))
        ->post(route('schedules.import.store'), ['file' => scheduleImportXlsx([scheduleImportRow([
            'event_title' => 'Excel Imported Meeting',
            'cfic_id' => 'IMPORT-XLSX-001',
        ])])])
        ->assertCreated()
        ->assertJsonPath('schedule_count', 1);

    $this->assertDatabaseHas('schedules', [
        'event_title' => 'Excel Imported Meeting',
        'cfic_id' => 'IMPORT-XLSX-001',
    ]);
});

it('blocks the entire import when a row conflicts', function () {
    $admin = scheduleImportAdmin();
    $room = Room::create([
        'room_name' => 'Test Import Room',
        'room_code' => 'IMPORT-ROOM-3',
        'status' => 'available',
    ]);
    Schedule::create([
        'room_id' => $room->id,
        'event_title' => 'Existing Schedule',
        'event_type' => 'meeting',
        'date' => '2026-09-10',
        'start_time' => '09:30',
        'end_time' => '10:30',
        'day_of_week' => 'Thursday',
        'status' => 'approved',
    ]);

    $this->actingAs($admin)
        ->withSession(scheduleImportSession($admin))
        ->post(route('schedules.import.store'), ['file' => scheduleImportCsv([
            scheduleImportRow(),
            scheduleImportRow([
                'event_title' => 'Otherwise Valid Schedule',
                'date' => '2026-09-11',
                'cfic_id' => 'IMPORT-002',
            ]),
        ])])
        ->assertUnprocessable()
        ->assertJsonPath('summary.invalid_rows', 1)
        ->assertJsonPath('rows.0.status', 'invalid');

    expect(Schedule::where('room_id', $room->id)->count())->toBe(1);
});

it('reports unknown rooms and duplicate rows before import', function () {
    $admin = scheduleImportAdmin();
    Room::create([
        'room_name' => 'Test Import Room',
        'room_code' => 'IMPORT-ROOM-4',
        'status' => 'available',
    ]);

    $this->actingAs($admin)
        ->withSession(scheduleImportSession($admin))
        ->post(route('schedules.import.preview'), ['file' => scheduleImportCsv([
            scheduleImportRow(),
            scheduleImportRow(['event_title' => 'Duplicate Slot', 'cfic_id' => 'IMPORT-003']),
            scheduleImportRow(['room_name' => 'Missing Room', 'event_title' => 'Unknown Room']),
        ])])
        ->assertOk()
        ->assertJsonPath('summary.valid_rows', 1)
        ->assertJsonPath('summary.invalid_rows', 2)
        ->assertJsonPath('rows.1.status', 'invalid')
        ->assertJsonPath('rows.2.status', 'invalid')
        ->assertJsonPath('rows.2.errors.0', 'Room "Missing Room" was not found. Select a current room or download a fresh template.');
});
