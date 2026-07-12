<?php

namespace App\Actions\Task;

use App\Events\Task\AttachmentsUploaded;
use App\Events\Task\TaskCreated;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use Throwable;
use App\Jobs\ProcesarImagen;
use Illuminate\Support\Facades\Log;
class CreateTask
{
    public function create(Project $project, array $data): Task
    {
        return DB::transaction(function () use ($project, $data) {
            $task = $project->tasks()->create([
                'group_id' => $data['group_id'],
                'created_by_user_id' => auth()->id(),
                'assigned_to_user_id' => auth()->id(),
                'name' => $data['name'],
                'number' => $project->tasks()->withArchived()->count() + 1,
                'description' => $data['description'] ?? null,
                'due_on' => $data['due_on'] ?? now(),
                'type_check' => $data['type_check'],
                'estimation' => $data['estimation'] ?? 0,
                'hidden_from_clients' => $data['hidden_from_clients'] ?? false,
                'billable' => $data['billable'] ?? true,
                'sent_archive' => $data['sent_archive'],
                'completed_at' => null,
            ]);

            // 1.lo mueve al inicio de la columna de estado donde esta
            $task->moveToStart();

            // 2.jala a los usuarios que realizan la tarea
            $task->subscribedUsers()->attach($data['subscribed_users'] ?? []);

            // 3.jala las etiquetas que tiene la tarea
            $task->labels()->attach($data['labels'] ?? []);

            // 4.si hay archivos adjuntos , los sube
            if (! empty($data['attachments'])) {
                $this->uploadAttachments($task, $data['attachments'], false);
            }

            // 5. notifica que se creo la tarea
            TaskCreated::dispatch($task);

            return $task;
        });
    }

    // Metodo para adjuntar archivos (fotos)
    // CreateTask.php - uploadAttachments
    public function uploadAttachments(Task $task, array $items, $dispatchEvent = true): Collection
    {
        $attachments = collect($items)->map(function (UploadedFile $item) use ($task) {
            $uploadStart = microtime(true);

            $filename = strtolower(Str::ulid()).'.'.$item->getClientOriginalExtension();
            $filepath = "tasks/{$task->id}/{$filename}";
            $thumbDir = "tasks/{$task->id}/thumbs";
            $thumbPath = "{$thumbDir}/{$filename}";

            Log::info("[UPLOAD] Iniciando subida", [
                'archivo' => $item->getClientOriginalName(),
                'tamaño_mb' => round($item->getSize() / 1048576, 2),
                'tipo' => $item->getClientMimeType(),
            ]);

            // 1. Guardar archivo
            $t1 = microtime(true);
            $item->storeAs('public', $filepath);
            chmod(storage_path("app/public/tasks/{$task->id}"), 0755);
            Log::info("[UPLOAD] Archivo guardado en " . round(microtime(true) - $t1, 3) . "s", [
                'path' => $filepath,
                'existe' => Storage::disk('public')->exists($filepath),
            ]);

            // 2. Thumbnail
            $thumbStored = null;
            $t2 = microtime(true);
            try {
                $dirCreado = Storage::disk('public')->makeDirectory($thumbDir);
                Log::info("[THUMB] Directorio", [
                    'path' => $thumbDir,
                    'creado_ok' => $dirCreado,
                    'existe' => Storage::disk('public')->exists($thumbDir),
                ]);

                $fullPathOrigen = storage_path("app/public/{$filepath}");
                Log::info("[THUMB] Leyendo imagen desde: " . $fullPathOrigen, [
                    'archivo_existe' => file_exists($fullPathOrigen),
                    'tamaño_bytes' => file_exists($fullPathOrigen) ? filesize($fullPathOrigen) : 'NO EXISTE',
                ]);

                $manager = new ImageManager(new Driver);
                $thumb = $manager->read($fullPathOrigen)->cover(100, 100);
                $thumbPutOk = Storage::disk('public')->put($thumbPath, $thumb->toJpeg());

                Log::info("[THUMB] Generado en " . round(microtime(true) - $t2, 3) . "s", [
                    'thumb_path' => $thumbPath,
                    'guardado_ok' => $thumbPutOk,
                    'existe_en_disco' => Storage::disk('public')->exists($thumbPath),
                    'url_final' => "/storage/{$thumbPath}",
                ]);

                $thumbStored = "/storage/{$thumbPath}";

            } catch (Throwable $e) {
                Log::error("[THUMB] FALLÓ", [
                    'error' => $e->getMessage(),
                    'linea' => $e->getLine(),
                    'archivo' => $e->getFile(),
                ]);
            }

            // 3. Guardar en DB
            $t3 = microtime(true);
            $attachment = $task->attachments()->create([
                'user_id' => auth()->id(),
                'name' => $item->getClientOriginalName(),
                'path' => "/storage/{$filepath}",
                'thumb' => $thumbStored,
                'type' => $item->getClientMimeType(),
                'size' => $item->getSize(),
            ]);

            Log::info("[DB] Attachment guardado en " . round(microtime(true) - $t3, 3) . "s", [
                'id' => $attachment->id,
                'thumb_en_db' => $attachment->thumb,
            ]);

            Log::info("[UPLOAD] TOTAL foto procesada en " . round(microtime(true) - $uploadStart, 3) . "s");

            Log::info("[DEBUG] Antes del dispatch. Attachment ID: " . $attachment->id);
            ProcesarImagen::dispatch($attachment);
            Log::info("[DEBUG] Despues del dispatch.");

            return $attachment;
        });

        return $attachments;
    }

    // protected function changePermissionsRecursively($dir)
    // {
    //     $files = scandir($dir);
    //     foreach ($files as $file) {
    //         if ($file === '.' || $file === '..') {
    //             continue;
    //         }
    //         $fullPath = $dir.'/'.$file;
    //         if (is_dir($fullPath)) {
    //             $this->changePermissionsRecursively($fullPath);
    //         }
    //         chmod($fullPath, 755);
    //     }
    // }
}
