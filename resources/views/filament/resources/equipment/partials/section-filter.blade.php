{{--
    Selector de Sección siempre visible en la barra de la tabla de Equipos.

    Se enlaza directamente al estado del SelectFilter `area_id` de la tabla, así
    que elegir una sección aplica el filtro al instante (sin abrir el panel de
    filtros ni pulsar «Aplicar»). Réplica del selector de secciones de
    Horómetros → Control de Mantenimiento.
--}}
<select
    wire:model.live="tableFilters.area_id.value"
    aria-label="Filtrar por sección"
    class="rounded-lg border-gray-300 bg-white py-1.5 pr-8 pl-3 text-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-white/10 dark:bg-white/5 dark:text-white"
>
    <option value="">Todas las secciones</option>
    @foreach ($areaOptions as $areaId => $areaName)
        <option value="{{ $areaId }}">{{ $areaName }}</option>
    @endforeach
</select>
