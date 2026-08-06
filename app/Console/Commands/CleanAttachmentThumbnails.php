<?php

namespace App\Console\Commands;

use App\Models\Attachment;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Throwable;

class CleanAttachmentThumbnails extends Command
{
    protected $signature = 'clean:attachment-thumbnails
        {--from= : Fecha inicio YYYY-MM-DD}
        {--to= : Fecha fin YYYY-MM-DD}
        {--all : Procesa todas las miniaturas registradas, sin filtrar por fecha}
        {--dry-run : Solo cuenta, no borra ni modifica nada}';

    protected $description = 'Elimina solo las miniaturas físicas y deja thumb en NULL';

    public function handle(): int
    {
        $from = $this->option('from');
        $to = $this->option('to');
        $all = (bool) $this->option('all');

        if ($all && ($from || $to)) {
            $this->error('Usa --all o un rango --from/--to, pero no ambos.');

            return self::FAILURE;
        }

        if (! $all && (! $from || ! $to)) {
            $this->error('Debes indicar --all o las dos fechas --from y --to.');

            return self::FAILURE;
        }

        if ($all) {
            $query = Attachment::query()->whereNotNull('thumb');
        } else {
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

            $query = $this->attachmentsQuery($fromDate, $toDate);
        }
        $total = (clone $query)->count();

        $this->info("Miniaturas registradas encontradas: {$total}");

        if ($total > 0) {
            $oldest = (clone $query)->min('created_at');
            $newest = (clone $query)->max('created_at');

            $this->line("Primera miniatura registrada: {$oldest}");
            $this->line("Última miniatura registrada: {$newest}");
        }

        if ($this->option('dry-run')) {
            $this->warn('DRY RUN - No se borró ni modificó nada.');

            return self::SUCCESS;
        }

        if ($total === 0) {
            return self::SUCCESS;
        }

        if (! $this->confirm("¿Borrar {$total} miniaturas? Los originales no serán tocados.")) {
            $this->info('Cancelado.');

            return self::SUCCESS;
        }

        $deleted = 0;
        $missing = 0;
        $errors = 0;
        $databaseUpdated = 0;

        $query->chunkById(500, function ($attachments) use (&$deleted, &$missing, &$errors, &$databaseUpdated) {
            foreach ($attachments as $attachment) {
                $relativePath = $this->relativePublicPath($attachment->thumb);

                try {
                    if ($relativePath && Storage::disk('public')->exists($relativePath)) {
                        if (! Storage::disk('public')->delete($relativePath)) {
                            $errors++;
                            continue;
                        }

                        $deleted++;
                    } else {
                        $missing++;
                    }

                    $attachment->forceFill(['thumb' => null])->save();
                    $databaseUpdated++;
                } catch (Throwable $exception) {
                    $errors++;
                    $this->error("Attachment {$attachment->id}: {$exception->getMessage()}");
                }
            }
        });

        $this->newLine();
        $this->info("Miniaturas eliminadas: {$deleted}");
        $this->warn("Miniaturas que ya no existían: {$missing}");
        $this->info("Registros con thumb en NULL: {$databaseUpdated}");

        if ($errors > 0) {
            $this->error("Errores: {$errors}");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function attachmentsQuery(Carbon $from, Carbon $to): Builder
    {
        return Attachment::query()
            ->whereNotNull('thumb')
            ->whereBetween('created_at', [$from, $to]);
    }

    private function relativePublicPath(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        return ltrim((string) preg_replace('#^/?storage/#', '', $path), '/');
    }
}
