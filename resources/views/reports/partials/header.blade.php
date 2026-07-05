<div id="header">
    <table style="width:100%; border-bottom:2px solid #059669; padding-bottom:6px;">
        <tr>
            <td style="width:52%; vertical-align:middle;">
                @if($logoBase64)
                    <img src="{{ $logoBase64 }}" style="max-height:36px; max-width:150px;" alt="Logo">
                @else
                    <span style="font-size:15px; font-weight:bold; color:#047857;">{{ $tenant?->name ?? 'Fronda CMMS' }}</span>
                @endif
            </td>
            <td style="width:33%; text-align:right; vertical-align:middle; color:#64748b; font-size:8px; line-height:1.5;">
                @if($documentNumber ?? null)
                    <strong style="color:#1e293b; font-size:9px;">N.º {{ $documentNumber }}</strong><br>
                @endif
                Versión {{ $documentVersion ?? '1.0' }}<br>
                Emitido: {{ $generatedAt->format('d/m/Y H:i') }}
            </td>
            <td style="width:15%; text-align:right; vertical-align:middle;">
                @if($qrBase64 ?? null)
                    <img src="{{ $qrBase64 }}" style="width:36px; height:36px;" alt="Código de verificación del documento">
                @endif
            </td>
        </tr>
    </table>
    @if($tenant && ($tenant->tax_id || $tenant->address || $tenant->contact_phone || $tenant->contact_email))
    <table style="width:100%; margin-top:3px;">
        <tr>
            <td style="font-size:7px; color:#94a3b8;">
                {{ $tenant->name }}@if($tenant->tax_id) &middot; NIT {{ $tenant->tax_id }}@endif@if($tenant->address) &middot; {{ $tenant->address }}@endif@if($tenant->contact_phone) &middot; Tel. {{ $tenant->contact_phone }}@endif@if($tenant->contact_email) &middot; {{ $tenant->contact_email }}@endif
            </td>
        </tr>
    </table>
    @endif
</div>
