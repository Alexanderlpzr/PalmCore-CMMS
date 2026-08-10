<?php

namespace Database\Seeders;

use App\Domain\Assets\Enums\EquipmentStatus;
use App\Models\Area;
use App\Models\Equipment;
use App\Models\EquipmentCategory;
use App\Models\EquipmentComponent;
use App\Models\Plant;
use App\Models\Tenant;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Inventario base de una extractora de aceite de palma: catálogo de tipos de
 * equipo, los 99 equipos del flujo de proceso y sus componentes.
 *
 * Transcrito del inventario de campo de El Pajuil («Secciones-equipos-componentes»),
 * que es el estándar de la planta y sirve de punto de partida para cualquier
 * extractora nueva: el ingeniero ajusta lo que sobre en vez de cargar 99 equipos
 * a mano. Se ejecuta desde ProvisionTenantBaseStructure al crear el tenant.
 *
 * Correcciones aplicadas sobre el archivo original, que no eran cosméticas:
 *
 * - El código dejó de ser ambiguo. `A10SPG.13.02` estaba repetido en los 19
 *   auxiliares de la caldera y `A06CLA.34.03` en dos equipos de clarificación;
 *   con `unique(tenant_id, code)` eso no entra en la base. Se renumeró hacia
 *   abajo desde el primero (13.02 → 13.20; 34.03 → 34.04).
 * - Todos los códigos siguen ahora `A##XXX.##.##`. Los sufijos `_1`, `-A`…`-D`
 *   y el `04EBT` sin la «A» inicial pasaron al siguiente número libre de su
 *   estación, que estaba disponible en todos los casos.
 * - `A01STR.03.02` era imposible: STR es la estación 02, no la 01.
 *
 * El prefijo del código es la estación de proceso (REC=01, STR=02, TRS=03,
 * EBT=04, EXT=05, CLA=06, DEP=07, KRS=08, SPG=10, CMP=19) y no siempre coincide
 * con el área: Palmistería opera equipos DEP y KRS, Desfibrado opera KRS y SPG.
 * Eso es correcto y se respeta — el área es la agrupación operativa.
 */
class TenantInventorySeeder extends Seeder
{
    /**
     * Marca los equipos cuyo desglose no venía en el inventario de campo y se
     * completó con la plantilla de su tipo, para que nadie lo tome por dato
     * levantado en planta.
     */
    public const UNVERIFIED_COMPONENTS_NOTE = 'Componentes propuestos a partir de la plantilla de su tipo: el inventario original no los desglosaba. Pendiente de validar en campo.';

    /**
     * Marca los equipos que no venían en el inventario de campo y aparecieron
     * registrando paros en la planilla «REGISTROS DE PAROS». Existen y fallan;
     * lo que falta es la ficha (marca, modelo, serie), no la máquina.
     */
    public const FROM_DOWNTIME_LOG_NOTE = 'Equipo identificado en el registro de paros de la planta: no figuraba en el levantamiento de inventario. Pendiente de completar ficha técnica en campo.';

    private const CATEGORIES = [
        ['BOM', 'Bomba'],
        ['SNF', 'Sinfín'],
        ['RDL', 'Redler'],
        ['BND', 'Banda transportadora'],
        ['ELV', 'Elevador'],
        ['VNT', 'Ventilador'],
        ['ESL', 'Esclusa'],
        ['UHD', 'Unidad hidráulica'],
        ['TMZ', 'Tamiz'],
        ['PRN', 'Prensa'],
        ['CMP', 'Compresor'],
        ['GEN', 'Planta eléctrica'],
        ['CTF', 'Centrífuga'],
        ['DIG', 'Digestor'],
        ['EZR', 'Esterilizador'],
        ['MOL', 'Molino y triturador'],
        ['HDC', 'Hidrociclón'],
        ['TMB', 'Tambor'],
        ['TRP', 'Transportador'],
        ['FLT', 'Filtro'],
        ['SIL', 'Silo'],
        ['CIC', 'Ciclón'],
        ['TNQ', 'Tanque'],
        ['DSF', 'Desfrutador'],
        ['TLV', 'Tolva'],
        ['REF', 'Sistema de refrigeración'],
        ['TEL', 'Tablero eléctrico'],
    ];

