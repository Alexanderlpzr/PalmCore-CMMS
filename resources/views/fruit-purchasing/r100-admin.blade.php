@extends('layouts.public')

@section('title', 'Método R-100 — Precio del aceite')

@section('content')
    @include('fruit-purchasing.partials.number-format-script')
    <div class="min-h-screen bg-[#f6f4ee]">
        @livewire('fruit-purchasing.oil-price-admin')
    </div>
@endsection
