<x-filament-panels::page>
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Empresas</p>
            <p class="mt-2 text-3xl font-bold text-gray-900 dark:text-white">{{ $totalTenants }}</p>
        </div>
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-6 shadow-sm dark:border-emerald-700 dark:bg-emerald-900/20">
            <p class="text-sm font-medium text-emerald-700 dark:text-emerald-400">Activas</p>
            <p class="mt-2 text-3xl font-bold text-emerald-800 dark:text-emerald-300">{{ $activeTenants }}</p>
        </div>
        <div class="rounded-xl border border-blue-200 bg-blue-50 p-6 shadow-sm dark:border-blue-700 dark:bg-blue-900/20">
            <p class="text-sm font-medium text-blue-700 dark:text-blue-400">En Prueba</p>
            <p class="mt-2 text-3xl font-bold text-blue-800 dark:text-blue-300">{{ $trialTenants }}</p>
        </div>
        <div class="rounded-xl border border-red-200 bg-red-50 p-6 shadow-sm dark:border-red-700 dark:bg-red-900/20">
            <p class="text-sm font-medium text-red-700 dark:text-red-400">Suspendidas</p>
            <p class="mt-2 text-3xl font-bold text-red-800 dark:text-red-300">{{ $suspendedTenants }}</p>
        </div>
    </div>
</x-filament-panels::page>