    private const COMPONENT_TEMPLATES = [
        'sinfin' => [
            'Sistema motriz',
            'Cuerpo del sinfín',
            'Estructura y contención',
            'Bujes y soportes',
            'Ductos de descarga',
        ],
        'bomba_con_tanque' => [
            'Tanque',
            'Sistema rotativo y de soporte',
            'Sistema de sellado',
            'Sistema hidráulico',
        ],
        'redler' => [
            'Elementos de transmisión y potencia',
            'Sistema de arrastre',
            'Estructura y contención',
            'Sistema de tensión',
        ],
        'esclusa_neumatica' => [
            'Rotor de aletas',
            'Carcasa cilíndrica',
            'Sistema de transmisión y potencia',
            'Sello hermético',
        ],
        'ventilador_caldera' => [
            'Motor eléctrico',
            'Rodete o impulsor',
            'Carcasa o voluta',
            'Eje de transmisión',
            'Rodamientos y chumaceras',
            'Sistema de transmisión',
            'Compuertas o dampers (reguladores)',
        ],
        'unidad_hidraulica' => [
            'Unidad de potencia',
            'Elementos de control y regulación',
            'Actuadores',
            'Transmisión y sellado',
        ],
        'banda' => [
            'Componentes estructurales',
            'Sistema de traslación y rodamiento',
            'Unidad de potencia',
        ],
        'esclusa_ceniza' => [
            'Cuerpo o carcasa',
            'Rotor (o paletas)',
            'Cojinetes y rodamientos',
            'Sistema de sellado',
            'Sistema de transmisión y potencia',
        ],
        'bomba_multietapa' => [
            'Sección hidráulica (cuerpos y etapas)',
            'Sistema rotativo',
            'Sellado y seguridad',
            'Sistema motriz',
        ],
        'elevador' => [
            'Elementos de transmisión y potencia',
            'Sistema de arrastre',
            'Estructura y cuerpo',
            'Sistema de tensión',
        ],
        'ventilador_neumatico' => [
            'Ventilador',
            'Columna de separación (vertical)',
            'Ciclón separador',
            'Válvulas rotatorias',
            'Ductos de conducción',
            'Bafles internos y compuertas de regulación',
        ],
        'planta_electrica' => [
            'Motor diésel',
            'Alternador',
            'Panel de control',
            'Regulador de voltaje',
            'Sistema de enfriamiento',
            'Sistema de escape y silenciador',
            'Chasis y tanque base',
            'Sistema eléctrico y baterías',
            'Interruptor principal (breaker)',
        ],
        'compresor' => [
            'Sistema mecánico (cabezal o bloque)',
            'Sistema eléctrico',
            'Sistema neumático (almacenamiento y control)',
            'Tratamiento y refrigeración',
        ],
        'bomba_dosificadora' => [
            'Sistema estructural',
            'Bomba eléctrica',
        ],
    ];

