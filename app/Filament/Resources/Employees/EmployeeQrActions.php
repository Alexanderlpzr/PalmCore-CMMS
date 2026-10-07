<?php

namespace App\Filament\Resources\Employees;

use App\Domain\HumanResources\Enums\QrRevocationReason;
use App\Domain\HumanResources\Services\EmployeeQrCodeService;
use App\Models\Employee;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Lo que se hace con el carné de un trabajador: descargarlo y reemitirlo.
 *
 * Reemitir vive solo en la ficha, apartado «Carné», y no en la lista de Personal: allí
 * quedaba al lado de «Descargar QR», del mismo color y tamaño, y un clic de más dejaba
 * sin servicio el carné que el trabajador tiene en el bolsillo. Ahora pide el motivo y
 * que se escriba la cédula, y el carné anulado guarda quién, cuándo y por qué.
 */
final class EmployeeQrActions
{
    /**
     * La imagen del QR, para pegarla en el carné que ya usa la empresa. Si se perdió del
     * disco, se vuelve a dibujar del mismo token: el carné impreso sigue sirviendo.
     */
    public static function downloadFor(Employee $employee): StreamedResponse
    {
        $qrCode = $employee->qrCode;
        $disk = Storage::disk(persistent_disk());

        if (! $qrCode->qr_image_path || ! $disk->exists($qrCode->qr_image_path)) {
            $qrCode->update([
                'qr_image_path' => app(EmployeeQrCodeService::class)->generateImage($qrCode->qr_token, $employee->tenant_id),
            ]);
        }

        return $disk->download(
            $qrCode->qr_image_path,
            Str::slug(trim(($employee->employee_code ?? '').' '.$employee->fullName())).'-qr.png',
        );
    }

    /**
     * Reemitir: el carné actual deja de servir en el acto y nace uno nuevo. Pide el
     * motivo y la cédula del trabajador escrita a mano, para que no se haga sin querer.
     *
     * @param  callable(): Employee  $employee  el dueño del carné (la ficha que se mira)
     */
    public static function reissue(callable $employee): Action
    {
        return Action::make('reemitirCarne')
            ->label('Reemitir carné')
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('danger')
            ->authorize(fn (): bool => auth()->user()?->can('manageQrCode', $employee()) ?? false)
            ->visible(fn (): bool => $employee()->qrCode !== null)
            ->modalIcon(Heroicon::OutlinedExclamationTriangle)
            ->modalIconColor('danger')
            ->modalHeading(fn (): string => 'Reemitir el carné de '.$employee()->fullName())
            ->modalDescription('El carné que tiene ahora deja de servir en el acto: si lo pasa por la puerta, la marca se rechaza. El nuevo hay que descargarlo, imprimirlo y entregárselo.')
            ->schema([
                Select::make('reason')
                    ->label('Motivo')
                    ->options(QrRevocationReason::options())
                    ->required()
                    ->native(false)
                    ->live(),
                Textarea::make('detail')
                    ->label('Detalle')
                    ->helperText('Obligatorio si el motivo es «Otro».')
                    ->required(fn (Get $get): bool => $get('reason') === QrRevocationReason::Otro->value)
                    ->rows(2),
                TextInput::make('document_confirmation')
                    ->label(fn (): string => 'Para confirmar, escriba la cédula del trabajador ('.$employee()->document_number.')')
                    ->required()
                    ->autocomplete('off')
                    ->rule(fn () => function (string $attribute, mixed $value, \Closure $fail) use ($employee): void {
                        if (self::digits((string) $value) !== self::digits((string) $employee()->document_number)) {
                            $fail('La cédula no coincide con la del trabajador.');
                        }
                    }),
            ])
            ->modalSubmitActionLabel('Anular el carné anterior')
            ->action(function (array $data) use ($employee): void {
                $trabajador = $employee();

                // El nuevo pasa a ser el carné de la ficha desde ya, no desde el próximo clic.
                $trabajador->setRelation('qrCode', app(EmployeeQrCodeService::class)->regenerate(
                    $trabajador->qrCode,
                    self::actor(),
                    QrRevocationReason::from($data['reason']),
                    $data['detail'] ?? null,
                ));

                Notification::make()
                    ->title('Carné reemitido')
                    ->body('El anterior quedó anulado. Descargue el nuevo para imprimirlo.')
                    ->success()
                    ->send();
            });
    }

    /** El trabajador sin carné —uno recién creado que no alcanzó a tenerlo— lo recibe. */
    public static function issue(callable $employee): Action
    {
        return Action::make('emitirCarne')
            ->label('Emitir carné')
            ->icon(Heroicon::OutlinedQrCode)
            ->authorize(fn (): bool => auth()->user()?->can('manageQrCode', $employee()) ?? false)
            ->visible(fn (): bool => $employee()->qrCode === null)
            ->action(function () use ($employee): void {
                $trabajador = $employee();

                // Si no, la ficha seguiría ofreciendo «Emitir» y escondiendo «Descargar QR».
                $trabajador->setRelation('qrCode', app(EmployeeQrCodeService::class)->createForEmployee($trabajador));

                Notification::make()->title('Carné emitido')->success()->send();
            });
    }

    /** La cédula se compara solo por sus dígitos: con o sin puntos es la misma. */
    private static function digits(string $value): string
    {
        return preg_replace('/\D/', '', $value) ?? '';
    }

    private static function actor(): User
    {
        /** @var User */
        return auth()->user();
    }
}
