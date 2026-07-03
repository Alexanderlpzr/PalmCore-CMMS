<div id="footer">
    <table style="width:100%; border-top:1px solid #e2e8f0; padding-top:4px;">
        <tr>
            <td style="font-size:7px; color:#94a3b8;">
                {{ $tenant?->name ?? 'Fronda CMMS' }} &middot; Generado con Fronda CMMS
            </td>
            <td style="font-size:7px; color:#94a3b8; text-align:right;">
                @if($documentNumber ?? null)
                    Documento N.º {{ $documentNumber }} &middot;
                @endif
                Página <span class="page"></span>
            </td>
        </tr>
    </table>
</div>
