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
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use Throwable;

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

            $task->moveToStart();

            $task->subscribedUsers()->attach($data['subscribed_users'] ?? []);

            $task->labels()->attach($data['labels'] ?? []);

            if (! empty($data['attachments'])) {
                $this->uploadAttachments($task, $data['attachments'], false);
            }

            TaskCreated::dispatch($task);

            return $task;
        });
    }

    public function uploadAttachments(Task $task, array $items, $dispatchEvent = true): Collection
    {
        $rows = collect($items)
            ->map(function (UploadedFile $item) use ($task) {
                $filename = strtolower(Str::ulid()).'.'.$item->getClientOriginalExtension();
                $filepath = "tasks/{$task->id}/{$filename}";

                $item->storeAs('public', $filepath);

                // Cambia los permisos de la carpeta que contiene el archivo
                $directoryPath = dirname(storage_path("app/public/{$filepath}"));
                chmod($directoryPath, 0755);

                $manager = new ImageManager(new Driver());
                $manager->read(storage_path("app/public/{$filepath}"))
                    ->resize(800, 500)
                    ->save(storage_path("app/public/{$filepath}"));

                $thumbFilepath = $this->generateThumb($item, $task, $filename);
                // exec('chmod -R 755 storage/app/public');
                return [
                    'user_id' => auth()->id(),
                    'name' => $item->getClientOriginalName(),
                    'path' => "/storage/$filepath",
                    'thumb' => $thumbFilepath ? "/storage/$thumbFilepath" : null,
                    'type' => $item->getClientMimeType(),
                    'size' => $item->getSize(),
                ];
            });

        $attachments = $task->attachments()->createMany($rows);

        $task->activities()->create([
            'project_id' => $task->project_id,
            'user_id' => auth()->id(),
            'title' => ($attachments->count() > 1 ? 'Attachments where' : 'Attachment was').' uploaded',
            'subtitle' => "to \"{$task->name}\" by ".auth()->user()->name,
        ]);

        if ($dispatchEvent) {
            AttachmentsUploaded::dispatch($task, $attachments);
        }

        return $attachments;
    }

    protected function generateThumb(UploadedFile $file, Task $task, string $filename)
    {
        if (in_array($file->getClientOriginalExtension(), ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'])) {
            try {
                $thumbFilepath = "tasks/{$task->id}/thumbs/{$filename}";

                $image = new ImageManager(new Driver());
                $image->read(storage_path("app/public/{$thumbFilepath}"))
                    ->resize(100, 100)
                    ->save(storage_path("app/public/{$thumbFilepath}"));


                Storage::put("public/$thumbFilepath", $image);
                $this->changePermissionsRecursively(storage_path("app/public/tasks"));
                return $thumbFilepath;
            } catch (Throwable $e) {
                return null;
            }
        }
        return null;
    }

    protected function changePermissionsRecursively($dir) {
        $files = scandir($dir);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') continue;
            $fullPath = $dir . '/' . $file;
            if (is_dir($fullPath)) {
                $this->changePermissionsRecursively($fullPath);
            }
            chmod($fullPath, 755);
        }
    }
}
