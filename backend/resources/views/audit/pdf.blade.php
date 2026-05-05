<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Audit export</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ccc; padding: 4px; text-align: left; vertical-align: top; }
        th { background: #f0f0f0; }
    </style>
</head>
<body>
<p>Generated {{ now()->toDateTimeString() }} — {{ $events->count() }} row(s)</p>
<table>
    <thead>
    <tr>
        <th>ID</th>
        <th>Time</th>
        <th>Actor</th>
        <th>Module</th>
        <th>Action</th>
        <th>Target</th>
        <th>OK</th>
    </tr>
    </thead>
    <tbody>
    @foreach ($events as $e)
        <tr>
            <td>{{ $e->id }}</td>
            <td>{{ $e->created_at }}</td>
            <td>{{ $e->actor?->email }}</td>
            <td>{{ $e->module }}</td>
            <td>{{ $e->action }}</td>
            <td>{{ $e->target_account_id }}</td>
            <td>{{ $e->success ? 'yes' : 'no' }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
</body>
</html>
