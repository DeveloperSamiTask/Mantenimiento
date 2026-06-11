<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
// Por si acaso haga conflicto con el de arriba en versiones nuevas
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use App\Models\Attachment;
use Illuminate\Support\Facades\Storage;
class ProcesarImagen implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public Attachment $attachment) {}

    // ProcesarImagen.php - solo resize, thumb ya está hecho
    public function handle()
    {
        $path = str_replace('/storage/', '', $this->attachment->path);
        $fullPath = storage_path("app/public/{$path}");

        $manager = new ImageManager(new Driver);
        $manager->read($fullPath)->resize(800, 500)->save($fullPath);

        Log::info("Resize completado para attachment {$this->attachment->id}");
    }
}
