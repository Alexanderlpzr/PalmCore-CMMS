<?php

namespace App\Jobs;

use App\Domain\Reports\Enums\ExcelReportType;
use App\Domain\Reports\Excel\WorkOrderExcelExport;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\URL;

class GenerateWorkOrdersExcelJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 300;

    public int $tries = 1;

    public function __construct(
        public readonly string $tenantId,
        public readonly string $requestedBy,
    ) {
        $this->onQueue('exports');
    }

    public function handle(): void
    {
        $path = $this->tenantId.'/'.ExcelReportType::WorkOrders->filename();
        $export = new WorkOrderExcelExport($this->tenantId);
        $export->store($path);

        $downloadUrl = URL::temporarySignedRoute(
            'reports.download',
            now()->addHours(24),
            ['path' => $path]
        );

        $user = User::find($this->requestedBy);

        if ($user) {
            Notification::make()
                ->title('Reporte '.ExcelReportType::WorkOrders->label().' (Excel) listo')
                ->body('El reporte está disponible para descarga por 24 horas.')
                ->success()
                ->actions([
                    Action::make('download')
                        ->label('Descargar Excel')
                        ->url($downloadUrl)
                        ->openUrlInNewTab(),
                ])
                ->sendToDatabase($user);
        }
    }
}
