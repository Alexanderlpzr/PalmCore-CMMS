<?php

namespace App\Filament\Resources\Permissions\Tables;

use App\Models\Permission;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class PermissionsTable
{
    /** @var array<string, string> */
    private static array $moduleLabels = [
        'announcements' => 'Anuncios',
        'areas' => 'Áreas',
        'audit-log' => 'Auditoría',
        'carousel-slides' => 'Carrusel',
        'equipment' => 'Equipos',
        'equipment-categories' => 'Categorías de Equipos',
        'equipment-documents' => 'Documentos de Equipos',
        'equipment-meter-readings' => 'Lecturas de Horómetro',
        'equipment-photos' => 'Fotos de Equipos',
        'equipment-qr' => 'QR de Equipos',
        'inventory' => 'Inventario',
        'issue-reports' => 'Reportes de Novedad',
        'maintenance-checklist-items' => 'Ítems de Checklist',
        'maintenance-plan-attachments' => 'Adjuntos de Plan',
        'maintenance-plan-tasks' => 'Tareas de Plan',
        'maintenance-plans' => 'Planes de Mantenimiento',
        'maintenance-request-attachments' => 'Adjuntos de Solicitud',
        'maintenance-request-comments' => 'Comentarios de Solicitud',
        'maintenance-requests' => 'Solicitudes de Mantenimiento',
        'manufacturers' => 'Fabricantes',
        'permissions' => 'Permisos',
        'plants' => 'Plantas',
        'roles' => 'Roles',
        'spare-parts' => 'Repuestos',
        'suppliers' => 'Proveedores',
        'tenants' => 'Empresas',
        'user-profiles' => 'Perfiles de Usuario',
        'users' => 'Usuarios',
        'warehouses' => 'Almacenes',
        'work-order-comments' => 'Comentarios de OT',
        'work-order-parts' => 'Repuestos de OT',
        'work-order-signatures' => 'Firmas de OT',
        'work-order-time-logs' => 'Horas de OT',
        'work-orders' => 'Órdenes de Trabajo',
    ];

    /** @var array<string, string> */
    private static array $actionLabels = [
        'acknowledge' => 'Reconocer',
        'activate' => 'Activar',
        'adjust' => 'Ajustar',
        'approve' => 'Aprobar',
        'archive' => 'Archivar',
        'assign' => 'Asignar',
        'close' => 'Cerrar',
        'convert' => 'Convertir',
        'create' => 'Crear',
        'delete' => 'Eliminar',
        'entry' => 'Entrada',
        'execute' => 'Ejecutar',
        'exit' => 'Salida',
        'manage' => 'Gestionar',
        'plan' => 'Planificar',
        'restore' => 'Restaurar',
        'review' => 'Revisar',
        'revoke' => 'Revocar',
        'transfer' => 'Transferir',
        'update' => 'Actualizar',
        'verify' => 'Verificar',
        'view' => 'Ver',
    ];

    private static function moduleLabel(string $slug): string
    {
        return self::$moduleLabels[$slug] ?? Str::headline(str_replace('-', ' ', $slug));
    }

    private static function actionLabel(string $slug): string
    {
        return self::$actionLabels[$slug] ?? Str::headline($slug);
    }

    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Permiso')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('module')
                    ->label('Módulo')
                    ->badge()
                    ->state(fn ($record): string => explode('.', $record->name)[0] ?? '')
                    ->formatStateUsing(fn (string $state): string => self::moduleLabel($state))
                    ->color('primary'),
                TextColumn::make('action')
                    ->label('Acción')
                    ->state(fn ($record): string => explode('.', $record->name)[1] ?? '')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => self::actionLabel($state))
                    ->color('gray'),
                TextColumn::make('guard_name')
                    ->label('Guard')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('module')
                    ->label('Módulo')
                    ->options(fn (): array => Permission::orderBy('name')
                        ->pluck('name')
                        ->mapWithKeys(fn ($name) => [
                            explode('.', $name)[0] => self::moduleLabel(explode('.', $name)[0]),
                        ])
                        ->unique()
                        ->toArray()
                    )
                    ->query(fn ($query, array $data) => filled($data['value'])
                        ? $query->where('name', 'like', $data['value'].'.%')
                        : $query
                    ),
            ])
            ->recordActions([])
            ->toolbarActions([])
            ->defaultSort('name');
    }
}
