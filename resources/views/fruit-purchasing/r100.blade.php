@extends('layouts.public')

@section('title', 'Método R-100 — ¿Cuánto vale su fruta?')

{{-- Vista previa cuando el enlace se comparte por WhatsApp o Facebook. --}}
@push('head')
    <meta name="description" content="Calcule la TEA y el valor de su viaje de fruta de palma con el Método R-100 de la Extractora El Pajuil.">
    <meta property="og:type" content="website">
    <meta property="og:locale" content="es_CO">
    <meta property="og:site_name" content="Extractora El Pajuil">
    <meta property="og:title" content="Método R-100 — ¿Cuánto vale su fruta?">
    <meta property="og:description" content="Calcule la TEA y el valor de su viaje de fruta de palma.">
    <meta property="og:url" content="{{ route('r100.calculator') }}">
    <meta property="og:image" content="{{ asset('images/r100/compartir.jpg') }}">
    <meta property="og:image:type" content="image/jpeg">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:alt" content="Método R-100, Extractora El Pajuil">
    <meta name="twitter:card" content="summary_large_image">
@endpush

@section('content')
    @include('fruit-purchasing.partials.number-format-script')
    <div class="min-h-screen bg-[#f6f4ee]">
        @livewire('fruit-purchasing.tea-calculator')
    </div>
@endsection
