<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
</head>

<body>
    <!--HEADER-->
    <table class="div-1Header">
        <tr>
            <td class="datos-grales-td">
                <table class="table_h_factura">
                    <thead>
                        <th class="headerDatosh titulos">{{  "#$project->id" }} {{ $project->fault_date ? 'Hoja de falla' : 'Orden de trabajo' }}</th>
                    </thead>
                    <tr>
                        <td class="titulos"><p class="titulos">{{ $ownerCompany->name }}</p></td>
                    </tr>
                    <tr>
                        <td><p>RUC: <span>20123724004</span></p></td>
                    </tr>
                    <tr>
                        <td><p>TELEFONO: <span>996319026</span> </p></td>
                    </tr>
                    <tr>
                        <td><p>CORREO: <span>atencionalcliente@lagranjavilla.com</span> </p></td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
    <table class="div-1Datos">
        <tr>
            <td class="receptor">
                <table class="table_receptor">
                    <tr>
                        <td class="titulos"><p class="titulos tituloRec">Datos generales</p></td>
                    </tr>
                    <tr>
                        <td><p>Codigo: <span>#{{ $project->id }}</span></p></td>
                    </tr>
                    <tr>
                        <td><p>Generó: <span>{{ $project->userGenerate->name }}</span></p></td>
                    </tr>
                    <tr>
                        @if ($project->fault_date)
                            <td><p>Tiempo fuera de servicio: <span>{{ $project->estimation }} /hr</span></p></td>
                        @else
                            <td><p>Duración estimada: <span>{{ $project->estimation * 10 }} /min</span></p></td>
                        @endif
                    </tr>
                    <tr>
                        <td>
                            <p>Responsables:
                            @foreach ($project->users as $user)
                            <li>
                                <span>{{ $user->name }}</span>
                            </li>
                            @endforeach
                        </td>
                    </tr>
                    <tr>
                        <td><p>{{$project->fault_date ? 'Hoja de falla' : 'Orden de trabajo'}}: <span>{{ $project->name }}</span></p></td>
                    </tr>
                    <tr>
                        <td><p>Atracción: <span>{{ $project->game ? $project->game->name : '' }}</span></p></td>
                    </tr>
                    <tr>
                        <td><p>Ubicación: <span>{{ $project->game ? $asset[0]['name'] : '' }}</span></p></td>
                    </tr>
                    <tr>
                        <td><p>Tipo: <span>{{ $project->type ? $project->type->name : '' }}</span></p></td>
                    </tr>
                    <tr>
                        <td><p>Descripción: <span>{{ $project->description }}</span></p></td>
                    </tr>
                    @if ($project->archived_at != null)
                        <tr>
                            <td><p>Motivo de cancelación: <span>{{ $project->motive_archived }}</span></p></td>
                        </tr>
                    @endif
                </table>
            </td>
            <td class="datosGral">
                <table class="table_datos">
                    @if ($project->fault_date)
                        <tr>
                            <td><p>FECHA DE FALLA:</p></td>
                            <td><p>{{ $project->fault_date }}</p></td>
                        </tr>
                        <tr>
                            <td><p>FECHA DE INICIO:</p></td>
                            <td><p>{{ $project->fault_date ? $project->start_date : $project->created_at }}</p></td>
                        </tr>
                    @endif
                    <tr>
                        <td><p>FECHA DE CREACIÓN:</p></td>
                        <td><p>{{ $project->created_at }}</p></td>
                    </tr>
                    <tr>
                        <td><p>FECHA DE TERMINO:</p></td>
                        <td><p>{{ $project->completed_at }}</p></td>
                    </tr>
                    <tr>
                        <td><p>TIEMPO DE EJECUCIÓN:</p></td>
                        <td><p>{{ count($project->timeLogs) > 0 ? $project->timeLogs[0]->minutes . '/min' . $project->timeLogs[0]->timer_stop % 60 . '/seg' : 'No registrado' }}</p></td>
                    </tr>
                    <tr>
                        <td><p>FECHA DE VENCIMIENTO:</p></td>
                        <td><p>{{ $project->due_on }}</p></td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
    <table class="table_materiales">
        <thead>
            <tr>
                <td>Tarea</td>
                <td>Resultado</td>
                <td>Detalle</td>
            </tr>
        </thead>
        <tbody>
            @foreach($tasks as $task)
                <tr>
                    <td>{{ $task->name }}</td>
                    <td>{{ $task->check }}</td>
                    <td>
                        @foreach ($task->attachments as $attachment)
                            @if(count($task->attachments) > 0)
                                @php
                                    $imageData = base64_encode(file_get_contents(public_path($attachment->path)));
                                @endphp
                                <div>
                                    <img  src="data:image/png;base64, {{ $imageData }}" width="35%">
                                </div>
                            @endif
                        @endforeach
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <table class="table_firmas">
        @php
            $aceptado = $project->userReview ? base64_encode(file_get_contents(public_path($project->userReview->signature))) : null;
            $validado = $project->userFinalize ? base64_encode(file_get_contents(public_path($project->userFinalize->signature))) : null;
            $realizado = $timeLogs ? ($timeLogs->user->signature ? base64_encode(file_get_contents(public_path($timeLogs->user->signature))) : null) : null;
        @endphp

        <thead>
            <tr>
                <td>
                    @if(intval($project->group_id) >= 3 && $aceptado)
                        <img  src="data:image;base64, {{ $aceptado }}" height="100" >
                        <br>
                        {{$project->userReview->name}}
                    @endif
                </td>
                <td>
                    @if($project->group_id == 4 && $validado)
                        <img  src="data:image;base64, {{ $validado }}" height="100" >
                        <br>
                        {{$project->userFinalize->name}}
                    @endif
                </td>
                <td style="text-align: center;">
                    @foreach ($project->users as $user)
                        <div style="display: inline-block; margin: 0 10px;">
                            @if ($user->signature)
                                <img src="data:image;base64, {{ base64_encode(file_get_contents(public_path($user->signature))) }}" height="100">
                                <br>
                                <span style="display: block; margin-top: 5px;">{{$user->name}}</span>
                            @endif
                        </div>
                    @endforeach
                </td>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Aceptado por</td>
                <td>Validado por</td>
                <td>Realizado por</td>
            </tr>
        </tbody>
    </table>
    <footer>
        <p>Todos los derechos reservados: https://lagranjavilla.com | Sistema Mantenimiento </p>
    </footer>
