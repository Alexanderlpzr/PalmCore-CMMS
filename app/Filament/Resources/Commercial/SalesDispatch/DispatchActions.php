<?php

namespace App\Filament\Resources\Commercial\SalesDispatch;

use App\Domain\Commercial\Enums\DispatchStatus;
use App\Domain\Commercial\Services\InvoiceEditor;
use App\Models\SalesDispatch;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
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
            ->visible(fn (SalesDispatch $record): bool => $record->status === DispatchStatus::Facturado && (auth()->user()?->can('manageInvoice', $record) ?? false))
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

    /**
     * Los cuatro datos de la factura y nada más: lo que lleva la auxiliar de la Directora.
     * A quien ya tiene «Registrar factura» en un cargue por facturar no se le repite.
     */
    public static function editInvoice(): Action
    {
        return Action::make('editarFactura')
            ->label(fn (SalesDispatch $record): string => blank($record->invoice_number) ? 'Registrar factura' : 'Editar factura')
            ->icon(Heroicon::OutlinedPencilSquare)
            ->color('primary')
            ->authorize(fn (SalesDispatch $record): bool => auth()->user()?->can('manageInvoice', $record) ?? false)
            ->visible(fn (SalesDispatch $record): bool => $record->status !== DispatchStatus::Cargado
                && ! ($record->status === DispatchStatus::Descargado && SalesDispatchResource::canEdit($record)))
            ->modalHeading(fn (SalesDispatch $record): string => "Factura de {$record->label()}")
            ->fillForm(fn (SalesDispatch $record): array => [
                'invoice_number' => $record->invoice_number,
                'invoiced_on' => $record->invoiced_on?->toDateString() ?? SalesDispatch::today(),
                'invoice_amount' => $record->invoice_amount ?? $record->expectedInvoiceAmount(),
                'invoice_status' => $record->status === DispatchStatus::Pagado ? DispatchStatus::Pagado->value : DispatchStatus::Facturado->value,
                'paid_on' => $record->paid_on?->toDateString(),
            ])
            ->schema([
                TextInput::make('invoice_number')->label('N.º de factura')->maxLength(30)->required()
                    ->live(onBlur: true)
                    ->helperText(fn (SalesDispatch $record, ?string $state): ?string => self::repeatedInvoiceWarning($record, $state)),
                DatePicker::make('invoiced_on')->label('Fecha de factura')->required(),
                TextInput::make('invoice_amount')->label('Valor de la factura')
                    ->numeric()->prefix('$')->minValue(0)->required()
                    ->helperText(fn (SalesDispatch $record): ?string => $record->expectedInvoiceAmount() === null ? null
                        : 'Según la liquidación: $'.number_format($record->expectedInvoiceAmount(), 0, ',', '.').'.'),
                Select::make('invoice_status')->label('Estado de la factura')
                    ->options([
                        DispatchStatus::Facturado->value => 'Por cobrar',
                        DispatchStatus::Pagado->value => 'Pagada',
                    ])
                    ->selectablePlaceholder(false)
                    ->live()
                    ->required(),
                DatePicker::make('paid_on')->label('Fecha de pago')
                    ->visible(fn (Get $get): bool => $get('invoice_status') === DispatchStatus::Pagado->value)
                    ->required(fn (SalesDispatch $record): bool => ! $record->isPaidWithoutDate())
                    ->helperText(fn (SalesDispatch $record): ?string => $record->isPaidWithoutDate() ? 'Vino del Excel como pagada, sin fecha. Puede dejarla vacía.' : null),
            ])
            ->action(function (SalesDispatch $record, array $data): void {
                app(InvoiceEditor::class)->update($record, $data);

                Notification::make()->title('Factura actualizada')->success()->send();
            });
    }

    /** Una factura puede cubrir varios cargues: se avisa, no se impide. */
    private static function repeatedInvoiceWarning(SalesDispatch $record, ?string $number): ?string
    {
        $others = app(InvoiceEditor::class)->otherLoadsWithNumber($record, $number);

        return match (true) {
            $others === 0 => null,
            $others === 1 => 'Ojo: este número ya está en otro cargue. Si la factura cubre los dos, guárdela así.',
            default => "Ojo: este número ya está en otros {$others} cargues. Si la factura los cubre, guárdela así.",
        };
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
