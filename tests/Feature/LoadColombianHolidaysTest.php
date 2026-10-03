<?php

use App\Console\Commands\LoadColombianHolidays;
use App\Models\Holiday;
use App\Models\Tenant;
use Illuminate\Support\Carbon;

/*
 * El calendario de festivos que liquida los recargos dominicales. Las fechas van
 * escritas en el comando; estas pruebas cuidan que la lista siga las reglas de la Ley
 * Emiliani y que cargarla no estropee lo que ya hay.
 */

it('loads the eighteen holidays of the year into the company', function (): void {
    $tenant = Tenant::factory()->create();

    $this->artisan('hr:load-holidays', ['year' => 2026, '--tenant' => $tenant->slug])->assertSuccessful();

    expect(Holiday::query()->forTenant($tenant->id)->count())->toBe(18)
        ->and(Holiday::query()->forTenant($tenant->id)->whereDate('holiday_date', '2026-08-17')->value('name'))
        ->toBe('Asunción de la Virgen');
});

it('can run twice without duplicating, and keeps the holidays of the company', function (): void {
    $tenant = Tenant::factory()->create();
    Holiday::factory()->on('2026-09-15', 'Fiesta patronal')->create(['tenant_id' => $tenant->id]);

    $this->artisan('hr:load-holidays', ['year' => 2026, '--tenant' => $tenant->slug])->assertSuccessful();
    $this->artisan('hr:load-holidays', ['year' => 2026, '--tenant' => $tenant->slug])->assertSuccessful();

    expect(Holiday::query()->forTenant($tenant->id)->count())->toBe(19);
});

it('refuses a year whose calendar nobody has written yet', function (): void {
    Tenant::factory()->create();

    $this->artisan('hr:load-holidays', ['year' => 2031])->assertFailed();
});

it('keeps every written calendar faithful to the Emiliani law', function (int $year, array $calendar): void {
    expect($calendar)->toHaveCount(18);

    // Los corridos al lunes, en lunes; los fijos, en su fecha.
    $movedToMonday = ['Reyes Magos', 'San José', 'San Pedro y San Pablo', 'Asunción de la Virgen', 'Día de la Raza',
        'Todos los Santos', 'Independencia de Cartagena', 'Ascensión del Señor', 'Corpus Christi', 'Sagrado Corazón'];

    foreach ($calendar as $date => $name) {
        $day = Carbon::parse($date);

        expect($day->year)->toBe($year);

        if (in_array($name, $movedToMonday, true)) {
            expect($day->isMonday())->toBeTrue("{$name} de {$year} debería caer en lunes");
        }
    }

    foreach (['01-01', '05-01', '07-20', '08-07', '12-08', '12-25'] as $fixed) {
        expect($calendar)->toHaveKey("{$year}-{$fixed}");
    }
})->with(fn (): array => collect(LoadColombianHolidays::CALENDARS)
    ->map(fn (array $calendar, int $year): array => [$year, $calendar])
    ->all());
