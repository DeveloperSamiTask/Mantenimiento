<?php

namespace App\Actions\Project;

use App\Events\Project\ProjectCreated;
use App\Events\Task\TaskCreated;
use App\Models\CheckList;
use App\Models\Project;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CreateProject
{
    public function create(array $data): Project
    {
        //1. inicia una transaccion
        return DB::transaction(function () use ($data) {

            //2. prepara datos
            $data['rate'] *= 100;
            $data['labels'] = 2;
            $data['number'] = Project::withArchived()->count() + 1;

            //3.crea proyectos con los datos recibidos
            $project = Project::create([
                'client_company_id' => $data['client_company_id'] ?? 1,
                'group_id' => 2, // En proceso
                'game_id' => $data['game_id'] ?? null,
                'period_id' => $data['period_id'] ?? null,
                'type_id' => $data['type_id'] ?? null,

                //'user_generate' => optional(auth())->id(), // ← agrega esto
                //'assigned_to_user_id' => optional(auth())->id(),
                'user_generate' => auth()->id(),

                'name' => $data['name'],
                'due_on' => $data['due_on'] ?? now(),
                'fault_date' => $data['fault_date'] ?? null,
                'start_date' => $data['start_date'] ?? null,
                'estimation' => $data['estimation'],
                'rate' => $data['rate'],
                'number' => $data['number'],
                'description' => $data['description'],
                'default' => false,
            ]);

            /* 4.Organiza el proyecto , lo mueve al inicio del tablero , asignando usuarios y etiquetas */
            $project->moveToStart();
            $project->users()->attach($data['users'] ?? []);
            $project->labels()->attach($data['labels']);

            //5.notifica que se creo el evento
            ProjectCreated::dispatch($project);

            //6.crea grupos basados en su estado --> dentro de la OT , los 4 grupos tipicos
            $taskGroup = $project->taskGroups()->createMany([ // Almacenar solo los periodos predeterminados
                ['name' => 'Pendiente'],
                ['name' => 'Proceso'],
                ['name' => 'Revision'],
                ['name' => 'Finalizado'],
            ]);

            /*
                7.Generar tareas desde el checklist para la OT creada
                    - Cuando creas un nuevo proyecto se revisan las plantillas checklist
                    - Si alguna plantilla coincide con el PERIODO y JUEGO del proyecto , se crea una tarea uatomatica en el pendiento de la OT
            */
            CheckList::get()->each(function ($checklist) use ($project, $taskGroup) {
                if ($checklist->period_id == $project->period_id && $checklist->game_id == $project->game_id) { // Verifica si el campo group_name de la tabla CheckList existe o es igual con la clave: "Diario" de $period
                    //se crea la tarea
                    $task = $project->tasks()->create([
                        'group_id' => $taskGroup->pluck('id', 'name')['Pendiente'],
                        //'created_by_user_id' => auth()->id(),
                        //'assigned_to_user_id' => auth()->id(),

                        'created_by_user_id' => auth()->id(),
                        'assigned_to_user_id' => auth()->id(),

                        'name' => $checklist->name,
                        'number' => $project->tasks()->withArchived()->count() + 1,
                        'estimation' => 0,
                        'due_on' => Carbon::now(),
                        'hidden_from_clients' => 0,
                        'billable' => 1,
                        'sent_archive' => $checklist->archive,
                        'type_check' => $checklist->type,
                        'completed_at' => null,
                    ]);
                    $task->labels()->attach([1]); // Pendiente
                    TaskCreated::dispatch($task);
                }
            });

            return $project;
        });
    }
}
