<?php

namespace App\Jobs;

use App\Services\RangeProjectPdfExportService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ExportRangeProjectPdfs implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600;

    public int $tries = 1;

    public bool $failOnTimeout = true;

    public function __construct(
        public string $exportId,
        public int $userId,
        public array $filters
    ) {}

    public function handle(RangeProjectPdfExportService $service): void
    {
        $zip = null;
        $zipPath = null;
        $startedAt = Carbon::now();
        $startedTimer = microtime(true);

        Log::info('Descarga masiva iniciada por el worker.', [
            'export_id' => $this->exportId,
            'user_id' => $this->userId,
            'date_range' => $this->filters['dateRange'] ?? null,
            'memory_mb' => $this->memoryUsageMb(),
        ]);

        try {
            [$startDate, $endDate] = $service->parseDateRange($this->filters['dateRange'] ?? []);
            $query = $service->projectsQuery($this->filters, $startDate, $endDate);
            $totalProjects = (clone $query)->count();

            Log::info('Descarga masiva: OTs encontradas.', [
                'export_id' => $this->exportId,
                'total' => $totalProjects,
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
                'memory_mb' => $this->memoryUsageMb(),
            ]);

            if ($totalProjects === 0) {
                throw new \RuntimeException('No hay OTs para el rango seleccionado.');
            }

            $tempDir = storage_path('app/temp');

            if (! file_exists($tempDir)) {
                mkdir($tempDir, 0755, true);
            }

            $zipFileName = 'ots_rango_'.$startDate->format('Y-m-d').'_a_'.$endDate->format('Y-m-d').'_'.date('His').'.zip';
            $zipPath = $tempDir.'/'.$zipFileName;
            $zip = new \ZipArchive;

            if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
                throw new \RuntimeException('No se pudo crear el ZIP de descarga.');
            }

            Log::info('Descarga masiva: ZIP creado.', [
                'export_id' => $this->exportId,
                'zip_path' => $zipPath,
                'memory_mb' => $this->memoryUsageMb(),
            ]);

            $processedProjects = 0;
            $generatedProjects = 0;
            $failedProjects = [];

            $this->updateStatus([
                'status' => 'running',
                'total' => $totalProjects,
                'processed' => 0,
                'generated' => 0,
                'failed' => 0,
                'message' => 'Generando PDFs...',
            ]);

            $query->orderBy('id')->chunkById(25, function ($projects) use (
                $service,
                $zip,
                &$processedProjects,
                &$generatedProjects,
                &$failedProjects,
                $totalProjects
            ) {
                foreach ($projects as $project) {
                    $processedProjects++;
                    $projectStartedTimer = microtime(true);

                    Log::info('Descarga masiva: iniciando OT.', [
                        'export_id' => $this->exportId,
                        'ot_id' => $project->id,
                        'ot_name' => $project->name,
                        'processed' => $processedProjects,
                        'total' => $totalProjects,
                        'memory_mb' => $this->memoryUsageMb(),
                    ]);

                    try {
                        $service->assertProjectAttachmentsExist($project);

                        Log::debug('Descarga masiva: fotos validadas.', [
                            'export_id' => $this->exportId,
                            'ot_id' => $project->id,
                        ]);

                        $pdfContent = $service->buildProjectPdfContent($project);

                        if ($pdfContent === '') {
                            throw new \RuntimeException('El PDF quedó vacío.');
                        }

                        $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $project->name);
                        $pdfName = $project->id.'_'.$safeName.'.pdf';

                        if (! $zip->addFromString($pdfName, $pdfContent)) {
                            throw new \RuntimeException('No se pudo agregar el PDF al ZIP.');
                        }

                        $generatedProjects++;

                        Log::info('Descarga masiva: OT agregada al ZIP.', [
                            'export_id' => $this->exportId,
                            'ot_id' => $project->id,
                            'processed' => $processedProjects,
                            'total' => $totalProjects,
                            'duration_seconds' => round(microtime(true) - $projectStartedTimer, 2),
                            'pdf_size_kb' => round(strlen($pdfContent) / 1024, 2),
                            'memory_mb' => $this->memoryUsageMb(),
                        ]);
                    } catch (\Throwable $exception) {
                        $failedProjects[] = [
                            'id' => $project->id,
                            'name' => $project->name,
                            'error' => $exception->getMessage(),
                        ];

                        Log::error('Descarga masiva: error generando OT.', [
                            'export_id' => $this->exportId,
                            'ot_id' => $project->id,
                            'ot_name' => $project->name,
                            'processed' => $processedProjects,
                            'total' => $totalProjects,
                            'duration_seconds' => round(microtime(true) - $projectStartedTimer, 2),
                            'memory_mb' => $this->memoryUsageMb(),
                            'exception' => $exception,
                        ]);
                    }

                    $this->updateStatus([
                        'status' => 'running',
                        'total' => $totalProjects,
                        'processed' => $processedProjects,
                        'generated' => $generatedProjects,
                        'failed' => count($failedProjects),
                        'message' => "Procesando OT {$processedProjects} de {$totalProjects}...",
                    ]);
                }

                Log::info('Descarga masiva: bloque procesado.', [
                    'export_id' => $this->exportId,
                    'processed' => $processedProjects,
                    'total' => $totalProjects,
                    'generated' => $generatedProjects,
                    'failed' => count($failedProjects),
                    'memory_mb' => $this->memoryUsageMb(),
                ]);
            });

            $durationSeconds = round(microtime(true) - $startedTimer, 2);
            $finishedAt = Carbon::now();
            $report = $service->buildReport(
                $startDate,
                $endDate,
                $startedAt,
                $finishedAt,
                $durationSeconds,
                $totalProjects,
                $processedProjects,
                $generatedProjects,
                $failedProjects
            );

            if (! $zip->addFromString('reporte_descarga.txt', $report)) {
                throw new \RuntimeException('No se pudo agregar el reporte al ZIP.');
            }

            if (! $zip->close()) {
                throw new \RuntimeException('El servidor no pudo cerrar correctamente el ZIP.');
            }

            $zip = null;

            if (! is_file($zipPath) || filesize($zipPath) < 22) {
                throw new \RuntimeException('El ZIP quedó vacío o incompleto después de generarse.');
            }

            $verificationZip = new \ZipArchive;
            $verificationResult = $verificationZip->open($zipPath, \ZipArchive::CHECKCONS);

            if ($verificationResult !== true || $verificationZip->numFiles < 1) {
                if ($verificationResult === true) {
                    $verificationZip->close();
                }

                throw new \RuntimeException('El ZIP generado no pasó la validación de integridad.');
            }

            $verificationZip->close();

            $this->updateStatus([
                'status' => 'completed',
                'total' => $totalProjects,
                'processed' => $processedProjects,
                'generated' => $generatedProjects,
                'failed' => count($failedProjects),
                'duration' => $service->formatDuration($durationSeconds),
                'file_name' => $zipFileName,
                'zip_path' => $zipPath,
                'message' => 'La descarga está lista.',
            ]);

            Log::info('Descarga masiva finalizada correctamente.', [
                'export_id' => $this->exportId,
                'total' => $totalProjects,
                'processed' => $processedProjects,
                'generated' => $generatedProjects,
                'failed' => count($failedProjects),
                'duration_seconds' => $durationSeconds,
                'zip_path' => $zipPath,
                'zip_size_mb' => file_exists($zipPath) ? round(filesize($zipPath) / 1024 / 1024, 2) : null,
                'memory_mb' => $this->memoryUsageMb(),
            ]);
        } catch (\Throwable $exception) {
            if ($zip instanceof \ZipArchive) {
                $zip->close();
            }

            if ($zipPath && file_exists($zipPath)) {
                @unlink($zipPath);
            }

            Log::error('Descarga masiva fallida en segundo plano.', [
                'export_id' => $this->exportId,
                'duration_seconds' => round(microtime(true) - $startedTimer, 2),
                'memory_mb' => $this->memoryUsageMb(),
                'zip_path' => $zipPath,
                'exception' => $exception,
            ]);
            $this->updateStatus([
                'status' => 'failed',
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    public function failed(?\Throwable $exception): void
    {
        Log::error('Descarga masiva marcada como failed por Laravel.', [
            'export_id' => $this->exportId,
            'exception' => $exception,
            'memory_mb' => $this->memoryUsageMb(),
        ]);

        $this->updateStatus([
            'status' => 'failed',
            'message' => $exception?->getMessage() ?? 'El proceso de descarga falló.',
        ]);
    }

    private function updateStatus(array $status): void
    {
        Cache::put(
            'range-pdf-export:'.$this->exportId,
            array_merge($status, ['user_id' => $this->userId, 'export_id' => $this->exportId]),
            now()->addDay()
        );
    }

    private function memoryUsageMb(): float
    {
        return round(memory_get_usage(true) / 1024 / 1024, 2);
    }
}
