<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
</head>
<body>

  {{-- HEADER --}}
  <div class="header-orden">
    <div class="headerDatosh titulos">
      #{{ $otInsumo->ot_id }} Insumos - Orden de Trabajo
    </div>
  </div>

  {{-- EMPRESA --}}
  <div class="header-empresa">
    <p class="titulos">{{ $ownerCompany->name }}</p>
    <p>RUC: <span>20123724004</span></p>
    <p>TELEFONO: <span>996319026</span></p>
    <p>CORREO: <span>atencionalcliente@lagranjavilla.com</span></p>
  </div>

  {{-- DATOS GENERALES --}}
  <div class="caja-datos">
    <p class="titulos tituloRec">Datos generales</p>
    <p>Código OT: <span>#{{ $otInsumo->ot_id }}</span></p>
    <p>Generó: <span>{{ $otInsumo->user->name ?? '—' }}</span></p>
    <p>OT asociada: <span>{{ $project->name ?? '—' }}</span></p>
    <p>Atracción: <span>{{ $project->game->name ?? '—' }}</span></p>
  </div>

  {{-- FECHAS --}}
  <div class="caja-fechas">
    <p>FECHA DE CREACIÓN: {{ $otInsumo->created_at->format('d/m/Y') }}</p>
    <p>FECHA DE TÉRMINO: {{ $project->completed_at ? \Carbon\Carbon::parse($project->completed_at)->format('d/m/Y') : '—' }}</p>
    <p>FECHA DE VENCIMIENTO: {{ \Carbon\Carbon::parse($otInsumo->due_on)->format('d/m/Y') }}</p>
  </div>

  {{-- TABLA DE INSUMOS --}}
  <table class="table_materiales">
    <thead>
      <tr>
        <td>Nombre</td>
        <td>Cód. Producto</td>
        <td>Almacén</td>
        <td>Unidad</td>
        <td>Cantidad</td>
      </tr>
    </thead>
    <tbody>
      @foreach ($otInsumo->insumos as $insumo)
        <tr>
          <td>{{ $insumo->name }}</td>
          <td>{{ $insumo->cod_producto }}</td>
          <td>{{ $insumo->almacen }}</td>
          <td>{{ $insumo->unidad }}</td>
          <td>{{ $insumo->cantidad }}</td>
        </tr>
      @endforeach
    </tbody>
  </table>

  <footer>
    <p>Todos los derechos reservados: https://lagranjavilla.com | Sistema Mantenimiento</p>
  </footer>

</body>
</html>

<style>
  * { font-family: Arial, Helvetica, sans-serif; font-size: 12px; }
  .titulos { font-size: 15px; text-transform: uppercase; }

  .header-orden { width: 100%; margin-bottom: 5px; }
  .header-orden .headerDatosh {
    text-align: right; color: #FFF; padding: 8px 10px;
    background-color: rgb(24, 140, 207); width: 100%;
  }

  .header-empresa {
    width: 45%; text-align: right; margin-left: auto;
    margin-bottom: 10px; padding: 5px;
    background-color: rgba(243,243,243,0.521);
  }

  .caja-datos {
    width: 45%; background-color: rgba(243,243,243,0.521);
    padding: 10px; border-radius: 5px;
    margin-right: 5%; margin-top: -150px;
    word-wrap: break-word;
  }
  .caja-datos span { display: block; word-wrap: break-word; }

  .caja-fechas {
    width: 45%; position: absolute;
    background-color: rgba(243,243,243,0.521);
    padding: 10px; border-radius: 5px;
    right: 0; top: 190px;
  }

  .tituloRec { color: rgb(24, 140, 207); }

  .table_materiales { width: 100%; margin-top: 50px; table-layout: fixed; }
  .table_materiales thead tr { background-color: rgb(24, 140, 207); color: #FFF; }
  .table_materiales thead tr td { padding: 5px; font-size: 14px; }
  .table_materiales tr td {
    text-align: left; padding: 5px;
    border-bottom: 1px solid rgba(20,20,20,0.096);
    vertical-align: middle;
  }

  footer { width: 100%; text-align: center; position: absolute; bottom: 0; }
</style>
