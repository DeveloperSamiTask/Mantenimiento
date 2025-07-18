<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Notifications\ProjectsOverdueNotification;
use App\Services\PermissionService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $projectIds = PermissionService::projectsThatUserCanAccess(auth()->user())->pluck('id');
        return Inertia::render('Dashboard/Index', [
            'projects' => Project::whereIn('id', $projectIds)
                ->where('default', '!=', 1)
                ->whereDate('created_at', now()->toDateString())
                ->with([
                    'clientCompany:id,name',
                ])
                ->withCount([
                    'tasks AS all_tasks_count',
                    'tasks AS completed_tasks_count' => fn ($query) => $query->whereNotNull('completed_at'),
                    'tasks AS overdue_tasks_count' => fn ($query) => $query->whereNull('completed_at')->whereDate('due_on', '<', now()),
                ])
                ->withExists('favoritedByAuthUser AS favorite')
                ->orderBy('favorite', 'desc')
                ->orderBy('name', 'asc')
                ->get(['id', 'name']),
            'overdueTasks' => Task::whereIn('project_id', $projectIds)
                ->whereHas('project', function ($request) {
                    $request->whereNull('archived_at');
                })
                ->whereNull('completed_at')
                ->whereDate('due_on', '<', now())
                ->whereYear('due_on', now())
                ->when($request->user()->isNotAdmin(), fn($q) => $q->where('assigned_to_user_id', auth()->id()))
                ->with('project:id,name')
                ->with('taskGroup:id,name')
                ->orderBy('due_on')
                ->get(['id', 'name', 'due_on', 'group_id', 'project_id']),
            'recentlyAssignedTasks' => Task::whereIn('project_id', $projectIds)
                ->whereHas('project', function ($request) {
                    $request->whereNull('archived_at');
                })
                ->whereNull('completed_at')
                ->whereNotNull('assigned_at')
                ->when($request->user()->isNotAdmin(), fn($q) => $q->where('assigned_to_user_id', auth()->id()))
                ->whereYear('due_on', now())
                ->with('project:id,name')
                ->with('taskGroup:id,name')
                ->orderBy('assigned_at')
                ->limit(10)
                ->get(['id', 'name', 'assigned_at', 'group_id', 'project_id']),
            'recentComments' => Comment::query()
                ->whereHas('task', function ($query) use ($projectIds) {
                    $query->whereIn('project_id', $projectIds)
                        ->whereHas('project', function ($request) {
                            $request->whereNull('archived_at');
                        })
                        ->whereYear('due_on', now())
                        ->where('assigned_to_user_id', auth()->id());
                })
                ->with([
                    'task:id,name,project_id',
                    'task.project:id,name',
                    'user:id,name',
                ])
                ->latest()
                ->get(),
        ]);
    }
}
