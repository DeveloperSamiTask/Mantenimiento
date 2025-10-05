<table>
    <thead>
        <tr>
            <th></th>
        </tr>
        <tr>
            <th>ATRACCION</th>
            <th>FECHA</th>
            <th>DESCRIPCION</th>
            <th>TIPO</th>
            <th>ESTADO</th>
        </tr>
    </thead>
    <tbody>
        @foreach($projects as $project)
        <tr>
            <td>{{ $project->game->name ?? '' }}</td>
            <td>{{ $project->due_on ? \Carbon\Carbon::parse($project->due_on)->format('d/m/Y') : '' }}</td>
            <td>{{ $project->name ?? '' }}</td>
            <td>{{ $project->period->name ?? '' }}</td>
            <td>{{ $project->projectGroup->name ?? '' }}</td>
        </tr>
        @endforeach
    </tbody>
</table>
