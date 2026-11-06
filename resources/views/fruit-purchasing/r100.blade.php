@extends('layouts.public')

@section('title', 'Palmo — ¿Cuánto vale su fruta?')

{{-- Marca Palmo y vista previa cuando el enlace se comparte por WhatsApp o Facebook. --}}
@push('head')
    @include('fruit-purchasing.partials.palmo-theme')
    <meta name="description" content="Palmo: calcule la TEA y el valor de su viaje de fruta de palma con el Método R-100 de la Extractora El Pajuil.">
    <meta property="og:type" content="website">
    <meta property="og:locale" content="es_CO">
    <meta property="og:site_name" content="Palmo">
    <meta property="og:title" content="Palmo — ¿Cuánto vale su fruta?">
    <meta property="og:description" content="Mida lo que vale su fruta: la TEA y el valor de su viaje, con el Método R-100 de la Extractora El Pajuil.">
    <meta property="og:url" content="{{ route('r100.calculator') }}">
    <meta property="og:image" content="{{ asset('images/palmo/compartir.jpg') }}">
    <meta property="og:image:type" content="image/jpeg">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:alt" content="Palmo, con el Método R-100 de la Extractora El Pajuil">
    <meta name="twitter:card" content="summary_large_image">
@endpush

@section('content')
    @include('fruit-purchasing.partials.number-format-script')
    <div class="palmo min-h-screen">
        @livewire('fruit-purchasing.tea-calculator')
        @livewire('fruit-purchasing.interested-signup')

        {{-- pb-36 en el celular: el pie queda a la vista sobre la barra fija del resultado. --}}
        <footer class="mx-auto max-w-6xl border-t border-petrol-100 px-4 pb-36 pt-6 text-center text-base text-gray-600 lg:pb-10">
            <p>© {{ now()->year }} Palmo · Método R-100 de la Extractora El Pajuil</p>
            <p class="mt-1">Desarrollador Alexander Lopez</p>
        </footer>
    </div>
@endsection
