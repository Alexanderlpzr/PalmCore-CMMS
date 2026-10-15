<?php

use App\Domain\Reports\Excel\FrondaWorkbook;
use App\Domain\Reports\Excel\XlsxLogo;
use App\Models\Tenant;
use OpenSpout\Reader\XLSX\Reader;

/*
 * El logo de la empresa arriba a la izquierda de cada hoja de los Excel. OpenSpout no sabe
 * de imágenes: el logo se agrega al archivo ya escrito, y el libro tiene que seguir
 * abriéndose igual.
 */

it('puts the company logo on the top left of every sheet and keeps the workbook readable', function (): void {
    $tenant = Tenant::factory()->create();
    $logo = XlsxLogo::forTenant($tenant);

    expect($logo)->not->toBeNull();

    $book = FrondaWorkbook::create($tenant);
    $book->sheet('Uno')->banner('Prueba', 'Detalle', 'Empresa', 4)->table(['A', 'B', 'C', 'D'], [[1, 2, 3, 4]]);
    $book->sheet('Dos')->banner('Otra', null, 'Empresa', 4);
    $path = $book->close();

    $zip = new ZipArchive;
    $zip->open($path);
    $sheet = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
    $types = (string) $zip->getFromName('[Content_Types].xml');
    $media = $zip->locateName('xl/media/logo.png') !== false || $zip->locateName('xl/media/logo.jpeg') !== false;
    $drawings = $zip->locateName('xl/drawings/drawingLogo1.xml') !== false && $zip->locateName('xl/drawings/drawingLogo2.xml') !== false;
    $zip->close();

    expect($media)->toBeTrue()
        ->and($drawings)->toBeTrue()
        ->and($sheet)->toContain('<drawing r:id="rIdLogo"/>')
        ->and($types)->toContain('drawingLogo1.xml');

    // Sigue abriéndose: la banda deja la columna A para el logo.
    $reader = new Reader;
    $reader->open($path);
    $first = null;

    foreach ($reader->getSheetIterator() as $hoja) {
        foreach ($hoja->getRowIterator() as $fila) {
            $first = $fila->toArray();
            break 2;
        }
    }

    $reader->close();
    @unlink($path);

    expect($first[0])->toBe('')
        ->and($first[1])->toBe('fronda.app');
});
