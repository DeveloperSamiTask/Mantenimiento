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
use App\Models\Insumo;
use App\Models\Label;
use App\Models\OTInsumo;
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
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class ProjectController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Project::class, 'project');
    }

    /* plan de tareas */
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

    /* KANBAN RESPALDO
    */
    public function kanban(Request $request, ?Project $project = null)
    {
        $tiempoTotal = microtime(true);
        $user = $request->user();

        // ── GRUPOS ──
        $t = microtime(true);
        $groups = ProjectGroup::when($request->has('archived'), fn ($q) => $q->onlyArchived())->get();
        Log::info('[KANBAN] Grupos: '.round((microtime(true) - $t) * 1000).'ms');

        // ── PROYECTOS + CONTEOS (sin tareas) ──
        $groupedProjects = $groups->mapWithKeys(function (ProjectGroup $group) use ($user, $request) {

            $t = microtime(true);
            $query = Project::query()
                ->where('group_id', $group->id)
                ->searchByQueryString()
                ->filterByQueryString()
                ->when($user->isNotAdmin(), fn ($q) => $q->whereHas('users', fn ($q) => $q->where('id', $user->id)))
                ->when(! $request->filled('date'), function ($q) {
                    $q->where(function ($q) {
                        $q->whereNull('due_on')
                            ->orWhere('default', '!=', '0')
                            ->orWhereDate('due_on', '<=', now());
                    });
                })
                ->when(! request()->filled('date'), function ($q) {
                    $q->where(function ($q) {
                        $q->whereDate('completed_at', now())
                            ->orWhereNull('completed_at');
                    });
                })
                ->orderBy('due_on', 'DESC')
                ->withDefault()
                ->limit(50);

            $projects = $query->get();
            Log::info('[KANBAN] Proyectos grupo '.$group->id.': '.round((microtime(true) - $t) * 1000).'ms — '.$projects->count().' OTs');

            // ── CONTEOS (se quedan, el tablero los necesita) ──
            $t = microtime(true);
            $projects->loadCount([
                'tasks AS all_tasks_count',
                'tasks AS completed_tasks_count' => fn ($q) => $q->whereNotNull('completed_at'),
                'tasks AS overdue_tasks_count' => fn ($q) => $q->whereNull('completed_at')->whereDate('due_on', '<', now()),
            ]);
            Log::info('[KANBAN] Conteos grupo '.$group->id.': '.round((microtime(true) - $t) * 1000).'ms');

            // ── TAREAS ELIMINADAS ──
            // Antes cargaba tareas + labels + adjuntos + usuarios aquí.
            // Ahora se cargan lazy cuando el usuario abre una OT.

            return [$group->id => $projects];
        });

        Log::info('[KANBAN] TOTAL: '.round((microtime(true) - $tiempoTotal) * 1000).'ms');

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

    public function loadMoreProjects(Request $request, $groupId)
    {
        $user = $request->user();
        $offset = $request->input('offset', 0);

        $query = Project::query()
            ->where('group_id', $groupId)
            ->searchByQueryString()
            ->filterByQueryString()
            ->when($user->isNotAdmin(), fn ($q) => $q->whereHas('users', fn ($q) => $q->where('id', $user->id)))
             /*
                        ->where(function ($q) {
                            $q->whereNull('due_on')
                            ->orWhere('default', '!=', '0')
                            ->orWhereDate('due_on', '<=', now());
                    })
                    */
            ->when(! $request->filled('date'), function ($q) {
                $q->where(function ($q) {
                    $q->whereNull('due_on')
                        ->orWhere('default', '!=', '0')
                        ->orWhereDate('due_on', '<=', now());
                });
            })
            ->where(function ($q) {
                $q->whereDate('completed_at', now())
                    ->orWhereNull('completed_at');
            })
            ->orderBy('due_on', 'DESC')
            ->withDefault()
            ->skip($offset)
            ->limit(20);

        Log::info('SQL Query:', [
            'sql' => $query->toSql(),
            'bindings' => $query->getBindings(),
            'request_periods' => $request->periods,
        ]);

        $projects = $query->get();

        $projects->loadCount([
            'tasks AS all_tasks_count',
            'tasks AS completed_tasks_count' => fn ($q) => $q->whereNotNull('completed_at'),
            'tasks AS overdue_tasks_count' => fn ($q) => $q->whereNull('completed_at')->whereDate('due_on', '<', now()),
        ]);

        return response()->json([
            'projects' => $projects,
            'hasMore' => $projects->count() === 20,
        ]);
    }

    public function loadMoreCompletados(Request $request, $groupId)
    {
        $user = $request->user();
        $offset = $request->input('offset', 0); // Desde qué proyecto empezar

        $projects = Project::where('group_id', $groupId)
            ->searchByQueryString()
            ->filterByQueryString()
            ->when($request->user()->isNotAdmin(), function ($query) {
                $query->whereHas('users', fn ($query) => $query->where('id', auth()->id()));
            })
            ->when($request->has('archived'), fn ($query) => $query->onlyArchived())
            ->whereHas('labels', fn ($query) => $query->where('id', 7))
            ->where(function ($query) {
                $query->whereNull('due_on')
                    ->orWhere('default', '!=', '0')
                    ->orWhereDate('due_on', '<=', now());
            })
            ->where(function ($query) {
                $query->where(function ($q) {
                    $q->whereDate('completed_at', now());
                })
                    ->orWhereNull('completed_at');
            })
            ->withDefault()
            ->skip($offset)  // 👈 Salta los que ya cargaste
            ->take(20)       // 👈 Trae 15 más
            ->get();

        // Cargar counts y tareas igual que antes
        $projects->loadCount([
            'tasks AS all_tasks_count',
            'tasks AS completed_tasks_count' => fn ($query) => $query->whereNotNull('completed_at'),
            'tasks AS overdue_tasks_count' => fn ($query) => $query->whereNull('completed_at')->whereDate('due_on', '<', now()),
        ]);

        return response()->json($projects);
    }

    public function completados(Request $request, ?Project $project = null)
    {
        $user = $request->user();

        // 1. Obtener los grupos
        $groups = ProjectGroup::when($request->has('archived'), fn ($query) => $query->onlyArchived())->get();

        // 2. Proyectos agrupados por columnas - SOLO COMPLETADOS
        $groupedProjects = $groups->mapWithKeys(function (ProjectGroup $group) use ($request) {

            $projectsQuery = Project::where('group_id', $group->id)
                ->searchByQueryString()
                ->filterByQueryString()
                ->when($request->user()->isNotAdmin(), function ($query) {
                    $query->whereHas('users', fn ($query) => $query->where('id', auth()->id()));
                })
                ->when($request->has('archived'), fn ($query) => $query->onlyArchived())
                ->orWhere('default', '!=', '0')
                ->whereHas('labels', fn ($query) => $query->where('id', 7)) // ID 7 = Completado
                ->orderBy('due_on', 'DESC') // Del más reciente (hoy) hacia atrás
                ->withDefault();

            // 👇 CAMBIO: En lugar de limit(), solo traemos los primeros 15
            $projects = $projectsQuery->take(20)->get();

            // Cargar counts
            $projects->loadCount([
                'tasks AS all_tasks_count',
                'tasks AS completed_tasks_count' => fn ($query) => $query->whereNotNull('completed_at'),
                'tasks AS overdue_tasks_count' => fn ($query) => $query->whereNull('completed_at')->whereDate('due_on', '<', now()),
            ]);

            // Cargar tareas (con TODAS sus relaciones como antes)
            return [$group->id => $projects];
        });

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

    public function kanban_RESPALDO(Request $request, ?Project $project = null)
    {

        // 1.obtener los grupos de los projectos
        $groups = ProjectGroup::when($request->has('archived'), fn ($query) => $query->onlyArchived())->get();
        $user = request()->user();
        $key = $request->archived ? 'groupedProjects'.$request->archived : 'groupedProjects';
        // if(Cache::has($key)){
        // $groupedProjects = Cache::get($key);
        // }else{

        // 2. proyectos agrupados por columnas
        $groupedProjects = ProjectGroup::with(['projects' => fn ($query) => $query->withArchived()])->get()
            ->mapWithKeys(function (ProjectGroup $group) use ($request, $user) {
                $projects = Project::where('group_id', $group->id)
                    ->searchByQueryString()
                    ->filterByQueryString()
                    // 3. solo el admin puede ver todos los productos
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
                // ->when($group->name === 'Plantilla', fn ($q) => $q->limit(20)) // 👈 aquí limitas solo Plantilla
                // ->when($group->name === 'Plantilla' && !$request->has('search'), fn($q) => $q->limit(20))
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

        // 4. retorna vista con los datos
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

    // Método nuevo para cargar más proyectos

    /* Envia datos para una lista desplegable */
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

    /* Se ejecuta cuando el usuario manda la solicitud POST */
    public function store(StoreProjectRequest $request): RedirectResponse
    {
        // 1.llama a un servicio que guarda el proyecto en la bd
        $project = (new CreateProject)->create($request->validated());

        // 2. Crea tareas iniciales
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

        $t = microtime(true);

        $nameProject = preg_replace('/\s*\(\d{4}-\d{2}-\d{2}\)\s*$/', '', $data['name']);
        $defaultProject = Project::where('name', $nameProject)->first();
        if ($defaultProject) {
            $defaultProject->update(['due_on' => $data['due_on']]);
        }

        Log::info('[MOVE] Buscar plantilla: '.round((microtime(true) - $t) * 1000).'ms');

        $t = microtime(true);
        $project = (new CreateProject)->create($request->validated());
        Log::info('[MOVE] Crear proyecto: '.round((microtime(true) - $t) * 1000).'ms');

        if (! empty($data['insumos'])) {
            $otInsumo = OTInsumo::create([
                'ot_id' => $project->id,
                'due_on' => $data['due_on'],
                'game_id' => $data['game_id'] ?? null,
                'period_id' => $data['period_id'] ?? null,
                'user_id' => auth()->id(),
                'name' => $project->id.' Insumo '.($data['name'] ?? ''),
            ]);

            Log::info('[MOVE] Crear OTInsumo: '.round((microtime(true) - $t) * 1000).'ms');

            $t = microtime(true);
            Insumo::insert(
                collect($data['insumos'])->map(fn ($i) => [
                    'ot_insumos_id' => $otInsumo->id,
                    'cod_producto' => $i['cod_producto'],
                    'name' => $i['name'],
                    'almacen' => $i['almacen'],
                    'unidad' => $i['unidad'] ?? '',
                    'cantidad' => $i['cantidad'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ])->toArray()
            );

            Log::info('[MOVE] Insert insumos: '.round((microtime(true) - $t) * 1000).'ms');

        }

        $t = microtime(true);
        $project = Project::with([
            'clientCompany:id,name',
            'users:id,name,signature',
            'userGenerate:id,name,signature',
            'userReview:id,name,signature',
            'userFinalize:id,name,signature',
            'game:id,name',
            'type:id,name',
            'period:id,name',
            'labels:id,name,color',
            'timeLogs.user:id,name',
        ])
            ->withCount([
                'tasks AS all_tasks_count',
                'tasks AS completed_tasks_count' => fn ($q) => $q->whereNotNull('completed_at'),
                'tasks AS overdue_tasks_count' => fn ($q) => $q->whereNull('completed_at')->whereDate('due_on', '<', now()),
            ])
            ->find($project->id);
        Log::info('[MOVE] Cargar proyecto completo: '.round((microtime(true) - $t) * 1000).'ms');

        $t = microtime(true);

        Cache::flush();
        Log::info('[MOVE] Cache flush: '.round((microtime(true) - $t) * 1000).'ms');

        return response()->json($project);
    }

    /* Descarga individual */
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

        // Comprime imágenes de tareas
        foreach ($data['tasks'] as $task) {
            foreach ($task->attachments as $attachment) {
                $path = public_path($attachment->path);
                if (file_exists($path)) {
                    $attachment->compressed_base64 = $this->compressImage($path);
                }
            }
        }

        // Comprime firmas - AQUÍ es donde estaba el problema
        if ($data['project']->userReview && $data['project']->userReview->signature) {
            $path = public_path($data['project']->userReview->signature);
            if (file_exists($path)) {
                $data['aceptado'] = $this->compressImage($path);
            }
        }

        if ($data['project']->userFinalize && $data['project']->userFinalize->signature) {
            $path = public_path($data['project']->userFinalize->signature);
            if (file_exists($path)) {
                $data['validado'] = $this->compressImage($path);
            }
        }

        if ($data['timeLogs'] && $data['timeLogs']->user->signature) {
            $path = public_path($data['timeLogs']->user->signature);
            if (file_exists($path)) {
                $data['realizado'] = $this->compressImage($path);
            }
        }

        // Comprime firmas de usuarios
        foreach ($data['project']->users as $user) {
            if ($user->signature && file_exists(public_path($user->signature))) {
                $user->signature_compressed = $this->compressImage(public_path($user->signature));
            }
        }

        try {
            $pdf = Pdf::loadView('vendor.project.pdf', $data);

            return $pdf->stream('project.pdf');

        } catch (\Exception $e) {
            Log::error('PDF Error: '.$e->getMessage());
            throw $e;
        }
    }

    private function compressImage($imagePath)
    {
        try {
            $image = imagecreatefromstring(file_get_contents($imagePath));

            if (! $image) {
                return null;
            }

            ob_start();
            imagejpeg($image, null, 20); // 30% calidad
            $compressed = ob_get_clean();
            imagedestroy($image);

            return 'data:image/jpeg;base64,'.base64_encode($compressed);

        } catch (\Exception $e) {
            Log::error('Image compression failed: '.$e->getMessage());

            return null;
        }
    }

    /* Descarga masiva */
    public function downloadAllPdfs(Request $request)
    {
        try {
            set_time_limit(600);
            ini_set('memory_limit', '1024M');

            $projectIds = $request->input('ids', []);

            if (empty($projectIds)) {
                return response()->json(['error' => 'No se enviaron IDs'], 400);
            }

            // Obtener proyectos
            $projects = Project::whereIn('id', $projectIds)
                ->when($request->user()->isNotAdmin(), function ($query) {
                    $query->whereHas('clientCompany.clients', fn ($query) => $query->where('users.id', auth()->id()))
                        ->orWhereHas('users', fn ($query) => $query->where('id', auth()->id()));
                })
                ->get();

            if ($projects->isEmpty()) {
                return response()->json(['error' => 'No se encontraron proyectos'], 404);
            }

            // 👇 SEPARAR: Finalizados vs No finalizados
            $validProjects = [];
            $excludedProjects = [];

            // Logica de las 3 firmas
            foreach ($projects as $project) {
                // 🔵 Si es group_id = 4 (Finalizado) → VALIDAR FIRMAS
                if ($project->group_id == 4) {

                    if ($this->tieneLasTresFirmas($project)) {
                        $validProjects[] = $project; // ✅ Tiene las 3 firmas
                    } else {
                        $excludedProjects[] = $project->name; // ❌ Le faltan firmas
                    }
                }
                // 🟢 Si NO es Finalizado → DESCARGAR SIN VALIDAR
                else {
                    $validProjects[] = $project;
                }
            }

            // Si NO hay proyectos válidos
            if (empty($validProjects)) {
                return response()->json([
                    'error' => 'No hay proyectos descargables',
                    'message' => 'Los proyectos finalizados deben tener las 3 firmas completas',
                    'excluidos' => $excludedProjects,
                ], 400);
            }

            // Crear ZIP
            $tempDir = storage_path('app/temp');
            if (! file_exists($tempDir)) {
                mkdir($tempDir, 0755, true);
            }

            $zipFileName = 'proyectos_'.date('Y-m-d_His').'.zip';
            $zipPath = $tempDir.'/'.$zipFileName;

            $zip = new \ZipArchive;

            if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
                return response()->json(['error' => 'No se pudo crear el ZIP'], 500);
            }

            // Generar PDFs
            $generatedCount = 0;
            foreach ($validProjects as $project) {
                try {
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

                    // Comprimir imágenes
                    foreach ($data['tasks'] as $task) {
                        foreach ($task->attachments as $attachment) {
                            $path = public_path($attachment->path);
                            if (file_exists($path)) {
                                $attachment->compressed_base64 = $this->compressImage($path);
                            }
                        }
                    }

                    // Comprimir firmas
                    if ($data['project']->userReview && $data['project']->userReview->signature) {
                        $path = public_path($data['project']->userReview->signature);
                        if (file_exists($path)) {
                            $data['aceptado'] = $this->compressImage($path);
                        }
                    }

                    if ($data['project']->userFinalize && $data['project']->userFinalize->signature) {
                        $path = public_path($data['project']->userFinalize->signature);
                        if (file_exists($path)) {
                            $data['validado'] = $this->compressImage($path);
                        }
                    }

                    if ($data['timeLogs'] && $data['timeLogs']->user->signature) {
                        $path = public_path($data['timeLogs']->user->signature);
                        if (file_exists($path)) {
                            $data['realizado'] = $this->compressImage($path);
                        }
                    }

                    foreach ($data['project']->users as $user) {
                        if ($user->signature && file_exists(public_path($user->signature))) {
                            $user->signature_compressed = $this->compressImage(public_path($user->signature));
                        }
                    }

                    $pdf = Pdf::loadView('vendor.project.pdf', $data);
                    $pdfContent = $pdf->output();

                    $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $project->name);
                    $pdfName = $project->id.'_'.$safeName.'.pdf';

                    $zip->addFromString($pdfName, $pdfContent);
                    $generatedCount++;

                } catch (\Exception $e) {
                    Log::error("Error generando PDF proyecto {$project->id}: ".$e->getMessage());

                    continue;
                }
            }

            $zip->close();

            // Crear respuesta
            $response = response()->download($zipPath, $zipFileName)->deleteFileAfterSend(true);

            // 👇 Agregar mensaje si hay excluidos
            if (count($excludedProjects) > 0) {
                $message = sprintf(
                    'Se descargaron %d proyectos. %d excluidos por firmas incompletas: %s',
                    $generatedCount,
                    count($excludedProjects),
                    implode(', ', array_slice($excludedProjects, 0, 3)).(count($excludedProjects) > 3 ? '...' : '')
                );

                $response->headers->set('X-Download-Info', $message);
            }

            return $response;

        } catch (\Exception $e) {
            Log::error('Error generando ZIP: '.$e->getMessage());

            return response()->json(['error' => 'Error: '.$e->getMessage()], 500);
        }
    }

    // Mantén este método igual
    private function tieneLasTresFirmas(Project $project)
    {
        if (! $project->userReview || ! $project->userReview->signature) {
            return false;
        }

        if (! $project->userFinalize || ! $project->userFinalize->signature) {
            return false;
        }

        $timeLogs = TimeLog::where('project_id', $project->id)->first();
        if (! $timeLogs || ! $timeLogs->user || ! $timeLogs->user->signature) {
            return false;
        }

        return true;
    }

    public function downloadAllFilteredPdfs(Request $request)
    {
        try {
            set_time_limit(600);
            ini_set('memory_limit', '1024M');

            // 1. QUERY BASE SIMPLE
            $query = Project::query()
                ->when($request->user()->isNotAdmin(), function ($query) {
                    $query->whereHas('clientCompany.clients', fn ($q) => $q->where('users.id', auth()->id()))
                        ->orWhereHas('users', fn ($q) => $q->where('id', auth()->id()));
                })
                ->where('default', '!=', 1);

            // 2. ✅ APLICAR FILTROS QUE SÍ RECIBES
            $hasAnyFilter = false;

            // Filtro games
            if ($request->filled('games') && count($request->games) > 0) {
                $query->whereIn('game_id', $request->games);
                $hasAnyFilter = true;
            }

            // Filtro periods
            if ($request->filled('periods') && count($request->periods) > 0) {
                $query->whereIn('period_id', $request->periods);
                $hasAnyFilter = true;
            }

            // Filtro groups
            if ($request->filled('groups') && count($request->groups) > 0) {
                $query->whereIn('group_id', $request->groups);
                $hasAnyFilter = true;
            }

            // Filtro dateRange
            if ($request->filled('dateRange') && is_array($request->dateRange) && count($request->dateRange) === 2) {
                $startDate = Carbon::parse($request->dateRange[0])->startOfDay();
                $endDate = Carbon::parse($request->dateRange[1])->endOfDay();

                if ($startDate->isSameDay($endDate)) {
                    $query->whereDate('due_on', $startDate);
                } else {
                    $query->whereBetween('due_on', [$startDate, $endDate]);
                }
                $hasAnyFilter = true;
            }

            // 3. ✅ SI NO HAY FILTROS, aplicar default
            if (! $hasAnyFilter) {
                $query->whereDate('created_at', '>=', now()->subDays(2));
            }

            // 4. EJECUTAR QUERY
            $projects = $query->get();

            if ($projects->isEmpty()) {
                return response()->json(['error' => 'No hay proyectos con esos filtros'], 404);
            }

            // 4. ✅ CREAR ZIP (tu código actual)
            $zip = new \ZipArchive;
            $zipFileName = 'todos_proyectos_'.date('Y-m-d_His').'.zip';
            $zipPath = storage_path('app/temp/'.$zipFileName);

            if (! file_exists(dirname($zipPath))) {
                mkdir(dirname($zipPath), 0755, true);
            }

            if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
                return response()->json(['error' => 'No se pudo crear el archivo ZIP'], 500);
            }

            $processed = 0;
            foreach ($projects as $project) {
                try {

                    // Reutilizar tu método pdf() existente
                    $pdfResponse = $this->pdf($project);
                    $pdfContent = $pdfResponse->getContent();

                    $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $project->name);
                    $pdfName = $project->id.'_'.$safeName.'.pdf';

                    $zip->addFromString($pdfName, $pdfContent);
                    $processed++;

                } catch (\Exception $e) {

                    continue;
                }
            }

            $zip->close();

            return response()->download($zipPath, $zipFileName)->deleteFileAfterSend(true);

        } catch (\Exception $e) {

            return response()->json(['error' => 'Error generando archivos: '.$e->getMessage()], 500);
        }
    }

    public function getAllFilteredIds(Request $request)
    {
        $ids = Project::without('type')
            ->with(['tasks', 'users', 'labels', 'game', 'projectGroup'])
            ->where('default', 0)
            ->when($request->groups, fn ($query) => $query->whereIn('projects.group_id', $request->groups))
            ->when($request->games, fn ($query) => $query->whereIn('projects.game_id', $request->games))
            ->when($request->periods, function ($query) use ($request) {
                $query->where(function ($q) use ($request) {
                    $q->whereIn('projects.period_id', $request->periods)
                        ->orWhereNull('projects.period_id');
                });
            })
            ->when($request->dateRange,
                function ($query) use ($request) {
                    $query->whereBetween('due_on', [
                        Carbon::parse($request->dateRange[0])->startOfDay(),
                        Carbon::parse($request->dateRange[1])->endOfDay(),
                    ]);
                },
                fn ($query) => $query->where('projects.created_at', '>', now()->subWeek())
            )
            ->pluck('id')
            ->toArray();

        return response()->json([
            'ids' => $ids,
            'total' => count($ids),
        ]);
    }
}
