<?php

namespace App\Filament\Resources\Commercial\SalesDispatch\Pages;

use App\Domain\Commercial\Enums\DispatchStatus;
use App\Domain\Commercial\Services\DispatchWorkbookImporter;
use App\Filament\Resources\Commercial\SalesDispatch\SalesDispatchResource;
use App\Filament\Widgets\Lists\SalesDispatchKpis;
use App\Models\SalesDispatch;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;

class ListSalesDispatches extends ListRecords
{
    protected static string $resource = SalesDispatchResource::class;

    protected function getHeaderWidgets(): array
    {
        return [SalesDispatchKpis::class];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('importar')
                ->label('Importar Excel')
                ->icon(Heroicon::OutlinedArrowUpTray)
                ->color('gray')
                ->authorize(fn (): bool => SalesDispatchResource::canCreate())
                ->modalHeading('Importar el Excel de despachos')
                ->modalDescription('El libro «Proyección vs Despacho»: sus hojas «Despacho 2026», «Ofertas Mercantiles» y la venta de palmiste de «Almendra y PKO». Crea los clientes, las transportadoras y las ofertas que falten. Un cargue ya importado no se repite.')
                ->schema([
                    FileUpload::make('file')
                        ->label('Archivo de Excel (.xlsx)')
                        ->acceptedFileTypes(['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])
                        ->disk('local')
                        ->directory('commercial-imports')
                        ->maxSize(51200)
                        ->required(),
                ])
                ->action(function (array $data): void {
                    /** @var User $user */
                    $user = auth()->user();
                    $path = Storage::disk('local')->path($data['file']);

                    try {
                        $summary = app(DispatchWorkbookImporter::class)->import($path, (string) filament()->getTenant()->getKey(), $user);
                    } catch (\Throwable $e) {
                        report($e);
                        Notification::make()->title('No se pudo leer el Excel')->body($e->getMessage())->danger()->send();

                        return;
                    } finally {
                        Storage::disk('local')->delete($data['file']);
                    }

                    Notification::make()
                        ->title("Importados {$summary['dispatches']} despachos")
                        ->body("{$summary['offers']} ofertas, {$summary['customers']} clientes y {$summary['carriers']} transportadoras nuevas"
                            .($summary['skipped'] > 0 ? "; {$summary['skipped']} cargues ya estaban" : '').'.'
                            .($summary['warnings'] !== [] ? ' Revise: '.implode(' ', $summary['warnings']) : ''))
                        ->success()
                        ->persistent()
                        ->send();
                }),
            CreateAction::make()->label('Registrar cargue'),
        ];
    }

    /** @return array<string, Tab> */
    public function getTabs(): array
    {
        $tabs = ['todos' => Tab::make('Todos')];

        foreach (DispatchStatus::cases() as $status) {
            $tabs[$status->value] = Tab::make($status->label())
                ->icon($status->icon())
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', $status->value))
                ->badge(fn (): ?int => $status === DispatchStatus::Pagado ? null : (SalesDispatch::query()->where('status', $status->value)->count() ?: null))
                ->badgeColor($status->color());
        }

        return $tabs;
    }
}
