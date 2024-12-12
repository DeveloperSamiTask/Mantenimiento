<?php

namespace App\Http\Controllers\Attraction;

use App\Http\Controllers\Controller;
use App\Http\Requests\CheckList\StoreCheckListRequest;
use App\Http\Requests\CheckList\UpdateCheckListRequest;
use App\Http\Resources\CheckList\CheckListResource;
use App\Models\CheckList;
use App\Models\Game;
use App\Models\Period;
use App\Models\TypeCheck;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CheckListController extends Controller
{

    public function __construct() // Roles
    {
        $this->authorizeResource(CheckList::class, 'checklist');
    }

    public function index(Request $request): Response
    {
        return Inertia::render('Attractions/CheckLists/Index', [
            'items' => CheckListResource::collection(
                CheckList::searchByQueryString()
                    ->sortByQueryString()
                    ->when($request->has('archived'), fn ($query) => $query->onlyArchived())
                    ->when($request->games, fn ($query) => $query->whereIn('game_id', $request->games))
                    ->when($request->periods, fn ($query) => $query->whereIn('period_id', $request->periods))
                    ->paginate(12)
            ),
            'dropdowns' => [
                'games' => Game::dropdownValues(),
                'periods' => Period::dropdownValues(),
            ],
        ]);

    }

    public function create()
    {
        return Inertia::render('Attractions/CheckLists/Create', [
            'dropdowns' => [
                'games' => Game::dropdownValues(),
                'periods' => Period::dropdownValues(),
                'typeChecks' => TypeCheck::dropdownValues(),
            ],
        ]);
    }

    public function store(StoreCheckListRequest $request)
    {
        CheckList::create($request->validated());
        return redirect()->route('attractions.checklists.index')->success('Checklist creado', 'Se creó exitosamente una nuevo checklist.');
    }

    public function edit(CheckList $checklist)
    {
        return Inertia::render('Attractions/CheckLists/Edit', [
            'item' => new ChecklistResource($checklist),
            'dropdowns' => [
                'games' => Game::dropdownValues(),
                'periods' => Period::dropdownValues(),
                'typeChecks' => TypeCheck::dropdownValues(),
            ],
        ]);
    }

    public function update(UpdateCheckListRequest $request, CheckList $checklist)
    {
        $checklist->update($request->validated());
        return redirect()->route('attractions.checklists.index')->success('Checklist actualizado', 'El checklist se actualizó correctamente.');
    }

    public function destroy(CheckList $checklist)
    {
        $checklist->archive();

        return redirect()->back()->success('Checklist archivado', 'El checklist se archivó correctamente.');
    }

    public function restore(int $checklistId)
    {
        $checklist = CheckList::withArchived()->findOrFail($checklistId);
        $checklist->unArchive();

        return redirect()->back()->success('Checklist restaurado', 'La restauración del checklist se completó con éxito.');
    }

    public function editImage(int $checklistId, int $option)
    {
        $checklist = CheckList::withArchived()->findOrFail($checklistId);
        $checklist->update(['archive' => $option]);

        return redirect()->back()->success('Checklist editado', 'El checklist se editó con éxito.');
    }

}
