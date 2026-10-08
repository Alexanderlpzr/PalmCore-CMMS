{{-- La pestaña «Horas extras» de la ficha, dentro de la ventana: los mismos días, con sus
     botones para ajustar horas o anular un día. --}}
@livewire(\App\Filament\Resources\Employees\RelationManagers\OvertimeRelationManager::class, [
    'ownerRecord' => $employee,
    'pageClass' => \App\Filament\Resources\Employees\Pages\EditEmployee::class,
], key('dias-'.$employee->getKey()))
