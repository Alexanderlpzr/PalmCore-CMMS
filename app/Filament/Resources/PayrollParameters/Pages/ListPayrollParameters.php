<?php

namespace App\Filament\Resources\PayrollParameters\Pages;

use App\Domain\HumanResources\Enums\PayrollParameter;
use App\Domain\HumanResources\Services\PayrollParameterService;
use App\Filament\Resources\PayrollParameters\PayrollParameterResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Carbon;

class ListPayrollParameters extends ListRecords
{
    protected static string $resource = PayrollParameterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Nueva vigencia'),

            /*
             * La primera carga. Sin esto, el módulo arranca sin un solo parámetro y la
             * primera liquidación falla con «no hay valor vigente» diecinueve veces
             * seguidas — que es correcto pero inservible.
             */
            Action::make('cargarValoresIniciales')
                ->label('Cargar valores iniciales')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->visible(fn (): bool => $this->missingParameterCount() > 0)
                ->requiresConfirmation()
                ->modalHeading('Cargar los valores del libro actual')
                ->modalDescription(
                    'Carga los parámetros que hoy aplica la nómina en Excel, vigentes desde el 1 de enero. '
                    .'No toca ninguno que ya exista. Revíselos después: el divisor de jornada y el recargo '
                    .'dominical son los dos que hay que confirmar con contabilidad.'
                )
                ->modalSubmitActionLabel('Cargar')
                ->action(function (): void {
                    $created = app(PayrollParameterService::class)->seedDefaults(
                        Filament::getTenant()->id,
                        Carbon::parse(now()->year.'-01-01'),
                        auth()->id(),
                    );

                    Notification::make()
                        ->title("Se cargaron {$created} parámetros")
                        ->body('Revise el divisor de horas mensuales y el recargo dominical antes de liquidar.')
                        ->success()
                        ->send();
                }),
        ];
    }

    private function missingParameterCount(): int
    {
        return count(app(PayrollParameterService::class)->missingOn(
            now(),
            Filament::getTenant()->id,
        ));
    }

    public function getSubheading(): ?string
    {
        $missing = $this->missingParameterCount();

        if ($missing === 0) {
            return null;
        }

        return "Faltan {$missing} de ".count(PayrollParameter::cases())
            .' parámetros por cargar. La liquidación no puede correr hasta que estén todos.';
    }
}
