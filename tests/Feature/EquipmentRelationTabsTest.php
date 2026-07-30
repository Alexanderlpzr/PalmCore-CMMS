<?php

use App\Filament\Resources\Equipment\EquipmentResource;
use App\Filament\Resources\Equipment\RelationManagers\ComponentsRelationManager;
use App\Filament\Resources\Equipment\RelationManagers\MaintenancePlansRelationManager;
use App\Filament\Resources\Equipment\RelationManagers\SparePartsRelationManager;
use App\Filament\Resources\Equipment\RelationManagers\WorkOrdersRelationManager;

it('el equipo solo expone las pestañas de Piezas, Repuestos, Preventivos y OT', function () {
    expect(EquipmentResource::getRelations())->toBe([
        'components' => ComponentsRelationManager::class,
        'spare_parts' => SparePartsRelationManager::class,
        'maintenance_plans' => MaintenancePlansRelationManager::class,
        'work_orders' => WorkOrdersRelationManager::class,
    ]);
});

it('ya no expone Análisis de Fallas RCM, Documentos ni Fotografías', function () {
    $registered = array_keys(EquipmentResource::getRelations());

    expect($registered)->not->toContain('failure_mode_analyses')
        ->and($registered)->not->toContain('documents')
        ->and($registered)->not->toContain('photos');
});
