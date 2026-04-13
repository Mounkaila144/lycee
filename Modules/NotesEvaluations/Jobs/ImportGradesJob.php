<?php

namespace Modules\NotesEvaluations\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\UploadedFile;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Modules\NotesEvaluations\Notifications\GradeImportCompletedNotification;
use Modules\NotesEvaluations\Services\GradeImportService;
use Modules\StructureAcademique\Entities\ModuleEvaluationConfig;
use Modules\UsersGuard\Entities\User;

class ImportGradesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 300;

    public function __construct(
        public string $filePath,
        public int $evaluationId,
        public int $teacherId,
        public string $mode
    ) {}

    public function handle(GradeImportService $service): void
    {
        $evaluation = ModuleEvaluationConfig::find($this->evaluationId);
        $teacher = User::find($this->teacherId);

        if (! $evaluation || ! $teacher) {
            return;
        }

        // Create UploadedFile from stored path
        $localPath = Storage::disk('local')->path($this->filePath);
        $file = new UploadedFile($localPath, 'import.xlsx');

        try {
            $report = $service->import($file, $evaluation, $teacher, $this->mode);

            // Notify teacher
            $teacher->notify(new GradeImportCompletedNotification($report, $evaluation));
        } finally {
            // Cleanup temp file
            Storage::disk('local')->delete($this->filePath);
        }
    }

    public function failed(\Throwable $exception): void
    {
        $teacher = User::find($this->teacherId);
        $evaluation = ModuleEvaluationConfig::find($this->evaluationId);

        if ($teacher && $evaluation) {
            $teacher->notify(new GradeImportCompletedNotification(
                [
                    'imported' => 0,
                    'updated' => 0,
                    'skipped' => 0,
                    'errors' => [['error' => $exception->getMessage()]],
                    'failed' => true,
                ],
                $evaluation
            ));
        }

        // Cleanup temp file
        Storage::disk('local')->delete($this->filePath);
    }
}
