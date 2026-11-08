<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\OwnerCompany;
use App\Models\Project;
use App\Models\Task;
use App\Models\TimeLog;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

class RangeProjectPdfExportService
{
    public function parseDateRange(array $dateRange): array
    {
        if (count($dateRange) !== 2 || ! $dateRange[0] || ! $dateRange[1]) {
            throw new InvalidArgumentException('Debes seleccionar un rango de fechas completo.');
        }

        try {
            $startDate = Carbon::parse($dateRange[0])->startOfDay();
            $endDate = Carbon::parse($dateRange[1])->endOfDay();
        } catch (\Throwable) {
            throw new InvalidArgumentException('El rango de fechas no es válido.');
        }

        if ($startDate->greaterThan($endDate)) {
            throw new InvalidArgumentException('La fecha inicial no puede ser posterior a la fecha final.');
        }

        return [$startDate, $endDate];
    }

    public function projectsQuery(array $filters, Carbon $startDate, Carbon $endDate): Builder
    {
        return Project::query()
            ->where('default', 0)
            ->whereBetween('due_on', [$startDate, $endDate])
            ->when($filters['games'] ?? [], fn ($q, $games) => $q->whereIn('game_id', $games))
            ->when($filters['periods'] ?? [], function ($q, $periods) {
                $q->where(function ($periodQuery) use ($periods) {
                    $periodQuery
                        ->whereIn('period_id', $periods)
                        ->orWhereNull('period_id');
                });
            })
            ->when($filters['groups'] ?? [], function ($q, $groups) {
                $groups = array_map('strval', $groups);
                $hasProceso = in_array('2', $groups, true);
                $hasSinIniciar = in_array('5', $groups, true);
                $otherGroups = array_values(array_filter(
                    $groups,
                    fn ($group) => ! in_array($group, ['2', '5', '3'], true)
                ));

                $q->where(function ($groupQuery) use ($hasProceso, $hasSinIniciar, $otherGroups, $groups) {
                    if ($otherGroups !== []) {
                        $groupQuery->whereIn('group_id', $otherGroups);
                    }

                    if (in_array('3', $groups, true)) {
                        $groupQuery->orWhere(function ($revisionQuery) {
                            $revisionQuery->where('group_id', 3)->whereNull('user_finalize');
                        });
                    }

                    if ($hasProceso) {
                        $groupQuery->orWhere(function ($processQuery) {
                            $processQuery
                                ->where('group_id', 2)
                                ->whereHas('timeLogs', fn ($timeLogQuery) => $timeLogQuery->whereNotNull('user_id'));
                        });
                    }

                    if ($hasSinIniciar) {
                        $groupQuery->orWhere(function ($pendingQuery) {
                            $pendingQuery->where('group_id', 2)->doesntHave('timeLogs');
                        });
                    }
                });
            });
    }

    public function assertProjectAttachmentsExist(Project $project): void
    {
        $missingAttachments = [];
        $tasks = Task::where('project_id', $project->id)->withDefault()->get();

        foreach ($tasks as $task) {
            foreach ($task->attachments as $attachment) {
                $path = public_path($attachment->path);

                if (! $attachment->path || ! is_file($path)) {
                    $missingAttachments[] = "attachment {$attachment->id}";
                }
            }
        }

        if ($missingAttachments !== []) {
            throw new \RuntimeException(
                'Fotos no encontradas: '.implode(', ', array_slice($missingAttachments, 0, 10))
            );
        }
    }

    public function buildProjectPdfContent(Project $project): string
    {
        if ($project->type_id == null) {
            $project->update(['type_id' => 2]);
        }

        $data = [
            'ownerCompany' => OwnerCompany::first(),
            'project' => $project->loadDefault(),
            'asset' => Asset::find($project->game()->get('asset_id')),
            'tasks' => Task::where('project_id', $project->id)->withDefault()->get(),
            'timeLogs' => TimeLog::where('project_id', $project->id)->first(),
        ];

        foreach ($data['tasks'] as $task) {
            foreach ($task->attachments as $attachment) {
                $path = public_path($attachment->path);

                if (file_exists($path)) {
                    $attachment->compressed_base64 = $this->compressImage($path);
                }
            }
        }

        if ($data['project']->userReview && $data['project']->userReview->signature) {
            $path = public_path($data['project']->userReview->signature);

            if (file_exists($path)) {
                $data['aceptado'] = $this->compressImage($path);
            }
        }

        if ($data['project']->userFinalize && $data['project']->userFinalize->signature) {
            $path = public_path($data['project']->userFinalize->signature);

            if (file_exists($path)) {
                $data['validado'] = $this->compressImage($path);
            }
        }

        if ($data['timeLogs'] && $data['timeLogs']->user && $data['timeLogs']->user->signature) {
            $path = public_path($data['timeLogs']->user->signature);

            if (file_exists($path)) {
                $data['realizado'] = $this->compressImage($path);
            }
        }

        foreach ($data['project']->users as $user) {
            if ($user->signature && file_exists(public_path($user->signature))) {
                $user->signature_compressed = $this->compressImage(public_path($user->signature));
            }
        }

        return Pdf::loadView('vendor.project.pdf', $data)->output();
    }

    public function buildReport(
        Carbon $startDate,
        Carbon $endDate,
        Carbon $startedAt,
        Carbon $finishedAt,
        float $durationSeconds,
        int $totalProjects,
        int $processedProjects,
        int $generatedProjects,
        array $failedProjects
    ): string {
        $lines = [
            'REPORTE DE DESCARGA MASIVA DE OTs',
            '=================================',
            'Rango: '.$startDate->format('Y-m-d').' hasta '.$endDate->format('Y-m-d'),
            'Inicio: '.$startedAt->format('Y-m-d H:i:s'),
            'Fin: '.$finishedAt->format('Y-m-d H:i:s'),
            'Duracion: '.$this->formatDuration($durationSeconds),
            '',
            'OTs encontradas: '.$totalProjects,
            'OTs procesadas: '.$processedProjects,
            'OTs generadas correctamente: '.$generatedProjects,
            'OTs con errores: '.count($failedProjects),
            '',
        ];

        if ($failedProjects === []) {
            $lines[] = 'No se registraron errores.';
        } else {
            $lines[] = 'DETALLE DE ERRORES';
            $lines[] = '------------------';

            foreach ($failedProjects as $failedProject) {
                $lines[] = sprintf(
                    'OT %s (%s): %s',
                    $failedProject['id'],
                    $failedProject['name'],
                    $failedProject['error']
                );
            }
        }

        return implode(PHP_EOL, $lines).PHP_EOL;
    }

    public function formatDuration(float $durationSeconds): string
    {
        $seconds = (int) round($durationSeconds);
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $remainingSeconds = $seconds % 60;

        return sprintf('%02dh %02dm %02ds', $hours, $minutes, $remainingSeconds);
    }

    private function compressImage(string $imagePath): ?string
    {
        try {
            $image = imagecreatefromstring(file_get_contents($imagePath));

            if (! $image) {
                return null;
            }

            ob_start();
            imagejpeg($image, null, 20);
            $compressed = ob_get_clean();
            imagedestroy($image);

            return 'data:image/jpeg;base64,'.base64_encode($compressed);
        } catch (\Throwable) {
            return null;
        }
    }
}
