<?php

namespace App\Filament\Resources\Employees\Pages;

use App\Filament\Resources\Employees\EmployeeResource;
use App\Models\Employee;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

/**
 * La ficha del trabajador, en pestañas: «Información» y a su lado «Documentos»,
 * novedades, bonificaciones y descuentos.
 *
 * Antes el formulario iba arriba y las tablas relacionadas apiladas debajo, así que para
 * llegar a la carpeta de alguien había que desplazarse por toda su ficha. Combinar el
 * formulario con las pestañas de las relaciones deja cada cosa a un clic.
 *
 * @property Employee $record
 */
class EditEmployee extends EditRecord
{
    protected static string $resource = EmployeeResource::class;

    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return true;
    }

    public function getContentTabLabel(): ?string
    {
        return 'Información';
    }

    public function getContentTabIcon(): string|\BackedEnum|Htmlable|null
    {
        return Heroicon::OutlinedIdentification;
    }

    /** El nombre de la persona, no «Editar trabajador». */
    public function getHeading(): string|Htmlable
    {
        return $this->record->fullName();
    }

    /** Cargo · documento · estado, como la cabecera de la ficha en papel. */
    public function getSubheading(): string|Htmlable|null
    {
        return collect([
            $this->record->position,
            $this->record->document_type.' '.$this->record->document_number,
            $this->record->status?->label(),
        ])->filter()->implode(' · ');
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
