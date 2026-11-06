@extends('layouts.public')

@section('title', 'Palmo — Administración')

@push('head')
    @include('fruit-purchasing.partials.palmo-theme')
@endpush

@section('content')
    @include('fruit-purchasing.partials.number-format-script')
    <div class="palmo min-h-screen">
        @livewire('fruit-purchasing.oil-price-admin')
    </div>
@endsection
