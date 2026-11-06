<?php

namespace App\Filament\Pages;

use App\Domain\Notifications\NotificationKind;
use App\Domain\Notifications\NotificationPresenter;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Livewire\Attributes\Url;

/**
 * El centro de notificaciones de cada persona: todo lo que le ha llegado a la campana,
 * con las que faltan por leer aparte. Cada aviso se abre, se marca leído o no leído, o se
 * borra; también de a varios.
 */
class Notificaciones extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBell;

    protected static ?string $title = 'Notificaciones';

    protected static ?string $slug = 'notificaciones';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.pages.notificaciones';

    /** sin-leer, todas o leidas. */
    #[Url(as: 'ver')]
    public string $vista = 'sin-leer';

    /** @var array<string, array{title: string, body: ?string, kind: NotificationKind, status: string, url: ?string}> */
    private array $shown = [];

    public static function canAccess(): bool
    {
        return auth()->check();
    }

    public function getSubheading(): ?string
    {
        $unread = $this->baseQuery()->whereNull('read_at')->count();

        return match ($unread) {
            0 => 'Está al día: no tiene avisos sin leer.',
            1 => 'Tiene 1 aviso sin leer.',
            default => "Tiene {$unread} avisos sin leer.",
        };
    }

    /** @return array{sin-leer: int, todas: int, leidas: int} */
    public function counts(): array
    {
        $all = $this->baseQuery()->count();
        $unread = $this->baseQuery()->whereNull('read_at')->count();

        return ['sin-leer' => $unread, 'todas' => $all, 'leidas' => $all - $unread];
    }

    public function setVista(string $vista): void
    {
        $this->vista = in_array($vista, ['sin-leer', 'todas', 'leidas'], true) ? $vista : 'sin-leer';
        $this->resetTable();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('todas-leidas')
                ->label('Marcar todas como leídas')
                ->icon(Heroicon::OutlinedCheckCircle)
                ->color('gray')
                ->visible(fn (): bool => $this->baseQuery()->whereNull('read_at')->exists())
                ->action(function (): void {
                    $count = $this->baseQuery()->whereNull('read_at')->update(['read_at' => now()]);
                    $this->refreshBell();
                    Notification::make()->title($count === 1 ? '1 aviso marcado como leído' : "{$count} avisos marcados como leídos")->success()->send();
                }),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => $this->baseQuery()
                ->when($this->vista === 'sin-leer', fn (Builder $query) => $query->whereNull('read_at'))
                ->when($this->vista === 'leidas', fn (Builder $query) => $query->whereNotNull('read_at')))
            ->defaultSort('created_at', 'desc')
            ->columns([
                IconColumn::make('read_at')
                    ->label('')
                    ->state(fn (DatabaseNotification $record): bool => $record->unread())
                    ->icon(fn (bool $state): string => $state ? 'heroicon-s-envelope' : 'heroicon-o-envelope-open')
                    ->color(fn (bool $state): string => $state ? 'primary' : 'gray')
                    ->tooltip(fn (bool $state): string => $state ? 'Sin leer' : 'Leída')
                    ->width('1%'),
                TextColumn::make('aviso')
                    ->label('Aviso')
                    ->state(fn (DatabaseNotification $record): string => $this->shown($record)['title'])
                    ->description(fn (DatabaseNotification $record): ?string => Str::limit((string) $this->shown($record)['body'], 240) ?: null)
                    ->icon(fn (DatabaseNotification $record): string => $this->shown($record)['kind']->icon())
                    ->iconColor(fn (DatabaseNotification $record): string => $this->shown($record)['status'])
                    ->weight(fn (DatabaseNotification $record): ?string => $record->unread() ? 'bold' : null)
                    ->wrap(),
                TextColumn::make('tipo')
                    ->label('Tipo')
                    ->state(fn (DatabaseNotification $record): string => $this->shown($record)['kind']->label())
                    ->badge()
                    ->color('gray'),
                TextColumn::make('created_at')
                    ->label('Llegó')
                    ->since()
                    ->dateTimeTooltip('d/m/Y h:i a')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('kind')
                    ->label('Tipo')
                    ->options(NotificationKind::options())
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? NotificationPresenter::whereKind($query, NotificationKind::from($data['value']))
                        : $query),
            ])
            ->recordActions([
                Action::make('abrir')
                    ->label('Abrir')
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->visible(fn (DatabaseNotification $record): bool => filled($this->shown($record)['url']))
                    ->action(function (DatabaseNotification $record): void {
                        $record->markAsRead();
                        $this->redirect($this->shown($record)['url']);
                    }),
                Action::make('marcar-leida')
                    ->label('Leída')
                    ->tooltip('Marcar como leída')
                    ->icon(Heroicon::OutlinedEnvelopeOpen)
                    ->color('gray')
                    ->visible(fn (DatabaseNotification $record): bool => $record->unread())
                    ->action(function (DatabaseNotification $record): void {
                        $record->markAsRead();
                        $this->refreshBell();
                    }),
                Action::make('marcar-no-leida')
                    ->label('No leída')
                    ->tooltip('Marcar como no leída')
                    ->icon(Heroicon::OutlinedEnvelope)
                    ->color('gray')
                    ->visible(fn (DatabaseNotification $record): bool => $record->read())
                    ->action(function (DatabaseNotification $record): void {
                        $record->markAsUnread();
                        $this->refreshBell();
                    }),
                Action::make('borrar')
                    ->label('Borrar')
                    ->icon(Heroicon::OutlinedTrash)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('¿Borrar este aviso?')
                    ->action(function (DatabaseNotification $record): void {
                        $record->delete();
                        $this->refreshBell();
                    }),
            ])
            ->toolbarActions([
                BulkAction::make('marcar-leidas')
                    ->label('Marcar como leídas')
                    ->icon(Heroicon::OutlinedEnvelopeOpen)
                    ->action(fn (Collection $records) => $this->bulkUpdate($records, now()))
                    ->deselectRecordsAfterCompletion(),
                BulkAction::make('marcar-no-leidas')
                    ->label('Marcar como no leídas')
                    ->icon(Heroicon::OutlinedEnvelope)
                    ->action(fn (Collection $records) => $this->bulkUpdate($records, null))
                    ->deselectRecordsAfterCompletion(),
                BulkAction::make('borrar')
                    ->label('Borrar')
                    ->icon(Heroicon::OutlinedTrash)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (Collection $records): void {
                        $this->baseQuery()->whereKey($records->modelKeys())->delete();
                        $this->refreshBell();
                    })
                    ->deselectRecordsAfterCompletion(),
            ])
            ->emptyStateIcon('heroicon-o-bell-slash')
            ->emptyStateHeading(fn (): string => $this->vista === 'sin-leer' ? 'No tiene avisos sin leer' : 'No hay avisos aquí')
            ->emptyStateDescription('Aquí le llegan los hallazgos de SST, las órdenes de trabajo, las alertas y las descargas que le tocan.')
            ->paginated([25, 50, 100]);
    }

    /** Solo los avisos de quien está mirando: nadie ve ni toca los de otro. */
    private function baseQuery(): Builder
    {
        /** @var User $user */
        $user = auth()->user();

        return DatabaseNotification::query()
            ->where('notifiable_type', $user->getMorphClass())
            ->where('notifiable_id', $user->getKey());
    }

    private function bulkUpdate(Collection $records, mixed $readAt): void
    {
        $this->baseQuery()->whereKey($records->modelKeys())->update(['read_at' => $readAt]);
        $this->refreshBell();
    }

    /** La campana del encabezado vuelve a contar sin esperar a su revisión de 30 s. */
    private function refreshBell(): void
    {
        $this->dispatch('databaseNotificationsSent');
    }

    /** @return array{title: string, body: ?string, kind: NotificationKind, status: string, url: ?string} */
    private function shown(DatabaseNotification $record): array
    {
        return $this->shown[$record->getKey()] ??= app(NotificationPresenter::class)->present($record);
    }
}
