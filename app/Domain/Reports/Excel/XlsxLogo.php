<?php

namespace App\Domain\Reports\Excel;

use App\Domain\Reports\Services\ReportBrandingService;
use App\Models\Tenant;
use ZipArchive;

/**
 * Pone el logo de la empresa en la esquina superior izquierda de cada hoja de un Excel ya
 * escrito.
 *
 * OpenSpout escribe rápido y sin gastar memoria, pero no sabe de imágenes; un .xlsx es un
 * zip de XML, así que el logo se agrega después, con las piezas que pide el formato: la
 * imagen en xl/media, un dibujo por hoja que la ancla a la celda A1, las relaciones y los
 * tipos de contenido. Si algo falla, el Excel queda como estaba, sin logo: nunca se pierde
 * el archivo por la imagen.
 */
final class XlsxLogo
{
    /** EMU por píxel, la unidad de medida de los dibujos de Office. */
    private const EMU_PER_PIXEL = 9525;

    /**
     * El logo de la empresa —o el de fronda.app si no tiene— como bytes y extensión.
     *
     * @return array{0: string, 1: string}|null
     */
    public static function forTenant(?Tenant $tenant): ?array
    {
        $branding = app(ReportBrandingService::class);
        $dataUri = $branding->logoBase64($tenant);

        // Excel solo muestra PNG y JPG: un logo en otro formato cede al de fronda.app.
        if (! $dataUri || ! preg_match('#^data:image/(png|jpe?g);base64,(.+)$#', $dataUri, $m)) {
            $dataUri = $branding->logoBase64(null);

            if (! $dataUri || ! preg_match('#^data:image/(png|jpe?g);base64,(.+)$#', $dataUri, $m)) {
                return null;
            }
        }

        $bytes = base64_decode($m[2], true);

        return $bytes ? [$bytes, $m[1] === 'png' ? 'png' : 'jpeg'] : null;
    }

    /**
     * @param  array{0: string, 1: string}  $logo  bytes y extensión (png o jpeg)
     */
    public static function stamp(string $path, array $logo, int $heightPx = 64): void
    {
        [$bytes, $extension] = $logo;
        $size = @getimagesizefromstring($bytes);

        if (! $size || $size[1] <= 0) {
            return;
        }

        $height = $heightPx;
        $width = (int) round($size[0] * $heightPx / $size[1]);

        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            return;
        }

        try {
            $sheets = [];

            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = (string) $zip->getNameIndex($i);

                if (preg_match('#^xl/worksheets/sheet(\d+)\.xml$#', $name, $m)) {
                    $sheets[(int) $m[1]] = $name;
                }
            }

            if ($sheets === []) {
                return;
            }

            $media = 'logo.'.($extension === 'png' ? 'png' : 'jpeg');
            $zip->addFromString('xl/media/'.$media, $bytes);

            $types = (string) $zip->getFromName('[Content_Types].xml');
            $mime = $extension === 'png' ? 'image/png' : 'image/jpeg';
            $ext = $extension === 'png' ? 'png' : 'jpeg';

            if (! str_contains($types, 'Extension="'.$ext.'"')) {
                $types = str_replace('<Default ', '<Default Extension="'.$ext.'" ContentType="'.$mime.'"/><Default ', $types);
            }

            foreach ($sheets as $index => $sheetPath) {
                $drawing = "drawingLogo{$index}.xml";

                $zip->addFromString("xl/drawings/{$drawing}", self::drawingXml($width, $height));
                $zip->addFromString(
                    "xl/drawings/_rels/{$drawing}.rels",
                    '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                    .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                    .'<Relationship Id="rIdLogoImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../media/'.$media.'"/>'
                    .'</Relationships>',
                );

                $relsPath = "xl/worksheets/_rels/sheet{$index}.xml.rels";
                $rels = $zip->getFromName($relsPath);
                $relation = '<Relationship Id="rIdLogo" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/drawing" Target="../drawings/'.$drawing.'"/>';
                $rels = $rels === false
                    ? '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.$relation.'</Relationships>'
                    : str_replace('</Relationships>', $relation.'</Relationships>', $rels);
                $zip->addFromString($relsPath, $rels);

                $sheet = (string) $zip->getFromName($sheetPath);

                if (! str_contains($sheet, 'xmlns:r=')) {
                    $sheet = preg_replace('#<worksheet #', '<worksheet xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" ', $sheet, 1) ?? $sheet;
                }

                // <drawing> va después de las celdas fundidas y la página, y antes del
                // dibujo heredado de los comentarios.
                $sheet = str_contains($sheet, '<legacyDrawing')
                    ? preg_replace('#<legacyDrawing#', '<drawing r:id="rIdLogo"/><legacyDrawing', $sheet, 1)
                    : str_replace('</worksheet>', '<drawing r:id="rIdLogo"/></worksheet>', $sheet);
                $zip->addFromString($sheetPath, (string) $sheet);

                $types = str_replace(
                    '</Types>',
                    '<Override PartName="/xl/drawings/'.$drawing.'" ContentType="application/vnd.openxmlformats-officedocument.drawing+xml"/></Types>',
                    $types,
                );
            }

            $zip->addFromString('[Content_Types].xml', $types);
        } finally {
            $zip->close();
        }
    }

    private static function drawingXml(int $width, int $height): string
    {
        $offset = 4 * self::EMU_PER_PIXEL;

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<xdr:wsDr xmlns:xdr="http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<xdr:oneCellAnchor>'
            .'<xdr:from><xdr:col>0</xdr:col><xdr:colOff>'.$offset.'</xdr:colOff><xdr:row>0</xdr:row><xdr:rowOff>'.$offset.'</xdr:rowOff></xdr:from>'
            .'<xdr:ext cx="'.($width * self::EMU_PER_PIXEL).'" cy="'.($height * self::EMU_PER_PIXEL).'"/>'
            .'<xdr:pic>'
            .'<xdr:nvPicPr><xdr:cNvPr id="2" name="Logo"/><xdr:cNvPicPr><a:picLocks noChangeAspect="1"/></xdr:cNvPicPr></xdr:nvPicPr>'
            .'<xdr:blipFill><a:blip r:embed="rIdLogoImage"/><a:stretch><a:fillRect/></a:stretch></xdr:blipFill>'
            .'<xdr:spPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="'.($width * self::EMU_PER_PIXEL).'" cy="'.($height * self::EMU_PER_PIXEL).'"/></a:xfrm><a:prstGeom prst="rect"><a:avLst/></a:prstGeom></xdr:spPr>'
            .'</xdr:pic>'
            .'<xdr:clientData/>'
            .'</xdr:oneCellAnchor>'
            .'</xdr:wsDr>';
    }
}
