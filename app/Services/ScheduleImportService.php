<?php

namespace App\Services;
use App\Models\Schedule;
use App\Models\Room;
use App\Models\ScheduleImportLog;
use Illuminate\Support\Facades\DB;

class ScheduleImportService
{
    public function import(array $data): array
    {
        DB::beginTransaction();

        try {

            $summary = [
                'received' => count($data['schedules']),
                'inserted' => 0,
                'failed' => 0,
            ];

            $inserted = [];
            $errors = [];
            $processed = [];
            $importLog = ScheduleImportLog::create([
                'user_id' => auth()->id(),
                'source' => 'JSON Import',
                'total_records' => 0,
                'inserted_records' => 0,
                'failed_records' => 0,
                'errors' => [],
            ]);

            foreach ($data['schedules'] as $schedule) {


            /**
             * Step 1: Check Duplicate Inside Import File
             */
            // $key = implode('|', [
            //     $schedule['room_id'],
            //     $schedule['event_type'],
            //     $schedule['course_code'] ?? '',
            //     $schedule['event_title'],
            //     $schedule['section'] ?? '',
            //     $schedule['faculty_id'] ?? '',
            //     $schedule['date'],
            //     $schedule['start_time'],
            //     $schedule['end_time'],
            // ]);

            $key = sprintf(
                '%s|%s|%s|%s|%s|%s|%s|%s|%s',
                $schedule['room_id'],
                $schedule['event_type'],
                $schedule['course_code'] ?? '',
                $schedule['event_title'],
                $schedule['section'] ?? '',
                $schedule['faculty_id'] ?? '',
                $schedule['date'],
                $schedule['start_time'],
                $schedule['end_time']
            );


            if (in_array($key, $processed)) {

                $summary['failed']++;

                $errors[] = [
                    'event_title' => $schedule['event_title'],
                    'reason' => 'Duplicate schedule found inside import file.'
                ];

                continue;
            }


    $processed[] = $key;


    /**
     * Step 2: Save Schedule
     */
    // $result = $this->saveSchedule($schedule); //make way for rollback
    $result = $this->saveSchedule($schedule, $importLog->id);


    if ($result['success']) {

                    $summary['inserted']++;

                    $inserted[] = $result['schedule'];

                } else {

                    $summary['failed']++;

                    $errors[] = $result['error'];

                }

            }

            $importLog->update([
                'total_records' => $summary['received'],
                'inserted_records' => $summary['inserted'],
                'failed_records' => $summary['failed'],
                'errors' => $errors,
            ]);

            DB::commit();

            

            return [

                'success' => true,

                'summary' => $summary,

                'inserted' => $inserted,

                'errors' => $errors

            ];

        } catch (\Throwable $e) {

            DB::rollBack();

            return [

                'success' => false,

                'message' => $e->getMessage()

            ];

        }
    }

