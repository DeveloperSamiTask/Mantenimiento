<?php

namespace App\Http\Controllers;

use App\Exports\ProjectsExport;
use App\Models\ClientCompany;
use App\Models\Game;
use App\Models\Period;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;

class ReportController extends Controller
{
    public function loggedTimeSum(Request $request): Response
    {
        Gate::allowIf(fn (User $user) => $user->can('ver informe de suma de tiempo registrado'));

        $completed = $request->get('completed', 'true') === 'true';

        return Inertia::render('Reports/LoggedTimeSum', [
            'projects' => DB::table('time_logs')
                ->join('tasks', 'tasks.id', '=', 'time_logs.task_id')
                ->join('projects', 'projects.id', '=', 'tasks.project_id')
                ->join('users', 'time_logs.user_id', '=', 'users.id')
                ->when($request->projects, fn ($query) => $query->whereIn('projects.id', $request->projects))
                ->when($request->users, fn ($query) => $query->whereIn('time_logs.user_id', $request->users))
                ->when($request->dateRange,
                    function ($query) use ($request) {
                        $query->whereBetween('time_logs.created_at', [
                            Carbon::parse($request->dateRange[0])->startOfDay(),
                            Carbon::parse($request->dateRange[1])->endOfDay(),
                        ]);
                    },
                    fn ($query) => $query->where('time_logs.created_at', '>', now()->subWeek())
                )
                ->{$completed ? 'whereNotNull' : 'whereNull'}('tasks.completed_at')
                ->where('billable', $request->get('billable', 'true') === 'true')
                ->groupBy(['tasks.project_id'])
                ->selectRaw('
                    MAX(projects.id) AS project_id, MAX(projects.name) AS project_name,
                    MAX(projects.rate) AS project_rate, MAX(projects.client_company_id) AS client_company_id,
                    MAX(users.id) AS user_id, MAX(users.name) AS user_name, MAX(users.rate) AS user_rate,
                    SUM(time_logs.minutes) / 60 AS total_hours
                ')
                ->orderBy('project_name')
                ->get()
                ->groupBy('project_id'),
            'clientCompanies' => ClientCompany::with('currency')->get(['id', 'name', 'currency_id']),
            'dropdowns' => [
                'projects' => Project::dropdownValues(),
                'users' => User::userDropdownValues(['cliente']),
            ],
        ]);
    }

    public function dailyLoggedTime(Request $request): Response
    {
        Gate::allowIf(fn (User $user) => $user->can('ver informe diario de tiempo registrado'));

        $completed = $request->get('completed', 'true') === 'true';

        $items = DB::table('time_logs')
            ->join('tasks', 'tasks.id', '=', 'time_logs.task_id')
            ->join('projects', 'projects.id', '=', 'tasks.project_id')
            ->join('users', 'time_logs.user_id', '=', 'users.id')
            ->when($request->projects, fn ($query) => $query->whereIn('projects.id', $request->projects))
            ->when($request->users, fn ($query) => $query->whereIn('time_logs.user_id', $request->users))
            ->when($request->dateRange,
                function ($query) use ($request) {
                    $query->whereBetween('time_logs.created_at', [
                        Carbon::parse($request->dateRange[0])->startOfDay(),
                        Carbon::parse($request->dateRange[1])->endOfDay(),
                    ]);
                },
                fn ($query) => $query->where('time_logs.created_at', '>', now()->subWeek())
            )
            ->{$completed ? 'whereNotNull' : 'whereNull'}('tasks.completed_at')
            ->where('billable', $request->get('billable', 'true') === 'true')
            ->groupBy(['time_logs.user_id', 'date'])
            ->selectRaw('
                MAX(projects.id) AS project_id, MAX(projects.name) AS project_name,
                MAX(users.id) AS user_id, MAX(users.name) AS user_name,
                SUM(time_logs.minutes) / 60 AS total_hours, DATE_FORMAT(time_logs.created_at, "%e. %b %Y") AS date
            ')
            ->orderBy('date')
            ->get();

        return Inertia::render('Reports/DailyLoggedTime', [
            'items' => $items
                ->groupBy('date')
                ->map->keyBy('user_id'),
            'users' => $items
                ->unique('user_id')
                ->mapInto(Collection::class)
                ->map->only('user_name', 'user_id')
                ->keyBy('user_id')
                ->sortBy('user_name'),
            'dropdowns' => [
                'projects' => Project::dropdownValues(),
                'users' => User::userDropdownValues(),
            ],
        ]);
    }

    public function searchProjects(Request $request): Response
    {
        Gate::allowIf(fn (User $user) => $user->can('buscar ordenes de trabajo'));
        $games = Game::dropdownValues();
        $periods = Period::dropdownValues();

        $items = Project::without('type') // ← ESTO ES LO MÁS IMPORTANTE
            ->with(['tasks', 'users', 'labels', 'game', 'projectGroup']) // ← SIN 'type' aquí
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
            ->orderBy('due_on', 'desc')
            ->paginate(12)
            ->withQueryString();

        return Inertia::render('Reports/SearchProject', [
            'items' => $items,
            'games' => $games,
            'periods' => $periods,
        ]);
    }

    public function exportProjects(Request $request)
    {
        try {

            $query = Project::query()
                ->with(['clientCompany', 'game', 'period', 'projectGroup'])
                ->where('default', 0)
                ->when($request->filled('games'), function ($q) use ($request) {
                    $q->whereIn('game_id', $request->games);
                })
                ->when($request->filled('periods'), function ($q) use ($request) {
                    $q->whereIn('period_id', $request->periods);
                })
                ->when($request->filled('groups'), function ($q) use ($request) {
                    $q->whereIn('group_id', $request->groups);
                })
                ->when($request->filled('dateRange'), function ($q) use ($request) {
                    $dates = array_values(array_filter($request->dateRange));
                    if (count($dates) >= 2) {
                        $start = Carbon::parse($dates[0])->startOfDay();
                        $end = Carbon::parse($dates[1])->endOfDay();
                        $q->whereBetween('due_on', [$start, $end]);
                    }
                });

            // ✅ OBTENER TODOS LOS REGISTROS (SIN PAGINACIÓN)
            $projects = $query->get();

            $data = ['projects' => $projects];

            return Excel::download(new ProjectsExport($data), 'OTs.xlsx');

        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
