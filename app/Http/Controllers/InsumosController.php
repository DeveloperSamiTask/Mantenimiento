<?php

namespace App\Http\Controllers;

use App\Models\Insumo;
use App\Models\OTInsumo;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class InsumosController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        //  dd($request->all());

        $data = $request->validate([
            'ot_id' => 'required|integer|exists:projects,id',
            'due_on' => 'required|date',
            'game_id' => 'nullable|exists:games,id',      // <- nuevo
            'period_id' => 'nullable|exists:periods,id',    // <- nuevo
            'nameOT' => 'nullable|string',
            'insumos' => 'required|array|min:1',
            'insumos.*.cod_producto' => 'required|string',
            'insumos.*.name' => 'required|string',
            'insumos.*.almacen' => 'required|string',
            'insumos.*.unidad' => 'nullable|string',
            'insumos.*.cantidad' => 'required|numeric|min:1',
        ]);

        // Crea la cabecera OTInsumo
        $otInsumo = OTInsumo::create([
            'ot_id' => $data['ot_id'],
            'due_on' => $data['due_on'],
            'game_id' => $data['game_id'] ?? null,    // <- nuevo
            'period_id' => $data['period_id'] ?? null,
            'user_id' => auth()->id(),
            'name' => ' Insumos - '.$data['nameOT'],
        ]);

        // Crea cada línea de insumo
        $lineas = collect($data['insumos'])->map(fn ($i) => [
            'ot_insumos_id' => $otInsumo->id,
            'cod_producto' => $i['cod_producto'],
            'name' => $i['name'],
            'almacen' => $i['almacen'],
            'unidad' => $i['unidad'] ?? '',
            'cantidad' => $i['cantidad'],
            'created_at' => now(),
            'updated_at' => now(),
        ])->toArray();

        Insumo::insert($lineas); // insert masivo, más eficiente que crear uno a uno

        return response()->json([
            'ot_insumo' => $otInsumo->load('insumos'),
        ], 201);
    }

    public function index(Request $request)
    {
        $search = $request->input('search', '');
        $perPage = 20;

        $query = DB::table('maeart as m')
            ->join('stkart as s', 'm.ACODIGO', '=', 's.STCODIGO')
            ->join('tabalm as a', 's.STALMA', '=', 'a.TAALMA')
            ->select(
                'm.ACODIGO   as id',
                'm.ADESCRI   as nombre',
                'a.TADESCRI  as almacen',
                's.STALMA    as codigo_almacen',
                'm.AUNIDAD as unidad ',
                's.STSKDIS   as stock'
            )
            ->where('m.AUSER', 'MANTO')
            ->where('s.STSKDIS', '>', 0)
            ->orderBy('s.STALMA')
            ->orderBy('m.ADESCRI');

        // Busqueda por nombre
        if ($search) {
            $query->where('m.ADESCRI', 'like', "%{$search}%");
        }

        $resultado = $query->paginate($perPage);

        return response()->json($resultado);
    }

    public function search(Request $request)
    {
        $start = $request->input('dateRange.0')
            ? \Carbon\Carbon::parse($request->input('dateRange.0'))->format('Y-m-d')
            : null;
        $end = $request->input('dateRange.1')
            ? \Carbon\Carbon::parse($request->input('dateRange.1'))->format('Y-m-d')
            : null;

        $query = OTInsumo::with(['insumos', 'user'])
            ->select('ot_insumos.*')  // <- evita columnas ambiguas
            ->distinct()
            ->when($request->ot_id, fn ($q) => $q->where('ot_id', $request->ot_id))
            ->when($request->game_id, fn ($q) => $q->whereIn('game_id', (array) $request->game_id))
            ->when($request->period_id, fn ($q) => $q->whereIn('period_id', (array) $request->period_id))
            ->when($start && $end, fn ($q) => $q->whereBetween('due_on', [$start, $end]))
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return Inertia::render('Reports/SearchInsumos', [
            'items' => $query,
            'games' => \App\Models\Game::selectRaw('CAST(id AS CHAR) as value, name as label')->get(),
            'periods' => \App\Models\Period::selectRaw('CAST(id AS CHAR) as value, name as label')->get(),
        ]);
    }

    public function pdf(OTInsumo $otInsumo)
    {
        $data = [
            'ownerCompany' => \App\Models\OwnerCompany::first(),
            'otInsumo' => $otInsumo->load(['insumos', 'user']),
            'project' => \App\Models\Project::find($otInsumo->ot_id),
        ];

        $pdf = Pdf::loadView('vendor.insumos.pdf', $data);

        return $pdf->stream('insumos-ot-'.$otInsumo->ot_id.'.pdf');
    }
}
