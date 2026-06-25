<?php

namespace App\Http\Controllers;

use App\Models\Insumo;
use App\Models\OTInsumo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InsumosController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ot_id' => 'required|integer|exists:projects,id',
            'due_on' => 'required|date',
            'game_id' => 'nullable|exists:games,id',      // <- nuevo
            'period_id' => 'nullable|exists:periods,id',    // <- nuevo
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
            'name' => 'OT-'.$data['ot_id'].'-insumos',
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
        $search  = $request->input('search', '');
        $perPage = 20;

        $query = DB::table('maeart as m')
            ->join('stkart as s', 'm.ACODIGO', '=', 's.STCODIGO')
            ->join('tabalm as a', 's.STALMA', '=', 'a.TAALMA')
            ->select(
                'm.ACODIGO   as id',
                'm.ADESCRI   as nombre',
                'a.TADESCRI  as almacen',
                's.STALMA    as codigo_almacen',
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
}