    // private function saveSchedule(array $schedule): array --- deleted to add rollback ---
    private function saveSchedule(array $schedule, int $importLogId): array
    {
         /**
         * Step 0: Validate Time Range
         */
        if ($schedule['start_time'] >= $schedule['end_time']) {

            return [
                'success' => false,
                'error' => [
                    'event_title' => $schedule['event_title'],
                    'reason' => 'Invalid time range. End time must be later than start time.',
                ],
            ];
        }
        /**
         * Step 1: (Next)
         * Check Duplicate Import
         */
        $duplicate = $this->checkDuplicateSchedule($schedule);

        if ($duplicate) {

            return [
                'success' => false,
                'error' => [
                    'event_title' => $schedule['event_title'],
                    'reason' => 'Duplicate schedule already exists.',
                    'existing_schedule' => [
                        'title' => $duplicate->event_title,
                        'date' => $duplicate->date,
                        'start_time' => $duplicate->start_time,
                        'end_time' => $duplicate->end_time,
                    ]
                ],
            ];

        }
        /**
         * Step 2: Validate Room
         */
        if ($error = $this->validateRoom($schedule['room_id'])) {

            return [
                'success' => false,
                'error' => [
                    'event_title' => $schedule['event_title'],
                    'reason' => $error['reason'],
                ],
            ];
        }

        /**
         * Step 3: Check Room Conflict
         */
        $conflict = $this->checkRoomConflict($schedule);

        if ($conflict) {

            return [
                'success' => false,
                'error' => [
                    'event_title' => $schedule['event_title'],
                    'reason' => 'Room is already occupied.',
                    'conflicting_schedule' => [
                        'title' => $conflict->event_title,
                        'date' => $conflict->date,
                        'start_time' => $conflict->start_time,
                        'end_time' => $conflict->end_time,
                    ]
                ],
            ];

        }

        /**
         * Step 4: (Next)
         * Check Faculty Conflict
         */
        $facultyConflict = $this->checkFacultyConflict($schedule);

        if ($facultyConflict) {

            return [
                'success' => false,
                'error' => [
                    'event_title' => $schedule['event_title'],
                    'reason' => 'Faculty is already assigned to another schedule.',
                    'conflicting_schedule' => [
                        'title' => $facultyConflict->event_title,
                        'date' => $facultyConflict->date,
                        'start_time' => $facultyConflict->start_time,
                        'end_time' => $facultyConflict->end_time,
                    ]
                ],
            ];

        }
        

        /**
         * Step 5:
         * Save to Database
         */
        $schedule['import_log_id'] = $importLogId;
        logger($schedule);

        $createdSchedule = Schedule::create($schedule);

        return [
            'success' => true,
            'schedule' => $createdSchedule,
        ];
    }

    private function checkRoomConflict(array $schedule): ?Schedule
    {
         $query = Schedule::query();

        $query->where('room_id', $schedule['room_id']);
        $query->where('date', $schedule['date']);

        $query->where(function ($query) use ($schedule) {

            $query->where(function ($q) use ($schedule) {

                $q->where('start_time', '<', $schedule['end_time']);
                $q->where('end_time', '>', $schedule['start_time']);

            });

        });

        return $query->first();
    }

    private function validateRoom(int $roomId): ?array
    {
        // $room = Room::find($roomId); error
        $room = Room::query()->find($roomId);

        if (!$room) {
            return [
                'success' => false,
                'reason' => 'Room does not exist.'
            ];
        }

        return null;
    }

    private function checkFacultyConflict(array $schedule)
    {
        // return Schedule::where('faculty_id', $schedule['faculty_id'])
        //     ->whereDate('date', $schedule['date'])
        //     ->where(function ($query) use ($schedule) {

        //         $query->where('start_time', '<', $schedule['end_time'])
        //             ->where('end_time', '>', $schedule['start_time']);

        //     })
        //     ->first();
        // Skip faculty conflict checking if no faculty is assigned

        // accepts the faculty even if null
        if (empty($schedule['faculty_id'])) {
            return null;
        }

        return Schedule::where('faculty_id', $schedule['faculty_id'])
            ->whereDate('date', $schedule['date'])
            ->where('start_time', '<', $schedule['end_time'])
            ->where('end_time', '>', $schedule['start_time'])
            ->first();
    }

    private function checkDuplicateSchedule(array $schedule)
    {
        return Schedule::where('room_id', $schedule['room_id'])
            ->where('event_title', $schedule['event_title'])
            ->whereDate('date', $schedule['date'])
            ->where('start_time', $schedule['start_time'])
            ->where('end_time', $schedule['end_time'])
            ->first();
    }

    public function rollbackImport(int $importLogId): array
    {
        DB::beginTransaction();

        try {

            $log = ScheduleImportLog::find($importLogId);

            if (!$log) {
                return [
                    'success' => false,
                    'message' => 'Import log not found.'
                ];
            }

            $deleted = Schedule::where('import_log_id', $importLogId)->delete();

            $log->delete();

            DB::commit();

            return [
                'success' => true,
                'deleted_records' => $deleted,
                'message' => 'Import rolled back successfully.'
            ];

        } catch (\Throwable $e) {

            DB::rollBack();

            return [
                'success' => false,
                'message' => $e->getMessage()
            ];

        }
    }



}