    private const EQUIPMENT = [

        // ── REC-01 ──
        ['code' => 'A01REC.02.01', 'name' => 'Unidad Hidráulica Tolva Recepción', 'area' => 'REC-01', 'category' => 'UHD', 'components' => 'unidad_hidraulica'],
        ['code' => 'A01REC.03.01', 'name' => 'Redler #1 Fruta de las Tolvas', 'area' => 'REC-01', 'category' => 'RDL', 'components' => 'redler'],
        ['code' => 'A01REC.07.01', 'name' => 'Bomba Centrífuga del Foso de Tolvas', 'area' => 'REC-01', 'category' => 'BOM', 'components' => ['Sistema rotativo y de soporte', 'Sistema de sellado', 'Sistema hidráulico']],

        // ── EST-01 ──
        ['code' => 'A02STR.02.01', 'name' => 'Redler #2 de Fruta a Esterilizadores', 'area' => 'EST-01', 'category' => 'RDL', 'components' => 'redler'],
        ['code' => 'A02STR.03.01', 'name' => 'Esterilizador Oblicuo Automático', 'area' => 'EST-01', 'category' => 'EZR', 'components' => ['Sistema de potencia', 'Sistema de arrastre', 'Estructura y contención', 'Sistema automatizado']],
        ['code' => 'A02STR.03.02', 'name' => 'Unidad Hidráulica Llenado Esterilizador', 'area' => 'EST-01', 'category' => 'UHD', 'components' => 'unidad_hidraulica'],
        ['code' => 'A02STR.03.03', 'name' => 'Unidad Hidráulica Descarga Esterilizador', 'area' => 'EST-01', 'category' => 'UHD', 'components' => 'unidad_hidraulica'],
        ['code' => 'A02STR.04.01', 'name' => 'Tolva y Dosificador para Fruto Esterilizado', 'area' => 'EST-01', 'category' => 'TLV', 'components' => ['Tolva', 'Sistema de dosificación', 'Elementos de transmisión y potencia']],
        ['code' => 'A02STR.10.01', 'name' => 'Transportador Redler de Fruto Esterilizado', 'area' => 'EST-01', 'category' => 'RDL', 'components' => 'redler'],
        ['code' => 'A02STR.08.01', 'name' => 'Bomba para Chimenea de Esterilización', 'area' => 'EST-01', 'category' => 'BOM', 'components' => ['Trampa de vapor', 'Tanque de condensados', 'Sistema rotativo y de soporte', 'Sistema de sellado', 'Sistema hidráulico']],

        // ── DFR-01 ──
        ['code' => 'A04EBT.01.01', 'name' => 'Desfrutador Sin Eje', 'area' => 'DFR-01', 'category' => 'DSF', 'components' => ['Cilindro o estructura principal', 'Sistema de impulsión y rodamiento', 'Sistema de alimentación y descarga']],
        ['code' => 'A03TRS.02.01', 'name' => 'Sinfín para Fruto Suelto', 'area' => 'DFR-01', 'category' => 'SNF', 'components' => ['Sistema motriz', 'Cuerpo del sinfín', 'Estructura y contención']],

        // ── RAQ-01 ──
        ['code' => 'A04EBT.01.02', 'name' => 'Banda #1 para Racimos Vacíos', 'area' => 'RAQ-01', 'category' => 'BND', 'components' => 'banda'],
        ['code' => 'A04EBT.02.01', 'name' => 'Crusher para Racimos Vacíos', 'area' => 'RAQ-01', 'category' => 'MOL', 'components' => ['Sistema de alimentación y descarga', 'Ejes rotativos', 'Sistema motriz', 'Componentes estructurales']],
        ['code' => 'A04EBT.03.01', 'name' => 'Prensa para Racimos Vacíos', 'area' => 'RAQ-01', 'category' => 'PRN', 'components' => ['Componentes estructurales', 'Sistema de transmisión y potencia', 'Sistema de prensado', 'Sistema de recolección']],
        ['code' => 'A04EBT.05.01', 'name' => 'Banda #2 para Racimos Prensados', 'area' => 'RAQ-01', 'category' => 'BND', 'components' => 'banda'],
        ['code' => 'A04EBT.07.01', 'name' => 'Tamiz Circular en Acero Inoxidable', 'area' => 'RAQ-01', 'category' => 'TMZ', 'components' => ['Motor vibrador', 'Mallas filtrantes', 'Salidas de descargas', 'Bases y soportes de suspensión']],
        ['code' => 'A04EBT.09.01', 'name' => 'Bomba #4 Licor de Prensado de Raquis', 'area' => 'RAQ-01', 'category' => 'BOM', 'components' => 'bomba_con_tanque'],

        // ── EXT-01 ──
        ['code' => 'A05EXT.01.01', 'name' => 'Elevador de Fruto', 'area' => 'EXT-01', 'category' => 'ELV', 'components' => 'elevador'],
        ['code' => 'A05EXT.04.01', 'name' => 'Digestor Vertical', 'area' => 'EXT-01', 'category' => 'DIG', 'components' => ['Sistema de accionamiento', 'Cuerpo cilíndrico', 'Eje central giratorio', 'Brazos agitadores y barredores', 'Camisa de calentamiento e inyección de vapor', 'Tolva de alimentación', 'Control de nivel']],
        ['code' => 'A05EXT.05.01', 'name' => 'Prensa de Doble Tornillo', 'area' => 'EXT-01', 'category' => 'PRN', 'components' => ['Doble tornillo sinfín', 'Cámara de prensado', 'Sistema de contrapresión', 'Pantalon de alimentación', 'Sistema de accionamiento', 'Bancada y chasis']],
        ['code' => 'A05EXT.07.01', 'name' => 'Tamiz Circular Aceite de Prensas', 'area' => 'EXT-01', 'category' => 'TMZ', 'components' => ['Unidad de accionamiento', 'Bastidor o estructura', 'Mallas o telas de tamizado', 'Sistema de limpieza de la malla', 'Resortes de suspensión', 'Abrazadera de liberación rápida', 'Bocas o tolvas de descarga']],
        ['code' => 'A05EXT.05.02', 'name' => 'Unidad Hidráulica Prensa P15', 'area' => 'EXT-01', 'category' => 'UHD', 'components' => 'unidad_hidraulica'],

        // ── CLA-01 ──
        ['code' => 'A06CLA.03.01', 'name' => 'Bomba para Aceite del Preclarificador', 'area' => 'CLA-01', 'category' => 'BOM', 'components' => 'bomba_con_tanque'],
        ['code' => 'A06CLA.03.02', 'name' => 'Tanque Pulmón del Tricanter (Agitador)', 'area' => 'CLA-01', 'category' => 'TNQ', 'components' => ['Sistema de agitación', 'Cuerpo cilíndrico', 'Sistema de calentamiento', 'Deflectores', 'Ciclones de entrada']],
        ['code' => 'A06CLA.03.03', 'name' => 'Bomba de Lodos del Preclarificador', 'area' => 'CLA-01', 'category' => 'BOM', 'components' => 'bomba_con_tanque', 'notes' => self::FROM_DOWNTIME_LOG_NOTE],
        ['code' => 'A06CLA.05.01', 'name' => 'Bomba para Lodos Aceitosos', 'area' => 'CLA-01', 'category' => 'BOM', 'components' => 'bomba_con_tanque'],
        ['code' => 'A06CLA.07.01', 'name' => 'Desarenador #1', 'area' => 'CLA-01', 'category' => 'TNQ', 'components' => ['Cuerpo del desarenador', 'Electroválvula de purga', 'Manguera y línea de aire', 'Cono de sedimentación', 'Salida de rebose'], 'notes' => self::FROM_DOWNTIME_LOG_NOTE],
        ['code' => 'A06CLA.08.01', 'name' => 'Bomba Tanque de Lodos Desarenados Segunda Etapa', 'area' => 'CLA-01', 'category' => 'BOM', 'components' => 'bomba_con_tanque'],
        ['code' => 'A06CLA.08.02', 'name' => 'Tanque de Lodos Desarenados', 'area' => 'CLA-01', 'category' => 'TNQ', 'components' => ['Cuerpo cilíndrico', 'Piso y fondo del tanque', 'Línea de rebose', 'Válvula de purga', 'Indicador de nivel'], 'notes' => self::FROM_DOWNTIME_LOG_NOTE],
        ['code' => 'A06CLA.12.01', 'name' => 'Tricanter', 'area' => 'CLA-01', 'category' => 'CTF', 'components' => ['Tambor giratorio', 'Tornillo sinfín', 'Caja de engranajes', 'Puerto de descarga de líquidos', 'Sistema de accionamiento', 'Chasis y carcasa de protección']],
        ['code' => 'A06CLA.14.01', 'name' => 'Sinfín de Sólidos Paleables del Tricanter', 'area' => 'CLA-01', 'category' => 'SNF', 'components' => 'sinfin'],
        ['code' => 'A06CLA.22.01', 'name' => 'Bomba de Aceite a Sistema de Secado', 'area' => 'CLA-01', 'category' => 'BOM', 'components' => 'bomba_con_tanque'],
        ['code' => 'A06CLA.23.02', 'name' => 'Bomba de Vacío SIHI Halberg', 'model' => 'SIHI LPHX 55312 AB', 'area' => 'CLA-01', 'category' => 'BOM', 'components' => 'bomba_con_tanque'],
        ['code' => 'A06CLA.26.01', 'name' => 'Bomba de Aceite Terminado Seco a Almacenamiento', 'area' => 'CLA-01', 'category' => 'BOM', 'components' => 'bomba_con_tanque'],
        ['code' => 'A06CLA.30.01', 'name' => 'Bomba para Aceite Recuperado del Florentino', 'area' => 'CLA-01', 'category' => 'BOM', 'components' => 'bomba_con_tanque'],
        ['code' => 'A06CLA.30.02', 'name' => 'Tanque de Aceite Recuperado del Florentino', 'area' => 'CLA-01', 'category' => 'TNQ', 'components' => ['Cuerpo del tanque', 'Tapa del cebadero', 'Línea de efluentes', 'Válvula de purga', 'Indicador de nivel'], 'notes' => self::FROM_DOWNTIME_LOG_NOTE],
        ['code' => 'A06CLA.32.01', 'name' => 'Bomba para Efluentes del Florentino', 'model' => '2x1 1/4', 'area' => 'CLA-01', 'category' => 'BOM', 'components' => 'bomba_con_tanque'],
        ['code' => 'A06CLA.33.01', 'name' => 'Bomba para Purgas del Foso de la Clarificación', 'area' => 'CLA-01', 'category' => 'BOM', 'components' => 'bomba_con_tanque'],
        ['code' => 'A06CLA.33.02', 'name' => 'Bomba para el Despacho de Aceite Crudo', 'area' => 'CLA-01', 'category' => 'BOM', 'components' => 'bomba_con_tanque'],
        ['code' => 'A06CLA.34.01', 'name' => 'Bomba Fase Pesada Decantador (Tricanter)', 'area' => 'CLA-01', 'category' => 'BOM', 'components' => 'bomba_con_tanque'],
        ['code' => 'A06CLA.34.02', 'name' => 'Motor Filtro Cepillo', 'area' => 'CLA-01', 'category' => 'FLT', 'components' => ['Malla cilíndrica', 'Sistema de cepillos giratorios', 'Sistema motriz', 'Carcasa o tanque contenedor', 'Boquillas o sistema de lavado', 'Válvulas de descarga']],
        ['code' => 'A06CLA.34.03', 'name' => 'Bomba de Aceite Recuperado Centrífuga Alfa Laval', 'area' => 'CLA-01', 'category' => 'BOM', 'components' => 'bomba_con_tanque'],
        ['code' => 'A06CLA.34.04', 'name' => 'Centrífuga Alfa Laval', 'area' => 'CLA-01', 'category' => 'CTF', 'components' => ['Tambor', 'Pila de discos', 'Cilindro y tornillo transportador', 'Sistema hidráulico', 'Estructura del equipo', 'Sistema de accionamiento']],

        // ── PAL-01 ──
        ['code' => 'A07DEP.01.01', 'name' => 'Transportador de Torta', 'area' => 'PAL-01', 'category' => 'TRP', 'components' => ['Canoa', 'Eje central giratorio', 'Paletas ajustables', 'Revestimientos de desgaste', 'Sistema motriz', 'Ductos de entrada y salida']],
        ['code' => 'A07DEP.03.01', 'name' => 'Tambor Pulidor Sin Eje', 'area' => 'PAL-01', 'category' => 'TMB', 'components' => ['Cilindro principal', 'Secciones de malla', 'Hélice o paletas internas', 'Sistema de transmisión', 'Pistas y anillos de rodadura', 'Ducto de descarga', 'Estructura de soporte']],
        ['code' => 'A08KRS.01.01', 'name' => 'Sinfín de Nueces Pulidas Hacia Sinfín', 'area' => 'PAL-01', 'category' => 'SNF', 'components' => 'sinfin'],
        ['code' => 'A08KRS.01.05', 'name' => 'Sinfín de Nueces a Elevador', 'area' => 'PAL-01', 'category' => 'SNF', 'components' => 'sinfin'],
        ['code' => 'A08KRS.02.01', 'name' => 'Elevador de Nueces', 'area' => 'PAL-01', 'category' => 'ELV', 'components' => 'elevador'],
        ['code' => 'A08KRS.20.02', 'name' => 'Parrilla Silo de Almendras', 'area' => 'PAL-01', 'category' => 'SIL', 'components' => ['Parrilla de recepción', 'Ducto de alimentación', 'Silo de secado', 'Sistema de calentamiento', 'Compuerta o válvula de descarga']],
        ['code' => 'A08KRS.11.01', 'name' => 'Molino Tipo Ripple Mill', 'area' => 'PAL-01', 'category' => 'MOL', 'components' => ['Rotor', 'Barras del rotor', 'Mordaza', 'Carcasa y placas laterales', 'Sistema de transmisión', 'Ducto de alimentación']],
        ['code' => 'A08KRS.13.01', 'name' => 'Esclusa Mezcla Triturada', 'area' => 'PAL-01', 'category' => 'ESL', 'components' => 'esclusa_neumatica'],
        ['code' => 'A08KRS.15.01', 'name' => 'Esclusa de la Interfase', 'area' => 'PAL-01', 'category' => 'ESL', 'components' => 'esclusa_neumatica'],
        ['code' => 'A08KRS.17.01', 'name' => 'Hidrociclón de Almendras y Cáscaras', 'area' => 'PAL-01', 'category' => 'HDC', 'components' => ['Sistema de impulsión y alimentación', 'Estructura y componentes del hidrociclón', 'Sistema de descarga y recirculación']],
        ['code' => 'A08KRS.19.01', 'name' => 'Sinfín de Almendras a Elevador', 'area' => 'PAL-01', 'category' => 'SNF', 'components' => 'sinfin'],
        ['code' => 'A08KRS.29.01', 'name' => 'Esclusa del Sistema Neumático de Cáscaras Húmedas', 'area' => 'PAL-01', 'category' => 'ESL', 'components' => 'esclusa_neumatica'],
        ['code' => 'A08KRS.29.02', 'name' => 'Ventilador del Sistema Neumático de Cáscaras Húmedas', 'area' => 'PAL-01', 'category' => 'VNT', 'components' => 'ventilador_neumatico'],
        ['code' => 'A08KRS.20.01', 'name' => 'Elevador de Almendras', 'area' => 'PAL-01', 'category' => 'ELV', 'components' => 'elevador'],

        // ── DFB-01 ──
        ['code' => 'A08KRS.21.01', 'name' => 'Ventilador del Silo de Almendras', 'area' => 'DFB-01', 'category' => 'VNT', 'components' => ['Ventilador', 'Motor eléctrico', 'Radiadores o intercambiadores de calor', 'Sistema de inyección y ductos', 'Extractor de techo', 'Compuertas o dampers', 'Trampas de vapor']],
        ['code' => 'A08KRS.22.01', 'name' => 'Sinfín Bajo Silo de Almendras', 'area' => 'DFB-01', 'category' => 'SNF', 'components' => 'sinfin'],
        ['code' => 'A10SPG.01.02', 'name' => 'Ventilador del Sistema Neumático de Desfibración', 'area' => 'DFB-01', 'category' => 'VNT', 'components' => 'ventilador_neumatico'],
        ['code' => 'A10SPG.01.03', 'name' => 'Esclusa del Sistema Neumático de Desfibración', 'area' => 'DFB-01', 'category' => 'ESL', 'components' => 'esclusa_neumatica'],
        ['code' => 'A10SPG.02.02', 'name' => 'Ventilador del Sistema Neumático de Cáscaras y Finos', 'area' => 'DFB-01', 'category' => 'VNT', 'components' => 'ventilador_neumatico'],
        ['code' => 'A10SPG.02.05', 'name' => 'Esclusa del Sistema Neumático de Cáscaras y Finos', 'area' => 'DFB-01', 'category' => 'ESL', 'components' => 'esclusa_neumatica'],

        // ── COG-01 ──
        ['code' => 'A10SPG.04.01', 'name' => 'Redler #3 para Combustible', 'area' => 'COG-01', 'category' => 'RDL', 'components' => 'redler'],
        ['code' => 'A10SPG.05.01', 'name' => 'Redler #4 para Combustible a Caldera', 'area' => 'COG-01', 'category' => 'RDL', 'components' => 'redler'],
        ['code' => 'A10SPG.11.01', 'name' => 'Redler #5 para el Retorno de Combustible', 'area' => 'COG-01', 'category' => 'RDL', 'components' => 'redler'],
        ['code' => 'A10SPG.17.01', 'name' => 'Esclusa para Ceniza #1', 'area' => 'COG-01', 'category' => 'ESL', 'components' => 'esclusa_ceniza'],
        ['code' => 'A10SPG.17.02', 'name' => 'Esclusa para Ceniza #2', 'area' => 'COG-01', 'category' => 'ESL', 'components' => 'esclusa_ceniza'],
        ['code' => 'A10SPG.17.03', 'name' => 'Esclusa para Ceniza #3', 'area' => 'COG-01', 'category' => 'ESL', 'components' => 'esclusa_ceniza'],
        ['code' => 'A10SPG.17.04', 'name' => 'Esclusa para Ceniza #4', 'area' => 'COG-01', 'category' => 'ESL', 'components' => 'esclusa_ceniza'],
        ['code' => 'A10SPG.18.01', 'name' => 'Sinfín N° 1 para Cenizas', 'area' => 'COG-01', 'category' => 'SNF', 'components' => 'sinfin'],
        ['code' => 'A10SPG.20.01', 'name' => 'Sinfín N° 2 para Cenizas', 'area' => 'COG-01', 'category' => 'SNF', 'components' => 'sinfin'],
        ['code' => 'A10SPG.20.02', 'name' => 'Sinfín N° 3 para Cenizas', 'area' => 'COG-01', 'category' => 'SNF', 'components' => 'sinfin'],
        ['code' => 'A10SPG.18.02', 'name' => 'Sinfín N° 4 bajo ESP para Cenizas', 'area' => 'COG-01', 'category' => 'SNF', 'components' => 'sinfin'],
        ['code' => 'A10SPG.20.03', 'name' => 'Sinfín N° 5 de Cenizas', 'area' => 'COG-01', 'category' => 'SNF', 'components' => 'sinfin'],
        ['code' => 'A10SPG.20.04', 'name' => 'Sinfín N° 6 de Cenizas', 'area' => 'COG-01', 'category' => 'SNF', 'components' => 'sinfin'],
        ['code' => 'A10SPG.20.05', 'name' => 'Sinfín N° 7 de Cenizas', 'area' => 'COG-01', 'category' => 'SNF', 'components' => 'sinfin'],
        ['code' => 'A10SPG.20.06', 'name' => 'Sinfín N° 8 de Cenizas', 'area' => 'COG-01', 'category' => 'SNF', 'components' => 'sinfin'],
        ['code' => 'A10SPG.26.01', 'name' => 'Planta Eléctrica de 1250 kVA', 'area' => 'COG-01', 'category' => 'GEN', 'components' => 'planta_electrica'],
        ['code' => 'A10SPG.26.02', 'name' => 'Planta Eléctrica de 72 kVA', 'area' => 'COG-01', 'category' => 'GEN', 'components' => 'planta_electrica'],
        ['code' => 'A10SPG.26.03', 'name' => 'Turbina Shinko RB-4 950 KVA', 'model' => 'Shinko RB-4', 'area' => 'COG-01', 'category' => 'GEN', 'components' => ['Rotor y álabes', 'Carcasa y estator', 'Válvula de alivio', 'Cojinetes y sistema de lubricación', 'Regulador de velocidad', 'Generador acoplado'], 'notes' => self::FROM_DOWNTIME_LOG_NOTE],
        ['code' => 'A10SPG.26.04', 'name' => 'Tablero CCM', 'area' => 'COG-01', 'category' => 'TEL', 'components' => ['Interruptor general', 'Arrancadores y contactores', 'Relés térmicos', 'PLC y módulos de E/S', 'Variadores de velocidad', 'Barraje y cableado de potencia'], 'notes' => self::FROM_DOWNTIME_LOG_NOTE],
        ['code' => 'A10SPG.27.01', 'name' => 'Compresor de Aire', 'model' => 'FSN', 'area' => 'COG-01', 'category' => 'CMP', 'components' => 'compresor'],
        ['code' => 'A10SPG.28.01', 'name' => 'Bomba de Saturación de Agua del Distribuidor de Vapor', 'area' => 'COG-01', 'category' => 'BOM', 'components' => 'bomba_con_tanque', 'notes' => self::FROM_DOWNTIME_LOG_NOTE],
        ['code' => 'A19CMP.01.01', 'name' => 'Banda #3 Raquis Prensado / Sinfín Lodos Tricanter', 'area' => 'COG-01', 'category' => 'BND', 'components' => 'banda'],
        ['code' => 'A19CMP.06.01', 'name' => 'Banda #5 Subproductos a Tolva / Bomba Distribuidor de Vapor', 'area' => 'COG-01', 'category' => 'BND', 'components' => 'banda'],
        ['code' => 'A10SPG.13.02', 'name' => 'Caldera Inducido #1', 'area' => 'COG-01', 'category' => 'VNT', 'components' => 'ventilador_caldera'],
        ['code' => 'A10SPG.13.03', 'name' => 'Caldera Inducido #2', 'area' => 'COG-01', 'category' => 'VNT', 'components' => 'ventilador_caldera'],
        ['code' => 'A10SPG.13.04', 'name' => 'Caldera Ventilador Forzado', 'area' => 'COG-01', 'category' => 'VNT', 'components' => 'ventilador_caldera'],
        ['code' => 'A10SPG.13.05', 'name' => 'Caldera Ventilador Secundario', 'area' => 'COG-01', 'category' => 'VNT', 'components' => 'ventilador_caldera'],
        ['code' => 'A10SPG.13.06', 'name' => 'Caldera Ventilador Alimentador', 'area' => 'COG-01', 'category' => 'VNT', 'components' => 'ventilador_caldera'],
        ['code' => 'A10SPG.13.07', 'name' => 'Caldera Sinfín Alimentador', 'area' => 'COG-01', 'category' => 'SNF', 'components' => 'sinfin'],
        ['code' => 'A10SPG.13.08', 'name' => 'Caldera Compresor', 'area' => 'COG-01', 'category' => 'CMP', 'components' => 'compresor'],
        ['code' => 'A10SPG.13.09', 'name' => 'Caldera Bomba Agua #1', 'area' => 'COG-01', 'category' => 'BOM', 'components' => 'bomba_multietapa'],
        ['code' => 'A10SPG.13.10', 'name' => 'Caldera Bomba Agua #2', 'area' => 'COG-01', 'category' => 'BOM', 'components' => 'bomba_multietapa'],
        ['code' => 'A10SPG.13.11', 'name' => 'Caldera Bomba Desaireador #1', 'area' => 'COG-01', 'category' => 'BOM', 'components' => 'bomba_multietapa'],
        ['code' => 'A10SPG.13.12', 'name' => 'Caldera Bomba Desaireador #2', 'area' => 'COG-01', 'category' => 'BOM', 'components' => 'bomba_multietapa'],
        ['code' => 'A10SPG.13.13', 'name' => 'Caldera Bomba Dosificadora Eléctrica #1', 'area' => 'COG-01', 'category' => 'BOM', 'components' => 'bomba_dosificadora'],
        ['code' => 'A10SPG.13.14', 'name' => 'Caldera Bomba Dosificadora Eléctrica #2', 'area' => 'COG-01', 'category' => 'BOM', 'components' => 'bomba_dosificadora'],
        ['code' => 'A10SPG.13.15', 'name' => 'Caldera Redler Ceniza Húmeda', 'area' => 'COG-01', 'category' => 'RDL', 'components' => 'redler'],
        ['code' => 'A10SPG.13.16', 'name' => 'Caldera Refrigeración Parrilla', 'area' => 'COG-01', 'category' => 'REF', 'components' => ['Bomba de recirculación', 'Intercambiador de calor', 'Tuberías y colectores', 'Tanque de expansión', 'Instrumentación y control'], 'notes' => self::UNVERIFIED_COMPONENTS_NOTE],
        ['code' => 'A10SPG.13.17', 'name' => 'Caldera Bomba Hidráulica #1', 'area' => 'COG-01', 'category' => 'UHD', 'components' => 'unidad_hidraulica', 'notes' => self::UNVERIFIED_COMPONENTS_NOTE],
        ['code' => 'A10SPG.13.18', 'name' => 'Caldera Bomba Hidráulica #2', 'area' => 'COG-01', 'category' => 'UHD', 'components' => 'unidad_hidraulica', 'notes' => self::UNVERIFIED_COMPONENTS_NOTE],
        ['code' => 'A10SPG.13.19', 'name' => 'Caldera Unidad Hidráulica A4', 'area' => 'COG-01', 'category' => 'UHD', 'components' => 'unidad_hidraulica', 'notes' => self::UNVERIFIED_COMPONENTS_NOTE],
        ['code' => 'A10SPG.13.20', 'name' => 'Caldera Ciclón A3', 'area' => 'COG-01', 'category' => 'CIC', 'components' => ['Cuerpo cilíndrico', 'Cono de descarga', 'Entrada tangencial', 'Buscador de vórtice', 'Válvula rotativa de descarga'], 'notes' => self::UNVERIFIED_COMPONENTS_NOTE],
    ];

