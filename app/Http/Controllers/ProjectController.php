<?php

namespace App\Http\Controllers;

use App\Actions\Project\CreateProject;
use App\Actions\Task\CreateTask;
use App\Events\Project\ProjectDeleted;
use App\Events\Project\ProjectGroupChanged;
use App\Events\Project\ProjectOrderChanged;
use App\Events\Project\ProjectRestored;
use App\Events\Project\ProjectUpdated;
use App\Http\Requests\Project\StoreProjectRequest;
use App\Http\Requests\Project\UpdateProjectRequest;
use App\Http\Resources\Project\ProjectResource;
use App\Models\Asset;
use App\Models\ClientCompany;
use App\Models\Currency;
use App\Models\Game;
use App\Models\Label;
use App\Models\OwnerCompany;
use App\Models\Period;
use App\Models\Project;
use App\Models\ProjectGroup;
use App\Models\ProjectType;
use App\Models\Task;
use App\Models\TimeLog;
use App\Models\TypeCheck;
use App\Models\User;
use App\Services\ProjectService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;

class ProjectController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Project::class, 'project');
    }

    /* plan de tareas*/
    public function index(Request $request)
    {
        try {
            // 1. CREAR LA QUERY BASE
            $query = Project::searchByQueryString()
                ->when($request->user()->isNotAdmin(), function ($query) {
                    $query->whereHas('clientCompany.clients', fn ($query) => $query->where('users.id', auth()->id()))
                        ->orWhereHas('users', fn ($query) => $query->where('id', auth()->id()));
                })
                ->when($request->has('archived'), fn ($query) => $query->onlyArchived())
                ->where('default', '!=', 1)
                ->with([
                    'clientCompany:id,name',
                    'clientCompany.clients:id,name,avatar',
                    'users:id,name,avatar',
                ])
                ->withCount([
                    'tasks AS all_tasks_count',
                    'tasks AS completed_tasks_count' => fn ($query) => $query->whereNotNull('completed_at'),
                    'tasks AS overdue_tasks_count' => fn ($query) => $query->whereNull('completed_at')->whereDate('due_on', '<', now()),
                ])
                ->withExists('favoritedByAuthUser AS favorite');

            // 2. DETECTAR SI HAY FILTROS ACTIVOS
            $hasFilters = $request->filled('search') || $request->filled('dateRange') || $request->has('archived');

            if (! $hasFilters) {
                // ✅ MODO POR DEFECTO: ÚLTIMOS 2 DÍAS
                $query->whereDate('created_at', '>=', now()->subDays(2));
            } else {
                // ✅ MODO CON FILTROS: lógica completa actual
                $query->where(function ($query) {
                    $query->whereNull('created_at')
                        ->orWhere('default', '!=', '0')
                        ->orWhereDate('created_at', '>=', now()->subDays(6));
                })
                    ->when($request->filled('dateRange'), function ($query) use ($request) {
                        $dates = collect($request->dateRange)
                            ->filter(fn ($d) => $d && strtotime($d))
                            ->map(fn ($d) => Carbon::parse($d)->toDateString())
                            ->values();

                        if ($dates->count() >= 2) {
                            $startDate = Carbon::parse($dates[0])->startOfDay();
                            $endDate = Carbon::parse($dates[1])->endOfDay();

                            if ($startDate->isSameDay($endDate)) {
                                $query->whereDate('due_on', $startDate);
                            } else {
                                $query->whereBetween('due_on', [$startDate, $endDate]);
                            }
                        }
                    });
            }

            // 3. ✅ APLICAR PAGINACIÓN (CAMBIO CRÍTICO)
            $projects = $query->orderBy('due_on', 'desc')
                ->orderBy('name', 'asc')
                ->paginate(20); // ← CAMBIAR get() por paginate(20)

            // 4. ✅ RETORNAR CON PAGINACIÓN
            return Inertia::render('Projects/Index', [
                'items' => ProjectResource::collection($projects),
            ]);

        } catch (\Throwable $e) {
            dd([
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'input' => $request->all(),
            ]);
        }
    }

    /*Retorna los proyectos para el kanban de ordenes de trabajo */
    public function kanban(Request $request, ?Project $project = null)
    {

        //1.obtener los grupos de los projectos
        $groups = ProjectGroup::when($request->has('archived'), fn ($query) => $query->onlyArchived())->get();
        $user = request()->user();
        $key = $request->archived ? 'groupedProjects'.$request->archived : 'groupedProjects';
        // if(Cache::has($key)){
        // $groupedProjects = Cache::get($key);
        // }else{

        //2. proyectos agrupados por columnas
        $groupedProjects = ProjectGroup::with(['projects' => fn ($query) => $query->withArchived()])->get()
            ->mapWithKeys(function (ProjectGroup $group) use ($request, $user) {
                $projects = Project::where('group_id', $group->id)
                    ->searchByQueryString()
                    ->filterByQueryString()
                    //3. solo el admin puede ver todos los productos
                    ->when($request->user()->isNotAdmin(), function ($query) {
                        $query->whereHas('users', fn ($query) => $query->where('id', auth()->id()));
                    })
                    ->with([
                        'tasks' => function ($query) use ($user) {
                            $query->when($user->hasRole('cliente'), fn ($query) => $query->where('hidden_from_clients', false))
                                // ->where('assigned_to_user_id', $user->id)
                                ->orderByRaw('number ASC')
                                ->with([
                                    'labels:id,name,color',
                                    'assignedToUser:id,name',
                                    'completedByUser:id,name',
                                    'taskGroup:id,name',
                                    'attachments',
                                ]);
                        },
                    ])
                    ->withCount([
                        'tasks AS all_tasks_count',
                        'tasks AS completed_tasks_count' => fn ($query) => $query->whereNotNull('completed_at'),
                        'tasks AS overdue_tasks_count' => fn ($query) => $query->whereNull('completed_at')->whereDate('due_on', '<', now()),
                    ])
                    ->when($request->has('archived'), fn ($query) => $query->onlyArchived())
                    ->where(function ($query) {
                        $query->whereNull('created_at')
                            ->orWhere('default', '!=', '0')
                            ->orWhereDate('created_at', '>=', now()->subDays(15));
                    })
                    ->where(function ($query) {
                        $query->where(function ($q) {
                            $q->whereDate('completed_at', now());
                        })
                            ->orWhereNull('completed_at');
                    })
                    ->withDefault();
                //->when($group->name === 'Plantilla', fn ($q) => $q->limit(20)) // 👈 aquí limitas solo Plantilla
                //->when($group->name === 'Plantilla' && !$request->has('search'), fn($q) => $q->limit(20))
                // 👇 Solo limitar a 20 si es grupo "Plantilla" y es carga inicial

                // Define $isInitialLoad as needed, for example:
                $isInitialLoad = ! $request->has('search');

                if ($group->name === 'Plantilla' && $isInitialLoad) {
                    $projects->limit(20);
                }

                $projects = $projects->get();

                return [
                    $group->id => $projects,
                ];
            });

        // Cache::put($key, $groupedProjects);
        // }

        //4. retorna vista con los datos
        return Inertia::render('Projects/Kanban/Index', [
            'labels' => Label::get(['id', 'name', 'color']),
            'projectGroups' => $groups,
            'groupedProjects' => $groupedProjects,
            'openedProject' => $project ? $project->loadDefault() : null,
            'users_access' => User::withoutRole('cliente')->get(['id', 'name']),
            'games' => Game::dropdownValues(),
            'periods' => Period::get(['id', 'name']),
            'types' => ProjectType::dropdownValues(),
            'typeChecks' => TypeCheck::dropdownValues(),
        ]);
    }

    /*Envia datos para una lista desplegable */
    public function create()
    {
        return Inertia::render('Projects/Create', [
            'dropdowns' => [
                'companies' => ClientCompany::dropdownValues(),
                'users' => User::userDropdownValues(),
                'labels' => Label::DropdownValues(),
                'games' => Game::DropdownValues(),
                'types' => ProjectType::DropdownValues(),
                'currencies' => Currency::dropdownValues(['with' => ['clientCompanies:id,currency_id']]),
            ],
        ]);
    }

    /*Se ejecuta cuando el usuario manda la solicitud POST */
    public function store(StoreProjectRequest $request): RedirectResponse
    {
        //1.llama a un servicio que guarda el proyecto en la bd
        $project = (new CreateProject)->create($request->validated());

        //2. Crea tareas iniciales
        if ($request->tasks && count($request->tasks) > 0) {
            foreach ($request->tasks as $task) {
                $data = [
                    'name' => $task,
                    'group_id' => $project->taskGroups()->where('name', 'Pendiente')->pluck('id')->first(),
                    'sent_archive' => true,
                    'type_check' => 1,
                ];
                (new CreateTask)->create($project, $data);
            }
        }
        Cache::flush();

        return redirect()->route('projects.kanban')->success('Orden de trabajo creado', 'La orden de trabajo se creó exitosamente.');
    }

    public function edit(Project $project)
    {
        return Inertia::render('Projects/Edit', [
            'item' => $project->loadDefault(),
            'dropdowns' => [
                'companies' => ClientCompany::dropdownValues(),
                'users' => User::userDropdownValues(),
                'games' => Game::DropdownValues(),
                'types' => ProjectType::DropdownValues(),
                'currencies' => Currency::dropdownValues(['with' => ['clientCompanies:id,currency_id']]),
            ],
        ]);
    }

    public function update(UpdateProjectRequest $request, Project $project): JsonResponse|RedirectResponse
    {

        if ($request['_method']) {
            $project->update($request->validated());

            return redirect()->route('projects.kanban')->success('Orden de trabajo actualizado', 'La orden de trabajo se actualizó exitosamente..');
        }

        $data = $request->validated();
        $updateField = key($data);

        if (! in_array($updateField, ['users', 'labels', 'tasks'])) {

            $project->update($data);
            if ($updateField == 'group_id') {
                $project->update(['order_column' => 0]);
            }
        }

        if ($updateField == 'tasks') {
            return response()->json();
        }

        if ($updateField == 'users') {
            $project->users()->sync($data['users']);
        }

        if ($updateField == 'labels') {
            $project->labels()->sync($data['labels']);
        }

        ProjectUpdated::dispatch($project, $updateField);
        Cache::flush();

        return response()->json();
    }

    public function destroy(Request $request, Project $project): RedirectResponse
    {
        $project->update([
            'name' => $project->name.'(archivado)',
            'motive_archived' => $request->motive_archived,
        ]);
        $project->archive();
        ProjectDeleted::dispatch($project->id);
        Cache::flush();

        return redirect()->back()->success('Orden de trabajo archivado', 'La orden de trabajo fue archivado exitosamente.');
    }

    public function restore(int $projectId): RedirectResponse
    {
        $project = Project::withArchived()->findOrFail($projectId);

        $this->authorize('restore', $project);

        $project->update(['motive_archived' => null]);
        $project->unArchive();
        ProjectRestored::dispatch($project);
        Cache::flush();

        return redirect()->back()->success('Orden de trabajo restaurado', 'La restauración de la orden se completó con éxito.');
    }

    public function favoriteToggle(Project $project)
    {
        request()->user()->toggleFavorite($project);

        return redirect()->back();
    }

    public function userAccess(Request $request, Project $project)
    {
        $this->authorize('editUserAccess', $project);

        $userIds = array_merge(
            $request->get('users', []),
            $request->get('clients', [])
        );

        (new ProjectService($project))->updateUserAccess($userIds);

        return redirect()->back();
    }

    public function reorder(Request $request): JsonResponse
    {
        $this->authorize('reorder', Project::class);

        Project::setNewOrder($request->ids);

        ProjectOrderChanged::dispatch(
            $request->group_id,
            $request->from_index,
            $request->to_index,
        );

        Cache::flush();

        return response()->json();
    }

    public function move(Request $request): JsonResponse
    {
        $this->authorize('reorder', Project::class);

        Project::setNewOrder($request->ids);
        Project::whereIn('id', $request->ids)->update(['group_id' => $request->to_group_id]);

        $groupMappings = [
            2 => [3 => 'user_review'],
            3 => [4 => 'user_finalize'],
        ];

        if (isset($groupMappings[$request->from_group_id][$request->to_group_id])) {
            $field = $groupMappings[$request->from_group_id][$request->to_group_id];
            Project::whereIn('id', $request->ids)->update([$field => auth()->id()]);
        }
        ProjectGroupChanged::dispatch(
            $request->from_group_id,
            $request->to_group_id,
            $request->from_index,
            $request->to_index,
        );
        Cache::flush();

        return response()->json(Label::get(['id', 'name', 'color']));
    }

    public function complete(Request $request, Project $project): JsonResponse
    {
        $this->authorize('complete', $project);

        $project->update([
            'completed_at' => ($request->completed == true) ? now() : null,
        ]);
        ProjectUpdated::dispatch($project, 'completed_at');
        Cache::flush();

        return response()->json();
    }

    public function expired(Request $request, Project $project): JsonResponse
    {

        $request->option ? $project->labels()->sync(6, false) : $project->labels()->detach(6);
        ProjectUpdated::dispatch($project, 'labels');
        Cache::flush();

        return response()->json();
    }

    public function checklist(Request $request, Project $project, Task $task): JsonResponse
    {
        abort_if($request->user()->hasRole('control'), 401);

        if ($request->check && ($task->sent_archive != 1 || ! $task->attachments->isEmpty())) {
            $task->update([
                'check' => $request->check,
                'group_id' => $project->taskGroups()->pluck('id', 'name')['Proceso'],
                'completed_by_user_id' => $request->user()->id,
                'completed_at' => now(),
            ]);
            $task->labels()->sync(7);
        }

        if (! $request->check && $request->type_check) {
            $task->update([
                'check' => $request->check,
                'type_check' => $request->type_check,
                'group_id' => $project->taskGroups()->pluck('id', 'name')['Pendiente'],
                'completed_at' => null,
            ]);
            $task->labels()->sync(1);
        }

        $user = auth()->user();
        $projectGroup = Project::find($project->id)
            ->loadDefault()
            ->load([
                'tasks' => function ($query) use ($user) {
                    $query->when($user->hasRole('cliente'), fn ($query) => $query->where('hidden_from_clients', false))
                        // ->where('assigned_to_user_id', $user->id)
                        ->orderByRaw('number ASC')
                        ->with([
                            'labels:id,name,color',
                            'assignedToUser:id,name',
                            'completedByUser:id,name',
                            'taskGroup:id,name',
                            'attachments',
                        ]);
                },
            ])
            ->loadCount([
                'tasks AS all_tasks_count',
                'tasks AS completed_tasks_count' => fn ($query) => $query->whereNotNull('completed_at'),
                'tasks AS overdue_tasks_count' => fn ($query) => $query->whereNull('completed_at')->whereDate('due_on', '<', now()),
            ]);

        if ($projectGroup->all_tasks_count == $projectGroup->completed_tasks_count && ! $projectGroup->labels()->where('id', 7)->exists()) {
            $projectGroup->labels()->attach(7);
            $projectGroup->load('labels');
        }

        Cache::flush();

        return response()->json([
            'project' => $projectGroup,
            'task' => $task->loadDefault(),
            'message' => $task->sent_archive == 1 && $task->attachments->isEmpty() && $request->check ? true : false,
        ]);
    }

    public function moveSelectedProjects(StoreProjectRequest $request): JsonResponse
    {
        $this->authorize('reorder', Project::class);
        $data = $request->validated();
        $nameProject = preg_replace('/\s*\(\d{4}-\d{2}-\d{2}\)\s*$/', '', $data['name']);
        $defaultProject = Project::where('name', $nameProject)->first();
        if ($defaultProject) {
            $defaultProject->update(['due_on' => $data['due_on']]);
        }

        $project = (new CreateProject)->create($request->validated());
        $user = auth()->user();
        $project = Project::find($project->id)
            ->loadDefault()
            ->load([
                'tasks' => function ($query) use ($user) {
                    $query->when($user->hasRole('cliente'), fn ($query) => $query->where('hidden_from_clients', false))
                        // ->where('assigned_to_user_id', $user->id)
                        ->orderByRaw('number ASC')
                        ->with([
                            'labels:id,name,color',
                            'assignedToUser:id,name',
                            'taskGroup:id,name',
                            'attachments',
                        ]);
                },
            ])
            ->loadCount([
                'tasks AS all_tasks_count',
                'tasks AS completed_tasks_count' => fn ($query) => $query->whereNotNull('completed_at'),
                'tasks AS overdue_tasks_count' => fn ($query) => $query->whereNull('completed_at')->whereDate('due_on', '<', now()),
            ]);
        Cache::flush();

        return response()->json($project);
    }

    public function pdf(Project $project)
    {
        if ($project->type_id == null) {
            $project->update(['type_id' => 2]);
        }
        $data = [
            'ownerCompany' => OwnerCompany::first(),
            'project' => $project->loadDefault(),
            'asset' => Asset::find($project->game()->get('asset_id')),
            'tasks' => Task::where('project_id', $project->id)->withDefault()->get(),
            'timeLogs' => TimeLog::where('project_id', $project->id)->first(),
        ];
        $pdf = Pdf::loadView('vendor.project.pdf', $data);

        return $pdf->stream();
    }
}
