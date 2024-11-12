<?php

use App\Http\Controllers\Account\NotificationController;
use App\Http\Controllers\Account\ProfileController;
use App\Http\Controllers\Attraction\AssetController;
use App\Http\Controllers\Attraction\CheckListController;
use App\Http\Controllers\Attraction\GameController;
use App\Http\Controllers\Client\ClientCompanyController;
use App\Http\Controllers\Client\ClientUserController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DropdownValuesController;
use App\Http\Controllers\Invoice\InvoiceTasksController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\MyWork\ActivityController;
use App\Http\Controllers\MyWork\MyWorkTaskController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\Settings\LabelController;
use App\Http\Controllers\Settings\OwnerCompanyController;
use App\Http\Controllers\Settings\RoleController;
use App\Http\Controllers\Task\AttachmentController;
use App\Http\Controllers\Task\CommentController;
use App\Http\Controllers\Task\GroupController;
use App\Http\Controllers\Task\TimeLogController;
use App\Http\Controllers\Project\TimeLogController as TimeLogProjectController;
use App\Http\Controllers\Settings\TypeCheckController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', 'dashboard');

Route::group(['middleware' => ['auth:sanctum']], function () {
    // Dashboard
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Projects
    Route::resource('projects', ProjectController::class)->except(['show']);

    Route::group(['prefix' => 'projects', 'as' => 'projects.'], function () {
        // PROJECT
        Route::post('{projectId}/restore', [ProjectController::class, 'restore'])->name('restore');
        Route::put('{project}/favorite/toggle', [ProjectController::class, 'favoriteToggle'])->name('favorite.toggle');
        Route::post('{project}/user-access', [ProjectController::class, 'userAccess'])->name('user_access');

        // KANBAN
        Route::get('kanban', [ProjectController::class, 'kanban'])->name('kanban');
        Route::group(['prefix' => 'kanban', 'as' => 'kanban.'], function () {

            // PROJECTS GROUPS
            Route::post('groups', [GroupController::class, 'store'])->name('groups.store');
            Route::put('groups/{projectGroup}', [GroupController::class, 'update'])->name('groups.update');
            Route::delete('groups/{projectGroup}', [GroupController::class, 'destroy'])->name('groups.destroy');
            Route::post('groups/{projectGroupId}/restore', [GroupController::class, 'restore'])->name('groups.restore');
            Route::post('groups/reorder', [GroupController::class, 'reorder'])->name('groups.reorder');

            // PROJECTS
            Route::post('', [ProjectController::class, 'store'])->name('store');
            Route::put('{project}', [ProjectController::class, 'update'])->name('update');
            Route::get('{project}/pdf', [ProjectController::class, 'pdf'])->name('pdf');
            Route::get('{project}/open', [ProjectController::class, 'kanban'])->name('open');
            Route::delete('{project}', [ProjectController::class, 'destroy'])->name('destroy');
            Route::post('{projectId}/restore', [ProjectController::class, 'restore'])->name('restore');
            Route::post('{project}/expired', [ProjectController::class, 'expired'])->name('expired');
            Route::post('{project}/complete', [ProjectController::class, 'complete'])->name('complete');
            Route::post('reorder', [ProjectController::class, 'reorder'])->name('reorder');
            Route::post('move', [ProjectController::class, 'move'])->name('move');
            Route::post('moveSelectedProjects', [ProjectController::class, 'moveSelectedProjects'])->name('moveSelectedProjects');

            // TIME LOGS
            Route::post('{project}/time-log', [TimeLogProjectController::class, 'store'])->name('time-logs.store');
            Route::delete('{project}/time-log/{timeLog}', [TimeLogProjectController::class, 'destroy'])->name('time-logs.destroy')->scopeBindings();
            Route::post('{project}/time-log/timer/start', [TimeLogProjectController::class, 'startTimer'])->name('time-logs.timer.start');
            Route::post('{project}/time-log/{timeLog}/timer/stop', [TimeLogProjectController::class, 'stopTimer'])->name('time-logs.timer.stop')->scopeBindings();

            // CHECKLIST
            Route::post('{project}/checklist/{task}', [ProjectController::class, 'checklist'])->name('check-list')->scopeBindings();

        });

        // TASK GROUPS
        Route::post('{project}/task-groups', [GroupController::class, 'store'])->name('task-groups.store');
        Route::put('{project}/task-groups/{taskGroup}', [GroupController::class, 'update'])->name('task-groups.update')->scopeBindings();
        Route::delete('{project}/task-groups/{taskGroup}', [GroupController::class, 'destroy'])->name('task-groups.destroy')->scopeBindings();
        Route::post('{project}/task-groups/{taskGroupId}/restore', [GroupController::class, 'restore'])->name('task-groups.restore')->scopeBindings();
        Route::post('{project}/task-groups/reorder', [GroupController::class, 'reorder'])->name('task-groups.reorder');

        // TASKS
        Route::get('{project}/tasks', [TaskController::class, 'index'])->name('tasks');
        Route::post('{project}/tasks', [TaskController::class, 'store'])->name('tasks.store');
        Route::put('{project}/tasks/{task}', [TaskController::class, 'update'])->name('tasks.update')->scopeBindings();
        Route::get('{project}/tasks/{task}/open/{controle?}', [TaskController::class, 'index'])->name('tasks.open')->scopeBindings();
        Route::delete('{project}/tasks/{task}', [TaskController::class, 'destroy'])->name('tasks.destroy')->scopeBindings();
        Route::post('{project}/tasks/{taskId}/restore', [TaskController::class, 'restore'])->name('tasks.restore')->scopeBindings();

        Route::post('{project}/tasks/{task}/complete', [TaskController::class, 'complete'])->name('tasks.complete')->scopeBindings();
        Route::post('{project}/tasks/reorder', [TaskController::class, 'reorder'])->name('tasks.reorder');
        Route::post('{project}/tasks/move', [TaskController::class, 'move'])->name('tasks.move');
        Route::post('{project}/tasks/{task}/expired', [TaskController::class, 'expired'])->name('tasks.expired')->scopeBindings();
        Route::post('{project}/tasks/grouped', [TaskController::class, 'grouped'])->name('tasks.grouped');

        // ATTACHMENTS
        Route::group(['prefix' => '{project}/tasks/{task}', 'as' => 'tasks.'], function () {
            Route::post('attachments/upload', [AttachmentController::class, 'store'])->name('attachments.upload');
            Route::delete('attachments/{attachment}', [AttachmentController::class, 'destroy'])->name('attachments.destroy');
        })->scopeBindings();

        // TIME LOGS
        Route::group(['prefix' => '{project}/tasks/{task}', 'as' => 'tasks.'], function () {
            Route::post('time-log', [TimeLogController::class, 'store'])->name('time-logs.store');
            Route::delete('time-log/{timeLog}', [TimeLogController::class, 'destroy'])->name('time-logs.destroy');
            Route::post('time-log/timer/start', [TimeLogController::class, 'startTimer'])->name('time-logs.timer.start');
            Route::post('time-log/{timeLog}/timer/stop', [TimeLogController::class, 'stopTimer'])->name('time-logs.timer.stop');
        })->scopeBindings();

        // COMMENTS
        Route::group(['prefix' => '{project}/tasks/{task}', 'as' => 'tasks.'], function () {
            Route::get('comment', [CommentController::class, 'index'])->name('comments');
            Route::post('comment', [CommentController::class, 'store'])->name('comments.store');
        })->scopeBindings();
    });

    // My Work
    Route::group(['prefix' => 'my-work', 'as' => 'my-work.'], function () {
        Route::get('tasks', [MyWorkTaskController::class, 'index'])->name('tasks.index');
        Route::get('activity', [ActivityController::class, 'index'])->name('activity.index');
    });

    // Attractions
    Route::group(['prefix' => 'attractions', 'as' => 'attractions.'], function () {
        // Games
        Route::resource('games', GameController::class)->except(['show']);
        Route::post('games/{gameId}/restore', [GameController::class, 'restore'])->name('games.restore');

        // Assets
        Route::resource('assets', AssetController::class)->except(['show']);
        Route::post('assets/{assetId}/restore', [AssetController::class, 'restore'])->name('assets.restore');

        // Checklists
        Route::resource('checklists', CheckListController::class)->except(['show']);
        Route::post('checklists/{checklistId}/restore', [CheckListController::class, 'restore'])->name('checklists.restore');
    });

    // Clients
    Route::group(['prefix' => 'clients', 'as' => 'clients.'], function () {
        Route::resource('users', ClientUserController::class)->except(['show']);
        Route::post('users/{userId}/restore', [ClientUserController::class, 'restore'])->name('users.restore');

        Route::resource('companies', ClientCompanyController::class)->except(['show']);
        Route::post('companies/{companyId}/restore', [ClientCompanyController::class, 'restore'])->name('companies.restore');
    });

    // Users
    Route::resource('users', UserController::class)->except(['show']);
    Route::post('users/{userId}/restore', [UserController::class, 'restore'])->name('users.restore');

    // Invoices
    Route::resource('invoices', InvoiceController::class)->except(['show']);
    Route::group(['prefix' => 'invoices', 'as' => 'invoices.'], function () {
        Route::get('tasks', [InvoiceTasksController::class, 'index'])->name('tasks');
        Route::put('{invoice}/status', [InvoiceController::class, 'setStatus'])->name('status');
        Route::post('{invoice}/restore', [InvoiceController::class, 'restore'])->name('restore');
        Route::get('{invoice}/download', [InvoiceController::class, 'download'])->name('download');
        Route::get('{invoice}/pdf', [InvoiceController::class, 'pdf'])->name('pdf');
    });

    // Reports
    Route::group(['prefix' => 'reports', 'as' => 'reports.'], function () {
        Route::get('logged-time/sum', [ReportController::class, 'loggedTimeSum'])->name('logged-time.sum');
        Route::get('logged-time/daily', [ReportController::class, 'dailyLoggedTime'])->name('logged-time.daily');
        Route::get('search-projects', [ReportController::class, 'searchProjects'])->name('search-projects');
    });

    // Settings
    Route::group(['prefix' => 'settings', 'as' => 'settings.'], function () {
        Route::get('company', [OwnerCompanyController::class, 'edit'])->name('company.edit');
        Route::put('company', [OwnerCompanyController::class, 'update'])->name('company.update');

        Route::resource('roles', RoleController::class)->except(['show']);
        Route::post('roles/{roleId}/restore', [RoleController::class, 'restore'])->name('roles.restore');

        Route::resource('labels', LabelController::class)->except(['show']);
        Route::post('labels/{labelId}/restore', [LabelController::class, 'restore'])->name('labels.restore');

        Route::resource('typesCheck', TypeCheckController::class)->except(['show']);
        Route::post('typeCheck/{typeCheckId}/restore', [TypeCheckController::class, 'restore'])->name('typesCheck.restore');
    });

    // Account
    Route::group(['prefix' => 'account', 'as' => 'account.'], function () {
        Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');
    });

    // Notifications
    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications');
    Route::put('notifications/{notification}/read', [NotificationController::class, 'read'])->name('notifications.read');
    Route::put('notifications/read/all', [NotificationController::class, 'readAll'])->name('notifications.read.all');

    Route::get('dropdown/values', DropdownValuesController::class)->name('dropdown.values');
});
