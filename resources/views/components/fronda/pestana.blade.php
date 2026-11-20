{{-- Un bloque de <x-fronda.pestanas>: se ve solo cuando su pestaña está elegida. --}}
@props(['nombre'])

<div
    x-show="ver === {{ \Illuminate\Support\Js::from($nombre) }}"
    x-cloak
    data-pestana="{{ $nombre }}"
    {{ $attributes->class(['space-y-6']) }}
>
    {{ $slot }}
</div>
