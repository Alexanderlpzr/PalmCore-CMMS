<?php

namespace App\Domain\Analytics\Support;

/**
 * El comodín «Planta general»: ni una sección ni un equipo, sino el cajón donde caen los
 * paros que son de toda la planta.
 *
 * Existe porque un paro tiene que colgar de algún sitio: un mantenimiento programado que
 * detiene la línea entera no es «de la prensa», y anotarlo en una prensa cualquiera
 * ensuciaría su historial. Pero en las gráficas de «dónde se pierden las horas» ese cajón
 * no responde la pregunta —no se puede ir a arreglar «la planta entera»— y además aplasta
 * a los demás: en El Pajuil la sección Planta general son 425,6 h contra 251,1 de
 * Extracción, la siguiente.
 *
 * Se reconoce por el nombre y no por un identificador porque es un registro que la planta
 * creó a mano, sin marca en el modelo. Comparar normalizado —sin tildes ni mayúsculas—
 * evita que «Planta General» y «PLANTA GENERAL» se traten como cosas distintas.
 */
class PlantaGeneral
{
    private const NOMBRES = ['planta general', 'plantageneral'];

    public static function es(?string $nombre): bool
    {
        if ($nombre === null) {
            return false;
        }

        $normalizado = mb_strtolower(trim($nombre));
        $normalizado = strtr($normalizado, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u']);

        return in_array($normalizado, self::NOMBRES, strict: true);
    }
}
