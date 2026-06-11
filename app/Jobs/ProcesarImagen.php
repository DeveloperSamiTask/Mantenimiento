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

    public function handle()
    {

        $start = microtime(true);

        $path = str_replace('/storage/', '', $this->attachment->path);
        $fullPath = storage_path("app/public/{$path}");

        // 1. Redimensionar imagen principal
        $manager = new ImageManager(new Driver);
        $manager->read($fullPath)->resize(800, 500)->save($fullPath);

        // 2. Generar miniatura
        $thumbDir = dirname($path) . '/thumbs';
        $filename = basename($path);
        $thumbPath = "{$thumbDir}/{$filename}";

        Storage::disk('public')->makeDirectory($thumbDir);

        $image = $manager->read($fullPath)->resize(100, 100);
        Storage::disk('public')->put($thumbPath, $image->toJpeg());

        $end = microtime(true); // <--- Detén el cronómetro
        $executionTime = round($end - $start, 4); // Calcula segundos

        Log::info("El job para el attachment {$this->attachment->id} tardó {$executionTime} segundos en procesarse.");

        // 3. Actualizar ruta en DB
        $this->attachment->update(['thumb' => "/storage/{$thumbPath}"]);
    }
}
