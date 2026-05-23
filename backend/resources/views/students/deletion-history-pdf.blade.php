<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Student deletion history export</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ccc; padding: 5px; text-align: left; vertical-align: top; }
        th { background: #f0f0f0; }
    </style>
</head>
<body>
<h1>Student deletion history</h1>
<p>Generated {{ now()->toDateTimeString() }} - {{ $events->count() }} deleted email(s)</p>
<table>
    <thead>
    <tr>
        <th>Email</th>
        <th>Deleted at</th>
    </tr>
    </thead>
    <tbody>
    @forelse ($events as $event)
        <tr>
            <td>{{ $event['email'] }}</td>
            <td>{{ $event['deleted_at'] }}</td>
        </tr>
    @empty
        <tr>
            <td colspan="2">No deleted emails match these filters.</td>
        </tr>
    @endforelse
    </tbody>
</table>
</body>
</html>
