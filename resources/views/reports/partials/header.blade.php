<div id="header">
    <table style="width:100%; border-bottom:2px solid #1e3a5f; padding-bottom:8px; margin-bottom:12px;">
        <tr>
            <td style="width:70%; vertical-align:middle;">
                @if($logoBase64)
                    <img src="{{ $logoBase64 }}" style="max-height:40px; max-width:160px;" alt="Logo">
                @else
                    <span style="font-size:16px; font-weight:bold; color:#1e3a5f;">{{ $tenant?->name ?? 'Fronda CMMS' }}</span>
                @endif
            </td>
            <td style="text-align:right; vertical-align:middle; color:#64748b; font-size:9px;">
                Generado: {{ $generatedAt->format('d/m/Y H:i') }}<br>
                {{ $tenant?->name }}
            </td>
        </tr>
    </table>
</div>