    /**
     * Puebla el inventario del tenant. Es aditivo e idempotente: nunca borra ni
     * renombra lo que ya exista, así que es seguro re-ejecutarlo sobre una planta
     * en producción que ya haya editado sus equipos.
     */
    public function run(Tenant $tenant, Plant $plant): void
    {
        $areas = Area::withoutGlobalScopes()
            ->where('plant_id', $plant->id)
            ->pluck('id', 'code');

        $categories = $this->seedCategories($tenant);

        foreach (self::EQUIPMENT as $row) {
            $areaId = $areas[$row['area']] ?? null;

            if ($areaId === null) {
                throw new RuntimeException("El área {$row['area']} no existe en la planta {$plant->code}; ProvisionTenantBaseStructure debe crearla antes de sembrar el inventario.");
            }

            $equipment = Equipment::withoutGlobalScopes()->firstOrCreate(
                ['tenant_id' => $tenant->id, 'code' => $row['code']],
                [
                    'plant_id' => $plant->id,
                    'area_id' => $areaId,
                    'category_id' => $categories[$row['category']],
                    'name' => $row['name'],
                    'model' => $row['model'] ?? null,
                    'status' => EquipmentStatus::Active->value,
                    'notes' => $row['notes'] ?? null,
                    'is_active' => true,
                ],
            );

            $this->seedComponents($tenant, $equipment, $row['components']);
        }
    }

