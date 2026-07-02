<?php
namespace App\Console\Commands;
use Illuminate\Console\Command;
use App\Models\Attachment;

class CleanAttachments extends Command
{
    protected $signature = 'clean:attachments 
        {--from= : Fecha inicio YYYY-MM-DD}
        {--to= : Fecha fin YYYY-MM-DD}
        {--dry-run : Solo lista, no borra nada}';

    protected $description = 'Limpia fotos físicas de OTs finalizadas por rango de fechas';

    public function handle()
    {
        $from   = $this->option('from');
        $to     = $this->option('to');
        $dryRun = $this->option('dry-run');

        $attachments = Attachment::whereHas('task', fn($q) =>
            $q->whereHas('project', fn($q2) =>
                $q2->where('group_id', 4)
                   ->whereNotNull('user_review')
                   ->whereNotNull('user_finalize')
                   ->whereBetween('created_at', [$from.' 00:00:00', $to.' 23:59:59'])
            )
        )->get();

        $this->info("Fotos encontradas: {$attachments->count()}");

        if ($dryRun) {
            $this->warn('DRY RUN - No se borra nada');
            return;
        }

        if (!$this->confirm("¿Borrar {$attachments->count()} fotos? Esto no se puede deshacer")) {
            $this->info('Cancelado.');
            return;
        }

        $borrados = 0;
        $errores  = 0;

        foreach ($attachments as $attachment) {
            $rutaFisica = storage_path('app/public'.str_replace('/storage', '', $attachment->path));
            if (file_exists($rutaFisica)) {
                unlink($rutaFisica);
                $borrados++;
            } else {
                $errores++;
            }
        }

        $this->info("✅ Borrados: $borrados archivos");
        $this->warn("⚠️ No encontrados: $errores archivos");
    }
}