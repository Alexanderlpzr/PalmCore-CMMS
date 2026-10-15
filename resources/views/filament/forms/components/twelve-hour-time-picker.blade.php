@php
    $statePath = $getStatePath();
    $isDisabled = $isDisabled();
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div
        x-data="{
            state: $wire.{{ $applyStateBindingModifiers("\$entangle('{$statePath}')") }},
            hour: '',
            minute: '',
            period: 'am',
            init() {
                this.read(this.state)
                this.$watch('state', (value) => this.read(value))
            },
            {{-- «18:05» → 6, 05, p. m. --}}
            read(value) {
                if (! value) return
                const [h, m] = String(value).split(':')
                const hours = parseInt(h, 10)
                if (Number.isNaN(hours)) return
                this.period = hours >= 12 ? 'pm' : 'am'
                this.hour = String(hours % 12 === 0 ? 12 : hours % 12)
                this.minute = String(m ?? '00').padStart(2, '0').slice(0, 2)
            },
            {{-- 6, 05, p. m. → «18:05». --}}
            write() {
                if (this.hour === '' || this.minute === '') return
                const hours = (parseInt(this.hour, 10) % 12) + (this.period === 'pm' ? 12 : 0)
                const value = String(hours).padStart(2, '0') + ':' + this.minute
                if (value !== this.state) this.state = value
            },
        }"
        class="flex items-center gap-2"
    >
        <x-filament::input.wrapper class="w-20" :disabled="$isDisabled">
            <x-filament::input.select x-model="hour" x-on:change="write()" :disabled="$isDisabled" aria-label="Hora">
                <option value="" disabled>Hora</option>
                @foreach (range(1, 12) as $h)
                    <option value="{{ $h }}">{{ $h }}</option>
                @endforeach
            </x-filament::input.select>
        </x-filament::input.wrapper>

        <span class="text-gray-500">:</span>

        <x-filament::input.wrapper class="w-20" :disabled="$isDisabled">
            <x-filament::input.select x-model="minute" x-on:change="write()" :disabled="$isDisabled" aria-label="Minutos">
                <option value="" disabled>Min</option>
                @foreach (range(0, 59) as $m)
                    <option value="{{ str_pad((string) $m, 2, '0', STR_PAD_LEFT) }}">{{ str_pad((string) $m, 2, '0', STR_PAD_LEFT) }}</option>
                @endforeach
            </x-filament::input.select>
        </x-filament::input.wrapper>

        <x-filament::input.wrapper class="w-24" :disabled="$isDisabled">
            <x-filament::input.select x-model="period" x-on:change="write()" :disabled="$isDisabled" aria-label="a. m. o p. m.">
                <option value="am">a. m.</option>
                <option value="pm">p. m.</option>
            </x-filament::input.select>
        </x-filament::input.wrapper>
    </div>
</x-dynamic-component>
