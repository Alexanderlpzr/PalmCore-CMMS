<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
    @page { margin: 10mm; }
    body { font-family: 'DejaVu Sans', sans-serif; color: #00384C; margin: 0; }
    table.labels { width: 100%; border-collapse: separate; border-spacing: 4mm; }
    td.label { width: 33%; height: 42mm; border: 1px solid #1A7E42; border-radius: 3mm; padding: 2mm; vertical-align: top; }
    .qr { float: left; width: 26mm; height: 26mm; margin-right: 2mm; }
    .code { font-size: 13pt; font-weight: bold; color: #1A7E42; }
    .name { font-size: 8pt; margin-top: 1mm; }
    .meta { font-size: 7pt; color: #4b5563; margin-top: 2mm; clear: both; }
    .company { font-size: 6pt; color: #9ca3af; }
</style>
</head>
<body>
<table class="labels">
    @foreach ($labels->chunk(3) as $row)
        <tr>
            @foreach ($row as $label)
                <td class="label">
                    <img class="qr" src="data:image/png;base64,{{ $label['qr'] }}" alt="">
                    <div class="code">{{ $label['code'] }}</div>
                    <div class="name">{{ \Illuminate\Support\Str::limit($label['name'], 70) }}</div>
                    <div class="meta">
                        @if ($label['location']) Ubicación: <strong>{{ $label['location'] }}</strong><br>@endif
                        Unidad: {{ $label['unit'] }}
                        <div class="company">{{ $tenant?->name }} · fronda.app</div>
                    </div>
                </td>
            @endforeach
            @for ($i = $row->count(); $i < 3; $i++)
                <td></td>
            @endfor
        </tr>
    @endforeach
</table>
</body>
</html>