</body>

</html>
<style>
    * {
        font-family: Arial, Helvetica, sans-serif;
        font-size: 12px;
    }

    .titulos {
        font-size: 15px;
        text-transform: uppercase;
    }

    /*HEADER*/
    .div-1Header,
    .div-1Datos {
        width: 100%;
    }

    .logotd {
        width: 50%;
        height: auto;
    }

    .datos-grales-td,
    .receptor {
        width: 50%;
    }

    .table_h_factura {
        width: 50%;
        height: 150px;
        background-color: #FFF;
        width: 100%;
        margin: 0px;
        padding: 0px;
    }

    .headerDatosh {
        text-align: right;
        color: #FFF;
        padding: 5px;
        background-color: rgb(24, 140, 207);
    }

    .table_h_factura tr td p {
        margin: 0px;
        padding: 2px;
        text-align: right;
        padding-right: 5px;
    }

    /*DATOS*/
    .table_receptor,
    .table_datos {
        width: 42%;
        height: 100px;
        background-color: rgba(243, 243, 243, 0.521);
        width: 100%;
        margin: 0px;
        padding: 10px;
        border-radius: 5px;
    }

    .table_receptor tr td p {
        margin: 0px;
        padding: 2px;
        text-align: left;
    }

    .tituloRec {
        color: rgb(24, 140, 207);
    }

    .table_datos tr td p {
        margin: 0px;
        padding: 2px;
        text-align: left;
    }

    /*MATERIALES*/
    .table_materiales {
        width: 100%;
        margin-top: 10px;
        margin-bottom: 10px;
    }

    .table_materiales thead tr {
        background-color: rgb(24, 140, 207);
        color: #FFF;
    }

    .table_materiales thead tr td {
        padding: 5px;
        text-align: left;
        font-size: 14px;
    }

    .table_materiales tr td {
        text-align: left;
        padding: 5px;
        border-bottom: 1px solid rgba(20, 20, 20, 0.096);
    }

    .table_materiales tbody img {
        margin: 3px;
        border-radius: 5px; /* Bordes redondeados para un mejor aspecto */
    }

    /*FIRMA*/
    .table_firmas {
        width: 100%;
        margin-top: 100px;
        margin-bottom: 10px;
    }

    .table_firmas thead tr td {
        padding: 50px;
        text-align: center;
    }

    .table_firmas tbody tr td {
        border-top: 1px solid rgba(20, 20, 20, 0.5);
        text-align: center;
        padding: 5px;
        font-size: 14px;
    }

    .table_firmas .signature-container {
        display: inline-block;
        margin: 0 10px;
        text-align: center;
    }

    .table_firmas .signature-name {
        display: block;
        margin-top: 5px;
        font-size: 12px;
    }

    /*FOOTER*/
    footer {
        width: 100%;
        text-align: center;
        position: absolute;
        bottom: 0px;
    }
</style>
