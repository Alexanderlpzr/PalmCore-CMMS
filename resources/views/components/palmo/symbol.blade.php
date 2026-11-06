@props(['tone' => 'light'])

{{--
    Símbolo de Palmo: un fruto de palma maduro con su hoja. «light» para fondo
    claro; «dark» aclara hojas y fruto para fondo oscuro. Decorativo: el nombre
    va en el texto que lo acompaña.
--}}
@php
    $dark = $tone === 'dark';
@endphp
<svg viewBox="0 0 120 120" {{ $attributes->merge(['aria-hidden' => 'true', 'focusable' => 'false']) }}>
    <path d="M61 30 C 70 10, 94 4, 112 10 C 96 16, 80 22, 66 36 Z" fill="{{ $dark ? '#45A963' : '#2F8F4E' }}"/>
    <path d="M57 31 C 50 15, 32 9, 14 13 C 29 19, 43 25, 52 37 Z" fill="{{ $dark ? '#7BC58F' : '#45A963' }}"/>
    <ellipse cx="60" cy="74" rx="34" ry="40" fill="#E8622C"/>
    <path d="M26 70 C 28 44, 44 34, 60 34 C 77 34, 92 45, 94 66 C 79 57, 42 57, 26 70 Z" fill="{{ $dark ? '#7A2A22' : '#5A1F1B' }}"/>
    <text x="60" y="103" text-anchor="middle" font-family="'Baloo 2', system-ui, sans-serif" font-weight="800" font-size="40" fill="#FBF6EC">$</text>
</svg>
