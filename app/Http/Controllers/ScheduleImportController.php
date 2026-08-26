<?php

namespace App\Http\Controllers;

use App\Models\Room;
use App\Services\ScheduleImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ScheduleImportController extends Controller
{
    public function __construct(
        private readonly ScheduleImportService $scheduleImport,
    ) {}

    public function index(): Response
    {
        return Inertia::render('ScheduleImport', [
            'roomCount' => Room::count(),
            'maxRows' => ScheduleImportService::MAX_ROWS,
            'supportedFormats' => ['CSV', 'XLSX', 'XLS'],
        ]);
    }

    public function preview(Request $request): JsonResponse
    {
        $file = $this->validateFile($request);

        try {
            $analysis = $this->scheduleImport->analyze($file);
        } catch (Throwable $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
                'errors' => ['file' => [$exception->getMessage()]],
            ], 422);
        }

        unset($analysis['prepared']);

        return response()->json([
            'success' => true,
            ...$analysis,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $file = $this->validateFile($request);

        try {
            $result = DB::transaction(function () use ($file) {
                $initial = $this->scheduleImport->analyze($file);
                $roomIds = collect($initial['prepared'])
                    ->pluck('room.id')
                    ->unique()
                    ->values();

                if ($roomIds->isNotEmpty()) {
                    Room::whereKey($roomIds)->lockForUpdate()->get();
                }

                $analysis = $this->scheduleImport->analyze($file);

                if ($analysis['summary']['invalid_rows'] > 0) {
                    return ['analysis' => $analysis, 'created' => collect()];
                }

                return [
                    'analysis' => $analysis,
                    'created' => $this->scheduleImport->create($analysis['prepared']),
                ];
            });
        } catch (Throwable $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
                'errors' => ['file' => [$exception->getMessage()]],
            ], 422);
        }

        if ($result['analysis']['summary']['invalid_rows'] > 0) {
            unset($result['analysis']['prepared']);

            return response()->json([
                'success' => false,
                'message' => 'Import blocked. Correct the invalid rows and upload the file again.',
                ...$result['analysis'],
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => "{$result['created']->count()} schedules imported successfully.",
            'imported_rows' => $result['analysis']['summary']['valid_rows'],
            'schedule_count' => $result['created']->count(),
        ], 201);
    }

    public function csvTemplate(): StreamedResponse
    {
        return response()->streamDownload(function () {
            $stream = fopen('php://output', 'w');
            fputcsv($stream, ScheduleImportService::HEADERS);
            fclose($stream);
        }, 'schedule-import-template.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function excelTemplate(): BinaryFileResponse
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Schedule Import');
        $sheet->fromArray(ScheduleImportService::HEADERS, null, 'A1');
        $lastColumn = $sheet->getHighestColumn();

        $sheet->freezePane('A2');
        $sheet->setAutoFilter("A1:{$lastColumn}1");
        $sheet->getStyle("A1:{$lastColumn}1")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['ARGB' => 'FFFFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['ARGB' => 'FF005740']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['ARGB' => 'FFD8E5DF']]],
        ]);
        $sheet->getStyle("A1:{$lastColumn}1")->getAlignment()->setWrapText(true);
        $sheet->getRowDimension(1)->setRowHeight(34);

        $sheet->getComment('E1')->getText()->createTextRun(
            "Single schedule: YYYY-MM-DD (example: 2026-08-27).\n".
            "Recurring schedule: DAYS from START_MONTH to END_MONTH START_TIME-END_TIME START_YEAR-END_YEAR.\n".
            'Example: T-TH from June to May 10:am-11:am 2026-2027.'
        );

        foreach (range('A', $lastColumn) as $column) {
            $sheet->getColumnDimension($column)->setWidth(in_array($column, ['E', 'N'], true) ? 44 : 20);
        }

        $examples = $spreadsheet->createSheet();
        $examples->setTitle('Examples - Do Not Import');
        $examples->setCellValue('A1', 'REFERENCE ONLY — DO NOT UPLOAD THESE ROWS');
        $examples->mergeCells("A1:{$lastColumn}1");
        $examples->setCellValue('A2', 'Copy an example to the Schedule Import sheet, then replace every example value, including cfic_id. Existing schedules are not updated by importing them again.');
        $examples->mergeCells("A2:{$lastColumn}2");
        $examples->fromArray(ScheduleImportService::HEADERS, null, 'A3');
        $examples->fromArray($this->templateExampleRows(), null, 'A4');
        $examplesLastRow = $examples->getHighestRow();
        $examples->freezePane('A4');
        $examples->setAutoFilter("A3:{$lastColumn}{$examplesLastRow}");
        $examples->getStyle("A1:{$lastColumn}1")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['ARGB' => Color::COLOR_WHITE], 'size' => 14],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['ARGB' => 'FF9C382F']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $examples->getStyle("A2:{$lastColumn}2")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['ARGB' => 'FF5F3B13']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['ARGB' => 'FFFFF3D6']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);
        $examples->getStyle("A3:{$lastColumn}3")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['ARGB' => Color::COLOR_WHITE]],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['ARGB' => 'FF52635E']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['ARGB' => 'FFD8E5DF']]],
        ]);
        $examples->getStyle("A3:{$lastColumn}{$examplesLastRow}")->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
        $examples->getRowDimension(1)->setRowHeight(30);
        $examples->getRowDimension(2)->setRowHeight(42);
        $examples->getRowDimension(3)->setRowHeight(34);
        $examples->getComment('E5')->getText()->createTextRun(
            "Days: Tuesday and Thursday\n".
            "Date range: June 1, 2026 to May 31, 2027\n".
            "Time: 10:00 AM to 11:00 AM\n".
            'start_time/end_time columns: Leave blank for recurring schedules'
        );
        foreach (range('A', $lastColumn) as $column) {
            $examples->getColumnDimension($column)->setWidth(in_array($column, ['E', 'N'], true) ? 44 : 20);
        }

        $instructions = $spreadsheet->createSheet();
        $instructions->setTitle('Instructions');
        $instructions->fromArray([
            ['Schedule Import Instructions'],
            ['1. The Schedule Import sheet is intentionally blank. Enter real schedules starting on row 2.'],
            ['2. The Examples - Do Not Import sheet is for reference only. Never upload its example rows unchanged.'],
            ['3. If you copy an example, replace every value, including event_title, room, date, time, and cfic_id.'],
            ['4. Save the workbook and upload the newly saved file. Confirm the filename before previewing.'],
            ['5. Keep all Schedule Import column headers unchanged.'],
            ['6. Provide either room_id or room_name, never both.'],
            ['7. For one date, use YYYY-MM-DD plus start_time and end_time in 24-hour HH:MM format.'],
            ['8. Recurring example: T-TH from June to May 10:am-11:am 2026-2027. Leave start_time and end_time blank.'],
            ['9. event_type must be class, meeting, event, or other.'],
            ['10. Separate equipment or additional requirements with a vertical bar (|).'],
            ['11. Every row is checked before import. Any invalid or conflicting row blocks the whole file.'],
            ['12. Import creates new schedules only. It does not update existing cfic_id records; edit those schedules in the Schedule page or remove them before reimporting.'],
            [],
            ['Recurring Schedule Breakdown'],
            ['Part', 'Value', 'Meaning'],
            ['Days', 'T-TH', 'Every Tuesday and Thursday'],
            ['Start month', 'June', 'Begins June 1, 2026'],
            ['End month', 'May', 'Ends May 31, 2027'],
            ['Time', '10:am-11:am', '10:00 AM to 11:00 AM'],
            ['Academic year', '2026-2027', 'June 2026 through May 2027'],
            ['Complete value', 'T-TH from June to May 10:am-11:am 2026-2027', 'Enter this entire value in the date column'],
        ], null, 'A1');
        $instructions->getStyle('A1')->getFont()->setBold(true)->setSize(16)->getColor()->setARGB('FF005740');
        $instructions->mergeCells('A1:C1');
        $instructions->getStyle('A15:C15')->getFont()->setBold(true)->setSize(13)->getColor()->setARGB('FF005740');
        $instructions->mergeCells('A15:C15');
        $instructions->getStyle('A16:C16')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['ARGB' => Color::COLOR_WHITE]],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['ARGB' => 'FF005740']],
        ]);
        $instructions->getColumnDimension('A')->setWidth(23);
        $instructions->getColumnDimension('B')->setWidth(52);
        $instructions->getColumnDimension('C')->setWidth(48);
        $instructions->getStyle('A1:C22')->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
        foreach (range(2, 13) as $row) {
            $instructions->mergeCells("A{$row}:C{$row}");
        }
        $instructions->getRowDimension(3)->setRowHeight(34);
        $instructions->getRowDimension(4)->setRowHeight(34);
        $instructions->getRowDimension(13)->setRowHeight(44);
        $instructions->getRowDimension(22)->setRowHeight(44);

        $path = tempnam(sys_get_temp_dir(), 'schedule-template-');
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        return response()
            ->download($path, 'schedule-import-template.xlsx', [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])
            ->deleteFileAfterSend(true);
    }

    private function validateFile(Request $request): \Illuminate\Http\UploadedFile
    {
        return $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx,xls', 'max:10240'],
        ])['file'];
    }

    private function templateExampleRows(): array
    {
        $rooms = Room::query()
            ->whereNotNull('room_name')
            ->where('room_name', '!=', '')
            ->orderBy('room_name')
            ->limit(2)
            ->get();

        return $this->scheduleImport->exampleRows($rooms);
    }
}
