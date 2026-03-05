<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\Project;
use App\Models\Task;
use App\Services\PermissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
class DashboardController extends Controller
{
    public function index(Request $request)
{
    $user = auth()->user();
    // 1. Obtenemos los IDs (que dices que son muchísimos)
    $projectIds = PermissionService::projectsThatUserCanAccess($user)->pluck('id');
    $today = now()->toDateString();

    // 2. En lugar de una query gigante, procesamos en bloques de 50
    // Esto mantiene la consulta SQL corta y segura para GoDaddy
    $projects = collect();
    foreach ($projectIds->chunk(50) as $chunk) {
        $chunkProjects = Project::whereIn('id', $chunk)
            ->where('default', '!=', 1)
            ->whereDate('created_at', $today)
            ->with(['clientCompany:id,name'])
            ->withCount([
                'tasks AS all_tasks_count',
                'tasks AS completed_tasks_count' => fn ($q) => $q->whereNotNull('completed_at'),
                'tasks AS overdue_tasks_count' => fn ($q) => $q->whereNull('completed_at')->whereDate('due_on', '<', $today),
            ])
            ->withExists('favoritedByAuthUser AS favorite')
            ->get(['id', 'name']);

        $projects = $projects->concat($chunkProjects);
    }

    // 3. Ordenamos en PHP una vez que tenemos todos los bloques unidos
    $projects = $projects->sortByDesc('favorite')->values();

    // 4. Para las tareas, aplicamos la misma lógica: consulta simple por rango
    // Traeremos solo las tareas de los proyectos que ya filtramos arriba
    $finalProjectIds = $projects->pluck('id');

    $overdueTasks = Task::whereIn('project_id', $finalProjectIds)
        ->whereNull('completed_at')
        ->whereDate('due_on', '<', $today)
        ->whereBetween('due_on', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])
        ->with(['project:id,name', 'taskGroup:id,name'])
        ->get(['id', 'name', 'due_on', 'group_id', 'project_id']);

    // ... Repite la lógica de queries simples para recentlyAssignedTasks y recentComments ...

    return Inertia::render('Dashboard/Index', [
        'projects' => $projects,
        'overdueTasks' => $overdueTasks,
        'recentlyAssignedTasks' => [], // Agrega la query simple aquí si la necesitas
        'recentComments' => [], // Agrega la query simple aquí si la necesitas
    ]);
}
}
