{{-- La hoja del trabajador dentro de la ventana de «Horas extras»: el componente navega solo. --}}
@livewire(\App\Livewire\HumanResources\OvertimeSheet::class, [
    'employeeIds' => $employeeIds,
    'employeeId' => $employeeId,
    'from' => $from,
    'to' => $to,
], key('hoja-de-horas-'.$employeeId.'-'.$from))
