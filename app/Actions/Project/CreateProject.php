<?php

namespace App\Actions\Project;

use App\Events\Project\ProjectCreated;
use App\Events\Task\TaskCreated;
use App\Models\CheckList;
use App\Models\Project;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Models\Task;


class CreateProject
{
    public function create(array $data): Project
    {
        return DB::transaction(function () use ($data) {
            $data['rate'] *= 100;
            $data['labels'] = 2;
            $data['number'] = Project::withArchived()->count() + 1;

            $project = Project::create([
                'client_company_id' => $data['client_company_id'] ?? 1,
                'group_id' => 2,
                'game_id' => $data['game_id'] ?? null,
                'period_id' => $data['period_id'] ?? null,
                'type_id' => $data['type_id'] ?? null,
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

            $project->moveToStart();
            $project->users()->attach($data['users'] ?? []);
            $project->labels()->attach($data['labels']);

            ProjectCreated::dispatch($project);

            $taskGroup = $project->taskGroups()->createMany([
                ['name' => 'Pendiente'],
                ['name' => 'Proceso'],
                ['name' => 'Revision'],
                ['name' => 'Finalizado'],
            ]);

            $checklists = CheckList::where('period_id', $project->period_id)
                ->where('game_id', $project->game_id)
                ->get();

            if ($checklists->isEmpty()) {
                return $project;
            }

            // Insert masivo de tareas — 1 query en lugar de N
            $taskNumber = 0;
            $pendienteId = $taskGroup->pluck('id', 'name')['Pendiente'];
            $userId = auth()->id();
            $now = Carbon::now();

            $tareas = $checklists->map(function ($checklist) use (
                $project, $pendienteId, $userId, $now, &$taskNumber
            ) {
                $taskNumber++;

                return [
                    'project_id' => $project->id,
                    'group_id' => $pendienteId,
                    'created_by_user_id' => $userId,
                    'assigned_to_user_id' => $userId,
                    'name' => $checklist->name,
                    'number' => $taskNumber,
                    'order_column' => $taskNumber,
                    'estimation' => 0,
                    'due_on' => $now,
                    'hidden_from_clients' => 0,
                    'billable' => 1,
                    'sent_archive' => $checklist->archive,
                    'type_check' => $checklist->type,
                    'completed_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            })->toArray();

            Task::insert($tareas); // 1 query

            // Insert masivo de labels — 1 query en lugar de N
            $tareasCreadas = Task::where('project_id', $project->id)->get(['id']);
            DB::table('label_task')->insert(
                $tareasCreadas->map(fn ($t) => [
                    'task_id' => $t->id,
                    'label_id' => 1,

                ])->toArray()
            );

            // TaskCreated::dispatch() eliminado — broadcast innecesario al crear desde plantilla

            return $project;
        });
    }
}
