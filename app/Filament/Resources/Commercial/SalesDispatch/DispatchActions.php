<?php

namespace App\Filament\Resources\Commercial\SalesDispatch;

use App\Domain\Commercial\Enums\DispatchStatus;
use App\Models\SalesDispatch;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;

/**
 * Los tres pasos de un cargue después de salir de la planta, cada uno con solo sus datos:
 * el descargue (lo que el cliente recibió y liquidó), la factura y el pago. Se usan en la
 * lista y en la ficha del despacho.
 */
final class DispatchActions
{
    public static function unload(): Action
    {
        return Action::make('registrarDescargue')
            ->label('Registrar descargue')
            ->icon(Heroicon::OutlinedInboxArrowDown)
            ->color('info')
            ->visible(fn (SalesDispatch $record): bool => $record->status === DispatchStatus::Cargado && SalesDispatchResource::canEdit($record))
            ->modalHeading(fn (SalesDispatch $record): string => "Descargue de {$record->label()}")
            ->modalDescription(fn (SalesDispatch $record): string => 'Salió con '.number_format((float) $record->departure_weight_tons, 2, ',', '.').' tn. Lo que el cliente recibió es lo que cuenta contra la oferta.')
            ->schema([
                DatePicker::make('unloaded_on')->label('Fecha de descargue')
                    ->default(fn (): string => SalesDispatch::today())
                    ->required(),
                TextInput::make('receipt_number')->label('Recibo')->maxLength(30),
                TextInput::make('arrival_weight_tons')->label('Peso de llegada')
                    ->numeric()->minValue(0)->suffix('tn')
                    ->required(),
                TextInput::make('arrival_acidity')->label('Acidez de llegada')
                    ->numeric()->minValue(0)->maxValue(100)->suffix('%'),
                TextInput::make('settlement_amount')->label('Total de la liquidación')
                    ->numeric()->prefix('$')
                    ->helperText('Lo que el cliente liquida por el cargue, antes de bonificaciones.'),
                TextInput::make('quality_bonus')->label('Bonificación por calidad')
                    ->numeric()->prefix('$')
                    ->helperText('Negativa si fue un descuento.'),
            ])
            ->action(function (SalesDispatch $record, array $data): void {
                $record->update($data);

                Notification::make()->title('Descargue registrado: queda por facturar')->success()->send();
            });
    }

    public static function invoice(): Action
    {
        return Action::make('registrarFactura')
            ->label('Registrar factura')
            ->icon(Heroicon::OutlinedDocumentText)
            ->color('primary')
            ->visible(fn (SalesDispatch $record): bool => $record->status === DispatchStatus::Descargado && SalesDispatchResource::canEdit($record))
            ->modalHeading(fn (SalesDispatch $record): string => "Factura de {$record->label()}")
            ->fillForm(fn (SalesDispatch $record): array => [
                'invoiced_on' => SalesDispatch::today(),
                'invoice_amount' => $record->expectedInvoiceAmount(),
            ])
            ->schema([
                TextInput::make('invoice_number')->label('N.º de factura')->maxLength(30)->required(),
                DatePicker::make('invoiced_on')->label('Fecha de factura')->required(),
                TextInput::make('billed_freight')->label('Flete cobrado al cliente')
                    ->numeric()->prefix('$')
                    ->helperText('Solo si la factura incluye el flete.'),
                TextInput::make('invoice_amount')->label('Valor de la factura')
                    ->numeric()->prefix('$')->minValue(0)
                    ->helperText(fn (SalesDispatch $record, Get $get): ?string => self::invoiceHint($record, $get))
                    ->required(),
            ])
            ->action(function (SalesDispatch $record, array $data): void {
                $record->update($data);

                Notification::make()->title('Factura registrada: queda por cobrar')->success()->send();
            });
    }

    public static function pay(): Action
    {
        return Action::make('registrarPago')
            ->label('Registrar pago')
            ->icon(Heroicon::OutlinedBanknotes)
            ->color('success')
            ->visible(fn (SalesDispatch $record): bool => $record->status === DispatchStatus::Facturado && SalesDispatchResource::canEdit($record))
            ->modalHeading(fn (SalesDispatch $record): string => "Pago de la factura {$record->invoice_number}")
            ->modalDescription(fn (SalesDispatch $record): string => 'Factura por $'.number_format((float) $record->invoice_amount, 0, ',', '.').' a '.$record->customer?->name.'.')
            ->schema([
                DatePicker::make('paid_on')->label('Fecha de pago')
                    ->default(fn (): string => SalesDispatch::today())
                    ->required(),
            ])
            ->action(function (SalesDispatch $record, array $data): void {
                $record->update($data);

                Notification::make()->title('Pago registrado')->success()->send();
            });
    }

    /** Si el valor no cuadra con la liquidación, se dice cuánto debería ser. */
    private static function invoiceHint(SalesDispatch $record, Get $get): ?string
    {
        if ($record->settlement_amount === null) {
            return null;
        }

        $expected = (float) $record->settlement_amount + (float) ($record->quality_bonus ?? 0) + (float) ($get('billed_freight') ?: 0);

        return 'Según la liquidación: $'.number_format($expected, 0, ',', '.').' (total + bonificación + flete cobrado).';
    }
}
