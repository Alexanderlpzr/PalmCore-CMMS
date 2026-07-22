<?php

namespace App\Filament\Pages;

use App\Models\PersonalAccessToken;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class ApiTokens extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static ?string $navigationLabel = 'API Tokens';

    protected static ?string $title = 'API Tokens';

    protected static string|\UnitEnum|null $navigationGroup = 'Configuración';

    protected static ?int $navigationSort = 90;

    protected string $view = 'filament.pages.api-tokens';

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    /** Plain-text token to display once after creation. */
    public ?string $newPlainTextToken = null;

    /** @return PersonalAccessToken[] */
    public function getTokens(): array
    {
        return auth()->user()
            ->tokens()
            ->where('tenant_id', Filament::getTenant()->id)
            ->latest()
            ->get()
            ->all();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('createToken')
                ->label('Crear Token')
                ->icon('heroicon-o-plus')
                ->form([
                    TextInput::make('token_name')
                        ->label('Nombre del token')
                        ->placeholder('Power BI - Producción')
                        ->required()
                        ->maxLength(255),
                    CheckboxList::make('abilities')
                        ->label('Permisos')
                        ->options([
                            'equipment.read' => 'Equipos — lectura',
                            'work-orders.read' => 'Órdenes de trabajo — lectura',
                            'maintenance-requests.read' => 'Solicitudes de mantenimiento — lectura',
                            'inventory.read' => 'Inventario — lectura',
                            'reliability.read' => 'Confiabilidad / KPIs — lectura',
                            'downtime.read' => 'Paros — lectura',
                            'downtime.write' => 'Paros — registro',
                            'permits.read' => 'Permisos de trabajo — lectura',
                            'permits.write' => 'Permisos de trabajo — firma del ejecutante',
                            'plants.read' => 'Plantas — lectura',
                            'plants.write' => 'Plantas / calendario de producción — escritura',
                            'areas.read' => 'Áreas — lectura',
                        ])
                        ->default(['*'])
                        ->columns(2)
                        ->helperText('Sin selección = acceso a todo (equivalente a *)'),
                    DateTimePicker::make('expires_at')
                        ->label('Fecha de expiración')
                        ->helperText('Dejar vacío para tokens sin expiración')
                        ->nullable()
                        ->minDate(now()->addMinute()),
                ])
                ->action(function (array $data): void {
                    $abilities = ! empty($data['abilities']) ? $data['abilities'] : ['*'];
                    $expiresAt = isset($data['expires_at']) ? Carbon::parse($data['expires_at']) : null;

                    $tokenResult = auth()->user()->createToken(
                        $data['token_name'],
                        $abilities,
                        $expiresAt,
                    );

                    $tokenResult->accessToken->forceFill([
                        'tenant_id' => Filament::getTenant()->id,
                    ])->save();

                    $this->newPlainTextToken = $tokenResult->plainTextToken;

                    $this->dispatch('open-modal', id: 'show-new-token');
                })
                ->modalSubmitActionLabel('Generar token'),
        ];
    }

    public function revokeToken(int $tokenId): void
    {
        $token = auth()->user()
            ->tokens()
            ->where('tenant_id', Filament::getTenant()->id)
            ->find($tokenId);

        if ($token) {
            $token->delete();

            Notification::make()
                ->title('Token revocado')
                ->success()
                ->send();
        }
    }
}
