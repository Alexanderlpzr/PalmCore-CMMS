<?php

namespace App\Filament\Pages;

use App\Domain\HumanResources\Enums\AttendanceDirection;
use App\Domain\HumanResources\Exceptions\AttendanceException;
use App\Domain\HumanResources\Services\AttendanceService;
use App\Models\AttendanceScan;
use App\Models\Tenant;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Vite;

/**
 * La puerta desde el panel: escanear el carné y ver quién marcó.
 *
 * Es la misma marca que hace la app móvil, para la portería que trabaja con un
 * computador o una tableta. La cámara lee el carné; un lector USB, que escribe el código
 * y da Enter, va en el campo de abajo, que queda con el cursor listo. Entrada o salida lo
 * decide el sistema, como en la app: el vigilante no escoge nada.
 */
class Porteria extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQrCode;

    protected static ?string $navigationLabel = 'Portería';

    protected static ?string $title = 'Portería';

    /** Arriba del todo: para el vigilante es la única pantalla del día. */
    protected static ?int $navigationSort = -90;

    protected static ?string $slug = 'porteria';

    protected string $view = 'filament.pages.porteria';

    private const SCANNER_ENTRY = 'resources/js/filament/porteria-scanner.js';

    /** La última marca, para mostrarla grande encima de la cámara. */
    public ?array $ultimo = null;

    public ?string $error = null;

    /**
     * La última marca buena, para la franja de abajo: un error en el siguiente carné no
     * borra la confirmación del anterior.
     */
    public ?array $ultimaBuena = null;

    /**
     * Cuenta cada intento de marcar. El aviso encima de la cámara se reinicia con cada
     * número, así que dos marcas seguidas de la misma persona igual se ven las dos.
     */
    public int $intento = 0;

    /** El campo del lector USB o del token escrito a mano. */
    public string $token = '';

    public static function canAccess(): bool
    {
        return auth()->user()?->can('create', AttendanceScan::class) ?? false;
    }

    public function registrarMarca(string $token): void
    {
        abort_unless(static::canAccess(), 403);

        $this->error = null;
        $token = trim($token);

        if (! preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $token)) {
            $this->falla('Ese no es el código de un carné.');

            return;
        }

        $service = app(AttendanceService::class);

        try {
            $qrCode = $service->resolveToken($token, $this->tenant()->id);
        } catch (AttendanceException $e) {
            $this->falla($e->getMessage());

            return;
        }

        $scan = $service->record($qrCode, recordedBy: auth()->id(), gate: 'Portería (panel)');
        $employee = $qrCode->employee;
        // Dentro de la pausa el servicio devuelve la marca anterior en lugar de crear otra.
        $repetido = ! $scan->wasRecentlyCreated;

        $this->ultimo = [
            'nombre' => $employee->fullName(),
            'documento' => $employee->document_number,
            'cargo' => $employee->position,
            'entrada' => $scan->direction === AttendanceDirection::Entrada,
            'sentido' => $scan->direction->label(),
            'hora' => $scan->scanned_at->copy()->setTimezone($this->timezone())->format('h:i a'),
            'aviso' => $repetido
                ? 'No se marcó de nuevo. Podrá volver a marcar desde las '
                    .$scan->scanned_at->copy()->addSeconds(AttendanceService::DEBOUNCE_SECONDS)->setTimezone($this->timezone())->format('h:i a').'.'
                : $scan->notes,
            'repetido' => $repetido,
        ];

        $this->ultimaBuena = $this->ultimo;
        $this->intento++;
        // Pitido corto si marcó; el largo con vibración si fue un pase repetido que no marcó.
        $this->dispatch('porteria-marca', ok: ! $repetido);
    }

    /** Un intento que no marcó: el aviso rojo encima de la cámara, y pitido largo con vibración. */
    private function falla(string $mensaje): void
    {
        $this->ultimo = null;
        $this->error = $mensaje;
        $this->intento++;
        $this->dispatch('porteria-marca', ok: false);
    }

    /** El lector USB escribe el código y da Enter; a mano es lo mismo. */
    public function marcarDelCampo(): void
    {
        $this->registrarMarca($this->token);
        $this->token = '';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $timezone = $this->timezone();
        $hoy = now($timezone);

        $marcas = AttendanceScan::query()
            ->forTenant($this->tenant()->id)
            ->whereBetween('scanned_at', [$hoy->copy()->startOfDay()->utc(), $hoy->copy()->endOfDay()->utc()])
            ->with('employee:id,first_name,last_name,document_number')
            ->orderByDesc('scanned_at')
            ->limit(150)
            ->get();

        return [
            'marcas' => $marcas,
            'adentro' => $this->peopleInside(),
            'timezone' => $timezone,
            'scriptUrl' => $this->scannerScriptUrl(),
        ];
    }

    /**
     * Quiénes están adentro: su última marca de las últimas 16 horas es una entrada.
     * Pasado ese lapso, una entrada sin salida ya no cuenta como turno abierto.
     */
    private function peopleInside(): int
    {
        return AttendanceScan::query()
            ->forTenant($this->tenant()->id)
            ->where('scanned_at', '>=', now()->subHours(AttendanceService::MAX_OPEN_SHIFT_HOURS))
            ->orderBy('scanned_at')
            ->get(['employee_id', 'direction'])
            ->keyBy('employee_id')
            ->filter(fn (AttendanceScan $scan): bool => $scan->direction === AttendanceDirection::Entrada)
            ->count();
    }

    /** Sin el manifiesto de Vite —en pruebas— la pantalla funciona igual, sin cámara. */
    private function scannerScriptUrl(): ?string
    {
        $manifest = public_path('build/manifest.json');

        if (! is_file($manifest)) {
            return null;
        }

        $entries = json_decode((string) file_get_contents($manifest), true);

        return is_array($entries) && array_key_exists(self::SCANNER_ENTRY, $entries)
            ? Vite::asset(self::SCANNER_ENTRY)
            : null;
    }

    private function timezone(): string
    {
        return $this->tenant()->plantTimezone();
    }

    private function tenant(): Tenant
    {
        /** @var Tenant */
        return Filament::getTenant();
    }
}
