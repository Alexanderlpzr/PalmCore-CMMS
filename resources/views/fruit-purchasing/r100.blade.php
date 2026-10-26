@extends('layouts.public')

@section('title', 'Método R-100 — ¿Cuánto vale su fruta?')

@section('content')
    @include('fruit-purchasing.partials.number-format-script')
    <div class="min-h-screen bg-[#f6f4ee]">
        @livewire('fruit-purchasing.tea-calculator')
    </div>
@endsection
