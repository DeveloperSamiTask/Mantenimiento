<?php

namespace App\Console\Commands;

use App\Models\Task;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Throwable;

class CleanEmptyTaskDirectories extends Command
{
    protected $signature = 'clean:empty-task-directories
        {--from= : Fecha de creación inicial de la OT, formato YYYY-MM-DD}
        {--to= : Fecha de creación final de la OT, formato YYYY-MM-DD}
        {--dry-run : Revisa y cuenta, pero no elimina nada}';

    protected $description = 'Elimina por rango únicamente carpetas de tareas que no contienen ningún archivo';

    public function handle(): int
    {
        $from = $this->option('from');
        $to = $this->option('to');

        if (! $from || ! $to) {
            $this->error('Debes indicar --from y --to.');

            return self::FAILURE;
        }

        try {
            $fromDate = Carbon::createFromFormat('Y-m-d', $from)->startOfDay();
            $toDate = Carbon::createFromFormat('Y-m-d', $to)->endOfDay();
        } catch (Throwable) {
            $this->error('Las fechas deben tener el formato YYYY-MM-DD.');

            return self::FAILURE;
        }

        if ($fromDate->greaterThan($toDate)) {
            $this->error('La fecha --from no puede ser posterior a --to.');

            return self::FAILURE;
        }

        $disk = Storage::disk('public');
        $dryRun = (bool) $this->option('dry-run');

        if (! $dryRun && ! $this->confirm(
            "¿Eliminar carpetas completamente vacías de tareas cuyas OT fueron creadas entre {$from} y {$to}?"
        )) {
            $this->info('Cancelado.');

            return self::SUCCESS;
        }

        $tasksReviewed = 0;
        $missingDirectories = 0;
        $protectedDirectories = 0;
        $emptyTaskDirectories = 0;
        $directoryEntriesRemoved = 0;
        $errors = 0;

        $this->info('Revisando carpetas. Ninguna carpeta con archivos será eliminada.');

        $this->tasksQuery($fromDate, $toDate)
            ->chunkById(500, function ($tasks) use (
                $disk,
                $dryRun,
                &$tasksReviewed,
                &$missingDirectories,
                &$protectedDirectories,
                &$emptyTaskDirectories,
                &$directoryEntriesRemoved,
                &$errors
            ) {
                foreach ($tasks as $task) {
                    $tasksReviewed++;
                    $directory = "tasks/{$task->id}";

                    try {
                        if (! $disk->directoryExists($directory)) {
                            $missingDirectories++;
                            continue;
                        }

                        if (count($disk->allFiles($directory)) > 0) {
                            $protectedDirectories++;
                            continue;
                        }

                        $emptyTaskDirectories++;
                        $entries = 1 + count($disk->allDirectories($directory));

                        if ($dryRun) {
                            $directoryEntriesRemoved += $entries;
                            continue;
                        }

                        if ($disk->deleteDirectory($directory)) {
                            $directoryEntriesRemoved += $entries;
                        } else {
                            $errors++;
                        }
                    } catch (Throwable $exception) {
                        $errors++;
                        $this->error("Tarea {$task->id}: {$exception->getMessage()}");
                    }
                }
            });

        $this->newLine();
        $this->info("Tareas revisadas: {$tasksReviewed}");
        $this->info("Carpetas de tarea completamente vacías: {$emptyTaskDirectories}");
        $this->info("Entradas de directorio que se liberarían/eliminaron: {$directoryEntriesRemoved}");
        $this->warn("Carpetas protegidas porque contienen archivos: {$protectedDirectories}");
        $this->line("Tareas que no tenían carpeta: {$missingDirectories}");

        if ($dryRun) {
            $this->warn('DRY RUN - No se eliminó ninguna carpeta.');

            return self::SUCCESS;
        }

        if ($errors > 0) {
            $this->error("Errores: {$errors}");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function tasksQuery(Carbon $from, Carbon $to): Builder
    {
        return Task::query()
            ->withoutGlobalScopes()
            ->select('tasks.id')
            ->whereHas('project', fn (Builder $projectQuery) =>
                $projectQuery
                    ->where('group_id', 4)
                    ->whereNotNull('user_review')
                    ->whereNotNull('user_finalize')
                    ->whereBetween('created_at', [$from, $to])
            );
    }
}
