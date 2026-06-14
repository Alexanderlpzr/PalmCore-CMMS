<?php

namespace App\Jobs;

use App\Domain\Reports\DTOs\ReportRequest;
use App\Domain\Reports\Services\InventoryPdfService;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class GenerateInventoryReportJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 300;

    public int $tries = 1;

    public function __construct(public readonly ReportRequest $request)
    {
        $this->onQueue('exports');
    }

    public function handle(InventoryPdfService $service): void
    {
        $bytes = $service->generate($this->request->tenantId);
        $path = $this->request->tenantId.'/INV-'.now()->format('Ymd-His').'-'.Str::random(8).'.pdf';

        Storage::disk('reports')->put($path, $bytes);

        $downloadUrl = URL::temporarySignedRoute(
            'reports.download',
            now()->addHours(24),
            ['path' => $path]
        );

        $user = User::find($this->request->requestedBy);

        if ($user) {
            Notification::make()
                ->title('Reporte de Inventario listo')
                ->body('El reporte ha sido generado y está disponible para descarga por 24 horas.')
                ->success()
                ->actions([
                    Action::make('download')
                        ->label('Descargar PDF')
                        ->url($downloadUrl)
                        ->openUrlInNewTab(),
                ])
                ->sendToDatabase($user);
        }
    }
}