    /**
     * Catálogo de tipos de equipo. Es lo que explica que los componentes se
     * repitan: catorce plantillas cubren setenta y dos de los noventa y nueve.
     *
     * @return array<string, string> código de categoría → id
     */
    private function seedCategories(Tenant $tenant): array
    {
        $ids = [];

        foreach (self::CATEGORIES as $index => [$code, $name]) {
            $ids[$code] = EquipmentCategory::withoutGlobalScopes()->firstOrCreate(
                ['tenant_id' => $tenant->id, 'code' => $code],
                [
                    'name' => $name,
                    'sort_order' => ($index + 1) * 10,
                    'is_active' => true,
                ],
            )->id;
        }

        return $ids;
    }

    /**
     * @param  string|list<string>  $components  Nombre de plantilla o lista explícita.
     */
    private function seedComponents(Tenant $tenant, Equipment $equipment, string|array $components): void
    {
        $names = is_string($components)
            ? self::COMPONENT_TEMPLATES[$components]
            : $components;

        foreach ($names as $name) {
            // La clave de idempotencia es el nombre: los componentes no traen
            // código propio del inventario, y unique(equipment_id, code) no
            // protege nada mientras code sea NULL.
            EquipmentComponent::withoutGlobalScopes()->firstOrCreate(
                ['equipment_id' => $equipment->id, 'name' => $name],
                ['tenant_id' => $tenant->id],
            );
        }
    }
}
