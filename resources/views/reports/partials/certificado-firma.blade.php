{{-- La firma de quien expide: su firma escaneada, o el espacio para firmar a mano. --}}
<div class="sign">
    @if ($signature)
        <img src="{{ $signature }}" alt="">
    @else
        <div style="height: 58px;"></div>
    @endif
    <div class="line">
        <div class="who">{{ $signer?->fullName() }}</div>
        <div>{{ $signer?->position }}</div>
    </div>
</div>
