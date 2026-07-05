@php
    $statePath = $getStatePath();
    $fieldWrapperView = $getFieldWrapperView();
@endphp

<x-dynamic-component :component="$fieldWrapperView" :field="$field">
    <div
        wire:ignore
        x-data="{
            state: $wire.$entangle('{{ $statePath }}'),
            drawing: false,
            ctx: null,
            hasStroke: false,
            init() {
                const canvas = this.$refs.canvas;
                const ratio = window.devicePixelRatio || 1;
                canvas.width = canvas.offsetWidth * ratio;
                canvas.height = canvas.offsetHeight * ratio;
                this.ctx = canvas.getContext('2d');
                this.ctx.scale(ratio, ratio);
                this.ctx.lineWidth = 2;
                this.ctx.lineCap = 'round';
                this.ctx.strokeStyle = '#1f2937';
                if (this.state) { this.hasStroke = true; }
            },
            pos(e) {
                const canvas = this.$refs.canvas;
                const rect = canvas.getBoundingClientRect();
                const point = e.touches ? e.touches[0] : e;
                return { x: point.clientX - rect.left, y: point.clientY - rect.top };
            },
            start(e) {
                e.preventDefault();
                this.drawing = true;
                const { x, y } = this.pos(e);
                this.ctx.beginPath();
                this.ctx.moveTo(x, y);
            },
            move(e) {
                if (! this.drawing) return;
                e.preventDefault();
                const { x, y } = this.pos(e);
                this.ctx.lineTo(x, y);
                this.ctx.stroke();
                this.hasStroke = true;
            },
            end() {
                if (! this.drawing) return;
                this.drawing = false;
                this.state = this.$refs.canvas.toDataURL('image/png');
            },
            clear() {
                const canvas = this.$refs.canvas;
                this.ctx.clearRect(0, 0, canvas.width, canvas.height);
                this.hasStroke = false;
                this.state = null;
            },
        }"
        x-init="init()"
        class="space-y-2"
    >
        <div class="relative rounded-lg border border-gray-300 bg-white dark:border-gray-600">
            <canvas
                x-ref="canvas"
                class="h-40 w-full touch-none"
                x-on:mousedown="start($event)"
                x-on:mousemove="move($event)"
                x-on:mouseup="end()"
                x-on:mouseleave="end()"
                x-on:touchstart="start($event)"
                x-on:touchmove="move($event)"
                x-on:touchend="end()"
            ></canvas>
            <p
                x-show="! hasStroke"
                x-cloak
                class="pointer-events-none absolute inset-0 flex items-center justify-center text-sm text-gray-400"
            >
                Firma aquí con el dedo o el mouse
            </p>
        </div>

        <button
            type="button"
            x-on:click="clear()"
            class="text-xs font-medium text-gray-500 hover:text-gray-700 dark:hover:text-gray-300"
        >
            Limpiar firma
        </button>
    </div>
</x-dynamic-component>
